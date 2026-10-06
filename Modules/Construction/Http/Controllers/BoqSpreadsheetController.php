<?php

namespace Modules\Construction\Http\Controllers;

use App\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Construction\Entities\ConstructionBoqImportBatch;
use Modules\Construction\Entities\ConstructionBoqVersion;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Exports\BoqWorkbookExport;
use Modules\Construction\Support\AuditTrail;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BoqSpreadsheetController extends BaseController
{
    public function template()
    {
        $this->authorizePermission('construction.boq.manage');

        return Excel::download(BoqWorkbookExport::template(), 'boq-import-template.xlsx');
    }

    public function export(int $project, int $version)
    {
        $this->authorizePermission('construction.boq.view');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        $items = $version->items()->with('parent:id,code')->orderBy('sort_order')->orderBy('id')->get();
        $fileName = 'Project-Items-'.$project->code.'-V'.$version->version_number.'.xlsx';

        return Excel::download(new BoqWorkbookExport($items, __('construction::lang.boq').' V'.$version->version_number), $fileName);
    }

    public function preview(Request $request, int $project, int $version)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        $this->ensureEditable($project, $version);
        $request->validate(['boq_file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv']]);

        $sheet = IOFactory::load($request->file('boq_file')->getRealPath())->getActiveSheet();
        abort_if($sheet->getHighestDataRow() > 2001, 422, __('construction::lang.import_row_limit'));
        $rawRows = $sheet->rangeToArray('A2:J'.$sheet->getHighestDataRow(), null, true, true, false);
        $existing = $version->items()->get(['code', 'row_type'])->keyBy(fn ($item) => mb_strtolower(trim($item->code)));
        $rows = $this->normalizeRows($rawRows, $existing);
        abort_if($rows->isEmpty(), 422, __('construction::lang.import_file_empty'));

        $batch = ConstructionBoqImportBatch::create([
            'token' => (string) Str::uuid(),
            'business_id' => $this->businessId(),
            'project_id' => $project->id,
            'boq_version_id' => $version->id,
            'user_id' => auth()->id(),
            'file_name' => $request->file('boq_file')->getClientOriginalName(),
            'row_count' => $rows->count(),
            'error_count' => $rows->sum(fn ($row) => count($row['errors'])),
            'rows_json' => $rows->values()->all(),
            'status' => 'preview',
            'expires_at' => now()->addHours(2),
        ]);

        return redirect()->route('construction.projects.boq.import.review', [$project->id, $version->id, $batch->token]);
    }

    public function review(int $project, int $version, string $token)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        $this->ensureEditable($project, $version);
        $batch = $this->findBatch($project, $version, $token);

        return view('construction::boq.import_review', compact('project', 'version', 'batch'));
    }

    public function commit(int $project, int $version, string $token)
    {
        $this->authorizePermission('construction.boq.manage');
        $project = $this->findProject($project);
        $version = $this->findVersion($project, $version);
        $this->ensureEditable($project, $version);
        $batch = $this->findBatch($project, $version, $token);
        abort_if($batch->error_count > 0, 422, __('construction::lang.import_has_errors'));

        DB::transaction(function () use ($project, $version, $batch) {
            $parents = $version->items()->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [mb_strtolower($code) => $id])->all();
            foreach ($batch->rows_json as $row) {
                $item = $version->items()->create([
                    'business_id' => $this->businessId(),
                    'project_id' => $project->id,
                    'parent_id' => $row['parent_code'] !== '' ? ($parents[mb_strtolower($row['parent_code'])] ?? null) : null,
                    'row_type' => $row['row_type'],
                    'code' => $row['code'],
                    'description' => $row['description'],
                    'unit' => $row['row_type'] === 'item' ? ($row['unit'] ?: null) : null,
                    'unit_id' => $row['row_type'] === 'item' ? $row['unit_id'] : null,
                    'contract_quantity' => $row['row_type'] === 'item' ? $row['quantity'] : 0,
                    'sales_unit_price' => $row['row_type'] === 'item' ? $row['unit_price'] : 0,
                    'sales_total' => $row['row_type'] === 'item' ? round($row['quantity'] * $row['unit_price'], 4) : 0,
                    'item_kind' => $row['row_type'] === 'item' ? $row['item_kind'] : 'standard',
                    'sort_order' => $row['row_number'] * 10,
                    'notes' => $row['notes'] ?: null,
                ]);
                $parents[mb_strtolower($row['code'])] = $item->id;
            }
            $version->refreshTotals();
            $batch->update(['status' => 'imported', 'imported_at' => now()]);
            AuditTrail::record('project_items_imported', $version, $project->id, [], ['rows' => $batch->row_count, 'file_name' => $batch->file_name, 'sales_total' => $version->sales_total]);
        });

        return redirect()->route('construction.projects.boq.show', [$project->id, $version->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.import_completed', ['count' => $batch->row_count])]);
    }

    private function normalizeRows(array $rawRows, $existing)
    {
        $unitLookup = [];
        foreach (Unit::where('business_id', $this->businessId())->get(['id', 'short_name', 'actual_name']) as $systemUnit) {
            foreach ([$systemUnit->short_name, $systemUnit->actual_name] as $label) {
                $unitLookup[mb_strtolower(trim($label))] ??= $systemUnit;
            }
        }
        $seen = [];
        $rows = collect();
        foreach ($rawRows as $offset => $raw) {
            if (collect($raw)->filter(fn ($value) => $value !== null && trim((string) $value) !== '')->isEmpty()) {
                continue;
            }
            $rowNumber = $offset + 2;
            $rowType = $this->rowType($raw[0] ?? null);
            $code = trim((string) ($raw[1] ?? ''));
            $description = trim((string) ($raw[2] ?? ''));
            $parentCode = trim((string) ($raw[3] ?? ''));
            $unit = trim((string) ($raw[4] ?? ''));
            $systemUnit = $unitLookup[mb_strtolower($unit)] ?? null;
            $quantity = $this->number($raw[5] ?? null);
            $unitPrice = $this->number($raw[6] ?? null);
            $itemKind = $this->itemKind($raw[7] ?? null);
            $notes = trim((string) ($raw[8] ?? ''));
            $errors = [];
            $normalizedCode = mb_strtolower($code);

            if (! $rowType) $errors[] = __('construction::lang.import_invalid_row_type');
            if ($code === '') $errors[] = __('construction::lang.import_code_required');
            if ($description === '') $errors[] = __('construction::lang.import_description_required');
            if ($code !== '' && (isset($seen[$normalizedCode]) || $existing->has($normalizedCode))) $errors[] = __('construction::lang.import_duplicate_code');
            if ($rowType === 'item' && $unit === '') $errors[] = __('construction::lang.import_unit_required');
            if ($rowType === 'item' && $unit !== '' && ! $systemUnit) $errors[] = __('construction::lang.import_unit_not_found');
            if ($rowType === 'item' && $quantity === null) $errors[] = __('construction::lang.import_invalid_quantity');
            if ($rowType === 'item' && $unitPrice === null) $errors[] = __('construction::lang.import_invalid_price');
            if (! $itemKind) $errors[] = __('construction::lang.import_invalid_item_kind');

            if ($parentCode !== '') {
                $parentKey = mb_strtolower($parentCode);
                $parent = $seen[$parentKey] ?? $existing->get($parentKey);
                if (! $parent) $errors[] = __('construction::lang.import_parent_must_precede');
                elseif (($parent['row_type'] ?? $parent->row_type) !== 'section') $errors[] = __('construction::lang.import_parent_not_section');
            }

            $row = [
                'row_number' => $rowNumber,
                'row_type' => $rowType ?: 'item',
                'code' => $code,
                'description' => $description,
                'parent_code' => $parentCode,
                'unit' => $rowType === 'item' && $systemUnit ? $systemUnit->short_name : $unit,
                'unit_id' => $rowType === 'item' ? $systemUnit?->id : null,
                'quantity' => $rowType === 'section' ? 0 : ($quantity ?? 0),
                'unit_price' => $rowType === 'section' ? 0 : ($unitPrice ?? 0),
                'item_kind' => $itemKind ?: 'standard',
                'notes' => $notes,
                'sales_total' => $rowType === 'item' && $quantity !== null && $unitPrice !== null ? round($quantity * $unitPrice, 4) : 0,
                'errors' => $errors,
            ];
            $rows->push($row);
            if ($code !== '' && ! isset($seen[$normalizedCode])) $seen[$normalizedCode] = $row;
        }

        return $rows;
    }

    private function rowType($value): ?string
    {
        return match (mb_strtolower(trim((string) $value))) {
            'باب', 'section' => 'section',
            'بند', 'item' => 'item',
            default => null,
        };
    }

    private function itemKind($value): ?string
    {
        return match (mb_strtolower(trim((string) $value))) {
            '', 'أساسي', 'اساسي', 'standard' => 'standard',
            'اختياري', 'optional' => 'optional',
            'بديل', 'alternative' => 'alternative',
            'أعمال إضافية', 'اعمال اضافية', 'variation' => 'variation',
            default => null,
        };
    }

    private function number($value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value) || (float) $value < 0) return null;

        return (float) $value;
    }

    private function findProject(int $id): ConstructionProject
    {
        return ConstructionProject::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function findVersion(ConstructionProject $project, int $id): ConstructionBoqVersion
    {
        return $project->boqVersions()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function findBatch(ConstructionProject $project, ConstructionBoqVersion $version, string $token): ConstructionBoqImportBatch
    {
        $batch = ConstructionBoqImportBatch::query()
            ->where('token', $token)
            ->where('business_id', $this->businessId())
            ->where('project_id', $project->id)
            ->where('boq_version_id', $version->id)
            ->where('user_id', auth()->id())
            ->where('status', 'preview')
            ->firstOrFail();
        abort_if($batch->expires_at->isPast(), 410, __('construction::lang.import_preview_expired'));

        return $batch;
    }

    private function ensureEditable(ConstructionProject $project, ConstructionBoqVersion $version): void
    {
        abort_unless($version->status !== 'superseded'
            && ! $project->contracts()->whereIn('status', ['active', 'completed'])->exists()
            && ! $project->measurements()->where('status', 'approved')->exists()
            && ! $project->customerCertificates()->whereIn('status', ['approved', 'partially_approved', 'posted', 'paid'])->exists(),
            422, __('construction::lang.contract_items_are_locked'));
    }
}

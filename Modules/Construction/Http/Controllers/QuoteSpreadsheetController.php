<?php

namespace Modules\Construction\Http\Controllers;

use App\Unit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Construction\Entities\ConstructionQuote;
use Modules\Construction\Entities\ConstructionQuoteImportBatch;
use Modules\Construction\Exports\QuoteItemsWorkbookExport;
use PhpOffice\PhpSpreadsheet\IOFactory;

class QuoteSpreadsheetController extends BaseController
{
    public function template()
    {
        $this->authorizePermission('construction.boq.manage');

        return Excel::download(QuoteItemsWorkbookExport::template(), 'quote-items-import-template.xlsx');
    }

    public function preview(Request $request, int $quote)
    {
        $this->authorizePermission('construction.boq.manage');
        $quote = $this->findQuote($quote);
        $this->ensureEditable($quote);
        $request->validate([
            'quote_file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
        ], [], ['quote_file' => __('construction::lang.choose_quote_excel_file')]);

        // مشاكل الملف بترجع كخطأ تحقق على quote_file، فتظهر جوه نافذة الاستيراد بدل صفحة خطأ
        try {
            $sheet = IOFactory::load($request->file('quote_file')->getRealPath())->getActiveSheet();
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['quote_file' => __('construction::lang.import_file_unreadable')]);
        }
        if ($sheet->getHighestDataRow() > 2001) {
            throw ValidationException::withMessages(['quote_file' => __('construction::lang.quote_import_row_limit')]);
        }
        $rawRows = $sheet->rangeToArray('A2:G'.$sheet->getHighestDataRow(), null, true, false, false);
        $existingCodes = $quote->items()->pluck('code')->map(fn ($code) => mb_strtolower(trim($code)))->flip();
        $rows = $this->normalizeRows(
            $rawRows,
            $existingCodes,
            $quote->items()->count() + 1,
            ((int) $quote->items()->max('sort_order')) + 10
        );
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['quote_file' => __('construction::lang.import_file_empty')]);
        }

        $batch = ConstructionQuoteImportBatch::create([
            'token' => (string) Str::uuid(),
            'business_id' => $this->businessId(),
            'quote_id' => $quote->id,
            'user_id' => auth()->id(),
            'file_name' => $request->file('quote_file')->getClientOriginalName(),
            'row_count' => $rows->count(),
            'error_count' => $rows->sum(fn ($row) => count($row['errors'])),
            'rows_json' => $rows->values()->all(),
            'status' => 'preview',
            'expires_at' => now()->addHours(2),
        ]);

        return redirect()->route('construction.quotes.items.import.review', [$quote->id, $batch->token]);
    }

    public function review(int $quote, string $token)
    {
        $this->authorizePermission('construction.boq.manage');
        $quote = $this->findQuote($quote);
        $this->ensureEditable($quote);
        $batch = $this->findBatch($quote, $token);

        return view('construction::quotes.import_review', compact('quote', 'batch'));
    }

    public function commit(int $quote, string $token)
    {
        $this->authorizePermission('construction.boq.manage');
        $quote = $this->findQuote($quote);
        $this->ensureEditable($quote);
        $batch = $this->findBatch($quote, $token);
        abort_if($batch->error_count > 0, 422, __('construction::lang.import_has_errors'));

        DB::transaction(function () use ($quote, $batch) {
            $lockedQuote = ConstructionQuote::query()
                ->where('business_id', $this->businessId())
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureEditable($lockedQuote);

            $existingCodes = $lockedQuote->items()->pluck('code')->map(fn ($code) => mb_strtolower(trim($code)))->all();
            foreach ($batch->rows_json as $row) {
                abort_if(in_array(mb_strtolower($row['code']), $existingCodes, true), 422, __('construction::lang.quote_duplicate_code'));
                $lockedQuote->items()->create([
                    'business_id' => $this->businessId(),
                    'code' => $row['code'],
                    'description' => $row['description'],
                    'unit_id' => $row['unit_id'],
                    'unit' => $row['unit'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'total' => $row['total'],
                    'sort_order' => $row['sort_order'],
                    'notes' => $row['notes'] ?: null,
                ]);
                $existingCodes[] = mb_strtolower($row['code']);
            }
            $lockedQuote->refreshTotal();
            $batch->update(['status' => 'imported', 'imported_at' => now()]);
        });

        return redirect()->route('construction.quotes.show', $quote->id)
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.quote_import_completed', ['count' => $batch->row_count])]);
    }

    private function normalizeRows(array $rawRows, $existingCodes, int $nextCodeNumber = 1, int $firstSortOrder = 10)
    {
        $unitLookup = [];
        foreach (Unit::where('business_id', $this->businessId())->whereNull('deleted_at')->get(['id', 'short_name', 'actual_name']) as $systemUnit) {
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
            $code = trim((string) ($raw[0] ?? ''));
            if ($code === '') {
                do {
                    $code = 'ITM-'.str_pad((string) $nextCodeNumber++, 3, '0', STR_PAD_LEFT);
                    $normalizedCode = mb_strtolower($code);
                } while (isset($seen[$normalizedCode]) || $existingCodes->has($normalizedCode));
            }
            $description = trim((string) ($raw[1] ?? ''));
            $unitLabel = trim((string) ($raw[2] ?? ''));
            $systemUnit = $unitLookup[mb_strtolower($unitLabel)] ?? null;
            $quantity = $this->number($raw[3] ?? null, false);
            $unitPrice = $this->number($raw[4] ?? null, true);
            $notes = trim((string) ($raw[5] ?? ''));
            $errors = [];
            $normalizedCode = mb_strtolower($code);

            if (mb_strlen($code) > 60) $errors[] = __('construction::lang.quote_import_code_too_long');
            if ($description === '') $errors[] = __('construction::lang.import_description_required');
            elseif (mb_strlen($description) > 500) $errors[] = __('construction::lang.quote_import_description_too_long');
            if (isset($seen[$normalizedCode]) || $existingCodes->has($normalizedCode)) $errors[] = __('construction::lang.quote_import_duplicate_code');
            if ($unitLabel === '') $errors[] = __('construction::lang.import_unit_required');
            elseif (! $systemUnit) $errors[] = __('construction::lang.import_unit_not_found');
            if ($quantity === null) $errors[] = __('construction::lang.quote_import_invalid_quantity');
            if ($unitPrice === null) $errors[] = __('construction::lang.import_invalid_price');
            if (mb_strlen($notes) > 2000) $errors[] = __('construction::lang.quote_import_notes_too_long');

            $row = [
                'row_number' => $rowNumber,
                'code' => $code,
                'description' => $description,
                'unit' => $systemUnit?->short_name ?: $unitLabel,
                'unit_id' => $systemUnit?->id,
                'quantity' => $quantity ?? 0,
                'unit_price' => $unitPrice ?? 0,
                'notes' => $notes,
                'total' => $quantity !== null && $unitPrice !== null ? round($quantity * $unitPrice, 4) : 0,
                'sort_order' => $firstSortOrder + ($rows->count() * 10),
                'errors' => $errors,
            ];
            $rows->push($row);
            if (! isset($seen[$normalizedCode])) $seen[$normalizedCode] = true;
        }

        return $rows;
    }

    private function number($value, bool $allowZero): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) return null;
        $number = (float) $value;
        if ($number < 0 || (! $allowZero && $number <= 0)) return null;

        return $number;
    }

    private function findQuote(int $id): ConstructionQuote
    {
        return ConstructionQuote::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function findBatch(ConstructionQuote $quote, string $token): ConstructionQuoteImportBatch
    {
        $batch = ConstructionQuoteImportBatch::query()
            ->where('token', $token)
            ->where('business_id', $this->businessId())
            ->where('quote_id', $quote->id)
            ->where('user_id', auth()->id())
            ->where('status', 'preview')
            ->firstOrFail();
        if ($batch->expires_at->isPast()) {
            throw new HttpResponseException(redirect()->route('construction.quotes.show', $quote->id)
                ->withErrors(['quote_file' => __('construction::lang.import_preview_expired')]));
        }

        return $batch;
    }

    private function ensureEditable(ConstructionQuote $quote): void
    {
        abort_unless(in_array($quote->status, ['draft', 'sent'], true) && ! $quote->project_id, 422, __('construction::lang.quote_locked'));
    }
}

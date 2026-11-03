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
        $lastColumn = $sheet->getHighestDataColumn();
        $header = $sheet->rangeToArray('A1:'.$lastColumn.'1', null, true, false, false)[0] ?? [];
        $rawRows = $sheet->getHighestDataRow() >= 2
            ? $sheet->rangeToArray('A2:'.$lastColumn.$sheet->getHighestDataRow(), null, true, false, false)
            : [];
        $rows = $this->normalizeRows(
            $rawRows,
            $this->columnMap($header),
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

            // الكود بيتولد هنا (مش في المعاينة) بترتيب صفوف الملف، بعد أكبر كود مستخدم في العرض
            $existingCodes = $lockedQuote->items()->pluck('code')->map(fn ($code) => mb_strtolower(trim($code)))->flip();
            $next = $lockedQuote->nextItemCodeNumber();
            foreach ($batch->rows_json as $row) {
                do {
                    $code = ConstructionQuote::formatItemCode($next++);
                } while ($existingCodes->has(mb_strtolower($code)));
                $lockedQuote->items()->create([
                    'business_id' => $this->businessId(),
                    'code' => $code,
                    'description' => $row['description'],
                    'unit_id' => $row['unit_id'],
                    'unit' => $row['unit'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'total' => round((float) $row['quantity'] * (float) $row['unit_price'], 4),
                    'sort_order' => $row['sort_order'],
                    'notes' => $row['notes'] ?: null,
                ]);
            }
            $lockedQuote->refreshTotal();
            $batch->update(['status' => 'imported', 'imported_at' => now()]);
        });

        return redirect()->route('construction.quotes.show', $quote->id)
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.quote_import_completed', ['count' => $batch->row_count])]);
    }

    /**
     * أعمدة الملف بتتعرف من عناوين الصف الأول، فالنموذج الجديد (من غير كود وإجمالي) والقديم الاتنين شغالين.
     * عمودا "كود البند" و"الإجمالي" بيتجاهلوا: الكود بيتولد عند الحفظ والإجمالي = الكمية × السعر.
     * لو العناوين مش متعرفة، الترتيب الافتراضي هو النموذج الجديد.
     */
    private function columnMap(array $header): array
    {
        $aliases = [
            'description' => ['بيان الأعمال', 'البيان', 'الوصف', 'description'],
            'unit' => ['الوحدة', 'unit'],
            'quantity' => ['الكمية', 'quantity', 'qty'],
            'unit_price' => ['سعر الوحدة', 'unit price', 'price'],
            'notes' => ['ملاحظات', 'notes'],
        ];
        $map = [];
        foreach ($header as $index => $title) {
            $title = mb_strtolower(trim((string) $title));
            foreach ($aliases as $field => $names) {
                if (! isset($map[$field]) && in_array($title, $names, true)) {
                    $map[$field] = $index;
                }
            }
        }

        if (! isset($map['description'], $map['unit'], $map['quantity'], $map['unit_price'])) {
            return ['description' => 0, 'unit' => 1, 'quantity' => 2, 'unit_price' => 3, 'notes' => 4];
        }

        return $map + ['notes' => null];
    }

    private function normalizeRows(array $rawRows, array $columns, int $firstSortOrder = 10)
    {
        $unitLookup = [];
        foreach (Unit::where('business_id', $this->businessId())->whereNull('deleted_at')->get(['id', 'short_name', 'actual_name']) as $systemUnit) {
            foreach ([$systemUnit->short_name, $systemUnit->actual_name] as $label) {
                $unitLookup[mb_strtolower(trim($label))] ??= $systemUnit;
            }
        }
        $cell = fn (array $raw, string $field) => $columns[$field] === null ? null : ($raw[$columns[$field]] ?? null);

        $rows = collect();
        foreach ($rawRows as $offset => $raw) {
            $hasValue = collect(array_keys($columns))
                ->contains(fn ($field) => trim((string) $cell($raw, $field)) !== '');
            if (! $hasValue) {
                continue;
            }

            $rowNumber = $offset + 2;
            $description = trim((string) $cell($raw, 'description'));
            $unitLabel = trim((string) $cell($raw, 'unit'));
            $systemUnit = $unitLookup[mb_strtolower($unitLabel)] ?? null;
            $quantity = $this->number($cell($raw, 'quantity'), false);
            $unitPrice = $this->number($cell($raw, 'unit_price'), true);
            $notes = trim((string) $cell($raw, 'notes'));
            $errors = [];

            if ($description === '') $errors[] = __('construction::lang.import_description_required');
            elseif (mb_strlen($description) > 500) $errors[] = __('construction::lang.quote_import_description_too_long');
            if ($unitLabel === '') $errors[] = __('construction::lang.import_unit_required');
            elseif (! $systemUnit) $errors[] = __('construction::lang.import_unit_not_found_named', ['unit' => $unitLabel]);
            if ($quantity === null) $errors[] = __('construction::lang.quote_import_invalid_quantity');
            if ($unitPrice === null) $errors[] = __('construction::lang.import_invalid_price');
            if (mb_strlen($notes) > 2000) $errors[] = __('construction::lang.quote_import_notes_too_long');

            $rows->push([
                'row_number' => $rowNumber,
                'description' => $description,
                'unit' => $systemUnit?->short_name ?: $unitLabel,
                'unit_id' => $systemUnit?->id,
                'quantity' => $quantity ?? 0,
                'unit_price' => $unitPrice ?? 0,
                'notes' => $notes,
                'total' => $quantity !== null && $unitPrice !== null ? round($quantity * $unitPrice, 4) : 0,
                'sort_order' => $firstSortOrder + ($rows->count() * 10),
                'errors' => $errors,
            ]);
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
        abort_unless($quote->isEditable(), 422, __('construction::lang.quote_locked'));
    }
}

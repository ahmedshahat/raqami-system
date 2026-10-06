<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Unit;
use App\User;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionQuote;
use Modules\Construction\Support\PrintPdfFactory;

class QuoteController extends BaseController
{
    public function index(Request $request)
    {
        $this->authorizePermission('construction.project.view');
        $search = trim((string) $request->input('q'));
        $quotes = ConstructionQuote::query()->where('business_id', $this->businessId())
            ->with(['customer:id,name,supplier_business_name', 'project:id,code,name'])
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested
                ->where('number', 'like', '%'.$search.'%')->orWhere('title', 'like', '%'.$search.'%')
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%'))))
            ->latest('id')->paginate(20)->appends($request->only('q'));
        $customers = Contact::where('business_id', $this->businessId())
            ->whereIn('type', ['customer', 'both'])->orderBy('name')->get(['id', 'name', 'supplier_business_name']);

        return view('construction::quotes.index', compact('quotes', 'customers', 'search') + $this->conversionOptions());
    }

    public function store(Request $request)
    {
        $this->authorizePermission('construction.project.create');
        $validated = $this->validateHeader($request);
        $quote = DB::transaction(function () use ($validated) {
            $quote = ConstructionQuote::create($validated + [
                'business_id' => $this->businessId(),
                'number' => 'PENDING-'.uniqid(),
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
            $quote->update(['number' => 'QTN-'.str_pad((string) $quote->id, 4, '0', STR_PAD_LEFT)]);

            return $quote;
        });

        if ($request->expectsJson()) {
            return response()->json(['id' => $quote->id, 'url' => route('construction.quotes.show', $quote->id), 'message' => __('construction::lang.quote_created')], 201);
        }

        return redirect()->route('construction.quotes.show', $quote->id)
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.quote_created')]);
    }

    public function show(int $quote)
    {
        $this->authorizePermission('construction.project.view');
        $quote = $this->findQuote($quote)->load(['customer', 'project', 'items.systemUnit']);
        $units = Unit::where('business_id', $this->businessId())->orderBy('actual_name')->get(['id', 'actual_name', 'short_name']);
        $customers = Contact::where('business_id', $this->businessId())->whereIn('type', ['customer', 'both'])->orderBy('name')->get(['id', 'name', 'supplier_business_name']);

        return view('construction::quotes.show', compact('quote', 'units', 'customers') + $this->conversionOptions());
    }

    public function update(Request $request, int $quote)
    {
        $this->authorizePermission('construction.project.create');
        $validated = $this->validateHeader($request);
        DB::transaction(function () use ($quote, $validated) {
            $quote = ConstructionQuote::where('business_id', $this->businessId())
                ->whereKey($quote)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($quote->status, ['draft', 'sent'], true) && ! $quote->project_id,
                422, __('construction::lang.quote_header_locked'));
            $quote->update($validated);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('construction::lang.quote_saved')]);
        }

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.quote_saved')]);
    }

    public function destroy(Request $request, int $quote)
    {
        $this->authorizePermission('construction.project.delete');
        DB::transaction(function () use ($quote) {
            $quote = ConstructionQuote::where('business_id', $this->businessId())
                ->whereKey($quote)->lockForUpdate()->firstOrFail();
            abort_unless($quote->status === 'draft' && ! $quote->project_id
                && ! ConstructionProject::withTrashed()->where('business_id', $this->businessId())
                    ->where('quote_id', $quote->id)->exists(),
                422, __('construction::lang.quote_delete_locked'));
            $quote->items()->delete();
            $quote->delete();
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('construction::lang.quote_deleted')]);
        }

        return redirect()->route('construction.quotes.index')->with('status', [
            'success' => 1, 'msg' => __('construction::lang.quote_deleted'),
        ]);
    }

    public function storeItems(Request $request, int $quote)
    {
        $this->authorizePermission('construction.boq.manage');
        $quote = $this->findQuote($quote);
        abort_unless(in_array($quote->status, ['draft', 'sent'], true) && ! $quote->project_id, 422, __('construction::lang.quote_locked'));
        $rows = $request->input('items');
        $util = new Util();
        if (is_array($rows)) {
            foreach ($rows as &$row) {
                if (is_array($row)) {
                    $row['quantity'] = $util->num_uf($row['quantity'] ?? 0);
                    $row['unit_price'] = $util->num_uf($row['unit_price'] ?? 0);
                }
            }
            unset($row);
            $request->merge(['items' => $rows]);
        }
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.code' => ['nullable', 'string', 'max:60', 'distinct'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->whereNull('deleted_at'))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $units = Unit::where('business_id', $this->businessId())->whereIn('id', collect($validated['items'])->pluck('unit_id'))->get()->keyBy('id');

        DB::transaction(function () use ($quote, $validated, $units) {
            $lockedQuote = ConstructionQuote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedQuote->status, ['draft', 'sent'], true) && ! $lockedQuote->project_id, 422, __('construction::lang.quote_locked'));
            $next = $lockedQuote->items()->count() + 1;
            foreach ($validated['items'] as $row) {
                $code = trim((string) ($row['code'] ?? ''));
                if ($code === '') {
                    do {
                        $code = 'ITM-'.str_pad((string) $next++, 3, '0', STR_PAD_LEFT);
                    } while ($lockedQuote->items()->where('code', $code)->exists());
                }
                abort_if($lockedQuote->items()->where('code', $code)->exists(), 422, __('construction::lang.quote_duplicate_code'));
                $lockedQuote->items()->create([
                    'business_id' => $this->businessId(),
                    'code' => $code,
                    'description' => trim($row['description']),
                    'unit_id' => $row['unit_id'],
                    'unit' => $units[$row['unit_id']]->short_name,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'total' => round((float) $row['quantity'] * (float) $row['unit_price'], 4),
                    'sort_order' => $next++,
                    'notes' => $row['notes'] ?? null,
                ]);
            }
            $lockedQuote->refreshTotal();
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.quote_items_saved')]);
    }

    public function updateItem(Request $request, int $quote, int $item)
    {
        $this->authorizePermission('construction.boq.manage');
        $quote = $this->findQuote($quote);
        abort_unless(in_array($quote->status, ['draft', 'sent'], true) && ! $quote->project_id, 422, __('construction::lang.quote_locked'));
        $util = new Util();
        $request->merge([
            'quantity' => $util->num_uf($request->input('quantity', 0)),
            'unit_price' => $util->num_uf($request->input('unit_price', 0)),
        ]);
        $item = $quote->items()->findOrFail($item);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('construction_quote_items', 'code')->where(fn ($query) => $query->where('quote_id', $quote->id))->ignore($item->id)],
            'description' => ['required', 'string', 'max:500'],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->whereNull('deleted_at'))],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $unit = Unit::where('business_id', $this->businessId())->findOrFail($validated['unit_id']);
        $item->update([
            'code' => trim($validated['code']), 'description' => trim($validated['description']),
            'unit_id' => $unit->id, 'unit' => $unit->short_name,
            'quantity' => $validated['quantity'], 'unit_price' => $validated['unit_price'],
            'total' => round((float) $validated['quantity'] * (float) $validated['unit_price'], 4),
            'notes' => $validated['notes'] ?? null,
        ]);
        $quote->refreshTotal();

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.quote_item_updated')]);
    }

    public function destroyItem(int $quote, int $item)
    {
        $this->authorizePermission('construction.boq.manage');
        $quote = $this->findQuote($quote);
        abort_unless(in_array($quote->status, ['draft', 'sent'], true) && ! $quote->project_id, 422, __('construction::lang.quote_locked'));
        DB::transaction(function () use ($quote, $item) {
            $quote->items()->findOrFail($item)->delete();
            $quote->refreshTotal();
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.quote_item_deleted')]);
    }

    public function status(Request $request, int $quote)
    {
        $this->authorizePermission('construction.boq.approve');
        $validated = $request->validate(['status' => ['required', Rule::in(ConstructionQuote::STATUSES)]]);
        DB::transaction(function () use ($quote, $validated) {
            $quote = ConstructionQuote::where('business_id', $this->businessId())->whereKey($quote)->lockForUpdate()->firstOrFail();
            abort_if($quote->project_id, 422, __('construction::lang.quote_already_converted'));
            abort_if($quote->status === 'accepted' && $validated['status'] !== 'accepted', 422, __('construction::lang.quote_locked'));
            abort_if($validated['status'] !== 'draft' && ! $quote->items()->exists(), 422, __('construction::lang.quote_requires_items'));
            $quote->update(['status' => $validated['status']]);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.quote_status_saved')]);
    }

    public function convert(Request $request, int $quote)
    {
        $this->authorizePermission('construction.project.create');
        $this->normalizeBusinessDates($request, ['start_date', 'end_date']);
        $businessId = $this->businessId();
        $validated = $request->validate([
            'project_name' => ['required', 'string', 'max:190'],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at'))],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'consultant_contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->whereNull('deleted_at'))],
        ]);
        $project = DB::transaction(function () use ($quote, $validated) {
            $quote = ConstructionQuote::where('business_id', $this->businessId())->whereKey($quote)->lockForUpdate()->firstOrFail();
            abort_unless($quote->status === 'accepted', 422, __('construction::lang.quote_must_be_accepted'));
            abort_if($quote->project_id, 422, __('construction::lang.quote_already_converted'));
            $items = $quote->items()->orderBy('sort_order')->orderBy('id')->get();
            abort_if($items->isEmpty(), 422, __('construction::lang.quote_requires_items'));
            $code = 'PRJ-Q'.str_pad((string) $quote->id, 4, '0', STR_PAD_LEFT);
            abort_if(ConstructionProject::withTrashed()->where('business_id', $this->businessId())->where('code', $code)->exists(), 422, __('construction::lang.quote_project_code_conflict'));

            $project = ConstructionProject::create([
                'business_id' => $this->businessId(), 'code' => $code,
                'name' => trim($validated['project_name']), 'customer_id' => $quote->customer_id,
                'quote_id' => $quote->id, 'status' => 'draft',
                'manager_id' => $validated['manager_id'] ?? null,
                'location' => $validated['location'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'consultant_contact_id' => $validated['consultant_contact_id'] ?? null,
                'description' => $quote->notes, 'created_by' => auth()->id(),
            ]);
            if (! empty($validated['manager_id'])) {
                $project->members()->attach((int) $validated['manager_id'], [
                    'business_id' => $this->businessId(), 'access_level' => 'manage',
                    'assigned_by' => auth()->id(),
                ]);
            }
            $boq = $project->boqVersions()->create([
                'business_id' => $this->businessId(), 'version_number' => 1,
                'name' => __('construction::lang.quote_boq_name', ['number' => $quote->number]),
                'status' => 'approved', 'notes' => __('construction::lang.quote_boq_note', ['number' => $quote->number]),
                'approved_by' => auth()->id(), 'approved_at' => now(), 'created_by' => auth()->id(),
            ]);
            foreach ($items as $item) {
                $boq->items()->create([
                    'business_id' => $this->businessId(), 'project_id' => $project->id,
                    'row_type' => 'item', 'code' => $item->code, 'description' => $item->description,
                    'unit_id' => $item->unit_id, 'unit' => $item->unit,
                    'contract_quantity' => $item->quantity, 'sales_unit_price' => $item->unit_price,
                    'sales_total' => $item->total, 'item_kind' => 'standard', 'sort_order' => $item->sort_order,
                ]);
            }
            $boq->refreshTotals();
            $quote->update(['project_id' => $project->id]);

            return $project;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'url' => route('construction.projects.show', $project->id),
                'message' => __('construction::lang.quote_converted'),
            ]);
        }

        return redirect()->route('construction.projects.show', $project->id)
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.quote_converted')]);
    }

    public function preview(int $quote)
    {
        return view('construction::quotes.print', $this->printData($quote) + ['pdfMode' => false, 'autoPrint' => false]);
    }

    public function print(int $quote)
    {
        return view('construction::quotes.print', $this->printData($quote) + ['pdfMode' => false, 'autoPrint' => true]);
    }

    public function pdf(int $quote)
    {
        $data = $this->printData($quote) + ['pdfMode' => true, 'autoPrint' => false];
        $mpdf = PrintPdfFactory::make('P');
        $mpdf->WriteHTML(view('construction::quotes.print', $data)->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="quote-'.$data['quote']->number.'.pdf"',
        ]);
    }

    private function findQuote(int $id): ConstructionQuote
    {
        return ConstructionQuote::where('business_id', $this->businessId())->findOrFail($id);
    }

    private function conversionOptions(): array
    {
        $users = User::where('business_id', $this->businessId())->whereNull('deleted_at')
            ->orderBy('first_name')->get(['id', 'surname', 'first_name', 'last_name', 'username']);
        $consultants = Contact::where('business_id', $this->businessId())->whereNull('deleted_at')
            ->orderBy('name')->get(['id', 'name', 'supplier_business_name']);

        return compact('users', 'consultants');
    }

    private function validateHeader(Request $request): array
    {
        $this->normalizeBusinessDates($request, ['quote_date']);

        return $request->validate([
            'quote_date' => ['required', 'date'],
            'customer_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where(fn ($query) => $query
                ->where('business_id', $this->businessId())->whereIn('type', ['customer', 'both'])->whereNull('deleted_at'))],
            'title' => ['required', 'string', 'max:190'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function printData(int $id): array
    {
        $this->authorizePermission('construction.project.view');
        $quote = $this->findQuote($id)->load(['customer', 'items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);
        $business = Business::findOrFail($this->businessId());
        $businessLocation = BusinessLocation::where('business_id', $business->id)->active()->orderBy('id')->first();

        return compact('quote', 'business', 'businessLocation');
    }
}

<?php

namespace Modules\Construction\Http\Controllers;

use App\BusinessLocation;
use App\Business;
use App\Events\StockAdjustmentCreatedOrModified;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionSellLinesPurchaseLines;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use App\VariationLocationDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionBoqItem;
use Modules\Construction\Entities\ConstructionMaterialDocument;
use Modules\Construction\Entities\ConstructionMaterialDocumentLine;
use Modules\Construction\Entities\ConstructionProject;

class MaterialController extends BaseController
{
    public function __construct(
        protected ProductUtil $productUtil,
        protected TransactionUtil $transactionUtil
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizePermission('construction.material.view');
        $businessId = $this->businessId();
        $projects = ConstructionProject::where('business_id', $businessId)->orderBy('name')->get(['id', 'code', 'name']);
        $selectedProjectId = $request->integer('project_id') ?: null;
        abort_if($selectedProjectId && ! $projects->contains('id', $selectedProjectId), 404);

        $baseDocuments = ConstructionMaterialDocument::query()->where('business_id', $businessId);
        $stats = [
            'all' => (clone $baseDocuments)->count(),
            'draft' => (clone $baseDocuments)->where('status', 'draft')->count(),
            'approved' => (clone $baseDocuments)->where('status', 'approved')->count(),
            'issues' => (clone $baseDocuments)->where('type', 'issue')->count(),
            'returns' => (clone $baseDocuments)->where('type', 'return')->count(),
        ];
        $status = in_array($request->input('status'), ['draft', 'approved'], true) ? $request->input('status') : null;
        $type = in_array($request->input('type'), ['issue', 'return'], true) ? $request->input('type') : null;
        $search = trim((string) $request->input('q'));

        $documents = ConstructionMaterialDocument::query()
            ->where('business_id', $businessId)
            ->with(['project:id,code,name', 'location:id,name'])
            ->withSum('lines', 'total_cost')
            ->withCount('lines')
            ->when($selectedProjectId, fn ($query) => $query->where('project_id', $selectedProjectId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('number', 'like', "%{$search}%")
                    ->orWhereHas('project', fn ($project) => $project->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            }))
            ->latest('document_date')->latest('id')->paginate(20)->withQueryString();

        return view('construction::materials.index', compact('projects', 'selectedProjectId', 'documents', 'stats', 'status', 'type', 'search'));
    }

    public function create()
    {
        $this->authorizePermission('construction.material.manage');

        return view('construction::materials.create', [
            'projects' => ConstructionProject::where('business_id', $this->businessId())->whereNotIn('status', ['cancelled', 'completed'])->orderBy('name')->get(),
            'locations' => BusinessLocation::forDropdown($this->businessId(), false, false, true, true),
            'nextNumber' => $this->nextNumber('issue'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('construction.material.manage');
        $this->normalizeBusinessDates($request, ['document_date']);
        $businessId = $this->businessId();
        $locationIds = BusinessLocation::forDropdown($businessId, false, false, true, true)->keys()->map(fn ($id) => (int) $id)->all();
        $validated = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('construction_projects', 'id')->where(fn ($q) => $q->where('business_id', $businessId))],
            'location_id' => ['required', 'integer', Rule::in($locationIds)],
            'number' => ['required', 'string', 'max:60', Rule::unique('construction_material_documents', 'number')->where(fn ($q) => $q->where('business_id', $businessId))],
            'document_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $document = ConstructionMaterialDocument::create($validated + [
            'business_id' => $businessId,
            'type' => 'issue',
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('construction.materials.show', $document->id)
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.material_issue_created')]);
    }

    public function edit(int $document)
    {
        $this->authorizePermission('construction.material.manage');
        $document = $this->findDocument($document);
        abort_unless($document->isEditable(), 422, __('construction::lang.approved_material_document_locked'));
        $document->load(['project:id,code,name', 'location:id,name']);

        return view('construction::materials.edit', compact('document'));
    }

    public function update(Request $request, int $document)
    {
        $this->authorizePermission('construction.material.manage');
        $document = $this->findDocument($document);
        abort_unless($document->isEditable(), 422, __('construction::lang.approved_material_document_locked'));
        $this->normalizeBusinessDates($request, ['document_date']);
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:60', Rule::unique('construction_material_documents', 'number')->ignore($document->id)->where(fn ($q) => $q->where('business_id', $this->businessId()))],
            'document_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $document->update($validated);

        return redirect()->route('construction.materials.show', $document->id)
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.material_document_updated')]);
    }

    public function destroy(int $document)
    {
        $this->authorizePermission('construction.material.manage');
        $document = $this->findDocument($document);
        abort_unless($document->isEditable(), 422, __('construction::lang.approved_material_document_delete_blocked'));
        DB::transaction(function () use ($document) {
            $document->lines()->delete();
            $document->delete();
        });

        return redirect()->route('construction.materials.index')
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.material_document_deleted')]);
    }

    public function show(int $document)
    {
        $this->authorizePermission('construction.material.view');
        $document = $this->findDocument($document);
        $document->load([
            'project:id,code,name', 'location:id,name', 'parentIssue:id,number',
            'lines.product.unit', 'lines.variation.product_variation', 'lines.boqItem:id,code,description',
            'approvedBy:id,surname,first_name,last_name,username',
        ]);

        $products = collect();
        $boqItems = collect();
        if ($document->isEditable() && $document->type === 'issue') {
            $products = Variation::query()
                ->join('products as p', 'p.id', '=', 'variations.product_id')
                ->join('product_variations as pv', 'pv.id', '=', 'variations.product_variation_id')
                ->join('product_locations as ploc', function ($join) use ($document) {
                    $join->on('ploc.product_id', '=', 'p.id')->where('ploc.location_id', $document->location_id);
                })
                ->leftJoin('variation_location_details as vld', function ($join) use ($document) {
                    $join->on('vld.variation_id', '=', 'variations.id')->where('vld.location_id', $document->location_id);
                })
                ->where('p.business_id', $this->businessId())->where('p.enable_stock', 1)->whereNull('variations.deleted_at')
                ->select('variations.id', 'variations.product_id', 'variations.sub_sku', 'variations.name as variation_name', 'pv.name as group_name', 'p.name as product_name', DB::raw('COALESCE(vld.qty_available, 0) as qty_available'))
                ->orderBy('p.name')->get();

            $boqItems = ConstructionBoqItem::where('business_id', $this->businessId())->where('project_id', $document->project_id)
                ->where('row_type', 'item')->orderBy('sort_order')->get(['id', 'code', 'description']);
        }

        $returnedBySource = DB::table('construction_material_document_lines as lines')
            ->join('construction_material_documents as docs', 'docs.id', '=', 'lines.document_id')
            ->where('docs.business_id', $this->businessId())->where('docs.status', 'approved')->where('docs.type', 'return')
            ->whereIn('lines.source_issue_line_id', $document->lines->pluck('id'))
            ->groupBy('lines.source_issue_line_id')->pluck(DB::raw('SUM(lines.quantity)'), 'lines.source_issue_line_id');

        return view('construction::materials.show', compact('document', 'products', 'boqItems', 'returnedBySource'));
    }

    public function preview(int $document)
    {
        return view('construction::materials.print', $this->printData($document) + ['autoPrint' => false]);
    }

    public function print(int $document)
    {
        return view('construction::materials.print', $this->printData($document) + ['autoPrint' => true]);
    }

    public function storeLine(Request $request, int $document)
    {
        $this->authorizePermission('construction.material.manage');
        $document = $this->findDocument($document);
        abort_unless($document->isEditable() && $document->type === 'issue', 422);
        $this->normalizeLocalizedNumbers($request, ['quantity']);
        $validated = $request->validate([
            'variation_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'boq_item_id' => ['nullable', 'integer', Rule::exists('construction_boq_items', 'id')->where(fn ($q) => $q->where('business_id', $this->businessId())->where('project_id', $document->project_id)->where('row_type', 'item'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $variation = Variation::join('products as p', 'p.id', '=', 'variations.product_id')
            ->join('product_locations as ploc', function ($join) use ($document) {
                $join->on('ploc.product_id', '=', 'p.id')->where('ploc.location_id', $document->location_id);
            })
            ->where('variations.id', $validated['variation_id'])->where('p.business_id', $this->businessId())->where('p.enable_stock', 1)
            ->select('variations.*')->firstOrFail();

        $document->lines()->create([
            'business_id' => $this->businessId(), 'project_id' => $document->project_id,
            'product_id' => $variation->product_id, 'variation_id' => $variation->id,
            'boq_item_id' => $validated['boq_item_id'] ?? null, 'quantity' => $validated['quantity'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.material_line_added')]);
    }

    public function destroyLine(int $document, int $line)
    {
        $this->authorizePermission('construction.material.manage');
        $document = $this->findDocument($document);
        abort_unless($document->isEditable(), 422);
        $document->lines()->where('business_id', $this->businessId())->findOrFail($line)->delete();

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.material_line_deleted')]);
    }

    public function updateLine(Request $request, int $document, int $line)
    {
        $this->authorizePermission('construction.material.manage');
        $document = $this->findDocument($document);
        abort_unless($document->isEditable(), 422, __('construction::lang.approved_material_document_locked'));
        $line = $document->lines()->where('business_id', $this->businessId())->findOrFail($line);
        $this->normalizeLocalizedNumbers($request, ['quantity']);
        $rules = [
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
        if ($document->type === 'issue') {
            $rules['boq_item_id'] = ['nullable', 'integer', Rule::exists('construction_boq_items', 'id')->where(fn ($q) => $q->where('business_id', $this->businessId())->where('project_id', $document->project_id)->where('row_type', 'item'))];
        }
        $validated = $request->validate($rules);

        if ($document->type === 'return') {
            $source = ConstructionMaterialDocumentLine::where('business_id', $this->businessId())->findOrFail($line->source_issue_line_id);
            $alreadyReturned = (float) $this->approvedReturnedQuantities([$source->id])->get($source->id, 0);
            if ((float) $validated['quantity'] > (float) $source->quantity - $alreadyReturned + 0.0001) {
                throw ValidationException::withMessages(['quantity' => __('construction::lang.material_return_exceeds_issue')]);
            }
        }
        $line->update([
            'quantity' => $validated['quantity'],
            'boq_item_id' => $document->type === 'issue' ? ($validated['boq_item_id'] ?? null) : $line->boq_item_id,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.material_line_updated')]);
    }

    public function approve(int $document)
    {
        $this->authorizePermission('construction.material.approve');
        $document = $this->findDocument($document);

        try {
            DB::transaction(function () use ($document) {
                $document = ConstructionMaterialDocument::where('business_id', $this->businessId())->lockForUpdate()->findOrFail($document->id);
                abort_unless($document->isEditable(), 422);
                $lines = $document->lines()->lockForUpdate()->get();
                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages(['lines' => __('construction::lang.material_document_empty')]);
                }
                $document->type === 'issue' ? $this->approveIssue($document, $lines) : $this->approveReturn($document, $lines);
                $document->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.material_document_approved')]);
    }

    public function createReturn(int $document)
    {
        $this->authorizePermission('construction.material.manage');
        $issue = $this->findDocument($document);
        abort_unless($issue->type === 'issue' && $issue->status === 'approved', 422);
        $issue->load(['project:id,code,name', 'location:id,name', 'lines.product.unit', 'lines.variation.product_variation', 'lines.boqItem:id,code,description']);
        $returned = $this->approvedReturnedQuantities($issue->lines->pluck('id'));

        return view('construction::materials.return', ['issue' => $issue, 'returned' => $returned, 'nextNumber' => $this->nextNumber('return')]);
    }

    public function storeReturn(Request $request, int $document)
    {
        $this->authorizePermission('construction.material.manage');
        $issue = $this->findDocument($document);
        abort_unless($issue->type === 'issue' && $issue->status === 'approved', 422);
        $this->normalizeBusinessDates($request, ['document_date']);
        $rows = collect($request->input('lines', []))->map(function ($row) {
            $row['quantity'] = ($row['quantity'] ?? '') === '' ? 0 : $this->productUtil->num_uf($row['quantity']);
            return $row;
        })->all();
        $request->merge(['lines' => $rows]);
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:60', Rule::unique('construction_material_documents', 'number')->where(fn ($q) => $q->where('business_id', $this->businessId()))],
            'document_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array'], 'lines.*.id' => ['required', 'integer'], 'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);
        $sourceLines = $issue->lines()->get()->keyBy('id');
        $returned = $this->approvedReturnedQuantities($sourceLines->keys());
        $selected = collect($validated['lines'])->filter(fn ($row) => (float) $row['quantity'] > 0);
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('construction::lang.material_return_empty')]);
        }
        foreach ($selected as $index => $row) {
            $source = $sourceLines->get((int) $row['id']);
            $remaining = $source ? (float) $source->quantity - (float) ($returned[$source->id] ?? 0) : -1;
            if (! $source || (float) $row['quantity'] > $remaining + 0.0001) {
                throw ValidationException::withMessages(["lines.$index.quantity" => __('construction::lang.material_return_exceeds_issue')]);
            }
        }

        $return = DB::transaction(function () use ($validated, $issue, $selected, $sourceLines) {
            $return = ConstructionMaterialDocument::create([
                'business_id' => $this->businessId(), 'project_id' => $issue->project_id, 'location_id' => $issue->location_id,
                'parent_issue_id' => $issue->id, 'type' => 'return', 'number' => $validated['number'],
                'document_date' => $validated['document_date'], 'status' => 'draft', 'notes' => $validated['notes'] ?? null, 'created_by' => auth()->id(),
            ]);
            foreach ($selected as $row) {
                $source = $sourceLines->get((int) $row['id']);
                $return->lines()->create([
                    'business_id' => $this->businessId(), 'project_id' => $issue->project_id,
                    'product_id' => $source->product_id, 'variation_id' => $source->variation_id, 'boq_item_id' => $source->boq_item_id,
                    'source_issue_line_id' => $source->id, 'quantity' => $row['quantity'], 'notes' => $row['notes'] ?? null,
                ]);
            }
            return $return;
        });

        return redirect()->route('construction.materials.show', $return->id)->with('status', ['success' => 1, 'msg' => __('construction::lang.material_return_created')]);
    }

    private function approveIssue(ConstructionMaterialDocument $document, $lines): void
    {
        foreach ($lines as $line) {
            $stock = VariationLocationDetails::where('location_id', $document->location_id)->where('product_id', $line->product_id)->where('variation_id', $line->variation_id)->lockForUpdate()->first();
            if (! $stock || (float) $stock->qty_available + 0.0001 < (float) $line->quantity) {
                throw ValidationException::withMessages(['stock' => __('construction::lang.material_insufficient_stock')]);
            }
        }
        $transaction = Transaction::create([
            'business_id' => $this->businessId(), 'location_id' => $document->location_id, 'type' => 'stock_adjustment',
            'construction_material_document_id' => $document->id,
            'status' => 'final', 'adjustment_type' => 'normal', 'transaction_date' => $document->document_date->format('Y-m-d').' '.now()->format('H:i:s'),
            'ref_no' => $document->number, 'final_total' => 0, 'total_amount_recovered' => 0,
            'additional_notes' => __('construction::lang.material_stock_adjustment_note', ['number' => $document->number]), 'created_by' => auth()->id(),
        ]);
        foreach ($lines as $line) {
            $variation = Variation::findOrFail($line->variation_id);
            $stockLine = $transaction->stock_adjustment_lines()->create([
                'product_id' => $line->product_id, 'variation_id' => $line->variation_id, 'quantity' => $line->quantity,
                'unit_price' => $variation->default_purchase_price ?: 0,
            ]);
            $line->update(['stock_adjustment_line_id' => $stockLine->id]);
            $this->productUtil->decreaseProductQuantity($line->product_id, $line->variation_id, $document->location_id, (float) $line->quantity);
        }
        $business = ['id' => $this->businessId(), 'accounting_method' => session('business.accounting_method'), 'location_id' => $document->location_id];
        $this->transactionUtil->mapPurchaseSell($business, $transaction->stock_adjustment_lines, 'stock_adjustment');
        $total = 0;
        foreach ($document->lines()->get() as $line) {
            $cost = (float) DB::table('transaction_sell_lines_purchase_lines as map')->join('purchase_lines as pl', 'pl.id', '=', 'map.purchase_line_id')
                ->where('map.stock_adjustment_line_id', $line->stock_adjustment_line_id)->selectRaw('COALESCE(SUM(map.quantity * pl.purchase_price_inc_tax), 0) as total')->value('total');
            $unitCost = (float) $line->quantity > 0 ? $cost / (float) $line->quantity : 0;
            $line->update(['unit_cost' => $unitCost, 'total_cost' => $cost]);
            $total += $cost;
        }
        $transaction->update(['final_total' => $total]);
        $document->update(['stock_adjustment_transaction_id' => $transaction->id]);
        event(new StockAdjustmentCreatedOrModified($transaction, 'added'));
        $this->transactionUtil->activityLog($transaction, 'added', null, [], false);
    }

    private function approveReturn(ConstructionMaterialDocument $document, $lines): void
    {
        $returned = $this->approvedReturnedQuantities($lines->pluck('source_issue_line_id'));
        foreach ($lines as $line) {
            $source = ConstructionMaterialDocumentLine::where('business_id', $this->businessId())->lockForUpdate()->findOrFail($line->source_issue_line_id);
            if ((float) $line->quantity > (float) $source->quantity - (float) ($returned[$source->id] ?? 0) + 0.0001) {
                throw ValidationException::withMessages(['quantity' => __('construction::lang.material_return_exceeds_issue')]);
            }
            $remaining = (float) $line->quantity;
            $cost = 0.0;
            $maps = TransactionSellLinesPurchaseLines::where('stock_adjustment_line_id', $source->stock_adjustment_line_id)->lockForUpdate()->orderByDesc('id')->get();
            foreach ($maps as $map) {
                if ($remaining <= 0.0001) break;
                $quantity = min($remaining, (float) $map->quantity);
                $purchaseLine = PurchaseLine::lockForUpdate()->findOrFail($map->purchase_line_id);
                $cost += $quantity * (float) $purchaseLine->purchase_price_inc_tax;
                $purchaseLine->decrement('quantity_adjusted', $quantity);
                if ((float) $map->quantity <= $quantity + 0.0001) $map->delete(); else $map->decrement('quantity', $quantity);
                $remaining -= $quantity;
            }
            if ($remaining > 0.0001) {
                throw ValidationException::withMessages(['quantity' => __('construction::lang.material_return_mapping_error')]);
            }
            $this->productUtil->updateProductQuantity($document->location_id, $line->product_id, $line->variation_id, (float) $line->quantity, 0, null, false);
            $line->update(['unit_cost' => $cost / (float) $line->quantity, 'total_cost' => $cost]);
        }
    }

    private function findDocument(int $id): ConstructionMaterialDocument
    {
        return ConstructionMaterialDocument::where('business_id', $this->businessId())->findOrFail($id);
    }

    private function printData(int $id): array
    {
        $this->authorizePermission('construction.material.view');
        $document = $this->findDocument($id);
        $document->load([
            'project.customer:id,name,supplier_business_name',
            'project.manager:id,surname,first_name,last_name,username',
            'location', 'parentIssue:id,number', 'createdBy:id,surname,first_name,last_name,username',
            'approvedBy:id,surname,first_name,last_name,username',
            'lines.product.unit', 'lines.variation.product_variation', 'lines.boqItem:id,code,description',
        ]);

        return [
            'document' => $document,
            'business' => Business::findOrFail($this->businessId()),
            'businessLocation' => $document->location,
        ];
    }

    private function nextNumber(string $type): string
    {
        $prefix = $type === 'return' ? 'MAT-RET-' : 'MAT-ISS-';
        $next = (int) ConstructionMaterialDocument::where('business_id', $this->businessId())->where('type', $type)->max('id') + 1;
        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function approvedReturnedQuantities($sourceIds)
    {
        return DB::table('construction_material_document_lines as lines')->join('construction_material_documents as docs', 'docs.id', '=', 'lines.document_id')
            ->where('docs.business_id', $this->businessId())->where('docs.type', 'return')->where('docs.status', 'approved')
            ->whereIn('lines.source_issue_line_id', collect($sourceIds)->filter()->all())
            ->groupBy('lines.source_issue_line_id')->pluck(DB::raw('SUM(lines.quantity)'), 'lines.source_issue_line_id');
    }
}

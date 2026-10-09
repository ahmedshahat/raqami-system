<?php

namespace Modules\Construction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Business;
use App\BusinessLocation;
use App\Product;
use App\ProductVariation;
use App\TaxRate;
use App\Unit;
use App\Utils\TransactionUtil;
use App\Variation;
use Modules\Construction\Entities\ConstructionCustomerCertificate;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\PrintPdfFactory;
use Modules\Construction\Support\AuditTrail;
use Modules\Construction\Support\ConstructionAccountingPoster;

class CustomerCertificateController extends BaseController
{
    private const APPROVED_STATUSES = ['partially_approved', 'approved', 'posted', 'paid'];

    public function workspaceIndex(Request $request)
    {
        $this->authorizePermission('construction.certificate.view');

        $businessId = $this->businessId();
        $projects = ConstructionProject::query()
            ->where('business_id', $businessId)
            ->with([
                'customer:id,name,supplier_business_name',
                'primaryContract:id,project_id,original_value',
            ])
            ->withCount('customerCertificates')
            ->latest('id')
            ->paginate(20);

        return view('construction::certificates.workspace', compact('projects'));
    }

    public function index(Request $request, int $project)
    {
        $this->authorizePermission('construction.certificate.view');
        $project = $this->findProject($project);
        $selectedMeasurement = null;
        if ($request->filled('measurement_id')) {
            $selectedMeasurement = $project->measurements()
                ->where('business_id', $this->businessId())
                ->where('status', 'approved')
                ->findOrFail((int) $request->input('measurement_id'));
        }
        $certificates = $project->customerCertificates()->withCount('items')->latest('certificate_date')->latest('id')->get();
        $unbilledCount = DB::table('construction_measurement_items as lines')
            ->join('construction_measurements as headers', 'headers.id', '=', 'lines.measurement_id')
            ->where('lines.business_id', $this->businessId())
            ->where('lines.project_id', $project->id)
            ->where('headers.status', 'approved')
            ->whereNull('lines.certificate_id')
            ->where('lines.approved_quantity', '>', 0)
            ->count();
        $nextNumber = $this->nextNumber($project);
        $contract = $project->primaryContract;
        $retentionPercent = (float) optional($contract)->retention_percent;
        $previousRecovered = $contract ? (float) $project->customerCertificates()
            ->where('contract_id', $contract->id)->whereIn('status', self::APPROVED_STATUSES)
            ->sum('advance_recovery_value') : 0;
        $measurements = $project->measurements()->where('status', 'approved')
            ->whereHas('items', fn ($query) => $query->whereNull('certificate_id')->where('approved_quantity', '>', 0))
            ->orderByDesc('measurement_date')->get();

        return view('construction::certificates.index', compact('project', 'certificates', 'unbilledCount', 'nextNumber', 'retentionPercent', 'previousRecovered', 'selectedMeasurement', 'measurements', 'contract'));
    }

    public function store(Request $request, int $project)
    {
        $this->authorizePermission('construction.certificate.manage');
        $project = $this->findProject($project);
        $contract = $project->primaryContract;
        abort_unless($contract && $contract->status === 'active', 422, __('construction::lang.certificate_requires_active_contract'));
        $approvedBoq = $project->boqVersions()->where('status', 'approved')->findOrFail($contract->boq_version_id);
        $this->normalizeBusinessDates($request, ['certificate_date', 'period_from', 'period_to', 'retention_due_date']);
        $this->normalizeLocalizedNumbers($request, [
            'advance_recovery_value', 'other_deductions_value',
        ]);
        $validated = $request->validate([
            'certificate_date' => ['required', 'date'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'retention_due_date' => ['nullable', 'date'],
            'advance_recovery_value' => ['nullable', 'numeric', 'min:0'],
            'other_deductions_value' => ['nullable', 'numeric', 'min:0'],
            'measurement_id' => ['required', 'integer', Rule::exists('construction_measurements', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->where('project_id', $project->id)->where('status', 'approved'))],
        ]);

        $certificate = DB::transaction(function () use ($project, $contract, $approvedBoq, $validated) {
            $measurementId = (int) $validated['measurement_id'];
            ConstructionProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $measurement = $project->measurements()->where('business_id', $this->businessId())->where('status', 'approved')->findOrFail($measurementId);
            abort_if($project->customerCertificates()->where('measurement_id', $measurementId)->where('status', '!=', 'cancelled')->exists(), 422, __('construction::lang.measurement_already_certified'));
            $unbilled = DB::table('construction_measurement_items as lines')
                ->join('construction_measurements as headers', 'headers.id', '=', 'lines.measurement_id')
                ->join('construction_boq_items as boq', 'boq.id', '=', 'lines.boq_item_id')
                ->where('lines.business_id', $this->businessId())
                ->where('lines.project_id', $project->id)
                ->where('headers.status', 'approved')
                ->whereNull('lines.certificate_id')
                ->where('lines.approved_quantity', '>', 0)
                ->where('boq.boq_version_id', $approvedBoq->id)
                ->where('headers.id', $measurementId)
                ->selectRaw('lines.boq_item_id, boq.sales_unit_price, SUM(lines.approved_quantity) as submitted_quantity')
                ->groupBy('lines.boq_item_id', 'boq.sales_unit_price')
                ->lockForUpdate()
                ->get();
            abort_if($unbilled->isEmpty(), 422, __('construction::lang.no_unbilled_measurements'));

            $previousGross = (float) $project->customerCertificates()->whereIn('status', self::APPROVED_STATUSES)->sum('current_approved_gross');
            $advancePaid = (float) $contract->advance_payment_value;
            $previousRecovery = (float) $project->customerCertificates()->where('contract_id', $contract->id)->whereIn('status', self::APPROVED_STATUSES)->sum('advance_recovery_value');
            $recovery = (float) ($validated['advance_recovery_value'] ?? 0);
            abort_if($recovery > max(0, $advancePaid - $previousRecovery) + 0.0001, 422, __('construction::lang.advance_recovery_exceeds_remaining'));
            $business = Business::findOrFail($this->businessId());
            $taxRate = $business->default_sales_tax ? TaxRate::where('business_id', $business->id)->find($business->default_sales_tax) : null;
            $certificate = $project->customerCertificates()->create([
                'business_id' => $this->businessId(),
                'boq_version_id' => $approvedBoq->id,
                'contract_id' => $contract->id,
                'measurement_id' => $measurementId,
                'number' => $this->nextNumber($project),
                'certificate_date' => $validated['certificate_date'],
                'period_from' => $validated['period_from'] ?? $measurement->period_from,
                'period_to' => $validated['period_to'] ?? $measurement->period_to,
                'status' => 'draft',
                'previous_gross' => $previousGross,
                'retention_percent' => $contract->retention_percent,
                'retention_due_date' => $validated['retention_due_date'] ?? null,
                'advance_recovery_value' => $recovery,
                'other_deductions_value' => $validated['other_deductions_value'] ?? 0,
                'tax_rate_id' => $taxRate?->id,
                'tax_percent' => $taxRate?->amount ?? 0,
                'created_by' => auth()->id(),
            ]);

            foreach ($unbilled as $row) {
                $previousQuantity = (float) DB::table('construction_customer_certificate_items as items')
                    ->join('construction_customer_certificates as certificates', 'certificates.id', '=', 'items.certificate_id')
                    ->where('items.boq_item_id', $row->boq_item_id)
                    ->whereIn('certificates.status', self::APPROVED_STATUSES)
                    ->sum('items.approved_quantity');
                $submittedQuantity = (float) $row->submitted_quantity;
                $unitPrice = (float) $row->sales_unit_price;
                $certificate->items()->create([
                    'business_id' => $this->businessId(),
                    'project_id' => $project->id,
                    'boq_item_id' => $row->boq_item_id,
                    'previous_quantity' => $previousQuantity,
                    'submitted_quantity' => $submittedQuantity,
                    'approved_quantity' => 0,
                    'unit_price' => $unitPrice,
                    'previous_amount' => round($previousQuantity * $unitPrice, 4),
                    'submitted_amount' => round($submittedQuantity * $unitPrice, 4),
                    'approved_amount' => 0,
                ]);
                DB::table('construction_measurement_items')
                    ->where('business_id', $this->businessId())
                    ->where('project_id', $project->id)
                    ->where('boq_item_id', $row->boq_item_id)
                    ->where('approved_quantity', '>', 0)
                    ->whereNull('certificate_id')
                    ->where('measurement_id', $measurementId)
                    ->update(['certificate_id' => $certificate->id, 'updated_at' => now()]);
            }
            $certificate->recalculate(false);
            abort_if((float) $certificate->net_due < -0.0001, 422, __('construction::lang.negative_certificate_net'));

            return $certificate;
        });

        if ($request->expectsJson()) {
            return response()->json(['id' => $certificate->id, 'url' => route('construction.projects.certificates.show', [$project->id, $certificate->id]), 'message' => __('construction::lang.certificate_created')], 201);
        }

        return redirect()->route('construction.projects.certificates.show', [$project->id, $certificate->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.certificate_created')]);
    }

    public function show(int $project, int $certificate)
    {
        $this->authorizePermission('construction.certificate.view');
        $project = $this->findProject($project);
        $certificate = $this->findCertificate($project, $certificate);
        $certificate->load([
            'items.boqItem', 'boqVersion', 'contract', 'measurement', 'invoice', 'accountingPosting.mapping',
            'retentionReleases.accountingPosting.mapping', 'retentionReleases.cancellationAccountingPosting.mapping',
            'approvedBy:id,surname,first_name,last_name,username',
        ]);
        $recoveredBefore = $project->customerCertificates()->where('contract_id', $certificate->contract_id)
            ->whereIn('status', self::APPROVED_STATUSES)->where('id', '!=', $certificate->id)
            ->when($certificate->approved_at, fn ($query) => $query->where('approved_at', '<', $certificate->approved_at))
            ->sum('advance_recovery_value');

        return view('construction::certificates.show', compact('project', 'certificate', 'recoveredBefore'));
    }

    public function approve(int $project, int $certificate)
    {
        $this->authorizePermission('construction.certificate.approve');
        $project = $this->findProject($project);
        $certificate = $this->findCertificate($project, $certificate);
        abort_unless(in_array($certificate->status, ['draft', 'submitted'], true), 422, __('construction::lang.certificate_not_editable'));

        DB::transaction(function () use ($project, $certificate) {
            ConstructionProject::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $certificate = ConstructionCustomerCertificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($certificate->status, ['draft', 'submitted'], true), 422, __('construction::lang.certificate_not_editable'));
            $previousGross = (float) $project->customerCertificates()->whereIn('status', self::APPROVED_STATUSES)->sum('current_approved_gross');
            $contract = $certificate->contract ?: $project->primaryContract;
            if ($contract) {
                $recovered = (float) $project->customerCertificates()->where('contract_id', $contract->id)
                    ->whereIn('status', self::APPROVED_STATUSES)->sum('advance_recovery_value');
                abort_if($recovered + (float) $certificate->advance_recovery_value > (float) $contract->advance_payment_value + 0.0001, 422, __('construction::lang.advance_recovery_exceeds_remaining'));
            }
            foreach ($certificate->items()->with('boqItem')->get() as $item) {
                $approved = (float) $item->submitted_quantity;
                $previous = (float) DB::table('construction_customer_certificate_items as items')
                    ->join('construction_customer_certificates as certificates', 'certificates.id', '=', 'items.certificate_id')
                    ->where('items.boq_item_id', $item->boq_item_id)
                    ->whereIn('certificates.status', self::APPROVED_STATUSES)
                    ->sum('items.approved_quantity');
                abort_if($previous + $approved > (float) $item->boqItem->contract_quantity + 0.0001, 422, __('construction::lang.certificate_exceeds_contract_quantity', ['code' => $item->boqItem->code]));
                $item->update(['previous_quantity' => $previous, 'previous_amount' => round($previous * (float) $item->unit_price, 4), 'approved_quantity' => $approved, 'approved_amount' => round($approved * (float) $item->unit_price, 4)]);
            }
            $certificate->update([
                'previous_gross' => $previousGross,
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $certificate->recalculate(true);
            abort_if((float) $certificate->net_due < -0.0001, 422, __('construction::lang.negative_certificate_net'));
            AuditTrail::record('certificate_approved', $certificate, $project->id, ['status' => 'draft'], ['status' => 'approved', 'current_approved_gross' => $certificate->current_approved_gross, 'net_due' => $certificate->net_due]);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.certificate_approved')]);
    }

    public function cancel(int $project, int $certificate)
    {
        $this->authorizePermission('construction.certificate.manage');
        $project = $this->findProject($project);
        $certificate = $this->findCertificate($project, $certificate);
        abort_unless(in_array($certificate->status, ['draft', 'submitted'], true), 422, __('construction::lang.approved_certificate_cannot_be_cancelled'));
        DB::transaction(function () use ($certificate) {
            DB::table('construction_measurement_items')->where('certificate_id', $certificate->id)->update(['certificate_id' => null, 'updated_at' => now()]);
            $certificate->update(['status' => 'cancelled']);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.certificate_cancelled')]);
    }

    public function updateRetentionDueDate(Request $request, int $project, int $certificate)
    {
        $this->authorizePermission('construction.certificate.manage');
        $project = $this->findProject($project);
        $certificate = $this->findCertificate($project, $certificate);
        abort_if($certificate->status === 'cancelled', 422, __('construction::lang.cancelled_certificate_retention_date_locked'));
        $this->normalizeBusinessDates($request, ['retention_due_date']);
        $validated = $request->validate(['retention_due_date' => ['nullable', 'date']]);
        $before = $certificate->retention_due_date?->toDateString();
        $certificate->update(['retention_due_date' => $validated['retention_due_date'] ?? null]);
        AuditTrail::record('customer_retention_due_date_updated', $certificate, $project->id,
            ['retention_due_date' => $before], ['retention_due_date' => $certificate->fresh()->retention_due_date?->toDateString()]);

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.retention_due_date_updated')]);
    }

    public function preview(int $project, int $certificate)
    {
        return view('construction::certificates.print', $this->printData($project, $certificate) + ['autoPrint' => false, 'pdfMode' => false]);
    }

    public function print(int $project, int $certificate)
    {
        return view('construction::certificates.print', $this->printData($project, $certificate) + ['autoPrint' => true, 'pdfMode' => false]);
    }

    public function pdf(int $project, int $certificate)
    {
        $data = $this->printData($project, $certificate);
        $data['pdfMode'] = true;
        $data['autoPrint'] = false;
          $mpdf = PrintPdfFactory::make('L');
          $mpdf->SetHTMLFooter('<table class="print-footer" style="width:100%;border-top:1px solid #e3eaf2;color:#8a97a8;font-family:ibmplexsansarabic;font-size:10.5px"><tr><td style="text-align:right">'.e($data['business']->name).'</td><td style="text-align:left;direction:ltr">'.e($data['certificate']->number).'</td></tr></table>');
        if ($data['certificate']->status === 'draft') {
            $mpdf->SetWatermarkText(__('construction::lang.draft_unapproved'), 0.08);
            $mpdf->showWatermarkText = true;
            $mpdf->watermark_font = PrintPdfFactory::FONT;
        }
        $mpdf->WriteHTML(view('construction::certificates.print_pdf', $data)->render());

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="certificate-'.$data['certificate']->number.'.pdf"',
        ]);
    }

    public function createInvoice(int $project, int $certificate)
    {
        $this->authorizePermission('construction.certificate.view');
        abort_unless(auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access') || $this->isAdmin(), 403);
        $project = $this->findProject($project);
        $certificate = $this->findCertificate($project, $certificate);
        abort_unless(in_array($certificate->status, self::APPROVED_STATUSES, true), 422, __('construction::lang.certificate_not_approved_for_invoice'));

        [$invoice, $posting] = DB::transaction(function () use ($project, $certificate) {
            $certificate = ConstructionCustomerCertificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
            if ($certificate->invoice_transaction_id) {
                if ($certificate->invoice && $certificate->invoice->status === 'final') {
                    $posting = app(ConstructionAccountingPoster::class)
                        ->postCustomerCertificateInvoice($certificate->load('invoice'));
                    return [$certificate->invoice, $posting];
                }
                $certificate->update(['invoice_transaction_id' => null]);
            }
            $location = BusinessLocation::where('business_id', $this->businessId())->active()->orderBy('id')->first();
            abort_unless($location, 422, __('construction::lang.invoice_location_required'));
            Business::whereKey($this->businessId())->lockForUpdate()->firstOrFail();
            $gross = (float) $certificate->current_approved_gross;
            $deductions = (float) $certificate->retention_value + (float) $certificate->advance_recovery_value + (float) $certificate->other_deductions_value;
            $util = app(TransactionUtil::class);
            $input = [
                'location_id' => $location->id, 'contact_id' => $project->customer_id,
                'status' => 'final', 'transaction_date' => now(),
                'final_total' => (float) $certificate->net_due,
                'discount_type' => 'fixed', 'discount_amount' => $deductions,
                'tax_rate_id' => $certificate->tax_rate_id,
                'sale_note' => $project->name.' / '.$certificate->number,
                'staff_note' => 'Construction project #'.$project->id.' / certificate #'.$certificate->id,
                'source' => 'construction_certificate',
            ];
            $invoice = $util->createSellTransaction($this->businessId(), $input, [
                'total_before_tax' => $gross - $deductions,
                'tax' => (float) $certificate->tax_value,
            ], auth()->id(), false);
            $lines = $certificate->items()->with('boqItem')->get()->map(function ($item) {
                [$product, $variation, $unit] = $this->invoiceProductForBoqItem($item->boqItem);
                return [
                    'product_id' => $product->id, 'variation_id' => $variation->id,
                    'product_unit_id' => $unit->id,
                    'quantity' => (float) $item->approved_quantity,
                    'unit_price' => (float) $item->unit_price,
                    'unit_price_inc_tax' => (float) $item->unit_price,
                    'item_tax' => 0, 'tax_id' => null,
                    'sell_line_note' => '',
                ];
            })->all();
            $util->createOrUpdateSellLines($invoice, $lines, $location->id, false, null, [], false);
            $invoice->payment_status = 'due';
            $invoice->save();
            $certificate->update(['invoice_transaction_id' => $invoice->id]);
            $posting = app(ConstructionAccountingPoster::class)
                ->postCustomerCertificateInvoice($certificate->fresh(['invoice']));
            return [$invoice, $posting];
        });

        return redirect()->route('construction.projects.certificates.show', [$project->id, $certificate->id])
            ->with('status', ['success' => 1, 'msg' => $posting
                ? __('construction::lang.invoice_created_and_posted', ['number' => $invoice->invoice_no])
                : __('construction::lang.invoice_created_with_number', ['number' => $invoice->invoice_no])]);
    }

    private function invoiceProductForBoqItem($boqItem): array
    {
        $unit = $boqItem->unit_id
            ? Unit::where('business_id', $this->businessId())->find($boqItem->unit_id)
            : Unit::where('business_id', $this->businessId())
                ->where(function ($query) use ($boqItem) {
                    $query->where('short_name', $boqItem->unit)->orWhere('actual_name', $boqItem->unit);
                })->first();
        abort_unless($unit, 422, __('construction::lang.invoice_unit_required'));
        $sku = 'CONST-BOQ-'.$boqItem->id;
        $product = Product::where('business_id', $this->businessId())->where('sku', $sku)->first();
        if ($product) {
            abort_unless($product->type === 'single' && ! $product->enable_stock && (int) $product->unit_id === (int) $unit->id, 422, __('construction::lang.invoice_service_product_conflict'));
            return [$product, Variation::where('product_id', $product->id)->firstOrFail(), $unit];
        }
        $product = Product::create([
            'business_id' => $this->businessId(), 'name' => $boqItem->description,
            'type' => 'single', 'unit_id' => $unit->id, 'tax_type' => 'exclusive',
            'enable_stock' => 0, 'sku' => $sku, 'barcode_type' => 'C128',
            'created_by' => auth()->id(),
        ]);
        $productVariation = ProductVariation::create(['product_id' => $product->id, 'name' => 'DUMMY', 'is_dummy' => 1]);
        $variation = Variation::create([
            'product_id' => $product->id, 'product_variation_id' => $productVariation->id,
            'name' => 'DUMMY', 'sub_sku' => $boqItem->code,
            'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0,
            'default_sell_price' => 0, 'sell_price_inc_tax' => 0,
        ]);

        return [$product, $variation, $unit];
    }

    private function printData(int $projectId, int $certificateId): array
    {
        $this->authorizePermission('construction.certificate.view');
        $project = $this->findProject($projectId);
        $certificate = $this->findCertificate($project, $certificateId);
        $certificate->load(['items.boqItem', 'contract', 'measurement', 'boqVersion']);
        $project->load(['customer', 'manager', 'consultantContact']);
        $business = Business::findOrFail($this->businessId());
        $businessLocation = BusinessLocation::where('business_id', $business->id)->active()->orderBy('id')->first();

        return compact('project', 'certificate', 'business', 'businessLocation');
    }

    private function findProject(int $id): ConstructionProject
    {
        return ConstructionProject::query()->where('business_id', $this->businessId())->with('primaryContract')->findOrFail($id);
    }

    private function findCertificate(ConstructionProject $project, int $id): ConstructionCustomerCertificate
    {
        return $project->customerCertificates()->where('business_id', $this->businessId())->findOrFail($id);
    }

    private function nextNumber(ConstructionProject $project): string
    {
        $largest = $project->customerCertificates()->where('number', 'like', 'IPC-%')->pluck('number')
            ->map(fn ($number) => preg_match('/^IPC-(\d+)$/', $number, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        return 'IPC-'.str_pad((string) ($largest + 1), 4, '0', STR_PAD_LEFT);
    }
}

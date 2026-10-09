<?php

namespace Modules\Construction\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Account;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractCertificateItem;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Construction\Entities\ConstructionAccountingSetting;
use Modules\Construction\Support\ConstructionAccountingPoster;

class SubcontractCertificateController extends BaseController
{
    public function store(Request $request, int $subcontract)
    {
        $this->authorizePermission('construction.subcontract.manage');
        $contract = $this->findContract($subcontract);
        abort_unless($contract->status === 'approved', 422, __('construction::lang.subcontract_certificate_requires_approved_contract'));
        $this->normalizeBusinessDates($request, ['certificate_date', 'period_from', 'period_to', 'retention_due_date']);
        $validated = $request->validate([
            'certificate_date' => ['required', 'date'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'retention_due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $certificate = DB::transaction(function () use ($contract, $validated) {
            $certificate = $contract->certificates()->create($validated + [
                'business_id' => $this->businessId(),
                'project_id' => $contract->project_id,
                'number' => $this->nextNumber(),
                'retention_percent' => $contract->retention_percent,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            foreach ($contract->items as $item) {
                $certificate->items()->create([
                    'business_id' => $this->businessId(),
                    'subcontract_item_id' => $item->id,
                    'description' => $item->description,
                    'calculation_type' => $item->calculation_type,
                    'contract_quantity' => $item->quantity,
                    'unit_rate' => $item->unit_rate,
                    'previous_quantity' => $this->approvedQuantityBefore($item->id),
                    'current_quantity' => 0,
                    'current_value' => 0,
                ]);
            }

            return $certificate;
        });

        return redirect()->route('construction.subcontractors.certificates.show', [$contract->id, $certificate->id])
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_certificate_created')]);
    }

    public function show(int $subcontract, int $certificate)
    {
        $this->authorizePermission('construction.subcontract.view');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        $this->loadCertificate($contract, $certificate);
        $paymentTypes = (new Util())->payment_types(null, false, $this->businessId());
        $paymentAccounts = Account::forDropdown($this->businessId(), true, false, false);
        $accountingSettings = ConstructionAccountingSetting::where('business_id', $this->businessId())->first();
        $accountingPaymentAccounts = $accountingSettings
            ? AccountingAccount::where('business_id', $this->businessId())
                ->where('status', 'active')
                ->where('account_primary_type', 'asset')
                ->where('account_sub_type_id', 3)
                ->orderBy('name')
                ->pluck('name', 'id')
            : collect();

        return view('construction::subcontractors.certificates.show', compact(
            'contract', 'certificate', 'paymentTypes', 'paymentAccounts',
            'accountingSettings', 'accountingPaymentAccounts'
        ));
    }

    public function update(Request $request, int $subcontract, int $certificate)
    {
        $this->authorizePermission('construction.subcontract.manage');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        abort_unless($certificate->isEditable(), 422, __('construction::lang.approved_subcontract_certificate_locked'));
        $this->normalizeBusinessDates($request, ['certificate_date', 'period_from', 'period_to', 'retention_due_date']);
        $this->normalizeLocalizedNumbers($request, ['other_deductions']);
        $items = $request->input('items', []);
        foreach ($items as $key => $value) {
            $items[$key] = $this->parseLocalizedNumber($value);
        }
        $request->merge(['items' => $items]);
        $validated = $request->validate([
            'certificate_date' => ['required', 'date'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'retention_due_date' => ['nullable', 'date'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array'],
            'items.*' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($certificate, $validated) {
            $certificate->update([
                'certificate_date' => $validated['certificate_date'],
                'period_from' => $validated['period_from'] ?? null,
                'period_to' => $validated['period_to'] ?? null,
                'retention_due_date' => $validated['retention_due_date'] ?? null,
                'other_deductions' => $validated['other_deductions'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);
            foreach ($certificate->items()->get() as $item) {
                if (array_key_exists($item->id, $validated['items'])) {
                    $item->update(['current_quantity' => $validated['items'][$item->id]]);
                }
            }
            $this->syncCertificate($certificate, true);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_certificate_updated')]);
    }

    public function destroy(int $subcontract, int $certificate)
    {
        $this->authorizePermission('construction.subcontract.manage');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        abort_unless($certificate->isEditable(), 422, __('construction::lang.approved_subcontract_certificate_locked'));
        DB::transaction(function () use ($certificate) {
            $certificate->items()->delete();
            $certificate->delete();
        });

        return redirect()->route('construction.subcontractors.show', $contract->id)
            ->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_certificate_deleted')]);
    }

    public function updateRetentionDueDate(Request $request, int $subcontract, int $certificate)
    {
        $this->authorizePermission('construction.subcontract.manage');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        abort_if($certificate->status === 'cancelled', 422, __('construction::lang.cancelled_certificate_retention_date_locked'));
        $this->normalizeBusinessDates($request, ['retention_due_date']);
        $validated = $request->validate(['retention_due_date' => ['nullable', 'date']]);
        $certificate->update(['retention_due_date' => $validated['retention_due_date'] ?? null]);

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.retention_due_date_updated')]);
    }

    public function approve(int $subcontract, int $certificate)
    {
        $this->authorizePermission('construction.subcontract.approve');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        abort_unless($certificate->isEditable(), 422, __('construction::lang.approved_subcontract_certificate_locked'));

        $posting = DB::transaction(function () use ($certificate) {
            $certificate = ConstructionSubcontractCertificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
            abort_unless($certificate->isEditable(), 422, __('construction::lang.approved_subcontract_certificate_locked'));
            $this->syncCertificate($certificate, true);
            if ((float) $certificate->fresh()->gross_value <= 0) {
                throw ValidationException::withMessages(['certificate' => __('construction::lang.subcontract_certificate_requires_progress')]);
            }
            $certificate->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            return app(ConstructionAccountingPoster::class)->postSubcontractCertificateApproval($certificate->fresh());
        });

        return back()->with('status', [
            'success' => 1,
            'msg' => $posting
                ? __('construction::lang.subcontract_certificate_approved_and_posted')
                : __('construction::lang.subcontract_certificate_approved'),
        ]);
    }

    public function preview(int $subcontract, int $certificate)
    {
        return view('construction::subcontractors.certificates.print', $this->printData($subcontract, $certificate) + ['autoPrint' => false]);
    }

    public function print(int $subcontract, int $certificate)
    {
        return view('construction::subcontractors.certificates.print', $this->printData($subcontract, $certificate) + ['autoPrint' => true]);
    }

    private function syncCertificate(ConstructionSubcontractCertificate $certificate, bool $validateLimit): void
    {
        $gross = 0;
        foreach ($certificate->items()->get() as $item) {
            $previous = $this->approvedQuantityBefore($item->subcontract_item_id, $certificate->id);
            $current = (float) $item->current_quantity;
            if ($validateLimit && $previous + $current > (float) $item->contract_quantity + 0.0001) {
                throw ValidationException::withMessages([
                    'items.'.$item->id => __('construction::lang.subcontract_certificate_exceeds_quantity', ['item' => $item->description]),
                ]);
            }
            $value = $item->calculation_type === 'percentage'
                ? (float) $item->unit_rate * $current / 100
                : (float) $item->unit_rate * $current;
            $item->update(['previous_quantity' => $previous, 'current_value' => round($value, 4)]);
            $gross += $value;
        }

        $retention = $gross * (float) $certificate->retention_percent / 100;
        $deductions = (float) $certificate->other_deductions;
        $net = $gross - $retention - $deductions;
        if ($net < -0.0001) {
            throw ValidationException::withMessages(['other_deductions' => __('construction::lang.subcontract_certificate_negative_net')]);
        }
        $certificate->update([
            'gross_value' => round($gross, 4),
            'retention_value' => round($retention, 4),
            'net_value' => round(max(0, $net), 4),
        ]);
    }

    private function approvedQuantityBefore(int $subcontractItemId, ?int $exceptCertificateId = null): float
    {
        return (float) DB::table('construction_subcontract_certificate_items as items')
            ->join('construction_subcontract_certificates as certificates', 'certificates.id', '=', 'items.certificate_id')
            ->where('items.subcontract_item_id', $subcontractItemId)
            ->where('certificates.status', 'approved')
            ->when($exceptCertificateId, fn ($query) => $query->where('certificates.id', '!=', $exceptCertificateId))
            ->sum('items.current_quantity');
    }

    private function parseLocalizedNumber($value): float
    {
        $request = new Request(['value' => $value]);
        $this->normalizeLocalizedNumbers($request, ['value']);
        return (float) $request->input('value', 0);
    }

    private function nextNumber(): string
    {
        return 'SUB-IPC-'.str_pad((string) ((int) ConstructionSubcontractCertificate::where('business_id', $this->businessId())->max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    private function findContract(int $id): ConstructionSubcontract
    {
        return ConstructionSubcontract::where('business_id', $this->businessId())->findOrFail($id);
    }

    private function findCertificate(int $subcontract, int $certificate): array
    {
        $contract = $this->findContract($subcontract);
        $certificate = $contract->certificates()->where('business_id', $this->businessId())->findOrFail($certificate);
        return [$contract, $certificate];
    }

    private function loadCertificate(ConstructionSubcontract $contract, ConstructionSubcontractCertificate $certificate): void
    {
        $contract->load(['project:id,code,name', 'subcontractor:id,name,supplier_business_name,mobile', 'items']);
        $certificate->load(['items.subcontractItem', 'accountingPosting.mapping', 'payments.account:id,name', 'payments.accountingAccount:id,name', 'payments.accountingPosting.mapping', 'payments.createdBy:id,surname,first_name,last_name', 'retentionReleases.accountingPosting.mapping', 'retentionReleases.cancellationAccountingPosting.mapping', 'retentionReleases.createdBy:id,surname,first_name,last_name', 'retentionReleases.cancelledBy:id,surname,first_name,last_name', 'createdBy:id,surname,first_name,last_name', 'approvedBy:id,surname,first_name,last_name']);
    }

    private function printData(int $subcontract, int $certificate): array
    {
        $this->authorizePermission('construction.subcontract.view');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        $this->loadCertificate($contract, $certificate);
        return [
            'contract' => $contract,
            'certificate' => $certificate,
            'business' => Business::findOrFail($this->businessId()),
            'businessLocation' => BusinessLocation::where('business_id', $this->businessId())->first(),
        ];
    }
}

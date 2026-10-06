<?php

namespace Modules\Construction\Http\Controllers;

use App\Account;
use App\Business;
use App\BusinessLocation;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractPayment;

class SubcontractPaymentController extends BaseController
{
    public function store(Request $request, int $subcontract, int $certificate)
    {
        $this->authorizePermission('construction.subcontract.manage');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        abort_unless($certificate->status === 'approved', 422, __('construction::lang.payment_requires_approved_certificate'));
        $this->normalizeBusinessDates($request, ['payment_date']);
        $this->normalizeLocalizedNumbers($request, ['amount']);
        $methods = array_keys((new Util())->payment_types(null, false, $this->businessId()));
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in($methods)],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->whereNull('deleted_at')->where('is_closed', 0))],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment = DB::transaction(function () use ($contract, $certificate, $validated) {
            $certificate = ConstructionSubcontractCertificate::where('business_id', $this->businessId())->lockForUpdate()->findOrFail($certificate->id);
            $paid = (float) ConstructionSubcontractPayment::where('certificate_id', $certificate->id)->where('status', 'recorded')->sum('amount');
            $remaining = round(max(0, $certificate->payableValue() - $paid), 4);
            if ((float) $validated['amount'] > $remaining + 0.0001) {
                throw ValidationException::withMessages(['amount' => __('construction::lang.payment_exceeds_remaining', ['remaining' => (new Util())->num_f($remaining)])]);
            }

            return ConstructionSubcontractPayment::create([
                'business_id' => $this->businessId(),
                'project_id' => $contract->project_id,
                'subcontract_id' => $contract->id,
                'certificate_id' => $certificate->id,
                'account_id' => $validated['account_id'] ?? null,
                'number' => $this->nextNumber(),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'recorded',
                'created_by' => auth()->id(),
            ]);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_payment_recorded', ['number' => $payment->number])]);
    }

    public function preview(int $subcontract, int $certificate, int $payment)
    {
        return view('construction::subcontractors.payments.print', $this->printData($subcontract, $certificate, $payment) + ['autoPrint' => false]);
    }

    public function print(int $subcontract, int $certificate, int $payment)
    {
        return view('construction::subcontractors.payments.print', $this->printData($subcontract, $certificate, $payment) + ['autoPrint' => true]);
    }

    private function printData(int $subcontract, int $certificate, int $payment): array
    {
        $this->authorizePermission('construction.subcontract.view');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        $payment = $certificate->payments()->where('business_id', $this->businessId())->findOrFail($payment);
        $contract->load(['project:id,code,name', 'subcontractor:id,name,supplier_business_name,mobile']);
        $payment->load(['account:id,name', 'createdBy:id,surname,first_name,last_name']);
        $paymentTypes = (new Util())->payment_types(null, false, $this->businessId());

        return [
            'contract' => $contract,
            'certificate' => $certificate,
            'payment' => $payment,
            'paymentMethod' => $paymentTypes[$payment->method] ?? $payment->method,
            'business' => Business::findOrFail($this->businessId()),
            'businessLocation' => BusinessLocation::where('business_id', $this->businessId())->first(),
        ];
    }

    private function findCertificate(int $subcontract, int $certificate): array
    {
        $contract = ConstructionSubcontract::where('business_id', $this->businessId())->findOrFail($subcontract);
        $certificate = $contract->certificates()->where('business_id', $this->businessId())->findOrFail($certificate);
        return [$contract, $certificate];
    }

    private function nextNumber(): string
    {
        return 'SUB-PAY-'.str_pad((string) ((int) ConstructionSubcontractPayment::where('business_id', $this->businessId())->max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}

<?php

namespace Modules\Construction\Http\Controllers;

use App\Account;
use App\Business;
use App\BusinessLocation;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractPayment;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Construction\Entities\ConstructionAccountingSetting;
use Modules\Construction\Support\ConstructionAccountingPoster;

class SubcontractPaymentController extends BaseController
{
    public function store(Request $request, int $subcontract, int $certificate)
    {
        $this->authorizePermission('construction.payment.record');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        abort_unless($certificate->status === 'approved', 422, __('construction::lang.payment_requires_approved_certificate'));
        $this->normalizeBusinessDates($request, ['payment_date']);
        $this->normalizeLocalizedNumbers($request, ['amount']);
        $methods = array_keys((new Util())->payment_types(null, false, $this->businessId()));
        $accountingSettings = ConstructionAccountingSetting::where('business_id', $this->businessId())->first();
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in($methods)],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->whereNull('deleted_at')->where('is_closed', 0))],
            'accounting_account_id' => [
                Rule::requiredIf((bool) $accountingSettings),
                'nullable',
                'integer',
                Rule::exists('accounting_accounts', 'id')->where(fn ($query) => $query
                    ->where('business_id', $this->businessId())
                    ->where('status', 'active')
                    ->where('account_primary_type', 'asset')
                    ->where('account_sub_type_id', 3)),
            ],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        [$payment, $posting] = DB::transaction(function () use ($contract, $certificate, $validated) {
            $certificate = ConstructionSubcontractCertificate::where('business_id', $this->businessId())->lockForUpdate()->findOrFail($certificate->id);
            $paid = (float) ConstructionSubcontractPayment::where('certificate_id', $certificate->id)->where('status', 'recorded')->sum('amount');
            $remaining = round(max(0, $certificate->payableValue() - $paid), 4);
            if ((float) $validated['amount'] > $remaining + 0.0001) {
                throw ValidationException::withMessages(['amount' => __('construction::lang.payment_exceeds_remaining', ['remaining' => (new Util())->num_f($remaining)])]);
            }

            $payment = ConstructionSubcontractPayment::create([
                'business_id' => $this->businessId(),
                'project_id' => $contract->project_id,
                'subcontract_id' => $contract->id,
                'certificate_id' => $certificate->id,
                'account_id' => $validated['account_id'] ?? null,
                'accounting_account_id' => $validated['accounting_account_id'] ?? null,
                'number' => $this->nextNumber(),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'recorded',
                'created_by' => auth()->id(),
            ]);
            $posting = app(ConstructionAccountingPoster::class)->postSubcontractPayment($payment);

            return [$payment, $posting];
        });

        return back()->with('status', [
            'success' => 1,
            'msg' => $posting
                ? __('construction::lang.subcontract_payment_recorded_and_posted', ['number' => $payment->number])
                : __('construction::lang.subcontract_payment_recorded', ['number' => $payment->number]),
        ]);
    }

    public function cancel(Request $request, int $subcontract, int $certificate, int $payment)
    {
        $this->authorizePermission('construction.cancel');
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        if (! Hash::check($validated['current_password'], auth()->user()->password)) {
            throw ValidationException::withMessages(['current_password' => __('construction::lang.invalid_manager_password')]);
        }

        DB::transaction(function () use ($certificate, $payment, $validated) {
            $payment = ConstructionSubcontractPayment::where('business_id', $this->businessId())
                ->where('certificate_id', $certificate)->lockForUpdate()->findOrFail($payment);
            abort_unless($payment->status === 'recorded', 422, __('construction::lang.payment_already_cancelled'));
            $payment->update([
                'status' => 'cancelled', 'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_by' => auth()->id(), 'cancelled_at' => now(),
            ]);
            app(ConstructionAccountingPoster::class)->reversePostedDocument(
                $payment->business_id, 'subcontract_payment', $payment->id, 'recorded',
                'CT-PAY-CAN-'.$payment->number,
                __('construction::lang.payment_reversal_note', ['number' => $payment->number]),
                $payment->cancelled_at
            );
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.payment_cancelled_and_reversed')]);
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
        $payment->load(['account:id,name', 'accountingAccount:id,name', 'createdBy:id,surname,first_name,last_name']);
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

<?php

namespace Modules\Construction\Http\Controllers;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionAccountingSetting;
use Modules\Construction\Entities\ConstructionLaborPayment;
use Modules\Construction\Entities\ConstructionLaborSheet;
use Modules\Construction\Support\ConstructionAccountingPoster;

class LaborPaymentController extends BaseController
{
    public function store(Request $request, int $sheet)
    {
        $this->authorizePermission('construction.payment.record');
        $sheet = ConstructionLaborSheet::where('business_id', $this->businessId())->findOrFail($sheet);
        abort_unless($sheet->status === 'approved', 422, __('construction::lang.labor_payment_requires_approved_sheet'));

        $settings = ConstructionAccountingSetting::where('business_id', $this->businessId())->first();
        abort_unless($settings, 422, __('construction::lang.accounting_setup_required_for_labor_payment'));

        $this->normalizeBusinessDates($request, ['payment_date']);
        $this->normalizeLocalizedNumbers($request, ['amount']);
        $methods = array_keys((new Util())->payment_types(null, false, $this->businessId()));
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in($methods)],
            'accounting_account_id' => [
                'required', 'integer',
                Rule::exists('accounting_accounts', 'id')->where(fn ($query) => $query
                    ->where('business_id', $this->businessId())
                    ->where('status', 'active')
                    ->where('account_primary_type', 'asset')
                    ->where('account_sub_type_id', 3)),
            ],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        [$payment, $posting] = DB::transaction(function () use ($sheet, $validated) {
            $sheet = ConstructionLaborSheet::where('business_id', $this->businessId())
                ->lockForUpdate()->findOrFail($sheet->id);
            abort_unless($sheet->status === 'approved', 422, __('construction::lang.labor_payment_requires_approved_sheet'));

            $remaining = $sheet->remainingValue();
            if ((float) $validated['amount'] > $remaining + 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => __('construction::lang.payment_exceeds_remaining', [
                        'remaining' => (new Util())->num_f($remaining),
                    ]),
                ]);
            }

            $payment = ConstructionLaborPayment::create([
                'business_id' => $this->businessId(),
                'project_id' => $sheet->project_id,
                'labor_sheet_id' => $sheet->id,
                'accounting_account_id' => $validated['accounting_account_id'],
                'number' => $this->nextNumber(),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'recorded',
                'created_by' => auth()->id(),
            ]);

            return [$payment, app(ConstructionAccountingPoster::class)->postLaborPayment($payment)];
        });

        return back()->with('status', [
            'success' => 1,
            'msg' => $posting
                ? __('construction::lang.labor_payment_recorded_and_posted', ['number' => $payment->number])
                : __('construction::lang.labor_payment_recorded', ['number' => $payment->number]),
        ]);
    }

    public function cancel(Request $request, int $sheet, int $payment)
    {
        $this->authorizePermission('construction.cancel');
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        if (! Hash::check($validated['current_password'], auth()->user()->password)) {
            throw ValidationException::withMessages(['current_password' => __('construction::lang.invalid_manager_password')]);
        }

        DB::transaction(function () use ($sheet, $payment, $validated) {
            $payment = ConstructionLaborPayment::where('business_id', $this->businessId())
                ->where('labor_sheet_id', $sheet)->lockForUpdate()->findOrFail($payment);
            abort_unless($payment->status === 'recorded', 422, __('construction::lang.payment_already_cancelled'));
            $payment->update([
                'status' => 'cancelled', 'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_by' => auth()->id(), 'cancelled_at' => now(),
            ]);
            app(ConstructionAccountingPoster::class)->reversePostedDocument(
                $payment->business_id, 'labor_payment', $payment->id, 'recorded',
                'CT-LAB-PAY-CAN-'.$payment->number,
                __('construction::lang.payment_reversal_note', ['number' => $payment->number]),
                $payment->cancelled_at
            );
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.payment_cancelled_and_reversed')]);
    }

    private function nextNumber(): string
    {
        return 'LAB-PAY-'.str_pad((string) ((int) ConstructionLaborPayment::where('business_id', $this->businessId())->max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}

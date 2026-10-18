<?php

namespace Modules\Construction\Http\Controllers;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionAccountingSetting;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractAdvance;
use Modules\Construction\Support\ConstructionAccountingPoster;

class SubcontractAdvanceController extends BaseController
{
    public function store(Request $request, int $subcontract)
    {
        $this->authorizePermission('construction.payment.record');
        $contract = $this->contract($subcontract);
        abort_unless($contract->status === 'approved', 422, __('construction::lang.advance_requires_approved_subcontract'));
        $this->normalizeBusinessDates($request, ['advance_date']);
        $this->normalizeLocalizedNumbers($request, ['amount']);
        $settings = ConstructionAccountingSetting::where('business_id', $this->businessId())->first();
        $methods = array_keys((new Util())->payment_types(null, false, $this->businessId()));
        $validated = $request->validate([
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in($methods)],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->whereNull('deleted_at')->where('is_closed', 0))],
            'accounting_account_id' => [Rule::requiredIf((bool) $settings), 'nullable', 'integer', Rule::exists('accounting_accounts', 'id')->where(fn ($query) => $query->where('business_id', $this->businessId())->where('status', 'active')->where('account_primary_type', 'asset')->where('account_sub_type_id', 3))],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        [$advance, $posting] = DB::transaction(function () use ($contract, $validated) {
            $advance = ConstructionSubcontractAdvance::create([
                'business_id' => $this->businessId(), 'project_id' => $contract->project_id,
                'subcontract_id' => $contract->id, 'account_id' => $validated['account_id'] ?? null,
                'accounting_account_id' => $validated['accounting_account_id'] ?? null,
                'number' => $this->nextNumber(), 'advance_date' => $validated['advance_date'],
                'amount' => $validated['amount'], 'method' => $validated['method'],
                'reference_no' => $validated['reference_no'] ?? null, 'notes' => $validated['notes'] ?? null,
                'status' => 'recorded', 'created_by' => auth()->id(),
            ]);
            return [$advance, app(ConstructionAccountingPoster::class)->postSubcontractAdvance($advance)];
        });

        return back()->with('status', ['success' => 1, 'msg' => $posting
            ? __('construction::lang.subcontract_advance_recorded_and_posted', ['number' => $advance->number])
            : __('construction::lang.subcontract_advance_recorded', ['number' => $advance->number])]);
    }

    public function cancel(Request $request, int $subcontract, int $advance)
    {
        $this->authorizePermission('construction.cancel');
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        if (! Hash::check($validated['current_password'], auth()->user()->password)) {
            throw ValidationException::withMessages(['current_password' => __('construction::lang.invalid_manager_password')]);
        }

        DB::transaction(function () use ($subcontract, $advance, $validated) {
            $advance = ConstructionSubcontractAdvance::where('business_id', $this->businessId())
                ->where('subcontract_id', $subcontract)->lockForUpdate()->findOrFail($advance);
            abort_unless($advance->isActive(), 422, __('construction::lang.advance_already_cancelled'));
            $recovered = (float) $advance->subcontract->certificates()->where('status', 'approved')->sum('advance_recovery_value');
            $otherActiveAdvances = (float) ConstructionSubcontractAdvance::where('subcontract_id', $subcontract)
                ->where('status', 'recorded')->whereKeyNot($advance->id)->sum('amount');
            if ($recovered > $otherActiveAdvances + 0.0001) {
                throw ValidationException::withMessages(['cancellation_reason' => __('construction::lang.advance_with_recovery_cannot_be_cancelled')]);
            }
            $advance->update(['status' => 'cancelled', 'cancellation_reason' => $validated['cancellation_reason'], 'cancelled_by' => auth()->id(), 'cancelled_at' => now()]);
            app(ConstructionAccountingPoster::class)->reversePostedDocument(
                $advance->business_id, 'subcontract_advance', $advance->id, 'recorded',
                'CT-ADV-CAN-'.$advance->number,
                __('construction::lang.subcontract_advance_reversal_note', ['number' => $advance->number]),
                $advance->cancelled_at
            );
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.subcontract_advance_cancelled_and_reversed')]);
    }

    private function contract(int $id): ConstructionSubcontract
    {
        return ConstructionSubcontract::where('business_id', $this->businessId())->findOrFail($id);
    }

    private function nextNumber(): string
    {
        return 'SUB-ADV-'.str_pad((string) ((int) ConstructionSubcontractAdvance::where('business_id', $this->businessId())->max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}

<?php

namespace Modules\Construction\Http\Controllers;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractRetentionRelease;
use Modules\Construction\Support\AuditTrail;

class SubcontractRetentionReleaseController extends BaseController
{
    public function store(Request $request, int $subcontract, int $certificate)
    {
        $this->authorizePermission('construction.subcontract.manage');
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);
        abort_unless($certificate->status === 'approved', 422, __('construction::lang.retention_release_requires_approved_certificate'));
        $this->normalizeBusinessDates($request, ['release_date']);
        $this->normalizeLocalizedNumbers($request, ['retention_amount']);
        $validated = $request->validate([
            'release_date' => ['required', 'date'],
            'retention_amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $release = DB::transaction(function () use ($contract, $certificate, $validated) {
            $certificate = ConstructionSubcontractCertificate::where('business_id', $this->businessId())->lockForUpdate()->findOrFail($certificate->id);
            $remaining = $certificate->retentionRemainingValue();
            if ((float) $validated['retention_amount'] > $remaining + 0.0001) {
                throw ValidationException::withMessages(['retention_amount' => __('construction::lang.retention_release_exceeds_remaining', ['remaining' => (new Util())->num_f($remaining)])]);
            }

            $release = ConstructionSubcontractRetentionRelease::create([
                'business_id' => $this->businessId(),
                'project_id' => $contract->project_id,
                'subcontract_id' => $contract->id,
                'certificate_id' => $certificate->id,
                'number' => $this->nextNumber(),
                'release_date' => $validated['release_date'],
                'amount' => $validated['retention_amount'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'recorded',
                'created_by' => auth()->id(),
            ]);
            AuditTrail::record('subcontract_retention_released', $release, $contract->project_id, [], ['number' => $release->number, 'amount' => (float) $release->amount]);

            return $release;
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.retention_release_recorded', ['number' => $release->number])]);
    }

    public function cancel(Request $request, int $subcontract, int $certificate, int $release)
    {
        $this->authorizePermission('construction.subcontract.manage');
        $validated = $request->validate(['cancellation_reason' => ['required', 'string', 'max:1000']]);
        [$contract, $certificate] = $this->findCertificate($subcontract, $certificate);

        DB::transaction(function () use ($contract, $certificate, $release, $validated) {
            $certificate = ConstructionSubcontractCertificate::where('business_id', $this->businessId())->lockForUpdate()->findOrFail($certificate->id);
            $release = $certificate->retentionReleases()->where('business_id', $this->businessId())->lockForUpdate()->findOrFail($release);
            abort_unless($release->isActive(), 422, __('construction::lang.retention_release_already_cancelled'));
            $payableAfterCancellation = round($certificate->payableValue() - (float) $release->amount, 4);
            if ($certificate->paidValue() > $payableAfterCancellation + 0.0001) {
                throw ValidationException::withMessages(['cancellation_reason' => __('construction::lang.retention_release_cancel_blocked_by_payment')]);
            }
            $release->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['cancellation_reason'],
            ]);
            AuditTrail::record('subcontract_retention_release_cancelled', $release, $contract->project_id, ['amount' => (float) $release->amount], ['reason' => $validated['cancellation_reason']]);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.retention_release_cancelled')]);
    }

    private function findCertificate(int $subcontract, int $certificate): array
    {
        $contract = ConstructionSubcontract::where('business_id', $this->businessId())->findOrFail($subcontract);
        $certificate = $contract->certificates()->where('business_id', $this->businessId())->findOrFail($certificate);
        return [$contract, $certificate];
    }

    private function nextNumber(): string
    {
        return 'SUB-RET-'.str_pad((string) ((int) ConstructionSubcontractRetentionRelease::where('business_id', $this->businessId())->max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}

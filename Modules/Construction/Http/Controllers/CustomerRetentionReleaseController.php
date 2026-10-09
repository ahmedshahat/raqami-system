<?php

namespace Modules\Construction\Http\Controllers;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionCustomerCertificate;
use Modules\Construction\Entities\ConstructionCustomerRetentionRelease;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\AuditTrail;
use Modules\Construction\Support\ConstructionAccountingPoster;

class CustomerRetentionReleaseController extends BaseController
{
    public function store(Request $request, int $project, int $certificate)
    {
        $this->authorizePermission('construction.certificate.manage');
        [$project, $certificate] = $this->findCertificate($project, $certificate);
        abort_unless($certificate->invoice_transaction_id && $certificate->accountingPosting, 422, __('construction::lang.customer_retention_release_requires_invoice'));
        $this->normalizeBusinessDates($request, ['release_date']);
        $this->normalizeLocalizedNumbers($request, ['retention_amount']);
        $validated = $request->validate([
            'release_date' => ['required', 'date'],
            'retention_amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        [$release, $posting] = DB::transaction(function () use ($project, $certificate, $validated) {
            $certificate = ConstructionCustomerCertificate::where('business_id', $this->businessId())
                ->lockForUpdate()->findOrFail($certificate->id);
            $remaining = $certificate->retentionRemainingValue();
            if ((float) $validated['retention_amount'] > $remaining + 0.0001) {
                throw ValidationException::withMessages([
                    'retention_amount' => __('construction::lang.customer_retention_release_exceeds_remaining', [
                        'remaining' => (new Util())->num_f($remaining),
                    ]),
                ]);
            }

            $release = ConstructionCustomerRetentionRelease::create([
                'business_id' => $this->businessId(),
                'project_id' => $project->id,
                'certificate_id' => $certificate->id,
                'number' => $this->nextNumber(),
                'release_date' => $validated['release_date'],
                'amount' => $validated['retention_amount'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'recorded',
                'created_by' => auth()->id(),
            ]);
            AuditTrail::record('customer_retention_released', $release, $project->id, [], [
                'number' => $release->number, 'amount' => (float) $release->amount,
            ]);
            $posting = app(ConstructionAccountingPoster::class)->postCustomerRetentionRelease($release);

            return [$release, $posting];
        });

        return back()->with('status', ['success' => 1, 'msg' => $posting
            ? __('construction::lang.customer_retention_release_recorded_and_posted', ['number' => $release->number])
            : __('construction::lang.customer_retention_release_recorded', ['number' => $release->number])]);
    }

    public function cancel(Request $request, int $project, int $certificate, int $release)
    {
        $this->authorizePermission('construction.certificate.manage');
        $validated = $request->validate(['cancellation_reason' => ['required', 'string', 'max:1000']]);
        [$project, $certificate] = $this->findCertificate($project, $certificate);

        $reversal = DB::transaction(function () use ($project, $certificate, $release, $validated) {
            $release = $certificate->retentionReleases()->where('business_id', $this->businessId())
                ->lockForUpdate()->findOrFail($release);
            abort_unless($release->isActive(), 422, __('construction::lang.customer_retention_release_already_cancelled'));
            $release->update([
                'status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(),
                'cancellation_reason' => $validated['cancellation_reason'],
            ]);
            AuditTrail::record('customer_retention_release_cancelled', $release, $project->id,
                ['amount' => (float) $release->amount], ['reason' => $validated['cancellation_reason']]);

            return app(ConstructionAccountingPoster::class)->reverseCustomerRetentionRelease($release->fresh());
        });

        return back()->with('status', ['success' => 1, 'msg' => $reversal
            ? __('construction::lang.customer_retention_release_cancelled_and_reversed')
            : __('construction::lang.customer_retention_release_cancelled')]);
    }

    private function findCertificate(int $projectId, int $certificateId): array
    {
        $project = ConstructionProject::where('business_id', $this->businessId())->findOrFail($projectId);
        $certificate = $project->customerCertificates()->where('business_id', $this->businessId())->findOrFail($certificateId);
        return [$project, $certificate];
    }

    private function nextNumber(): string
    {
        $next = (int) ConstructionCustomerRetentionRelease::where('business_id', $this->businessId())->max('id') + 1;
        return 'CUS-RET-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}

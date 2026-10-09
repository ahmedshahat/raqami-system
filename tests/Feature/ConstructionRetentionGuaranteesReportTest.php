<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionCustomerCertificate;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractRetentionRelease;
use Modules\Construction\Support\RetentionGuaranteesReport;
use Tests\TestCase;

class ConstructionRetentionGuaranteesReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_tracks_customer_rights_subcontract_liabilities_and_due_schedule(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $supplier = Contact::where('business_id', $business->id)->whereIn('type', ['supplier', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'RET-'.uniqid(), 'name' => 'Retention report test',
            'customer_id' => $customer->id, 'status' => 'active', 'created_by' => $user->id,
        ]);
        $boq = $project->boqVersions()->create([
            'business_id' => $business->id, 'version_number' => 1, 'name' => 'Retention report BOQ',
            'status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'created_by' => $user->id,
        ]);

        $customerDue = $this->customerCertificate($business->id, $project->id, $boq->id, $user->id, 'DUE', 100, now()->subDay()->toDateString());
        $this->customerCertificate($business->id, $project->id, $boq->id, $user->id, 'UPCOMING', 50, now()->addDays(20)->toDateString());
        $this->customerCertificate($business->id, $project->id, $boq->id, $user->id, 'NO-DATE', 25, null);

        $subcontract = ConstructionSubcontract::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontractor_id' => $supplier->id,
            'number' => 'RET-SUB-'.uniqid(), 'title' => 'Retention test agreement', 'total_value' => 3000,
            'retention_percent' => 5, 'status' => 'approved', 'created_by' => $user->id,
            'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        $subcontractDue = $this->subcontractCertificate($business->id, $project->id, $subcontract->id, $user->id, 'DUE', 80, now()->subDay()->toDateString());
        $this->subcontractCertificate($business->id, $project->id, $subcontract->id, $user->id, 'UPCOMING', 40, now()->addDays(20)->toDateString());
        ConstructionSubcontractRetentionRelease::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontract_id' => $subcontract->id,
            'certificate_id' => $subcontractDue->id, 'number' => 'RET-REL-'.uniqid(),
            'release_date' => now()->subDay()->toDateString(), 'amount' => 30, 'status' => 'recorded', 'created_by' => $user->id,
        ]);

        $report = app(RetentionGuaranteesReport::class)->build($business->id, $project->id, now()->toDateString(), null, null);
        $this->assertEquals(175, $report['summary']['customer_retention']);
        $this->assertEquals(120, $report['summary']['subcontract_original']);
        $this->assertEquals(30, $report['summary']['subcontract_released']);
        $this->assertEquals(90, $report['summary']['subcontract_remaining']);
        $this->assertEquals(85, $report['summary']['net_position']);
        $this->assertEquals(100, $report['summary']['due_customer']);
        $this->assertEquals(50, $report['summary']['due_subcontractor']);
        $this->assertEquals(50, $report['summary']['upcoming_customer']);
        $this->assertEquals(40, $report['summary']['upcoming_subcontractor']);
        $this->assertSame(1, $report['summary']['no_date_count']);
        $this->assertSame('due', $report['customer_rows']->firstWhere('certificate.id', $customerDue->id)['due_status']);
        $this->assertSame('partial', $report['subcontractor_rows']->firstWhere('certificate.id', $subcontractDue->id)['release_status']);

        $dueOnly = app(RetentionGuaranteesReport::class)->build($business->id, $project->id, now()->toDateString(), null, 'due');
        $this->assertCount(2, $dueOnly['rows']);
        $this->assertEquals(50, $dueOnly['summary']['subcontract_remaining']);

        $params = ['project_id' => $project->id, 'to_date' => now()->toDateString()];
        $this->get(route('construction.reports.retention-guarantees.index', $params))->assertOk()
            ->assertSee(__('construction::lang.retention_guarantees_report'))->assertSee($customerDue->number)->assertSee($subcontractDue->number);
        $this->get(route('construction.reports.retention-guarantees.print', $params))->assertOk()
            ->assertSee(__('construction::lang.retention_guarantees_print_subtitle'))->assertSee($subcontract->number);
    }

    private function customerCertificate(int $businessId, int $projectId, int $boqId, int $userId, string $suffix, float $retention, ?string $dueDate): ConstructionCustomerCertificate
    {
        return ConstructionCustomerCertificate::create([
            'business_id' => $businessId, 'project_id' => $projectId, 'boq_version_id' => $boqId,
            'number' => 'RET-CUST-'.$suffix.'-'.uniqid(), 'certificate_date' => now()->subDays(5)->toDateString(),
            'retention_due_date' => $dueDate, 'status' => 'approved', 'current_approved_gross' => $retention * 20,
            'retention_percent' => 5, 'retention_value' => $retention, 'net_due' => $retention * 19,
            'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now(),
        ]);
    }

    private function subcontractCertificate(int $businessId, int $projectId, int $subcontractId, int $userId, string $suffix, float $retention, string $dueDate): ConstructionSubcontractCertificate
    {
        return ConstructionSubcontractCertificate::create([
            'business_id' => $businessId, 'project_id' => $projectId, 'subcontract_id' => $subcontractId,
            'number' => 'RET-SUB-CERT-'.$suffix.'-'.uniqid(), 'certificate_date' => now()->subDays(4)->toDateString(),
            'retention_due_date' => $dueDate, 'gross_value' => $retention * 20, 'retention_percent' => 5,
            'retention_value' => $retention, 'other_deductions' => 0, 'net_value' => $retention * 19,
            'status' => 'approved', 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractPayment;
use Modules\Construction\Entities\ConstructionSubcontractRetentionRelease;
use Modules\Construction\Support\SubcontractorStatementReport;
use Tests\TestCase;

class ConstructionSubcontractorStatementReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_statement_calculates_movements_balances_and_prints(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $supplier = Contact::where('business_id', $business->id)->whereIn('type', ['supplier', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'STMT-'.uniqid(), 'name' => 'Statement report test',
            'customer_id' => $customer->id, 'start_date' => now()->subMonth()->toDateString(),
            'status' => 'active', 'created_by' => $user->id,
        ]);
        $subcontract = ConstructionSubcontract::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontractor_id' => $supplier->id,
            'number' => 'STMT-SUB-'.uniqid(), 'title' => 'Statement test agreement', 'total_value' => 1000,
            'retention_percent' => 5, 'status' => 'approved', 'created_by' => $user->id,
            'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        $certificate = ConstructionSubcontractCertificate::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontract_id' => $subcontract->id,
            'number' => 'STMT-CERT-'.uniqid(), 'certificate_date' => now()->subDays(3)->toDateString(),
            'gross_value' => 600, 'retention_percent' => 5, 'retention_value' => 30, 'other_deductions' => 0,
            'net_value' => 570, 'status' => 'approved', 'created_by' => $user->id,
            'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        ConstructionSubcontractRetentionRelease::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontract_id' => $subcontract->id,
            'certificate_id' => $certificate->id, 'number' => 'STMT-REL-'.uniqid(),
            'release_date' => now()->subDays(2)->toDateString(), 'amount' => 20, 'status' => 'recorded',
            'created_by' => $user->id,
        ]);
        ConstructionSubcontractPayment::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontract_id' => $subcontract->id,
            'certificate_id' => $certificate->id, 'number' => 'STMT-PAY-'.uniqid(),
            'payment_date' => now()->subDay()->toDateString(), 'amount' => 200, 'method' => 'cash',
            'status' => 'recorded', 'created_by' => $user->id,
        ]);

        $statement = app(SubcontractorStatementReport::class)->build($supplier, $project->id, null, now()->toDateString());
        $this->assertEquals(600, $statement['summary']['gross']);
        $this->assertEquals(590, $statement['summary']['payable']);
        $this->assertEquals(200, $statement['summary']['paid']);
        $this->assertEquals(390, $statement['summary']['balance']);
        $this->assertEquals(10, $statement['summary']['retention_remaining']);
        $this->assertEquals(390, $statement['summary']['closing_balance']);
        $this->assertCount(3, $statement['movements']);

        $opening = app(SubcontractorStatementReport::class)->build($supplier, $project->id, now()->toDateString(), now()->toDateString());
        $this->assertEquals(390, $opening['summary']['opening_balance']);
        $this->assertCount(0, $opening['movements']);
        $this->assertEquals(390, $opening['summary']['closing_balance']);

        $params = ['subcontractor_id' => $supplier->id, 'project_id' => $project->id, 'to_date' => now()->toDateString()];
        $this->get(route('construction.reports.subcontractor-statement.index', $params))->assertOk()
            ->assertSee(__('construction::lang.subcontractor_statement'))->assertSee($certificate->number);
        $this->get(route('construction.reports.subcontractor-statement.print', $params))->assertOk()
            ->assertSee(__('construction::lang.subcontractor_statement_print_subtitle'))->assertSee($subcontract->number);
    }
}

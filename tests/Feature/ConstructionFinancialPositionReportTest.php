<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Transaction;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Support\ProjectFinancialPositionReport;
use Tests\TestCase;

class ConstructionFinancialPositionReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_financial_position_combines_approved_costs_and_has_print_view(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $supplier = Contact::where('business_id', $business->id)->whereIn('type', ['supplier', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id,
            'code' => 'FIN-'.uniqid(),
            'name' => 'Financial position report test',
            'customer_id' => $customer->id,
            'start_date' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        Transaction::create([
            'business_id' => $business->id,
            'location_id' => $business->locations()->value('id'),
            'type' => 'expense',
            'status' => 'final',
            'payment_status' => 'due',
            'transaction_date' => now(),
            'ref_no' => 'FIN-EXP-'.uniqid(),
            'final_total' => 250,
            'total_before_tax' => 250,
            'construction_project_id' => $project->id,
            'created_by' => $user->id,
        ]);

        $subcontract = ConstructionSubcontract::create([
            'business_id' => $business->id,
            'project_id' => $project->id,
            'subcontractor_id' => $supplier->id,
            'number' => 'FIN-SUB-'.uniqid(),
            'title' => 'Financial report subcontract',
            'total_value' => 1000,
            'retention_percent' => 5,
            'status' => 'approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        ConstructionSubcontractCertificate::create([
            'business_id' => $business->id,
            'project_id' => $project->id,
            'subcontract_id' => $subcontract->id,
            'number' => 'FIN-CERT-'.uniqid(),
            'certificate_date' => now()->toDateString(),
            'gross_value' => 600,
            'retention_percent' => 5,
            'retention_value' => 30,
            'net_value' => 570,
            'status' => 'approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $report = app(ProjectFinancialPositionReport::class)->build($project, $project->start_date->toDateString(), now()->toDateString());
        $this->assertEquals(250, $report['costs']['expenses']);
        $this->assertEquals(600, $report['costs']['subcontracts']);
        $this->assertEquals(850, $report['costs']['actual']);
        $this->assertEquals(30, $report['subcontracts']['retention']);
        $this->assertEquals(570, $report['subcontracts']['balance']);

        $params = ['project_id' => $project->id, 'to_date' => now()->toDateString()];
        $this->get(route('construction.reports.index', $params))->assertOk()
            ->assertSee(__('construction::lang.financial_position_report'))
            ->assertSee(__('construction::lang.actual_cost_breakdown'))
            ->assertSee($subcontract->number);
        $this->get(route('construction.reports.financial-position.print', $params))->assertOk()
            ->assertSee(__('construction::lang.executive_owner_summary'))
            ->assertSee(__('construction::lang.accountant_details'));
    }
}

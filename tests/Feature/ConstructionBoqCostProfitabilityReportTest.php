<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Transaction;
use App\Unit;
use App\User;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionBoqCostAllocation;
use Modules\Construction\Entities\ConstructionCostCode;
use Modules\Construction\Entities\ConstructionLaborSheet;
use Modules\Construction\Entities\ConstructionMaterialDocument;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractCertificateItem;
use Modules\Construction\Support\BoqCostProfitabilityReport;
use Tests\TestCase;

class ConstructionBoqCostProfitabilityReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_combines_item_cost_sources_and_keeps_unlinked_cost_visible(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $supplier = Contact::where('business_id', $business->id)->whereIn('type', ['supplier', 'both'])->firstOrFail();
        $variation = Variation::whereHas('product', fn ($query) => $query->where('business_id', $business->id))->firstOrFail();
        $locationId = $business->locations()->value('id');
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'COST-PROFIT-'.uniqid(), 'name' => 'Cost profitability report test',
            'customer_id' => $customer->id, 'status' => 'active', 'created_by' => $user->id,
        ]);
        $boq = $project->boqVersions()->create([
            'business_id' => $business->id, 'version_number' => 1, 'name' => 'Cost report BOQ', 'status' => 'approved',
            'sales_total' => 2000, 'estimated_cost_total' => 1000, 'approved_by' => $user->id,
            'approved_at' => now(), 'created_by' => $user->id,
        ]);
        $item = $boq->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'row_type' => 'item', 'code' => 'COST-01',
            'description' => 'Cost controlled item', 'unit' => 'unit', 'contract_quantity' => 1,
            'sales_unit_price' => 2000, 'sales_total' => 2000,
        ]);
        $costCode = ConstructionCostCode::create([
            'business_id' => $business->id, 'code' => 'COST-'.uniqid(), 'name' => 'Estimated item cost', 'category' => 'material',
        ]);
        ConstructionBoqCostAllocation::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'boq_version_id' => $boq->id,
            'boq_item_id' => $item->id, 'cost_code_id' => $costCode->id, 'estimated_cost' => 1000,
        ]);

        $material = ConstructionMaterialDocument::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'location_id' => $locationId,
            'type' => 'issue', 'number' => 'COST-MAT-'.uniqid(), 'document_date' => now()->subDays(4)->toDateString(),
            'status' => 'approved', 'created_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        $material->lines()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'product_id' => $variation->product_id,
            'variation_id' => $variation->id, 'boq_item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 200, 'total_cost' => 200,
        ]);

        $labor = ConstructionLaborSheet::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'number' => 'COST-LAB-'.uniqid(),
            'period_start' => now()->subDays(3)->toDateString(), 'period_end' => now()->subDays(3)->toDateString(),
            'status' => 'approved', 'created_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        $labor->lines()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'boq_item_id' => $item->id,
            'worker_name' => 'Cost test worker', 'calculation_type' => 'day', 'quantity' => 1, 'unit_cost' => 150, 'total_cost' => 150,
        ]);

        $agreement = ConstructionSubcontract::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontractor_id' => $supplier->id,
            'number' => 'COST-SUB-'.uniqid(), 'title' => 'Cost test agreement', 'total_value' => 500,
            'retention_percent' => 5, 'status' => 'approved', 'created_by' => $user->id,
            'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        $agreementItem = $agreement->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'boq_item_id' => $item->id,
            'description' => 'Assigned cost test work', 'calculation_type' => 'lump_sum', 'quantity' => 1,
            'unit_rate' => 500, 'total_value' => 500,
        ]);
        $certificate = ConstructionSubcontractCertificate::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontract_id' => $agreement->id,
            'number' => 'COST-CERT-'.uniqid(), 'certificate_date' => now()->subDays(2)->toDateString(),
            'gross_value' => 300, 'retention_percent' => 5, 'retention_value' => 15, 'net_value' => 285,
            'status' => 'approved', 'created_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        ConstructionSubcontractCertificateItem::create([
            'business_id' => $business->id, 'certificate_id' => $certificate->id, 'subcontract_item_id' => $agreementItem->id,
            'description' => $agreementItem->description, 'calculation_type' => 'lump_sum', 'contract_quantity' => 1,
            'unit_rate' => 500, 'current_quantity' => 0.6, 'current_value' => 300,
        ]);

        foreach ([[100, $item->id, 'ITEM'], [50, null, 'UNLINKED']] as [$amount, $boqItemId, $suffix]) {
            Transaction::create([
                'business_id' => $business->id, 'location_id' => $locationId, 'type' => 'expense', 'status' => 'final',
                'payment_status' => 'due', 'transaction_date' => now()->subDay(), 'ref_no' => 'COST-EXP-'.$suffix.'-'.uniqid(),
                'final_total' => $amount, 'total_before_tax' => $amount, 'construction_project_id' => $project->id,
                'construction_boq_item_id' => $boqItemId, 'created_by' => $user->id,
            ]);
        }

        $report = app(BoqCostProfitabilityReport::class)->build($project, now()->toDateString());
        $row = $report['rows']->firstWhere('code', 'COST-01');
        $this->assertEquals(1000, $row['estimated']['total']);
        $this->assertEquals(200, $row['actual']['materials']);
        $this->assertEquals(150, $row['actual']['labor']);
        $this->assertEquals(300, $row['actual']['subcontracts']);
        $this->assertEquals(100, $row['actual']['expenses']);
        $this->assertEquals(750, $row['actual']['total']);
        $this->assertEquals(250, $row['variance']);
        $this->assertEquals(50, $report['unlinked']['expenses']);
        $this->assertEquals(800, $report['summary']['actual']);
        $this->assertEquals(200, $report['summary']['remaining_budget']);
        $this->assertEquals(1200, $report['summary']['sale_less_actual']);

        $params = ['project_id' => $project->id, 'to_date' => now()->toDateString()];
        $this->get(route('construction.reports.boq-cost-profitability.index', $params))->assertOk()
            ->assertSee(__('construction::lang.boq_cost_profitability'))->assertSee('COST-01');
        $this->get(route('construction.reports.boq-cost-profitability.print', $params))->assertOk()
            ->assertSee(__('construction::lang.boq_cost_profitability_print_subtitle'))->assertSee('COST-01');
    }
}

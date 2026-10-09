<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionCustomerCertificate;
use Modules\Construction\Entities\ConstructionCustomerCertificateItem;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractCertificateItem;
use Modules\Construction\Support\BoqSubcontractComparisonReport;
use Tests\TestCase;

class ConstructionBoqSubcontractComparisonReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_compares_contractual_and_actual_margin_by_boq_item(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $supplier = Contact::where('business_id', $business->id)->whereIn('type', ['supplier', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'CMP-'.uniqid(), 'name' => 'BOQ comparison report test',
            'customer_id' => $customer->id, 'status' => 'active', 'created_by' => $user->id,
        ]);
        $boq = $project->boqVersions()->create([
            'business_id' => $business->id, 'version_number' => 1, 'name' => 'Approved comparison BOQ',
            'status' => 'approved', 'sales_total' => 150000, 'approved_by' => $user->id,
            'approved_at' => now(), 'created_by' => $user->id,
        ]);
        $profitableItem = $boq->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'row_type' => 'item',
            'code' => 'CMP-01', 'description' => 'Profitable plaster work', 'unit' => 'm2',
            'contract_quantity' => 1000, 'sales_unit_price' => 100, 'sales_total' => 100000,
        ]);
        $lossItem = $boq->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'row_type' => 'item',
            'code' => 'CMP-02', 'description' => 'Loss electrical work', 'unit' => 'unit',
            'contract_quantity' => 1, 'sales_unit_price' => 50000, 'sales_total' => 50000,
        ]);
        $agreement = ConstructionSubcontract::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontractor_id' => $supplier->id,
            'number' => 'CMP-SUB-'.uniqid(), 'title' => 'Comparison agreement', 'total_value' => 120000,
            'retention_percent' => 5, 'status' => 'approved', 'created_by' => $user->id,
            'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        $profitableSubItem = $agreement->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'boq_item_id' => $profitableItem->id,
            'description' => 'Assigned plaster', 'calculation_type' => 'square_meter', 'quantity' => 1000,
            'unit_rate' => 65, 'total_value' => 65000,
        ]);
        $lossSubItem = $agreement->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'boq_item_id' => $lossItem->id,
            'description' => 'Assigned electrical work', 'calculation_type' => 'lump_sum', 'quantity' => 1,
            'unit_rate' => 55000, 'total_value' => 55000,
        ]);
        $customerCertificate = ConstructionCustomerCertificate::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'boq_version_id' => $boq->id,
            'number' => 'CMP-IPC-'.uniqid(), 'certificate_date' => now()->subDay()->toDateString(),
            'status' => 'approved', 'current_approved_gross' => 50000, 'net_due' => 50000,
            'created_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        foreach ([[$profitableItem, 400, 40000], [$lossItem, 1, 10000]] as [$item, $quantity, $amount]) {
            ConstructionCustomerCertificateItem::create([
                'business_id' => $business->id, 'project_id' => $project->id, 'certificate_id' => $customerCertificate->id,
                'boq_item_id' => $item->id, 'approved_quantity' => $quantity, 'unit_price' => $item->sales_unit_price,
                'approved_amount' => $amount,
            ]);
        }
        $subcontractCertificate = ConstructionSubcontractCertificate::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'subcontract_id' => $agreement->id,
            'number' => 'CMP-SC-'.uniqid(), 'certificate_date' => now()->subDay()->toDateString(),
            'gross_value' => 37000, 'retention_percent' => 5, 'retention_value' => 1850, 'net_value' => 35150,
            'status' => 'approved', 'created_by' => $user->id, 'approved_by' => $user->id, 'approved_at' => now(),
        ]);
        foreach ([[$profitableSubItem, 400, 26000], [$lossSubItem, 1, 11000]] as [$item, $quantity, $amount]) {
            ConstructionSubcontractCertificateItem::create([
                'business_id' => $business->id, 'certificate_id' => $subcontractCertificate->id,
                'subcontract_item_id' => $item->id, 'description' => $item->description,
                'calculation_type' => $item->calculation_type, 'contract_quantity' => $item->quantity,
                'unit_rate' => $item->unit_rate, 'current_quantity' => $quantity, 'current_value' => $amount,
            ]);
        }

        $report = app(BoqSubcontractComparisonReport::class)->build($project, now()->toDateString());
        $this->assertEquals(150000, $report['contract']['sales']);
        $this->assertEquals(120000, $report['contract']['assignment']);
        $this->assertEquals(30000, $report['contract']['margin']);
        $this->assertEquals(20, $report['contract']['margin_percent']);
        $this->assertEquals(50000, $report['actual']['sales']);
        $this->assertEquals(37000, $report['actual']['assignment']);
        $this->assertEquals(13000, $report['actual']['margin']);
        $this->assertSame('loss', $report['rows']->firstWhere('code', 'CMP-02')['contract']['status']);
        $this->assertSame('loss', $report['rows']->firstWhere('code', 'CMP-02')['actual']['status']);

        $params = ['project_id' => $project->id, 'to_date' => now()->toDateString()];
        $this->get(route('construction.reports.boq-subcontract-comparison.index', $params))->assertOk()
            ->assertSee(__('construction::lang.boq_subcontract_comparison'))->assertSee('CMP-01')->assertSee('CMP-02');
        $this->get(route('construction.reports.boq-subcontract-comparison.print', $params))->assertOk()
            ->assertSee(__('construction::lang.boq_subcontract_comparison_print_subtitle'))->assertSee('CMP-01');
    }
}

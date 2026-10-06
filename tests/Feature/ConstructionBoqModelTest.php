<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Modules\Construction\Entities\ConstructionBoqCostAllocation;
use Modules\Construction\Entities\ConstructionCostCode;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionBoqModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_boq_sales_cost_and_margin_are_calculated_and_approved_version_is_locked(): void
    {
        $this->assertTrue(Schema::hasTable('construction_boq_versions'));
        $this->assertTrue(Schema::hasTable('construction_boq_items'));
        $this->assertTrue(Schema::hasTable('construction_cost_codes'));
        $this->assertTrue(Schema::hasTable('construction_boq_cost_allocations'));

        $project = ConstructionProject::create([
            'business_id' => 900002,
            'code' => 'TEST-BOQ-001',
            'name' => 'BOQ test project',
            'customer_id' => 900002,
            'status' => 'draft',
            'created_by' => 900002,
        ]);
        $version = $project->boqVersions()->create([
            'business_id' => 900002,
            'version_number' => 1,
            'name' => 'Base BOQ',
            'status' => 'draft',
            'created_by' => 900002,
        ]);
        $item = $version->items()->create([
            'business_id' => 900002,
            'project_id' => $project->id,
            'row_type' => 'item',
            'code' => '01.001',
            'description' => 'Reinforced concrete',
            'unit' => 'm3',
            'contract_quantity' => 100,
            'sales_unit_price' => 2500,
            'sales_total' => 250000,
            'item_kind' => 'standard',
        ]);
        $material = ConstructionCostCode::create([
            'business_id' => 900002,
            'code' => 'MAT-CON',
            'name' => 'Concrete materials',
            'category' => 'material',
        ]);
        ConstructionBoqCostAllocation::create([
            'business_id' => 900002,
            'project_id' => $project->id,
            'boq_version_id' => $version->id,
            'boq_item_id' => $item->id,
            'cost_code_id' => $material->id,
            'estimated_cost' => 175000,
        ]);

        $version->refreshTotals();
        $version->refresh();

        $this->assertSame('250000.0000', $version->sales_total);
        $this->assertSame('175000.0000', $version->estimated_cost_total);
        $this->assertSame(75000.0, $item->fresh('allocations')->estimated_margin);
        $this->assertTrue($version->isEditable());

        $version->update(['status' => 'approved', 'approved_by' => 900002, 'approved_at' => now()]);
        $this->assertFalse($version->fresh()->isEditable());
    }
}

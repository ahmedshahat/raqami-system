<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionAuditLog;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionContractAdjustmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approved_contract_locks_original_items_and_only_approved_adjustments_reach_future_measurements(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $unit = Unit::where('business_id', $business->id)->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'TEST-ADJ-001', 'name' => 'Adjustment project',
            'customer_id' => $customer->id, 'status' => 'active', 'created_by' => $user->id,
        ]);
        $boq = $project->boqVersions()->create([
            'business_id' => $business->id, 'version_number' => 1, 'name' => 'Project items',
            'status' => 'approved', 'sales_total' => 1000, 'created_by' => $user->id,
        ]);
        $original = $boq->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'row_type' => 'item',
            'code' => 'ITEM-001', 'description' => 'Original work', 'unit_id' => $unit->id, 'unit' => $unit->short_name,
            'contract_quantity' => 100, 'sales_unit_price' => 10, 'sales_total' => 1000, 'item_kind' => 'standard',
        ]);
        $contract = $project->contracts()->create([
            'business_id' => $business->id, 'boq_version_id' => $boq->id, 'contract_number' => 'TEST-ADJ-C01',
            'title' => 'Approved contract', 'contract_type' => 'remeasurement', 'original_value' => 1000,
            'is_primary' => true, 'status' => 'active', 'created_by' => $user->id,
        ]);
        $this->get(route('construction.projects.contracts.adjustments.index', [$project->id, $contract->id]))
            ->assertOk()->assertSee(__('construction::lang.add_contract_adjustment'));

        $this->put(route('construction.projects.boq.items.update', [$project->id, $boq->id, $original->id]), [
            'code' => 'ITEM-001', 'description' => 'Changed illegally', 'unit_id' => $unit->id,
            'contract_quantity' => 120, 'sales_unit_price' => 10,
        ])->assertStatus(422);

        $payload = [
            'original_boq_item_id' => $original->id, 'code' => 'ITEM-001-V',
            'description' => 'Additional original work', 'unit_id' => $unit->id,
            'quantity' => 20, 'unit_price' => 12, 'reason' => 'Owner requested increase',
            'adjustment_date' => now()->toDateString(), 'notes' => 'Future measurements only',
        ];
        $this->post(route('construction.projects.contracts.adjustments.store', [$project->id, $contract->id]), $payload)->assertRedirect();
        $adjustment = $contract->adjustments()->firstOrFail();
        $this->assertSame('draft', $adjustment->status);
        $this->assertNull($adjustment->boq_item_id);
        $this->assertEquals(100, (float) $original->fresh()->contract_quantity);

        $draftMeasurement = $project->measurements()->create([
            'business_id' => $business->id, 'number' => 'MSR-BEFORE-ADJ',
            'measurement_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $user->id,
        ]);
        $this->get(route('construction.projects.measurements.show', [$project->id, $draftMeasurement->id]))
            ->assertOk()->assertDontSee('Additional original work');

        $this->post(route('construction.projects.contracts.adjustments.approve', [$project->id, $contract->id, $adjustment->id]))->assertRedirect();
        $adjustment = $adjustment->fresh();
        $this->assertSame('approved', $adjustment->status);
        $this->assertNotNull($adjustment->boq_item_id);
        $this->assertSame('variation', $adjustment->boqItem->item_kind);
        $this->assertEquals(20, (float) $adjustment->boqItem->contract_quantity);
        $this->assertEquals(100, (float) $original->fresh()->contract_quantity);

        $this->get(route('construction.projects.contracts.adjustments.index', [$project->id, $contract->id]))
            ->assertOk()->assertSee('Additional original work')->assertSee(__('construction::lang.approved'));

        $this->get(route('construction.projects.measurements.show', [$project->id, $draftMeasurement->id]))
            ->assertOk()->assertSee('Original work')->assertSee('Additional original work');
        $this->assertTrue(ConstructionAuditLog::where('project_id', $project->id)->where('event', 'contract_adjustment_created')->exists());
        $this->assertTrue(ConstructionAuditLog::where('project_id', $project->id)->where('event', 'contract_adjustment_approved')->exists());
    }
}

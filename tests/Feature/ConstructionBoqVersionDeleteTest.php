<?php

namespace Tests\Feature;

use App\Business;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionBoqVersion;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionBoqVersionDeleteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_only_unused_draft_versions_can_be_deleted(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'TEST-BOQ-DEL', 'name' => 'BOQ delete',
            'status' => 'draft', 'created_by' => $user->id,
        ]);
        $approved = $project->boqVersions()->create([
            'business_id' => $business->id, 'version_number' => 1, 'name' => 'Approved',
            'status' => 'approved', 'created_by' => $user->id,
        ]);
        $draft = $project->boqVersions()->create([
            'business_id' => $business->id, 'version_number' => 2, 'name' => 'Mistake',
            'status' => 'draft', 'created_by' => $user->id,
        ]);
        $item = $draft->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'row_type' => 'item',
            'code' => '01', 'description' => 'Item', 'contract_quantity' => 1, 'sales_unit_price' => 10,
            'sales_total' => 10, 'item_kind' => 'standard',
        ]);

        // زرار الحذف بيظهر للمسودة بس (رابط الحذف هو نفس رابط عرض الإصدار بطريقة DELETE)
        $html = $this->get(route('construction.projects.boq.show', [$project->id, $draft->id]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'ct-boq-version-delete'));
        $this->assertStringContainsString('action="' . route('construction.projects.boq.destroy', [$project->id, $draft->id]) . '" onsubmit', $html);

        // الإصدار المعتمد مايتمسحش
        $this->delete(route('construction.projects.boq.destroy', [$project->id, $approved->id]))
            ->assertSessionHas('status.success', 0);
        $this->assertNotNull(ConstructionBoqVersion::find($approved->id));

        // مسودة مربوطة بعقد مايتمسحش
        $contract = $project->contracts()->create([
            'business_id' => $business->id, 'boq_version_id' => $draft->id, 'contract_number' => 'CTR-DEL',
            'title' => 'Contract', 'contract_type' => 'remeasurement', 'original_value' => 10,
            'status' => 'draft', 'created_by' => $user->id,
        ]);
        $this->delete(route('construction.projects.boq.destroy', [$project->id, $draft->id]))
            ->assertSessionHas('status.success', 0);
        $this->assertNotNull(ConstructionBoqVersion::find($draft->id));
        $contract->delete();

        // مسودة حرة → تتمسح هي وبنودها ويرجع لآخر إصدار
        $this->delete(route('construction.projects.boq.destroy', [$project->id, $draft->id]))
            ->assertRedirect(route('construction.projects.boq.show', [$project->id, $approved->id]))
            ->assertSessionHas('status.success', 1);
        $this->assertNull(ConstructionBoqVersion::find($draft->id));
        $this->assertDatabaseMissing('construction_boq_items', ['id' => $item->id]);

        // الإصدار الوحيد مايتمسحش حتى لو مسودة
        $approved->update(['status' => 'draft']);
        $this->delete(route('construction.projects.boq.destroy', [$project->id, $approved->id]))
            ->assertSessionHas('status.success', 0);
        $this->assertNotNull(ConstructionBoqVersion::find($approved->id));
    }
}

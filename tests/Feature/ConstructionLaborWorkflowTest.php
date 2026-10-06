<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Modules\Construction\Entities\ConstructionLaborSheet;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionLaborWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_labor_sheet_is_calculated_approved_and_locked(): void
    {
        $business = Business::whereNotNull('owner_id')->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $user->forceFill(['password' => Hash::make('manager-secret')])->save();
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id,
            'code' => 'LAB-TEST-'.uniqid(),
            'name' => 'Labor workflow test',
            'customer_id' => $customer->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->get(route('construction.labor.index'))->assertOk()->assertSee(__('construction::lang.labor_sheets'));
        $this->get(route('construction.labor.create'))->assertOk()->assertSee($project->code);
        $response = $this->post(route('construction.labor.store'), [
            'project_id' => $project->id,
            'number' => 'LAB-TEST-'.uniqid(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'work_site' => 'Test site',
        ])->assertRedirect();
        $sheet = ConstructionLaborSheet::where('project_id', $project->id)->latest('id')->firstOrFail();
        $this->assertStringContainsString(route('construction.labor.show', $sheet->id), $response->headers->get('Location'));

        $this->post(route('construction.labor.lines.store', $sheet->id), [
            'worker_name' => 'Temporary carpenter team',
            'role_name' => 'Carpentry',
            'calculation_type' => 'day',
            'quantity' => 3,
            'unit_cost' => 500,
        ])->assertRedirect();
        $line = $sheet->lines()->firstOrFail();
        $this->assertEquals(1500, (float) $line->total_cost);
        $this->get(route('construction.labor.show', $sheet->id))->assertOk()->assertSee('Temporary carpenter team');
        $this->get(route('construction.labor.edit', $sheet->id))->assertOk()->assertSee($sheet->number);

        $this->put(route('construction.labor.lines.update', [$sheet->id, $line->id]), [
            'worker_name' => 'Temporary carpenter team',
            'role_name' => 'Carpentry',
            'calculation_type' => 'square_meter',
            'quantity' => 10,
            'unit_cost' => 200,
        ])->assertRedirect();
        $this->assertEquals(2000, (float) $line->fresh()->total_cost);
        $this->assertSame('square_meter', $line->fresh()->calculation_type);
        $this->get(route('construction.labor.preview', $sheet->id))->assertOk()
            ->assertSee(__('construction::lang.labor_sheet_print_title'))
            ->assertSee(__('construction::lang.site_supervisor'))
            ->assertSee(__('construction::lang.accounts_approval'));

        $this->post(route('construction.labor.approve', $sheet->id))->assertRedirect();
        $this->assertSame('approved', $sheet->fresh()->status);
        $this->get(route('construction.costs.index', ['project_id' => $project->id]))->assertOk()->assertSee('2,000');
        $this->get(route('construction.labor.edit', $sheet->id))->assertStatus(422);
        $this->delete(route('construction.labor.destroy', $sheet->id))->assertStatus(422);
        $this->post(route('construction.labor.approve', $sheet->id))->assertStatus(422);

        $this->from(route('construction.labor.show', $sheet->id))->post(route('construction.labor.cancel', $sheet->id), [
            'current_password' => 'wrong-password',
            'cancellation_reason' => 'Duplicate approved labor sheet',
        ])->assertRedirect(route('construction.labor.show', $sheet->id))->assertSessionHasErrors('current_password');
        $this->assertSame('approved', $sheet->fresh()->status);

        $this->post(route('construction.labor.cancel', $sheet->id), [
            'current_password' => 'manager-secret',
            'cancellation_reason' => 'Duplicate approved labor sheet',
        ])->assertRedirect(route('construction.labor.show', $sheet->id));
        $sheet->refresh();
        $this->assertSame('cancelled', $sheet->status);
        $this->assertSame('Duplicate approved labor sheet', $sheet->cancellation_reason);
        $this->assertSame($user->id, $sheet->cancelled_by);
        $this->assertNotNull($sheet->cancelled_at);
        $this->get(route('construction.costs.index', ['project_id' => $project->id]))->assertOk()->assertDontSee('2,000');
        $this->get(route('construction.labor.show', $sheet->id))->assertOk()->assertSee('Duplicate approved labor sheet');
        $this->get(route('construction.labor.preview', $sheet->id))->assertOk()->assertSee(__('construction::lang.cancelled'));
        $this->post(route('construction.labor.cancel', $sheet->id), [
            'current_password' => 'manager-secret',
            'cancellation_reason' => 'Repeated cancellation attempt',
        ])->assertStatus(422);
    }
}

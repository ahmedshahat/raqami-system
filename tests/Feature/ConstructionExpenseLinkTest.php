<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Transaction;
use App\Unit;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionExpenseLinkTest extends TestCase
{
    use DatabaseTransactions;

    public function test_expenses_can_optionally_link_to_a_project_and_one_of_its_items(): void
    {
        [$business, $user, $customer, $locationId] = $this->context();
        $this->actingAs($user);

        [$projectA, $itemA] = $this->projectWithItem($business->id, $user->id, $customer->id, 'A');
        [$projectB, $itemB] = $this->projectWithItem($business->id, $user->id, $customer->id, 'B');

        $this->get(route('expenses.create'))->assertOk()
            ->assertSee(__('expense.construction_project'))
            ->assertSee(__('expense.construction_project_item'));

        $this->post(route('expenses.store'), $this->expensePayload($locationId, 'EXP-PLAIN', 100))
            ->assertRedirect('expenses');
        $plain = Transaction::where('business_id', $business->id)->where('ref_no', 'EXP-PLAIN')->firstOrFail();
        $this->assertNull($plain->construction_project_id);
        $this->assertNull($plain->construction_boq_item_id);

        $this->post(route('expenses.store'), $this->expensePayload($locationId, 'EXP-PROJECT', 200) + [
            'construction_project_id' => $projectA->id,
        ])->assertRedirect('expenses');
        $general = Transaction::where('business_id', $business->id)->where('ref_no', 'EXP-PROJECT')->firstOrFail();
        $this->assertSame($projectA->id, $general->construction_project_id);
        $this->assertNull($general->construction_boq_item_id);

        $this->post(route('expenses.store'), $this->expensePayload($locationId, 'EXP-ITEM', 300) + [
            'construction_project_id' => $projectA->id,
            'construction_boq_item_id' => $itemA->id,
        ])->assertRedirect('expenses');
        $itemExpense = Transaction::where('business_id', $business->id)->where('ref_no', 'EXP-ITEM')->firstOrFail();
        $this->assertSame($projectA->id, $itemExpense->constructionProject->id);
        $this->assertSame($itemA->id, $itemExpense->constructionProjectItem->id);

        $this->from(route('expenses.create'))->post(route('expenses.store'), $this->expensePayload($locationId, 'EXP-INVALID', 400) + [
            'construction_project_id' => $projectA->id,
            'construction_boq_item_id' => $itemB->id,
        ])->assertRedirect(route('expenses.create'))->assertSessionHasErrors('construction_boq_item_id');
        $this->assertDatabaseMissing('transactions', ['business_id' => $business->id, 'ref_no' => 'EXP-INVALID']);

        $this->put(route('expenses.update', $itemExpense->id), $this->expensePayload($locationId, 'EXP-ITEM', 300) + [
            'construction_project_id' => $projectB->id,
            'construction_boq_item_id' => '',
        ])->assertRedirect('expenses');
        $itemExpense->refresh();
        $this->assertSame($projectB->id, $itemExpense->construction_project_id);
        $this->assertNull($itemExpense->construction_boq_item_id);

        $items = $this->getJson(route('expenses.construction-project-items', $projectA->id))
            ->assertOk()->json();
        $this->assertSame([$itemA->id], collect($items)->pluck('id')->all());

        $projectPage = $this->get(route('construction.projects.show', $projectA->id))->assertOk();
        $projectPage->assertSee(__('construction::lang.project_cost_summary'))
            ->assertSee(__('construction::lang.recent_project_expenses'))
            ->assertSee('EXP-PROJECT')
            ->assertSee('Construction expense test')
            ->assertSee('200');
        $this->assertSame(1, substr_count($projectPage->getContent(), 'ct-project-expense-note'));

        $this->assertEquals(200.0, (float) $projectA->expenses()->sum('final_total'));
        $this->assertNull($plain->fresh()->construction_project_id);

        $this->post(route('expenses.store'), array_merge(
            $this->expensePayload($locationId, 'EXP-WITHOUT-NOTES', 75),
            ['construction_project_id' => $projectA->id, 'additional_notes' => '']
        ))->assertRedirect('expenses');
        $projectPageWithoutEmptyNote = $this->get(route('construction.projects.show', $projectA->id))->assertOk();
        $projectPageWithoutEmptyNote->assertSee('EXP-WITHOUT-NOTES');
        $this->assertSame(1, substr_count($projectPageWithoutEmptyNote->getContent(), 'ct-project-expense-note'));
    }

    public function test_project_item_cannot_be_selected_without_a_project(): void
    {
        [$business, $user, $customer, $locationId] = $this->context();
        $this->actingAs($user);
        [, $item] = $this->projectWithItem($business->id, $user->id, $customer->id, 'ONLY');

        $this->from(route('expenses.create'))->post(route('expenses.store'), $this->expensePayload($locationId, 'EXP-NO-PROJECT', 50) + [
            'construction_boq_item_id' => $item->id,
        ])->assertRedirect(route('expenses.create'))->assertSessionHasErrors('construction_boq_item_id');
    }

    public function test_costs_overview_uses_project_linked_expenses_and_supports_project_filtering(): void
    {
        [$business, $user, $customer, $locationId] = $this->context();
        $this->actingAs($user);
        [$projectA] = $this->projectWithItem($business->id, $user->id, $customer->id, 'COST-A');
        [$projectB] = $this->projectWithItem($business->id, $user->id, $customer->id, 'COST-B');

        $this->post(route('expenses.store'), $this->expensePayload($locationId, 'EXP-COST-A', 250) + [
            'construction_project_id' => $projectA->id,
        ])->assertRedirect('expenses');
        $this->post(route('expenses.store'), $this->expensePayload($locationId, 'EXP-COST-B', 400) + [
            'construction_project_id' => $projectB->id,
        ])->assertRedirect('expenses');

        $overview = $this->get(route('construction.costs.index'))->assertOk();
        $overview->assertSee(__('construction::lang.costs_overview_title'))
            ->assertSee(__('construction::lang.total_recorded_costs'))
            ->assertSee(__('construction::lang.cost_materials'))
            ->assertSee(__('construction::lang.cost_labor'))
            ->assertSee('EXP-COST-A')
            ->assertSee('EXP-COST-B');

        $filtered = $this->get(route('construction.costs.index', ['project_id' => $projectA->id]))->assertOk();
        $filtered->assertSee('data-expense-reference="EXP-COST-A"', false)
            ->assertDontSee('data-expense-reference="EXP-COST-B"', false);
        $this->assertStringContainsString(
            route('expenses.index', ['construction_only' => 1, 'construction_project_id' => $projectA->id]),
            html_entity_decode($filtered->getContent())
        );
    }

    public function test_expenses_remain_independent_when_construction_is_not_in_the_company_subscription(): void
    {
        [$business, $user, $customer, $locationId] = $this->context();
        $this->actingAs($user);
        [$project, $item] = $this->projectWithItem($business->id, $user->id, $customer->id, 'DISABLED');

        $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
        $moduleUtil->shouldReceive('isModuleInstalled')->with('Construction')->andReturnTrue();
        $moduleUtil->shouldReceive('hasThePermissionInSubscription')
            ->with($business->id, 'construction_module')->andReturnFalse();
        $this->app->instance(ModuleUtil::class, $moduleUtil);

        $constructionQueries = [];
        DB::listen(function ($query) use (&$constructionQueries) {
            if (str_contains(strtolower($query->sql), 'construction_projects')
                || str_contains(strtolower($query->sql), 'construction_boq_items')) {
                $constructionQueries[] = $query->sql;
            }
        });

        $this->get(route('expenses.create'))->assertOk()
            ->assertDontSee(__('expense.construction_project'))
            ->assertDontSee(__('expense.construction_project_item'));

        $this->post(route('expenses.store'), $this->expensePayload($locationId, 'EXP-MODULE-OFF', 125) + [
            'construction_project_id' => $project->id,
            'construction_boq_item_id' => $item->id,
        ])->assertRedirect('expenses');

        $expense = Transaction::where('business_id', $business->id)
            ->where('ref_no', 'EXP-MODULE-OFF')->firstOrFail();
        $this->assertNull($expense->construction_project_id);
        $this->assertNull($expense->construction_boq_item_id);

        $this->get(route('expenses.edit', $expense->id))->assertOk()
            ->assertDontSee(__('expense.construction_project'))
            ->assertDontSee(__('expense.construction_project_item'));
        $this->put(route('expenses.update', $expense->id), $this->expensePayload($locationId, 'EXP-MODULE-OFF', 150) + [
            'construction_project_id' => $project->id,
            'construction_boq_item_id' => $item->id,
        ])->assertRedirect('expenses');
        $expense->refresh();
        $this->assertEquals(150.0, (float) $expense->final_total);
        $this->assertNull($expense->construction_project_id);
        $this->assertNull($expense->construction_boq_item_id);

        $this->getJson(route('expenses.construction-project-items', $project->id))->assertNotFound();
        $this->assertSame([], $constructionQueries, 'Disabled integration must not query Construction project or item tables.');
    }

    private function context(): array
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $locationId = $business->locations()->value('id');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        return [$business, $user, $customer, $locationId];
    }

    private function projectWithItem(int $businessId, int $userId, int $customerId, string $suffix): array
    {
        $project = ConstructionProject::create([
            'business_id' => $businessId,
            'code' => 'EXP-'.$suffix.'-'.uniqid(),
            'name' => 'Expense project '.$suffix,
            'customer_id' => $customerId,
            'status' => 'draft',
            'created_by' => $userId,
        ]);
        $boq = $project->boqVersions()->create([
            'business_id' => $businessId,
            'version_number' => 1,
            'name' => 'Project items',
            'status' => 'draft',
            'sales_total' => 1000,
            'created_by' => $userId,
        ]);
        $item = $boq->items()->create([
            'business_id' => $businessId,
            'project_id' => $project->id,
            'row_type' => 'item',
            'code' => 'ITEM-'.$suffix,
            'description' => 'Expense item '.$suffix,
            'unit' => 'وحدة',
            'contract_quantity' => 1,
            'sales_unit_price' => 1000,
            'sales_total' => 1000,
            'item_kind' => 'standard',
        ]);

        return [$project, $item];
    }

    private function expensePayload(int $locationId, string $reference, float $total): array
    {
        return [
            'location_id' => $locationId,
            'ref_no' => $reference,
            'final_total' => (string) $total,
            'additional_notes' => 'Construction expense test',
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionQuote;
use Tests\TestCase;

class ConstructionProjectFromQuoteWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_project_imports_an_accepted_quote_as_an_editable_independent_snapshot(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $unit = Unit::where('business_id', $business->id)->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $quote = ConstructionQuote::create([
            'business_id' => $business->id, 'number' => 'QTN-SNAPSHOT-'.uniqid(),
            'quote_date' => now(), 'customer_id' => $customer->id, 'title' => 'Quoted project',
            'validity_days' => 30, 'status' => 'accepted', 'total' => 500, 'created_by' => $user->id,
        ]);
        $quoteItem = $quote->items()->create([
            'business_id' => $business->id, 'code' => 'Q-001', 'description' => 'Quoted work',
            'unit_id' => $unit->id, 'unit' => $unit->short_name, 'quantity' => 10,
            'unit_price' => 50, 'total' => 500, 'sort_order' => 1, 'notes' => 'Original quote note',
        ]);

        $response = $this->postJson(route('construction.projects.store'), [
            'code' => 'PRJ-SNAPSHOT-'.uniqid(), 'name' => 'Editable project copy',
            'customer_id' => $customer->id, 'quote_id' => $quote->id,
            'consultant_selection' => 'none', 'status' => 'draft',
        ])->assertCreated();
        $project = ConstructionProject::findOrFail($response->json('id'));
        $boq = $project->boqVersions()->firstOrFail();
        $projectItem = $boq->items()->firstOrFail();
        $this->assertSame('draft', $boq->status);
        $this->assertSame($quote->id, $project->quote_id);
        $this->assertSame($project->id, $quote->fresh()->project_id);

        $projectPage = $this->get(route('construction.projects.show', $project->id))->assertOk()
            ->assertSee(__('construction::lang.boq'))
            ->assertSee(__('construction::lang.workspace_next_contract'))
            ->assertSee("تم إنشاء المشروع {$project->code} من عرض السعر {$quote->number}")
            ->assertDontSee('المقايسة')
            ->assertDontSee('&quot;code&quot;', false);
        $this->assertGreaterThanOrEqual(2, substr_count($projectPage->getContent(), __('construction::lang.workspace_complete')));
        preg_match('/<article class="ct-project-action[^>]*>(.*?)<\/article>/s', $projectPage->getContent(), $projectItemsCard);
        $this->assertNotEmpty($projectItemsCard);
        $this->assertStringContainsString(__('construction::lang.workspace_complete'), $projectItemsCard[1]);
        $this->assertStringNotContainsString(__('construction::lang.under_review'), $projectItemsCard[1]);

        $this->put(route('construction.projects.boq.items.update', [$project->id, $boq->id, $projectItem->id]), [
            'code' => 'Q-001', 'description' => 'Changed only in project', 'unit_id' => $unit->id,
            'contract_quantity' => 12, 'sales_unit_price' => 60, 'notes' => 'Project copy changed',
        ])->assertRedirect();

        $this->assertSame('Quoted work', $quoteItem->fresh()->description);
        $this->assertEquals(10, (float) $quoteItem->quantity);
        $this->assertEquals(500, (float) $quote->fresh()->total);
        $this->assertSame('Changed only in project', $projectItem->fresh()->description);
        $this->assertEquals(720, (float) $boq->fresh()->sales_total);
        $this->post(route('construction.quotes.status', $quote->id), ['status' => 'draft'])->assertStatus(422);
    }
}

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

class ConstructionQuoteListActionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_list_actions_follow_status_and_delete_only_unlinked_drafts(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $unit = Unit::where('business_id', $business->id)->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $create = function (string $status) use ($business, $customer, $user): ConstructionQuote {
            return ConstructionQuote::create([
                'business_id' => $business->id,
                'number' => 'QTN-TEST-'.uniqid(),
                'quote_date' => '2026-09-17',
                'customer_id' => $customer->id,
                'title' => 'Test '.$status,
                'validity_days' => 30,
                'status' => $status,
                'created_by' => $user->id,
            ]);
        };
        $draft = $create('draft');
        $draftItem = $draft->items()->create([
            'business_id' => $business->id, 'code' => 'A1', 'description' => 'Work item',
            'unit_id' => $unit->id, 'unit' => $unit->short_name,
            'quantity' => 2, 'unit_price' => 100, 'total' => 200,
        ]);
        $sent = $create('sent');
        $accepted = $create('accepted');
        $rejected = $create('rejected');
        $expired = $create('expired');
        $converted = $create('accepted');
        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'TEST-QTN-'.uniqid(),
            'name' => 'Converted quote project', 'customer_id' => $customer->id,
            'quote_id' => $converted->id, 'status' => 'draft', 'created_by' => $user->id,
        ]);
        $converted->update(['project_id' => $project->id]);

        $html = $this->get(route('construction.quotes.index'))->assertOk()->getContent();
        $row = function (ConstructionQuote $quote) use ($html): string {
            $this->assertSame(1, preg_match('/<tr data-quote-id="'.$quote->id.'">(.*?)<\/tr>/s', $html, $matches));

            return $matches[1];
        };
        foreach ([$draft, $sent, $accepted, $rejected, $expired, $converted] as $quote) {
            $markup = $row($quote);
            $this->assertStringContainsString(route('construction.quotes.show', $quote->id), $markup);
            $this->assertStringContainsString(route('construction.quotes.preview', $quote->id), $markup);
            $this->assertStringContainsString(route('construction.quotes.print', $quote->id), $markup);
            $this->assertStringContainsString(route('construction.quotes.pdf', $quote->id), $markup);
            $this->assertStringContainsString('ct-quote-menu-toggle', $markup);
        }
        $this->assertStringContainsString('data-target="#ct-quote-edit"', $row($draft));
        $this->assertStringContainsString('data-target="#ct-quote-delete"', $row($draft));
        $this->assertStringContainsString('data-target="#ct-quote-edit"', $row($sent));
        $this->assertStringNotContainsString('data-target="#ct-quote-delete"', $row($sent));
        $this->assertStringContainsString('data-target="#ct-quote-convert"', $row($accepted));
        foreach ([$accepted, $rejected, $expired, $converted] as $quote) {
            $this->assertStringNotContainsString('data-target="#ct-quote-edit"', $row($quote));
            $this->assertStringNotContainsString('data-target="#ct-quote-delete"', $row($quote));
        }
        $this->assertStringNotContainsString('data-target="#ct-quote-convert"', $row($converted));
        $this->assertStringContainsString(route('construction.projects.show', $project->id), $row($converted));
        foreach ([$rejected, $expired] as $quote) {
            $this->assertStringNotContainsString('data-target="#ct-quote-convert"', $row($quote));
        }

        $this->put(route('construction.quotes.update', $sent->id), [
            'quote_date' => '2026-09-18', 'customer_id' => $customer->id,
            'title' => 'Updated sent quote', 'validity_days' => 45,
        ])->assertRedirect();
        $this->assertSame('Updated sent quote', $sent->fresh()->title);
        $this->putJson(route('construction.quotes.update', $sent->id), [
            'quote_date' => '2026-09-18', 'customer_id' => $customer->id,
            'title' => 'Updated from list modal', 'validity_days' => 45,
        ])->assertOk()->assertJsonPath('message', __('construction::lang.quote_saved'));
        $this->assertSame('Updated from list modal', $sent->fresh()->title);
        $this->put(route('construction.quotes.update', $accepted->id), [
            'quote_date' => '2026-09-18', 'customer_id' => $customer->id,
            'title' => 'Blocked edit', 'validity_days' => 45,
        ])->assertStatus(422);
        $this->delete(route('construction.quotes.destroy', $accepted->id))->assertStatus(422);
        $this->delete(route('construction.quotes.destroy', $converted->id))->assertStatus(422);
        $accepted->items()->create([
            'business_id' => $business->id, 'code' => 'A2', 'description' => 'Accepted item',
            'unit_id' => $unit->id, 'unit' => $unit->short_name,
            'quantity' => 1, 'unit_price' => 100, 'total' => 100,
        ]);
        $this->postJson(route('construction.quotes.convert', $accepted->id), [
            'project_name' => 'Project from list modal',
        ])->assertOk()->assertJsonPath('message', __('construction::lang.quote_converted'));
        $this->assertNotNull($accepted->fresh()->project_id);
        $this->delete(route('construction.quotes.destroy', $draft->id))->assertRedirect(route('construction.quotes.index'));
        $this->assertNull($draft->fresh());
        $this->assertNull($draftItem->fresh());
        $ajaxDraft = $create('draft');
        $this->deleteJson(route('construction.quotes.destroy', $ajaxDraft->id))
            ->assertOk()->assertJsonPath('message', __('construction::lang.quote_deleted'));
        $this->assertNull($ajaxDraft->fresh());
    }
}

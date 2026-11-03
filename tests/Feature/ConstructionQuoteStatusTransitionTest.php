<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionAuditLog;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionQuote;
use Tests\TestCase;

class ConstructionQuoteStatusTransitionTest extends TestCase
{
    use DatabaseTransactions;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $this->actingAs(User::findOrFail($this->business->owner_id));
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    public function test_every_transition_follows_the_matrix(): void
    {
        foreach (ConstructionQuote::STATUSES as $from) {
            foreach (ConstructionQuote::STATUSES as $to) {
                $quote = $this->quote($from);
                $allowed = in_array($to, ConstructionQuote::TRANSITIONS[$from], true);

                $response = $this->from(route('construction.quotes.show', $quote->id))
                    ->post(route('construction.quotes.status', $quote->id), ['status' => $to]);

                $response->assertRedirect(route('construction.quotes.show', $quote->id));
                if ($allowed) {
                    $response->assertSessionHasNoErrors();
                    $this->assertSame($to, $quote->fresh()->status, "$from -> $to should be allowed");
                } else {
                    $response->assertSessionHasErrors('status');
                    $this->assertSame($from, $quote->fresh()->status, "$from -> $to should be rejected");
                }
            }
        }
    }

    public function test_accepted_returns_to_sent_and_becomes_editable_again(): void
    {
        $quote = $this->quote('accepted');
        $this->assertFalse($quote->isEditable());

        $this->post(route('construction.quotes.status', $quote->id), ['status' => 'sent'])->assertSessionHasNoErrors();

        $this->assertTrue($quote->fresh()->isEditable());
    }

    public function test_converted_quote_status_cannot_change(): void
    {
        $quote = $this->quote('accepted');
        $project = ConstructionProject::create([
            'business_id' => $this->business->id, 'code' => 'QST-'.uniqid(), 'name' => 'Converted',
            'customer_id' => $quote->customer_id, 'quote_id' => $quote->id, 'status' => 'draft',
            'created_by' => $this->business->owner_id,
        ]);
        $quote->update(['project_id' => $project->id]);

        foreach (ConstructionQuote::STATUSES as $to) {
            $this->post(route('construction.quotes.status', $quote->id), ['status' => $to])
                ->assertSessionHasErrors(['status' => __('construction::lang.quote_status_converted_locked')]);
        }
        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame(0, ConstructionAuditLog::where('auditable_type', ConstructionQuote::class)->where('auditable_id', $quote->id)->count());
    }

    public function test_status_change_is_audited_with_and_without_reason(): void
    {
        $quote = $this->quote('accepted');

        $this->post(route('construction.quotes.status', $quote->id), ['status' => 'sent', 'reason' => 'العميل طلب تعديل الكميات']);
        $this->post(route('construction.quotes.status', $quote->id), ['status' => 'rejected']);

        $logs = ConstructionAuditLog::where('auditable_type', ConstructionQuote::class)
            ->where('auditable_id', $quote->id)->where('event', 'quote_status_changed')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['status' => 'accepted'], $logs[0]->old_values);
        $this->assertSame(['status' => 'sent', 'reason' => 'العميل طلب تعديل الكميات'], $logs[0]->new_values);
        $this->assertSame(['status' => 'sent'], $logs[1]->old_values);
        $this->assertSame(['status' => 'rejected'], $logs[1]->new_values);
        $this->assertSame($this->business->owner_id, (int) $logs[0]->user_id);
        $this->assertNotNull($logs[0]->created_at);
    }

    public function test_reopening_expired_quote_keeps_it_sent_and_warns_about_validity(): void
    {
        $quote = $this->quote('expired', '2026-01-01', 10);

        $this->post(route('construction.quotes.status', $quote->id), ['status' => 'sent'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($status) => str_contains($status['msg'], '2026-01-11'));

        $this->assertSame('sent', $quote->fresh()->status);
        $this->assertTrue($quote->fresh()->isEditable());
    }

    public function test_status_modal_lists_only_allowed_transitions(): void
    {
        $quote = $this->quote('accepted');

        $html = $this->get(route('construction.quotes.show', $quote->id))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<select id="rr-quote-status-select"[^>]*>(.*?)<\/select>/s', $html, $select));
        preg_match_all('/<option value="([a-z]+)"/', $select[1], $options);
        $this->assertSame(['sent'], $options[1]);
    }

    private function quote(string $status, string $date = '2026-10-22', int $validity = 30): ConstructionQuote
    {
        $customer = Contact::where('business_id', $this->business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $unit = Unit::where('business_id', $this->business->id)->firstOrFail();
        $quote = ConstructionQuote::create([
            'business_id' => $this->business->id, 'number' => 'QTN-ST-'.uniqid(), 'quote_date' => $date,
            'customer_id' => $customer->id, 'title' => 'Status test', 'validity_days' => $validity,
            'status' => $status, 'created_by' => $this->business->owner_id,
        ]);
        $quote->items()->create([
            'business_id' => $this->business->id, 'code' => 'ITM-001', 'description' => 'Item',
            'unit_id' => $unit->id, 'unit' => $unit->short_name, 'quantity' => 1, 'unit_price' => 10, 'total' => 10,
        ]);

        return $quote;
    }
}

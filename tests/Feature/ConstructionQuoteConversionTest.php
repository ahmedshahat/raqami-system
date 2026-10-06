<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Currency;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionQuote;
use Tests\TestCase;

class ConstructionQuoteConversionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_accepted_quote_converts_once_to_project_and_approved_boq_without_reentering_items(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $business->update(['currency_id' => Currency::where('code', 'SAR')->firstOrFail()->id]);
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $unit = Unit::create(['business_id' => $business->id, 'actual_name' => 'متر مربع', 'short_name' => 'م²', 'allow_decimal' => 1, 'created_by' => $user->id]);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $this->get(route('construction.quotes.index'))->assertOk()->assertSee(__('construction::lang.quotes'));
        $this->post(route('construction.quotes.store'), [
            'quote_date' => '2026-09-17', 'customer_id' => $customer->id,
            'title' => 'إنشاء مبنى تجريبي', 'validity_days' => 30,
            'notes' => 'شروط العرض التجريبي',
        ])->assertRedirect();
        $quote = ConstructionQuote::where('business_id', $business->id)->latest('id')->firstOrFail();
        $this->assertSame('QTN-'.str_pad((string) $quote->id, 4, '0', STR_PAD_LEFT), $quote->number);
        $this->post(route('construction.quotes.convert', $quote->id), ['project_name' => 'مبنى تجريبي'])->assertStatus(422);

        $this->post(route('construction.quotes.items.store', $quote->id), ['items' => [[
            'code' => '01.001', 'description' => 'أعمال خرسانة', 'unit_id' => $unit->id,
            'quantity' => '100', 'unit_price' => '23570',
        ]]])->assertRedirect();
        $this->assertEquals(2357000, (float) $quote->fresh()->total);
        $this->get(route('construction.quotes.show', $quote->id))
            ->assertOk()->assertSee('saudi-riyal-new.svg')->assertSee('2,357,000');
        $preview = $this->get(route('construction.quotes.preview', $quote->id));
        $preview->assertOk()->assertSee($quote->number)->assertSee('IBM Plex Sans Arabic')
            ->assertSee('saudi-riyal-new.svg')->assertSee('class="sar-currency-icon"', false);
        $business->update(['currency_symbol_placement' => 'before']);
        session()->put('business', $business->fresh());
        $beforePreview = $this->get(route('construction.quotes.preview', $quote->id))->assertOk();
        $this->assertMatchesRegularExpression('/print-money--before[^>]*>\s*<img[^>]*saudi-riyal-new\.svg[^>]*>\s*<span>2,357,000/s', $beforePreview->getContent());
        $business->update(['currency_symbol_placement' => 'after']);
        session()->put('business', $business->fresh());
        $afterPreview = $this->get(route('construction.quotes.preview', $quote->id))->assertOk();
        $this->assertMatchesRegularExpression('/print-money--after[^>]*>\s*<span>2,357,000[^<]*<\/span>\s*<img[^>]*saudi-riyal-new\.svg/s', $afterPreview->getContent());
        $pdf = $this->get(route('construction.quotes.pdf', $quote->id));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        if (getenv('CONSTRUCTION_QUOTE_PDF_QA')) {
            file_put_contents(base_path('tmp/pdfs/quote-qa.pdf'), $pdf->getContent());
            file_put_contents(base_path('tmp/pdfs/quote-qa.html'), $preview->getContent());
        }

        $this->post(route('construction.quotes.status', $quote->id), ['status' => 'sent'])->assertRedirect();
        $this->post(route('construction.quotes.status', $quote->id), ['status' => 'accepted'])->assertRedirect();
        $this->post(route('construction.quotes.convert', $quote->id), [
            'project_name' => 'مبنى تجريبي', 'manager_id' => $user->id,
            'location' => 'القاهرة', 'start_date' => '2026-09-17', 'end_date' => '2027-09-17',
            'consultant_contact_id' => $customer->id,
        ])->assertRedirect();
        $quote = $quote->fresh();
        $project = $quote->project()->firstOrFail();
        $this->get(route('construction.quotes.index'))->assertOk()
            ->assertSee(__('construction::lang.quote_status_converted'))
            ->assertSee(route('construction.projects.show', $project->id));
        $this->assertSame($quote->id, $project->quote_id);
        $this->assertSame($customer->id, $project->customer_id);
        $this->assertSame('draft', $project->status);
        $this->assertSame($user->id, $project->manager_id);
        $this->assertSame('القاهرة', $project->location);
        $this->assertSame($customer->id, $project->consultant_contact_id);
        $this->assertTrue($project->members()->whereKey($user->id)->exists());
        $boq = $project->boqVersions()->firstOrFail();
        $this->assertSame('approved', $boq->status);
        $this->assertEquals(2357000, (float) $boq->sales_total);
        $item = $boq->items()->firstOrFail();
        $this->assertSame('01.001', $item->code);
        $this->assertSame('أعمال خرسانة', $item->description);
        $this->assertSame($unit->id, $item->unit_id);
        $this->assertEquals(100, (float) $item->contract_quantity);
        $this->assertEquals(23570, (float) $item->sales_unit_price);
        $projectPage = $this->get(route('construction.projects.show', $project->id))
            ->assertOk()->assertSee($quote->number)->assertSee(__('construction::lang.view_original_quote'));
        preg_match('/<ol class="ct-project-steps">(.*?)<\/ol>/s', $projectPage->getContent(), $stepper);
        $this->assertNotEmpty($stepper);
        $this->assertStringContainsString(__('construction::lang.boq'), $stepper[1]);
        $this->assertStringContainsString(__('construction::lang.workspace_next_contract'), $projectPage->getContent());
        $this->get(route('construction.projects.contracts.create', $project->id))
            ->assertOk()->assertSee($quote->number)->assertDontSee(__('construction::lang.linked_approved_boq'));
        $this->post(route('construction.projects.contracts.store', $project->id), [
            'title' => 'عقد تنفيذ المبنى التجريبي', 'contract_type' => 'remeasurement',
            'boq_version_id' => $boq->id,
        ])->assertRedirect();
        $contract = $project->contracts()->firstOrFail();
        $this->assertSame($boq->id, $contract->boq_version_id);
        $this->assertEquals(2357000, (float) $contract->original_value);
        $this->get(route('construction.projects.contracts.preview', [$project->id, $contract->id]))
            ->assertOk()->assertSee($quote->number);
        $this->put(route('construction.projects.contracts.update', [$project->id, $contract->id]), [
            'title' => 'عقد تنفيذ المبنى التجريبي', 'contract_type' => 'remeasurement',
            'boq_version_id' => $boq->id, 'signed_at' => '2026-09-17',
        ])->assertRedirect();
        $this->post(route('construction.projects.contracts.activate', [$project->id, $contract->id]))->assertRedirect();
        $this->post(route('construction.projects.measurements.store', $project->id), [
            'number' => 'MSR-QTN-'.$quote->id, 'measurement_date' => '2026-09-18',
        ])->assertRedirect();
        $measurement = $project->measurements()->firstOrFail();
        $this->get(route('construction.projects.measurements.show', [$project->id, $measurement->id]))
            ->assertOk()->assertSee('أعمال خرسانة')->assertSee('م²');
        $this->post(route('construction.projects.measurements.items.store', [$project->id, $measurement->id]), [
            'boq_item_id' => $item->id, 'executed_quantity' => '10',
        ])->assertRedirect();
        $this->post(route('construction.projects.measurements.approve', [$project->id, $measurement->id]))->assertRedirect();
        $this->post(route('construction.projects.certificates.store', $project->id), [
            'measurement_id' => $measurement->id, 'certificate_date' => '2026-09-19',
        ])->assertRedirect();
        $certificate = $project->customerCertificates()->firstOrFail();
        $this->post(route('construction.projects.certificates.approve', [$project->id, $certificate->id]))->assertRedirect();
        $this->post(route('construction.projects.certificates.invoice', [$project->id, $certificate->id]))->assertRedirect();
        $certificate = $certificate->fresh();
        $this->assertSame('final', $certificate->invoice->status);
        $this->assertSame($unit->id, $certificate->invoice->sell_lines->first()->product->unit_id);
        $this->assertEquals(235700, (float) $certificate->current_approved_gross);
        $this->delete(route('construction.projects.destroy', $project->id))->assertStatus(422);
        $this->post(route('construction.quotes.convert', $quote->id), ['project_name' => 'مكرر'])->assertStatus(422);
        $this->assertSame(1, $quote->project()->count());

        $business->update(['currency_id' => Currency::where('code', 'USD')->firstOrFail()->id]);
        session()->put('currency.code', 'USD');
        session()->put('currency.symbol', '$');
        $this->get(route('construction.quotes.preview', $quote->id))
            ->assertOk()->assertDontSee('saudi-riyal-new.svg');
    }
}

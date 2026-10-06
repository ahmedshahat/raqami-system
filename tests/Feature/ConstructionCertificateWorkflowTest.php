<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Currency;
use App\User;
use App\Unit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionCertificateWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approved_measurement_creates_one_certificate_and_approves_it_without_submission(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $business->update(['currency_id' => Currency::where('code', 'SAR')->firstOrFail()->id]);
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $cubicMetre = Unit::create(['business_id' => $business->id, 'actual_name' => 'متر مكعب', 'short_name' => 'م³', 'allow_decimal' => 1, 'created_by' => $user->id]);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'TEST-IPC-FLOW', 'name' => 'Certificate workflow',
            'customer_id' => $customer->id, 'status' => 'active', 'created_by' => $user->id,
        ]);
        $boq = $project->boqVersions()->create([
            'business_id' => $business->id, 'version_number' => 1, 'name' => 'BOQ',
            'status' => 'approved', 'sales_total' => 4000000, 'created_by' => $user->id,
        ]);
        $item = $boq->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'row_type' => 'item',
            'code' => '01.001', 'description' => 'Concrete', 'unit' => 'م³', 'unit_id' => $cubicMetre->id,
            'contract_quantity' => 100, 'sales_unit_price' => 2000, 'sales_total' => 200000,
            'item_kind' => 'standard',
        ]);
        $contract = $project->contracts()->create([
            'business_id' => $business->id, 'boq_version_id' => $boq->id, 'contract_number' => 'CTR-001',
            'title' => 'Contract', 'contract_type' => 'remeasurement', 'original_value' => 4000000,
            'retention_percent' => 5, 'advance_payment_value' => 10000, 'is_primary' => true,
            'status' => 'active', 'created_by' => $user->id,
        ]);
        $this->postJson(route('construction.projects.measurements.store', $project->id), [
            'number' => 'MSR-QUICK', 'measurement_date' => '2026-09-16',
        ])->assertCreated()->assertJsonStructure(['id', 'url', 'message']);
        $measurement = $project->measurements()->create([
            'business_id' => $business->id, 'number' => 'MSR-001', 'measurement_date' => '2026-09-16',
            'status' => 'approved', 'approved_by' => $user->id, 'created_by' => $user->id,
        ]);
        $measurement->items()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'boq_item_id' => $item->id,
            'executed_quantity' => 10, 'approved_quantity' => 10,
        ]);

        $this->postJson(route('construction.projects.certificates.store', $project->id), [
            'measurement_id' => $measurement->id, 'certificate_date' => '2026-09-16',
            'advance_recovery_value' => '1000',
        ])->assertCreated()->assertJsonStructure(['id', 'url', 'message']);

        $certificate = $project->customerCertificates()->firstOrFail();
        $this->assertSame('IPC-0001', $certificate->number);
        $this->assertSame($contract->id, $certificate->contract_id);
        $this->assertSame($measurement->id, $certificate->measurement_id);
        $this->assertSame('20000.0000', $certificate->current_submitted_gross);
        $this->assertSame('1000.0000', $certificate->retention_value);
        $this->assertSame('draft', $certificate->status);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('construction.projects.certificates.submit'));
        $this->get(route('construction.projects.certificates.index', $project->id))
            ->assertOk()->assertSee('IPC-0001');

        $this->post(route('construction.projects.certificates.store', $project->id), [
            'measurement_id' => $measurement->id, 'certificate_date' => '2026-09-16',
        ])->assertStatus(422);
        $this->post(route('construction.projects.certificates.approve', [$project->id, $certificate->id]))->assertRedirect();
        $this->assertSame('approved', $certificate->fresh()->status);
        $this->assertSame('10.0000', $certificate->items()->firstOrFail()->approved_quantity);
        $this->post(route('construction.projects.certificates.approve', [$project->id, $certificate->id]))->assertStatus(422);

        $this->get(route('construction.projects.certificates.show', [$project->id, $certificate->id]))
            ->assertOk()->assertSee('IPC-0001');
        $this->get(route('construction.projects.certificates.preview', [$project->id, $certificate->id]))
            ->assertOk()->assertSee('IPC-0001')->assertSee('IBM Plex Sans Arabic')->assertSee('saudi-riyal-new.svg');
        $this->get(route('construction.projects.contracts.preview', [$project->id, $contract->id]))
            ->assertOk()->assertSee('IBM Plex Sans Arabic')->assertSee('saudi-riyal-new.svg');
        $this->get(route('construction.print-font', 'regular'))
            ->assertOk()->assertHeader('Content-Type', 'font/ttf');
        $contractPdf = $this->get(route('construction.projects.contracts.pdf', [$project->id, $contract->id]));
        $contractPdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $this->get(route('construction.projects.certificates.pdf', [$project->id, $certificate->id]));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        if (getenv('CONSTRUCTION_CERTIFICATE_PDF_QA')) {
            file_put_contents(base_path('tmp/pdfs/contract-qa.pdf'), $contractPdf->getContent());
            file_put_contents(base_path('tmp/pdfs/certificate-qa.pdf'), $pdf->getContent());
        }

        $certificateUrl = route('construction.projects.certificates.show', [$project->id, $certificate->id]);
        $this->post(route('construction.projects.certificates.invoice', [$project->id, $certificate->id]))
            ->assertRedirect($certificateUrl);
        $certificate = $certificate->fresh();
        $invoiceId = $certificate->invoice_transaction_id;
        $this->assertNotNull($invoiceId);
        $this->assertSame('final', $certificate->invoice->status);
        $this->assertSame($customer->id, $certificate->invoice->contact_id);
        $this->assertSame('due', $certificate->invoice->payment_status);
        $this->assertEquals((float) $certificate->net_due, (float) $certificate->invoice->final_total);
        $this->assertCount(1, $certificate->invoice->sell_lines);
        $invoiceLine = $certificate->invoice->sell_lines->first();
        $this->assertSame('Concrete', $invoiceLine->product->name);
        $this->assertSame($cubicMetre->id, $invoiceLine->product->unit_id);
        $this->assertSame('م³', $invoiceLine->product->unit->short_name);
        $this->assertSame('01.001', $invoiceLine->variations->sub_sku);
        $this->assertEquals(10, $invoiceLine->quantity);
        $this->get(url('/sells/'.$invoiceId))->assertOk()
            ->assertSee('Certificate workflow')
            ->assertSee('IPC-0001')
            ->assertSee('م³')
            ->assertDontSee('data-construction-invoice-container', false)
            ->assertDontSee('CONST-BOQ-'.$item->id);
        $this->get($certificateUrl)
            ->assertOk()
            ->assertSee(__('construction::lang.invoice_created_with_number', ['number' => $certificate->invoice->invoice_no]))
            ->assertSee('view_invoice_url')
            ->assertSee('?construction=1', false)
            ->assertDontSee('id="ct-create-certificate-invoice"', false)
            ->assertDontSee(__('construction::lang.create_invoice_from_certificate'));
        $this->get(url('/sells/'.$invoiceId).'?construction=1')
            ->assertOk()
            ->assertSee('data-construction-invoice-container', false)
            ->assertSee('ct-module-page', false)
            ->assertSee(route('sell.printInvoice', $invoiceId), false)
            ->assertSee(route('sell.downloadPdf', $invoiceId), false)
            ->assertSee('IPC-0001');
        $this->post(route('construction.projects.certificates.invoice', [$project->id, $certificate->id]))
            ->assertRedirect($certificateUrl);
        $this->assertSame($invoiceId, $certificate->fresh()->invoice_transaction_id);
    }
}

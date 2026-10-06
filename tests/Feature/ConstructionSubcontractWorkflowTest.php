<?php

namespace Tests\Feature;

use App\Contact;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractPayment;
use Tests\TestCase;

class ConstructionSubcontractWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_subcontract_is_created_calculated_approved_and_locked(): void
    {
        $supplier = Contact::whereIn('type', ['supplier', 'both'])->where('contact_status', 'active')->firstOrFail();
        $business = $supplier->business;
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id,
            'code' => 'SUB-TEST-'.uniqid(),
            'name' => 'Subcontract workflow test',
            'customer_id' => $customer->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->get(route('construction.subcontractors.index'))->assertOk()->assertSee(__('construction::lang.subcontract_agreements'));
        $response = $this->post(route('construction.subcontractors.store'), [
            'project_id' => $project->id,
            'subcontractor_id' => $supplier->id,
            'title' => 'Electrical installation package',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'retention_percent' => 5,
        ])->assertRedirect();
        $contract = ConstructionSubcontract::where('project_id', $project->id)->latest('id')->firstOrFail();
        $this->assertStringContainsString(route('construction.subcontractors.show', $contract->id), $response->headers->get('Location'));

        $this->post(route('construction.subcontractors.items.store', $contract->id), [
            'description' => 'Install electrical points',
            'calculation_type' => 'unit',
            'quantity' => 10,
            'unit_rate' => 700,
        ])->assertRedirect();
        $item = $contract->items()->firstOrFail();
        $this->assertEquals(7000, (float) $item->total_value);
        $this->assertEquals(7000, (float) $contract->fresh()->total_value);
        $this->get(route('construction.subcontractors.show', $contract->id))->assertOk()->assertSee('Install electrical points');

        $this->put(route('construction.subcontractors.items.update', [$contract->id, $item->id]), [
            'description' => 'Install electrical points',
            'calculation_type' => 'unit',
            'quantity' => 12,
            'unit_rate' => 700,
        ])->assertRedirect();
        $this->assertEquals(8400, (float) $contract->fresh()->total_value);
        $this->get(route('construction.subcontractors.preview', $contract->id))->assertOk()->assertSee(__('construction::lang.subcontract_print_title'));

        $this->post(route('construction.subcontractors.approve', $contract->id))->assertRedirect();
        $this->assertSame('approved', $contract->fresh()->status);
        $this->put(route('construction.subcontractors.items.update', [$contract->id, $item->id]), [
            'description' => 'Locked attempt', 'calculation_type' => 'unit', 'quantity' => 1, 'unit_rate' => 1,
        ])->assertStatus(422);
        $this->delete(route('construction.subcontractors.destroy', $contract->id))->assertStatus(422);

        $this->post(route('construction.subcontractors.certificates.store', $contract->id), [
            'certificate_date' => now()->toDateString(),
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->toDateString(),
        ])->assertRedirect();
        $certificate = ConstructionSubcontractCertificate::where('subcontract_id', $contract->id)->latest('id')->firstOrFail();
        $certificateItem = $certificate->items()->firstOrFail();
        $this->put(route('construction.subcontractors.certificates.update', [$contract->id, $certificate->id]), [
            'certificate_date' => now()->toDateString(),
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->toDateString(),
            'other_deductions' => 100,
            'items' => [$certificateItem->id => 5],
        ])->assertRedirect();
        $certificate->refresh();
        $this->assertEquals(3500, (float) $certificate->gross_value);
        $this->assertEquals(175, (float) $certificate->retention_value);
        $this->assertEquals(3225, (float) $certificate->net_value);
        $this->get(route('construction.subcontractors.certificates.show', [$contract->id, $certificate->id]))
            ->assertOk()->assertSee($certificate->number);
        $this->get(route('construction.subcontractors.certificates.preview', [$contract->id, $certificate->id]))
            ->assertOk()->assertSee(__('construction::lang.subcontract_certificate_print_title'));
        $this->post(route('construction.subcontractors.certificates.approve', [$contract->id, $certificate->id]))->assertRedirect();
        $this->assertSame('approved', $certificate->fresh()->status);
        $this->put(route('construction.subcontractors.certificates.update', [$contract->id, $certificate->id]), [])->assertStatus(422);

        $this->post(route('construction.subcontractors.certificates.payments.store', [$contract->id, $certificate->id]), [
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'method' => 'cash',
            'reference_no' => 'TEST-PARTIAL',
        ])->assertRedirect();
        $certificate->refresh();
        $this->assertEquals(1000, $certificate->paidValue());
        $this->assertEquals(2225, $certificate->remainingValue());
        $this->assertSame('partially_paid', $certificate->paymentStatus());
        $payment = ConstructionSubcontractPayment::where('certificate_id', $certificate->id)->firstOrFail();
        $this->get(route('construction.subcontractors.certificates.payments.preview', [$contract->id, $certificate->id, $payment->id]))
            ->assertOk()->assertSee(__('construction::lang.subcontract_payment_receipt'));

        $this->post(route('construction.subcontractors.certificates.payments.store', [$contract->id, $certificate->id]), [
            'payment_date' => now()->toDateString(), 'amount' => 3000, 'method' => 'cash',
        ])->assertRedirect()->assertSessionHasErrors('amount');
        $this->assertEquals(1000, $certificate->paidValue());

        $this->post(route('construction.subcontractors.certificates.payments.store', [$contract->id, $certificate->id]), [
            'payment_date' => now()->toDateString(), 'amount' => 2225, 'method' => 'bank_transfer',
        ])->assertRedirect();
        $this->assertEquals(3225, $certificate->paidValue());
        $this->assertEquals(0, $certificate->remainingValue());
        $this->assertSame('paid', $certificate->paymentStatus());

        $this->post(route('construction.subcontractors.certificates.store', $contract->id), [
            'certificate_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();
        $second = ConstructionSubcontractCertificate::where('subcontract_id', $contract->id)->latest('id')->firstOrFail();
        $secondItem = $second->items()->firstOrFail();
        $this->assertEquals(5, (float) $secondItem->previous_quantity);
        $this->put(route('construction.subcontractors.certificates.update', [$contract->id, $second->id]), [
            'certificate_date' => now()->addDay()->toDateString(),
            'other_deductions' => 0,
            'items' => [$secondItem->id => 8],
        ])->assertRedirect()->assertSessionHasErrors('items.'.$secondItem->id);
        $this->assertEquals(0, (float) $secondItem->fresh()->current_quantity);
    }
}

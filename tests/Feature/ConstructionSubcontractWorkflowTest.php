<?php

namespace Tests\Feature;

use App\Contact;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Construction\Entities\ConstructionAccountingPosting;
use Modules\Construction\Entities\ConstructionAccountingSetting;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionSubcontract;
use Modules\Construction\Entities\ConstructionSubcontractCertificate;
use Modules\Construction\Entities\ConstructionSubcontractPayment;
use Modules\Construction\Entities\ConstructionSubcontractRetentionRelease;
use Modules\Construction\Entities\ConstructionSubcontractAdvance;
use Modules\Construction\Support\ConstructionAccountingPoster;
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

        $account = function (string $name, string $type) use ($business, $user) {
            return AccountingAccount::create([
                'business_id' => $business->id,
                'name' => $name.' '.uniqid(),
                'account_primary_type' => $type,
                'status' => 'active',
                'created_by' => $user->id,
            ]);
        };
        $costAccount = $account('Test subcontract cost', 'expenses');
        $payableAccount = $account('Test subcontract payable', 'liability');
        $retentionAccount = $account('Test subcontract retention', 'liability');
        $deductionAccount = $account('Test subcontract deductions', 'income');
        $inputTaxAccount = $account('Test input VAT', 'asset');
        $advanceAccount = $account('Test subcontract advances', 'asset');
        $cashAccount = AccountingAccount::create([
            'business_id' => $business->id,
            'name' => 'Test cash '.uniqid(),
            'account_primary_type' => 'asset',
            'account_sub_type_id' => 3,
            'detail_type_id' => 31,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        ConstructionAccountingSetting::updateOrCreate(
            ['business_id' => $business->id],
            [
                'subcontract_cost_account_id' => $costAccount->id,
                'subcontract_payable_account_id' => $payableAccount->id,
                'subcontract_retention_account_id' => $retentionAccount->id,
                'subcontract_deduction_account_id' => $deductionAccount->id,
                'input_tax_account_id' => $inputTaxAccount->id,
                'subcontract_advance_account_id' => $advanceAccount->id,
                'cash_account_id' => $cashAccount->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

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

        $this->post(route('construction.subcontractors.advances.store', $contract->id), [
            'advance_date' => now()->toDateString(), 'amount' => 1000, 'method' => 'cash',
            'accounting_account_id' => $cashAccount->id, 'reference_no' => 'ADV-TEST',
        ])->assertRedirect();
        $advance = ConstructionSubcontractAdvance::where('subcontract_id', $contract->id)->firstOrFail();
        $advancePosting = ConstructionAccountingPosting::where('source_type', 'subcontract_advance')
            ->where('source_id', $advance->id)->where('event', 'recorded')->firstOrFail();
        $advanceLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $advancePosting->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertEquals(1000, (float) $advanceLines['construction_subcontract_advance']->amount);
        $this->assertSame('debit', $advanceLines['construction_subcontract_advance']->type);
        $this->assertEquals(1000, (float) $advanceLines['construction_subcontract_advance_cash']->amount);
        $this->assertSame('credit', $advanceLines['construction_subcontract_advance_cash']->type);
        $this->assertEquals(1000, $contract->advanceRemaining());

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
        $posting = ConstructionAccountingPosting::where([
            'business_id' => $business->id,
            'source_type' => 'subcontract_certificate',
            'source_id' => $certificate->id,
            'event' => 'approval',
        ])->firstOrFail();
        $journalLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $posting->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertCount(4, $journalLines);
        $this->assertEquals(3500, (float) $journalLines['construction_subcontract_cost']->amount);
        $this->assertSame('debit', $journalLines['construction_subcontract_cost']->type);
        $this->assertEquals(3225, (float) $journalLines['construction_subcontract_payable']->amount);
        $this->assertSame('credit', $journalLines['construction_subcontract_payable']->type);
        $this->assertEquals(175, (float) $journalLines['construction_subcontract_retention']->amount);
        $this->assertEquals(100, (float) $journalLines['construction_subcontract_deduction']->amount);
        $this->assertEquals(3500, (float) $journalLines->where('type', 'debit')->sum('amount'));
        $this->assertEquals(3500, (float) $journalLines->where('type', 'credit')->sum('amount'));
        $samePosting = DB::transaction(fn () => app(ConstructionAccountingPoster::class)
            ->postSubcontractCertificateApproval($certificate->fresh()));
        $this->assertSame($posting->id, $samePosting->id);
        $this->assertSame(1, ConstructionAccountingPosting::where('source_type', 'subcontract_certificate')
            ->where('source_id', $certificate->id)->where('event', 'approval')->count());
        $this->get(route('construction.subcontractors.certificates.show', [$contract->id, $certificate->id]))
            ->assertOk()
            ->assertSee('btn-modal', false)
            ->assertSee('data-container=".view_modal"', false)
            ->assertSee(route('journal-entry.show', $posting->accounting_mapping_id), false);
        $this->put(route('construction.subcontractors.certificates.update', [$contract->id, $certificate->id]), [])->assertStatus(422);

        $this->post(route('construction.subcontractors.certificates.payments.store', [$contract->id, $certificate->id]), [
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'method' => 'cash',
            'accounting_account_id' => $cashAccount->id,
            'reference_no' => 'TEST-PARTIAL',
        ])->assertRedirect();
        $certificate->refresh();
        $this->assertEquals(1000, $certificate->paidValue());
        $this->assertEquals(2225, $certificate->remainingValue());
        $this->assertSame('partially_paid', $certificate->paymentStatus());
        $payment = ConstructionSubcontractPayment::where('certificate_id', $certificate->id)->firstOrFail();
        $paymentPosting = ConstructionAccountingPosting::where('source_type', 'subcontract_payment')
            ->where('source_id', $payment->id)->where('event', 'recorded')->firstOrFail();
        $paymentLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $paymentPosting->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertEquals(1000, (float) $paymentLines['construction_subcontract_payable_payment']->amount);
        $this->assertSame('debit', $paymentLines['construction_subcontract_payable_payment']->type);
        $this->assertEquals(1000, (float) $paymentLines['construction_subcontract_cash_payment']->amount);
        $this->assertSame('credit', $paymentLines['construction_subcontract_cash_payment']->type);
        $this->assertEquals(
            (float) $paymentLines->where('type', 'debit')->sum('amount'),
            (float) $paymentLines->where('type', 'credit')->sum('amount')
        );
        $samePaymentPosting = DB::transaction(fn () => app(ConstructionAccountingPoster::class)
            ->postSubcontractPayment($payment->fresh()));
        $this->assertSame($paymentPosting->id, $samePaymentPosting->id);
        $this->assertSame(1, ConstructionAccountingPosting::where('source_type', 'subcontract_payment')
            ->where('source_id', $payment->id)->where('event', 'recorded')->count());
        $this->get(route('construction.subcontractors.certificates.payments.preview', [$contract->id, $certificate->id, $payment->id]))
            ->assertOk()->assertSee(__('construction::lang.subcontract_payment_receipt'));

        $this->post(route('construction.subcontractors.certificates.payments.store', [$contract->id, $certificate->id]), [
            'payment_date' => now()->toDateString(), 'amount' => 3000, 'method' => 'cash',
            'accounting_account_id' => $cashAccount->id,
        ])->assertRedirect()->assertSessionHasErrors('amount');
        $this->assertEquals(1000, $certificate->paidValue());

        $this->post(route('construction.subcontractors.certificates.payments.store', [$contract->id, $certificate->id]), [
            'payment_date' => now()->toDateString(), 'amount' => 2225, 'method' => 'bank_transfer',
            'accounting_account_id' => $cashAccount->id,
        ])->assertRedirect();
        $this->assertEquals(3225, $certificate->paidValue());
        $this->assertEquals(0, $certificate->remainingValue());
        $this->assertSame('paid', $certificate->paymentStatus());

        $costBeforeRelease = $this->get(route('construction.costs.index', ['project_id' => $project->id]))->assertOk();
        $costBeforeRelease->assertSee('data-cost-source="subcontracts" data-cost-value="3500"', false);

        $this->post(route('construction.subcontractors.certificates.retention-releases.store', [$contract->id, $certificate->id]), [
            'release_date' => now()->toDateString(),
            'retention_amount' => 100,
            'notes' => 'Partial retention release',
        ])->assertRedirect();
        $release = ConstructionSubcontractRetentionRelease::where('certificate_id', $certificate->id)->latest('id')->firstOrFail();
        $releasePosting = ConstructionAccountingPosting::where('source_type', 'subcontract_retention_release')
            ->where('source_id', $release->id)->where('event', 'recorded')->firstOrFail();
        $releaseLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $releasePosting->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertEquals(100, (float) $releaseLines['construction_retention_recorded_debit']->amount);
        $this->assertSame('debit', $releaseLines['construction_retention_recorded_debit']->type);
        $this->assertEquals(100, (float) $releaseLines['construction_retention_recorded_credit']->amount);
        $this->assertSame('credit', $releaseLines['construction_retention_recorded_credit']->type);
        $sameReleasePosting = DB::transaction(fn () => app(ConstructionAccountingPoster::class)
            ->postSubcontractRetentionRelease($release->fresh()));
        $this->assertSame($releasePosting->id, $sameReleasePosting->id);
        $this->assertSame(1, ConstructionAccountingPosting::where('source_type', 'subcontract_retention_release')
            ->where('source_id', $release->id)->where('event', 'recorded')->count());
        $this->assertEquals(100, $certificate->releasedRetentionValue());
        $this->assertEquals(75, $certificate->retentionRemainingValue());
        $this->assertEquals(3325, $certificate->payableValue());
        $this->assertEquals(100, $certificate->remainingValue());
        $this->assertSame('partially_paid', $certificate->paymentStatus());

        $this->post(route('construction.subcontractors.certificates.retention-releases.cancel', [$contract->id, $certificate->id, $release->id]), [
            'cancellation_reason' => 'Release entered too early',
        ])->assertRedirect();
        $this->assertSame('cancelled', $release->fresh()->status);
        $releaseReversal = ConstructionAccountingPosting::where('source_type', 'subcontract_retention_release')
            ->where('source_id', $release->id)->where('event', 'cancelled')->firstOrFail();
        $reversalLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $releaseReversal->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertEquals(100, (float) $reversalLines['construction_retention_cancelled_debit']->amount);
        $this->assertSame('debit', $reversalLines['construction_retention_cancelled_debit']->type);
        $this->assertEquals(100, (float) $reversalLines['construction_retention_cancelled_credit']->amount);
        $this->assertSame('credit', $reversalLines['construction_retention_cancelled_credit']->type);
        $this->assertEquals(0, $certificate->releasedRetentionValue());
        $this->assertEquals(175, $certificate->retentionRemainingValue());
        $this->assertEquals(0, $certificate->remainingValue());

        $this->post(route('construction.subcontractors.certificates.retention-releases.store', [$contract->id, $certificate->id]), [
            'release_date' => now()->toDateString(),
            'retention_amount' => 175,
        ])->assertRedirect();
        $finalRelease = ConstructionSubcontractRetentionRelease::where('certificate_id', $certificate->id)->where('status', 'recorded')->latest('id')->firstOrFail();
        $this->assertDatabaseHas('construction_accounting_postings', [
            'source_type' => 'subcontract_retention_release',
            'source_id' => $finalRelease->id,
            'event' => 'recorded',
        ]);
        $this->post(route('construction.subcontractors.certificates.retention-releases.store', [$contract->id, $certificate->id]), [
            'release_date' => now()->toDateString(),
            'retention_amount' => 1,
        ])->assertRedirect()->assertSessionHasErrors('retention_amount');

        $this->post(route('construction.subcontractors.certificates.payments.store', [$contract->id, $certificate->id]), [
            'payment_date' => now()->toDateString(), 'amount' => 175, 'method' => 'cash',
            'accounting_account_id' => $cashAccount->id,
        ])->assertRedirect();
        $this->assertEquals(3400, $certificate->paidValue());
        $this->assertEquals(0, $certificate->remainingValue());
        $this->assertSame('paid', $certificate->paymentStatus());
        $retentionPayment = ConstructionSubcontractPayment::where('certificate_id', $certificate->id)->latest('id')->firstOrFail();
        $retentionPaymentPosting = ConstructionAccountingPosting::where('source_type', 'subcontract_payment')
            ->where('source_id', $retentionPayment->id)->where('event', 'recorded')->firstOrFail();
        $retentionPaymentLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $retentionPaymentPosting->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertEquals(175, (float) $retentionPaymentLines['construction_subcontract_payable_payment']->amount);
        $this->assertSame('debit', $retentionPaymentLines['construction_subcontract_payable_payment']->type);
        $this->post(route('construction.subcontractors.certificates.retention-releases.cancel', [$contract->id, $certificate->id, $finalRelease->id]), [
            'cancellation_reason' => 'Blocked after payment',
        ])->assertRedirect()->assertSessionHasErrors('cancellation_reason');
        $this->assertSame('recorded', $finalRelease->fresh()->status);
        $this->assertDatabaseMissing('construction_accounting_postings', [
            'source_type' => 'subcontract_retention_release',
            'source_id' => $finalRelease->id,
            'event' => 'cancelled',
        ]);

        $costAfterRelease = $this->get(route('construction.costs.index', ['project_id' => $project->id]))->assertOk();
        $costAfterRelease->assertSee('data-cost-source="subcontracts" data-cost-value="3500"', false);

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

        $this->put(route('construction.subcontractors.certificates.update', [$contract->id, $second->id]), [
            'certificate_date' => now()->addDay()->toDateString(),
            'tax_percent' => 15, 'advance_recovery_value' => 500, 'other_deductions' => 0,
            'items' => [$secondItem->id => 1],
        ])->assertRedirect();
        $second->refresh();
        $this->assertEquals(700, (float) $second->gross_value);
        $this->assertEquals(105, (float) $second->tax_value);
        $this->assertEquals(35, (float) $second->retention_value);
        $this->assertEquals(500, (float) $second->advance_recovery_value);
        $this->assertEquals(270, (float) $second->net_value);
        $this->post(route('construction.subcontractors.certificates.approve', [$contract->id, $second->id]))->assertRedirect();
        $secondPosting = ConstructionAccountingPosting::where('source_type', 'subcontract_certificate')
            ->where('source_id', $second->id)->where('event', 'approval')->firstOrFail();
        $secondLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $secondPosting->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertEquals(105, (float) $secondLines['construction_subcontract_input_tax']->amount);
        $this->assertSame('debit', $secondLines['construction_subcontract_input_tax']->type);
        $this->assertEquals(500, (float) $secondLines['construction_subcontract_advance_recovery']->amount);
        $this->assertSame('credit', $secondLines['construction_subcontract_advance_recovery']->type);
        $this->assertEquals(805, (float) $secondLines->where('type', 'debit')->sum('amount'));
        $this->assertEquals(805, (float) $secondLines->where('type', 'credit')->sum('amount'));
        $this->assertEquals(500, $contract->advanceRemaining());

        $user->update(['password' => Hash::make('Test-pass-123')]);
        $this->post(route('construction.subcontractors.advances.store', $contract->id), [
            'advance_date' => now()->toDateString(), 'amount' => 100, 'method' => 'cash',
            'accounting_account_id' => $cashAccount->id,
        ])->assertRedirect();
        $cancelledAdvance = ConstructionSubcontractAdvance::where('subcontract_id', $contract->id)->latest('id')->firstOrFail();
        $this->post(route('construction.subcontractors.advances.cancel', [$contract->id, $cancelledAdvance->id]), [
            'current_password' => 'Test-pass-123', 'cancellation_reason' => 'Duplicate advance entry',
        ])->assertRedirect();
        $this->assertSame('cancelled', $cancelledAdvance->fresh()->status);
        $this->assertDatabaseHas('construction_accounting_postings', [
            'source_type' => 'subcontract_advance', 'source_id' => $cancelledAdvance->id, 'event' => 'cancelled',
        ]);
        $this->post(route('construction.subcontractors.advances.cancel', [$contract->id, $advance->id]), [
            'current_password' => 'Test-pass-123', 'cancellation_reason' => 'Cannot cancel recovered advance',
        ])->assertRedirect()->assertSessionHasErrors('cancellation_reason');
        $this->assertSame('recorded', $advance->fresh()->status);
    }
}

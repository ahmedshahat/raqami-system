<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Construction\Entities\ConstructionAccountingPosting;
use Modules\Construction\Entities\ConstructionAccountingSetting;
use Modules\Construction\Entities\ConstructionLaborPayment;
use Modules\Construction\Entities\ConstructionLaborSheet;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\ConstructionAccountingPoster;
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

        $laborCostAccount = AccountingAccount::create([
            'business_id' => $business->id, 'name' => 'Test labor cost '.uniqid(),
            'account_primary_type' => 'expenses', 'status' => 'active', 'created_by' => $user->id,
        ]);
        $laborPayableAccount = AccountingAccount::create([
            'business_id' => $business->id, 'name' => 'Test accrued wages '.uniqid(),
            'account_primary_type' => 'liability', 'status' => 'active', 'created_by' => $user->id,
        ]);
        $cashAccount = AccountingAccount::create([
            'business_id' => $business->id, 'name' => 'Test labor cash '.uniqid(),
            'account_primary_type' => 'asset', 'account_sub_type_id' => 3,
            'status' => 'active', 'created_by' => $user->id,
        ]);
        ConstructionAccountingSetting::updateOrCreate(
            ['business_id' => $business->id],
            [
                'labor_cost_account_id' => $laborCostAccount->id,
                'labor_payable_account_id' => $laborPayableAccount->id,
                'cash_account_id' => $cashAccount->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

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
        $approvalPosting = ConstructionAccountingPosting::where([
            'business_id' => $business->id, 'source_type' => 'labor_sheet',
            'source_id' => $sheet->id, 'event' => 'approval',
        ])->firstOrFail();
        $approvalLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $approvalPosting->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertSame($project->id, (int) $approvalPosting->mapping->construction_project_id);
        $this->assertTrue($approvalLines->every(fn ($line) => (int) $line->construction_project_id === $project->id));
        $this->assertCount(2, $approvalLines);
        $this->assertSame('debit', $approvalLines['construction_labor_approval_debit']->type);
        $this->assertSame($laborCostAccount->id, (int) $approvalLines['construction_labor_approval_debit']->accounting_account_id);
        $this->assertEquals(2000, (float) $approvalLines['construction_labor_approval_debit']->amount);
        $this->assertSame('credit', $approvalLines['construction_labor_approval_credit']->type);
        $this->assertSame($laborPayableAccount->id, (int) $approvalLines['construction_labor_approval_credit']->accounting_account_id);
        $sameApprovalPosting = DB::transaction(fn () => app(ConstructionAccountingPoster::class)->postLaborSheetApproval($sheet->fresh()));
        $this->assertSame($approvalPosting->id, $sameApprovalPosting->id);
        $this->get(route('construction.labor.show', $sheet->id))->assertOk()
            ->assertSee(route('journal-entry.show', $approvalPosting->accounting_mapping_id), false);
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
        $reversalPosting = ConstructionAccountingPosting::where([
            'business_id' => $business->id, 'source_type' => 'labor_sheet',
            'source_id' => $sheet->id, 'event' => 'cancelled',
        ])->firstOrFail();
        $reversalLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $reversalPosting->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertCount(2, $reversalLines);
        $this->assertSame('debit', $reversalLines['construction_labor_cancelled_debit']->type);
        $this->assertSame($laborPayableAccount->id, (int) $reversalLines['construction_labor_cancelled_debit']->accounting_account_id);
        $this->assertSame('credit', $reversalLines['construction_labor_cancelled_credit']->type);
        $this->assertSame($laborCostAccount->id, (int) $reversalLines['construction_labor_cancelled_credit']->accounting_account_id);
        $this->get(route('construction.costs.index', ['project_id' => $project->id]))->assertOk()->assertDontSee('2,000');
        $this->get(route('construction.labor.show', $sheet->id))->assertOk()->assertSee('Duplicate approved labor sheet');
        $this->get(route('construction.labor.preview', $sheet->id))->assertOk()->assertSee(__('construction::lang.cancelled'));
        $this->post(route('construction.labor.cancel', $sheet->id), [
            'current_password' => 'manager-secret',
            'cancellation_reason' => 'Repeated cancellation attempt',
        ])->assertStatus(422);

        $paidSheet = ConstructionLaborSheet::create([
            'business_id' => $business->id, 'project_id' => $project->id,
            'number' => 'LAB-PAY-TEST-'.uniqid(), 'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(), 'status' => 'draft', 'created_by' => $user->id,
        ]);
        $paidSheet->lines()->create([
            'business_id' => $business->id, 'project_id' => $project->id,
            'worker_name' => 'Paid labor team', 'calculation_type' => 'day',
            'quantity' => 4, 'unit_cost' => 500, 'total_cost' => 2000,
        ]);
        $this->post(route('construction.labor.approve', $paidSheet->id))->assertRedirect();
        $this->post(route('construction.labor.payments.store', $paidSheet->id), [
            'payment_date' => now()->toDateString(), 'amount' => 600,
            'method' => 'cash', 'accounting_account_id' => $cashAccount->id,
            'reference_no' => 'LAB-CASH-1',
        ])->assertRedirect();
        $payment = ConstructionLaborPayment::where('labor_sheet_id', $paidSheet->id)->firstOrFail();
        $this->assertEquals(600, $paidSheet->fresh()->paidValue());
        $this->assertEquals(1400, $paidSheet->fresh()->remainingValue());
        $this->assertSame('partially_paid', $paidSheet->fresh()->paymentStatus());
        $paymentPosting = ConstructionAccountingPosting::where([
            'business_id' => $business->id, 'source_type' => 'labor_payment',
            'source_id' => $payment->id, 'event' => 'recorded',
        ])->firstOrFail();
        $paymentLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $paymentPosting->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertSame($project->id, (int) $paymentPosting->mapping->construction_project_id);
        $this->assertTrue($paymentLines->every(fn ($line) => (int) $line->construction_project_id === $project->id));
        $this->assertCount(2, $paymentLines);
        $this->assertSame('debit', $paymentLines['construction_labor_payable_payment']->type);
        $this->assertSame($laborPayableAccount->id, (int) $paymentLines['construction_labor_payable_payment']->accounting_account_id);
        $this->assertSame('credit', $paymentLines['construction_labor_cash_payment']->type);
        $this->assertSame($cashAccount->id, (int) $paymentLines['construction_labor_cash_payment']->accounting_account_id);
        $this->assertEquals(600, (float) $paymentLines['construction_labor_cash_payment']->amount);
        $this->get(route('construction.labor.show', $paidSheet->id))->assertOk()
            ->assertSee($payment->number)
            ->assertSee(route('journal-entry.show', $paymentPosting->accounting_mapping_id), false);

        $this->from(route('construction.labor.show', $paidSheet->id))->post(route('construction.labor.payments.store', $paidSheet->id), [
            'payment_date' => now()->toDateString(), 'amount' => 1401,
            'method' => 'cash', 'accounting_account_id' => $cashAccount->id,
        ])->assertRedirect(route('construction.labor.show', $paidSheet->id))->assertSessionHasErrors('amount');
        $this->assertSame(1, ConstructionLaborPayment::where('labor_sheet_id', $paidSheet->id)->count());

        $this->post(route('construction.labor.cancel', $paidSheet->id), [
            'current_password' => 'manager-secret',
            'cancellation_reason' => 'Cannot cancel a paid labor sheet',
        ])->assertSessionHasErrors('cancellation_reason');
        $this->assertSame('approved', $paidSheet->fresh()->status);

        $this->post(route('construction.labor.payments.cancel', [$paidSheet->id, $payment->id]), [
            'current_password' => 'manager-secret',
            'cancellation_reason' => 'Incorrect labor payment account',
        ])->assertRedirect();
        $payment->refresh();
        $this->assertSame('cancelled', $payment->status);
        $this->assertEquals(0, $paidSheet->fresh()->paidValue());
        $paymentReversal = ConstructionAccountingPosting::where([
            'source_type' => 'labor_payment', 'source_id' => $payment->id, 'event' => 'cancelled',
        ])->firstOrFail();
        $reversedLines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $paymentReversal->accounting_mapping_id)
            ->get()->keyBy('map_type');
        $this->assertSame('credit', $reversedLines['construction_labor_payable_payment_reversal']->type);
        $this->assertSame('debit', $reversedLines['construction_labor_cash_payment_reversal']->type);
        $this->assertSame($project->id, (int) $paymentReversal->mapping->construction_project_id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $payment->delete();
    }
}

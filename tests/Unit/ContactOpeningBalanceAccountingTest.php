<?php

namespace Tests\Unit;

use App\BusinessLocation;
use App\Contact;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Accounting\Utils\AccountingUtil;
use Tests\TestCase;

class ContactOpeningBalanceAccountingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_opening_balance_debits_receivables_and_credits_opening_equity(): void
    {
        [$contact, $location] = $this->accountingCustomerContext();
        $receivables = AccountingAccount::where('business_id', $contact->business_id)
            ->where('status', 'active')
            ->where('account_primary_type', 'asset')
            ->firstOrFail();

        $map = json_decode($location->accounting_default_map ?: '[]', true);
        $map['sale']['deposit_to'] = $receivables->id;
        $location->accounting_default_map = json_encode($map);
        $location->save();

        $transaction = Transaction::create([
            'business_id' => $contact->business_id,
            'location_id' => $location->id,
            'type' => 'opening_balance',
            'status' => 'final',
            'payment_status' => 'due',
            'contact_id' => $contact->id,
            'transaction_date' => now(),
            'total_before_tax' => 5000,
            'final_total' => 5000,
            'created_by' => $contact->created_by ?: 1,
            'ref_no' => 'OB-TEST-1',
        ]);

        app(AccountingUtil::class)->syncContactOpeningBalance($transaction);

        $entries = AccountingAccountsTransaction::where('transaction_id', $transaction->id)
            ->where('sub_type', 'contact_opening_balance')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertTrue($entries->contains(function ($entry) use ($receivables) {
            return $entry->accounting_account_id === $receivables->id
                && $entry->type === 'debit'
                && (float) $entry->amount === 5000.0;
        }));
        $this->assertTrue($entries->contains(function ($entry) {
            return $entry->map_type === 'opening_balance_equity'
                && $entry->type === 'credit'
                && (float) $entry->amount === 5000.0;
        }));
    }

    public function test_supplier_opening_balance_credits_payables_and_debits_opening_equity(): void
    {
        [$contact, $location] = $this->accountingSupplierContext();
        $payables = AccountingAccount::where('business_id', $contact->business_id)
            ->where('status', 'active')
            ->where('account_primary_type', 'liability')
            ->firstOrFail();
        $cash = AccountingAccount::where('business_id', $contact->business_id)
            ->where('status', 'active')
            ->where('account_primary_type', 'asset')
            ->firstOrFail();

        $map = json_decode($location->accounting_default_map ?: '[]', true);
        // A legacy/misconfigured purchase map may point the credit side to
        // cash. The supplier-payment debit side still identifies payables.
        $map['purchases']['payment_account'] = $cash->id;
        $map['purchase_payment']['deposit_to'] = $payables->id;
        $location->accounting_default_map = json_encode($map);
        $location->save();

        $transaction = Transaction::create([
            'business_id' => $contact->business_id,
            'location_id' => $location->id,
            'type' => 'opening_balance',
            'status' => 'final',
            'payment_status' => 'due',
            'contact_id' => $contact->id,
            'transaction_date' => now(),
            'total_before_tax' => 500,
            'final_total' => 500,
            'created_by' => $contact->created_by ?: 1,
            'ref_no' => 'OB-SUPPLIER-TEST-1',
        ]);

        app(AccountingUtil::class)->syncContactOpeningBalance($transaction);

        $entries = AccountingAccountsTransaction::where('transaction_id', $transaction->id)
            ->where('sub_type', 'contact_opening_balance')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertTrue($entries->contains(function ($entry) use ($payables) {
            return $entry->accounting_account_id === $payables->id
                && $entry->type === 'credit'
                && (float) $entry->amount === 500.0;
        }));
        $this->assertTrue($entries->contains(function ($entry) {
            return $entry->map_type === 'opening_balance_equity'
                && $entry->type === 'debit'
                && (float) $entry->amount === 500.0;
        }));
    }

    public function test_contact_level_customer_collection_debits_cash_and_credits_receivables(): void
    {
        [$contact] = $this->accountingCustomerContext();
        $accounts = AccountingAccount::where('business_id', $contact->business_id)
            ->where('status', 'active')
            ->limit(2)
            ->get();
        $this->assertCount(2, $accounts);

        $payment = TransactionPayment::create([
            'business_id' => $contact->business_id,
            'amount' => 2000,
            'method' => 'cash',
            'paid_on' => now(),
            'created_by' => $contact->created_by ?: 1,
            'payment_for' => $contact->id,
            'payment_ref_no' => 'OBPAY-TEST-1',
            'is_advance' => 1,
            'payment_type' => 'credit',
        ]);

        app(AccountingUtil::class)->saveMap(
            'sell_payment',
            $payment->id,
            $payment->created_by,
            $contact->business_id,
            $accounts[0]->id,
            $accounts[1]->id
        );

        $entries = AccountingAccountsTransaction::where('transaction_payment_id', $payment->id)->get();

        $this->assertCount(2, $entries);
        $this->assertSame('debit', $entries->firstWhere('map_type', 'deposit_to')->type);
        $this->assertSame('credit', $entries->firstWhere('map_type', 'payment_account')->type);
    }

    private function accountingCustomerContext(): array
    {
        $businessIds = AccountingAccount::where('status', 'active')
            ->select('business_id')
            ->groupBy('business_id')
            ->havingRaw('COUNT(*) >= 2')
            ->pluck('business_id');

        foreach ($businessIds as $businessId) {
            $contact = Contact::where('business_id', $businessId)
                ->whereIn('type', ['customer', 'both'])
                ->first();
            $location = BusinessLocation::where('business_id', $businessId)->first();
            if (! empty($contact) && ! empty($location)) {
                return [$contact, $location];
            }
        }

        $this->fail('No business with accounting accounts, a location and a customer was found.');
    }

    private function accountingSupplierContext(): array
    {
        $businessIds = AccountingAccount::where('status', 'active')
            ->select('business_id')
            ->groupBy('business_id')
            ->havingRaw("SUM(account_primary_type = 'asset') >= 1")
            ->havingRaw("SUM(account_primary_type = 'liability') >= 1")
            ->pluck('business_id');

        foreach ($businessIds as $businessId) {
            $contact = Contact::where('business_id', $businessId)
                ->where('type', 'supplier')
                ->first();
            $location = BusinessLocation::where('business_id', $businessId)->first();
            if (! empty($contact) && ! empty($location)) {
                return [$contact, $location];
            }
        }

        $this->fail('No business with accounting accounts, a location and a supplier was found.');
    }
}

<?php

namespace Tests\Unit;

use App\Events\SellCreatedOrModified;
use App\Transaction;
use Modules\Accounting\Utils\AccountingUtil;
use PHPUnit\Framework\TestCase;

class AccountingUtilTest extends TestCase
{
    public function test_expense_debits_are_included_as_positive_balances(): void
    {
        $formula = (new AccountingUtil())->balanceFormula();

        $this->assertStringContainsString("account_primary_type='expenses'", $formula);
        $this->assertStringNotContainsString("account_primary_type='expense'", $formula);
    }

    public function test_normal_payment_debits_cash_and_credits_the_other_account(): void
    {
        $types = (new AccountingUtil())->getPaymentEntryTypes(false);

        $this->assertSame('credit', $types['payment_account']);
        $this->assertSame('debit', $types['deposit_to']);
    }

    public function test_return_payment_reverses_the_accounting_entry(): void
    {
        $types = (new AccountingUtil())->getPaymentEntryTypes(true);

        $this->assertSame('debit', $types['payment_account']);
        $this->assertSame('credit', $types['deposit_to']);
    }

    public function test_receipt_voucher_debits_cash_and_credits_offset_account(): void
    {
        $types = (new AccountingUtil())->getVoucherEntryTypes('receipt_voucher');

        $this->assertSame('debit', $types['cash_account']);
        $this->assertSame('credit', $types['offset_account']);
    }

    public function test_payment_voucher_credits_cash_and_debits_offset_account(): void
    {
        $types = (new AccountingUtil())->getVoucherEntryTypes('payment_voucher');

        $this->assertSame('credit', $types['cash_account']);
        $this->assertSame('debit', $types['offset_account']);
    }

    public function test_asset_normal_balance_is_debit_less_credit(): void
    {
        $balance = (new AccountingUtil())->calculateNormalBalance('asset', 4554, 11640);

        $this->assertSame(-7086.0, $balance);
    }

    public function test_liability_normal_balance_is_credit_less_debit(): void
    {
        $balance = (new AccountingUtil())->calculateNormalBalance('liability', 200, 1200);

        $this->assertSame(1000.0, $balance);
    }

    public function test_sale_event_carries_deletion_state(): void
    {
        $transaction = new Transaction();
        $event = new SellCreatedOrModified($transaction, true);

        $this->assertTrue($event->isDeleted);
        $this->assertSame($transaction, $event->transaction);
    }

    public function test_arabic_account_descriptions_do_not_fall_back_to_english(): void
    {
        $translations = require dirname(__DIR__, 2).'/Modules/Accounting/Resources/lang/ar/lang.php';

        foreach ($translations as $key => $value) {
            if (str_ends_with($key, '_desc')) {
                $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/', strip_tags((string) $value), $key);
            }
        }
    }
}

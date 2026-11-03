<?php

namespace Tests\Unit;

use Modules\Accounting\Http\Controllers\CoaController;
use ReflectionClass;
use Tests\TestCase;

class AccountingLedgerDescriptionTest extends TestCase
{
    public function test_sale_payment_description_contains_invoice_and_payment_reference(): void
    {
        app()->setLocale('en');

        $controller = (new ReflectionClass(CoaController::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(CoaController::class))->getMethod('formatLedgerDescription');
        $method->setAccessible(true);

        $description = $method->invoke($controller, (object) [
            'sub_type' => 'sell_payment',
            'invoice_no' => '0011',
            'payment_ref_no' => 'SP2026/0001',
            'a_ref' => null,
            'aat_note' => null,
        ]);

        $this->assertStringContainsString('Sales Payments', $description);
        $this->assertStringContainsString('0011', $description);
        $this->assertStringContainsString('SP2026/0001', $description);
    }

    public function test_opening_balance_collection_has_a_clear_description(): void
    {
        app()->setLocale('en');

        $controller = (new ReflectionClass(CoaController::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(CoaController::class))->getMethod('formatLedgerDescription');
        $method->setAccessible(true);

        $description = $method->invoke($controller, (object) [
            'sub_type' => 'sell_payment',
            'source_transaction_type' => 'opening_balance',
            'invoice_no' => null,
            'ref_no' => 'OB2026/0001',
            'payment_ref_no' => 'SP2026/0002',
            'a_ref' => null,
            'aat_note' => null,
        ]);

        $this->assertStringContainsString('Customer opening balance collection', $description);
        $this->assertStringContainsString('OB2026/0001', $description);
        $this->assertStringContainsString('SP2026/0002', $description);
    }
}

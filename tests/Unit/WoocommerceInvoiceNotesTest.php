<?php

namespace Tests\Unit;

use Modules\Woocommerce\Utils\WoocommerceUtil;
use Tests\TestCase;

class WoocommerceInvoiceNotesTest extends TestCase
{
    public function test_customer_note_and_vodafone_cash_marker_are_kept_in_separate_fields(): void
    {
        app()->setLocale('ar');
        $util = app(WoocommerceUtil::class);
        $order = (object) [
            'customer_note' => 'التواصل قبل التسليم',
            'payment_method' => 'vodafone_cash',
            'payment_method_title' => 'فودافون كاش',
        ];

        $customer_note = $util->getWooOrderInvoiceNotes($order);
        $staff_note = $util->getWooOrderStaffNote($order);

        $this->assertSame('📝 توجد ملاحظة: التواصل قبل التسليم', $customer_note);
        $this->assertSame('✅ مدفوع', $staff_note);
    }

    public function test_other_payment_methods_do_not_get_vodafone_cash_marker(): void
    {
        app()->setLocale('ar');
        $util = app(WoocommerceUtil::class);
        $order = (object) [
            'customer_note' => '<b>اترك الطلب عند الحارس</b>',
            'payment_method' => 'cod',
            'payment_method_title' => 'الدفع عند الاستلام',
        ];

        $customer_note = $util->getWooOrderInvoiceNotes($order);
        $staff_note = $util->getWooOrderStaffNote($order);

        $this->assertSame('📝 توجد ملاحظة: اترك الطلب عند الحارس', $customer_note);
        $this->assertNull($staff_note);
    }
}

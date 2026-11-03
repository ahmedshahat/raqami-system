<?php

namespace Tests\Unit;

use Modules\Construction\Support\CurrencyFormatter;
use Tests\TestCase;

class ConstructionCurrencyFormatterTest extends TestCase
{
    public function test_symbol_position_and_business_number_settings_are_shared(): void
    {
        session()->put('currency', [
            'code' => 'SAR', 'symbol' => 'ر.س',
            'decimal_separator' => ',', 'thousand_separator' => '.',
        ]);
        session()->put('business.currency_precision', 2);
        session()->put('business.currency_symbol_placement', 'before');
        session()->put('business', (object) ['currency_symbol_placement' => 'before', 'currency_precision' => 2]);

        $this->assertSame('ر.س 2.357.000,50', CurrencyFormatter::format(2357000.5));
        $this->assertSame('before', CurrencyFormatter::parts(2357000.5)['position']);
        $beforeHtml = view('construction::partials.money', ['value' => 2357000.5])->render();
        $this->assertStringContainsString('print-money--before', $beforeHtml);
        $this->assertStringNotContainsString('dir="ltr"', $beforeHtml);

        session()->put('business.currency_symbol_placement', 'after');
        session()->put('business.currency_precision', 0);
        session()->put('business', (object) ['currency_symbol_placement' => 'after', 'currency_precision' => 0]);

        $this->assertSame('2.357.001 ر.س', CurrencyFormatter::format(2357000.5));
        $this->assertSame('after', CurrencyFormatter::parts(2357000.5)['position']);
        $this->assertStringContainsString('print-money--after', view('construction::partials.money', ['value' => 2357000.5])->render());

        $css = file_get_contents(public_path('modules/construction/css/refinement.css'));
        $this->assertStringContainsString('direction: inherit', $css);
        $this->assertStringContainsString('[dir=rtl] .ct-contract-value--before', $css);
    }
}

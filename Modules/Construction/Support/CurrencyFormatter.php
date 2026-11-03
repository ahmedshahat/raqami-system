<?php

namespace Modules\Construction\Support;

final class CurrencyFormatter
{
    public static function parts($amount): array
    {
        $currency = session('currency', []);
        $business = session('business');
        $precisionSetting = data_get($business, 'currency_precision', session('business.currency_precision', 2));
        $position = data_get($business, 'currency_symbol_placement', session('business.currency_symbol_placement', 'after'));
        $precision = max(0, min(6, (int) $precisionSetting));

        return [
            'amount' => number_format(
                (float) $amount,
                $precision,
                (string) ($currency['decimal_separator'] ?? '.'),
                (string) ($currency['thousand_separator'] ?? ',')
            ),
            'symbol' => (string) ($currency['symbol'] ?? ''),
            'code' => strtoupper((string) ($currency['code'] ?? '')),
            'position' => $position === 'before' ? 'before' : 'after',
        ];
    }

    public static function format($amount): string
    {
        $parts = self::parts($amount);
        if ($parts['symbol'] === '') {
            return $parts['amount'];
        }

        return $parts['position'] === 'before'
            ? $parts['symbol'].' '.$parts['amount']
            : $parts['amount'].' '.$parts['symbol'];
    }
}

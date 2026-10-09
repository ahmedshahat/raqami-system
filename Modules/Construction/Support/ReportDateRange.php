<?php

namespace Modules\Construction\Support;

use Carbon\Carbon;

class ReportDateRange
{
    public const PRESETS = [
        'today',
        'yesterday',
        'last_7_days',
        'last_30_days',
        'last_3_months',
        'last_6_months',
        'last_year',
        'last_3_years',
        'custom',
    ];

    public static function resolve(?string $preset, ?string $fromDate, ?string $toDate): array
    {
        $preset = $preset ?: ($fromDate ? 'custom' : 'last_6_months');
        $end = Carbon::parse($toDate ?: now()->toDateString())->startOfDay();

        if ($preset === 'custom') {
            return [
                'dateRange' => $preset,
                'fromDate' => $fromDate ?: $end->copy()->subMonthsNoOverflow(6)->toDateString(),
                'toDate' => $end->toDateString(),
            ];
        }

        $start = match ($preset) {
            'today' => $end->copy(),
            'yesterday' => $end->copy()->subDay(),
            'last_7_days' => $end->copy()->subDays(6),
            'last_30_days' => $end->copy()->subDays(29),
            'last_3_months' => $end->copy()->subMonthsNoOverflow(3),
            'last_year' => $end->copy()->subYearNoOverflow(),
            'last_3_years' => $end->copy()->subYears(3),
            default => $end->copy()->subMonthsNoOverflow(6),
        };

        if ($preset === 'yesterday') {
            $end = $start->copy();
        }

        return [
            'dateRange' => $preset,
            'fromDate' => $start->toDateString(),
            'toDate' => $end->toDateString(),
        ];
    }
}

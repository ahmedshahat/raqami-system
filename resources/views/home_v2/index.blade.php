@extends('layouts.app')
@section('title', __('home.home'))

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? __('home_v2.good_morning') : __('home_v2.good_evening');

    // تغيّر القيمة عن الفترة السابقة؛ المصروفات (inverse) نزولها هو الكويس
    $delta = function ($value, $prev, $inverse = false) {
        if (empty($prev) || $prev == 0) {
            return null;
        }
        $pct = ($value - $prev) / abs($prev) * 100;

        return ['pct' => $pct, 'good' => $inverse ? $pct < 0 : $pct >= 0];
    };

    // خط اتجاه صغير في مساحة 100×40
    $spark = function (array $values) {
        if (count($values) == 1) {
            $values[] = $values[0];
        }
        $max = max($values);
        $min = min(0, min($values));
        $range = $max - $min ?: 1;
        $n = count($values) - 1;
        $points = [];
        foreach ($values as $i => $v) {
            $points[] = round($i / $n * 100, 2) . ' ' . round(38 - ($v - $min) / $range * 34, 2);
        }
        $line = 'M' . implode(' L', $points);

        return ['line' => $line, 'area' => $line . ' L100 40 L0 40 Z'];
    };

    // أقرب رقم "مستدير" فوق أعلى قيمة + تسميات مختصرة للمحور
    $niceMax = function ($max) {
        if ($max <= 0) {
            return 0;
        }
        $magnitude = 10 ** floor(log10($max));
        foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $step) {
            if ($step * $magnitude >= $max) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    };
    $compact = function ($value) {
        if ($value >= 1000000) return rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.') . ' ' . __('home_v2.million_short');
        if ($value >= 1000) return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . ' ' . __('home_v2.thousand_short');
        return (string) round($value);
    };

    $presets = ['today', '7_days', 'this_month', 'last_month', 'this_fy'];
@endphp

@section('css')
    <link rel="stylesheet" href="{{ asset('css/raqmi.css?v=' . $asset_v) }}">
@endsection

@section('content')
<main class="content rr-page" aria-labelledby="rr-home-title">

    {{-- ═══ بانر الترحيب + إجراءات سريعة ═══ --}}
    <section class="rr-hero">
        <div class="rr-hero__titles">
            <p class="rr-hero__eyebrow">@include('partials.rr.icon', ['name' => 'calendar', 'size' => 16]){{ now()->translatedFormat('l j F Y') }}</p>
            <h1 id="rr-home-title" class="rr-hero__title">{{ $greeting }}، {{ session('user.first_name') }}</h1>
            <p class="rr-hero__sub">{{ $can_view_data ? __('home_v2.hero_sub') : __('home_v2.hero_sub_basic') }}</p>
        </div>
        <div class="rr-hero__actions">
            @can('sell.create')
                <a class="rr-btn rr-btn--hero" href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}">@include('partials.rr.icon', ['name' => 'monitor'])@lang('home_v2.action_pos')</a>
            @endcan
            @can('direct_sell.access')
                <a class="rr-btn rr-btn--on-hero" href="{{ action([\App\Http\Controllers\SellController::class, 'create']) }}">@include('partials.rr.icon', ['name' => 'plus'])@lang('home_v2.action_invoice')</a>
            @endcan
            @can('purchase.create')
                <a class="rr-btn rr-btn--on-hero" href="{{ action([\App\Http\Controllers\PurchaseController::class, 'create']) }}">@include('partials.rr.icon', ['name' => 'cart'])@lang('home_v2.action_purchase')</a>
            @endcan
            @can('expense.add')
                <a class="rr-btn rr-btn--on-hero" href="{{ action([\App\Http\Controllers\ExpenseController::class, 'create']) }}">@include('partials.rr.icon', ['name' => 'receipt'])@lang('home_v2.action_expense')</a>
            @endcan
        </div>
    </section>

    @if($can_view_data)
        {{-- ═══ الفترة والفرع ═══ --}}
        <form class="rr-period" method="GET" action="{{ route('home.v2') }}" id="rr-period-form">
            <input type="hidden" name="period" value="{{ $period['preset'] }}">
            <nav class="rr-presets" aria-label="@lang('home_v2.period')">
                @foreach($presets as $preset)
                    <button type="submit" name="period" value="{{ $preset }}" class="rr-preset" @if($period['preset'] == $preset) aria-current="true" @endif>@lang('home_v2.period_' . $preset)</button>
                @endforeach
            </nav>
            <div class="rr-period__custom">
                <label class="rr-sr" for="rr-start">@lang('home_v2.from')</label>
                <div class="rr-control"><input class="rr-control__input" type="date" id="rr-start" name="start" value="{{ $period['start'] }}"></div>
                <label class="rr-sr" for="rr-end">@lang('home_v2.to')</label>
                <div class="rr-control"><input class="rr-control__input" type="date" id="rr-end" name="end" value="{{ $period['end'] }}"></div>
                <button type="submit" name="period" value="custom" class="rr-btn rr-btn--secondary rr-btn--sm" @if($period['preset'] == 'custom') aria-current="true" @endif>@lang('home_v2.apply')</button>
                @if(count($all_locations) > 1)
                    <label class="rr-sr" for="rr-location">@lang('home_v2.location')</label>
                    <div class="rr-control rr-control--location">
                        <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'map-pin', 'size' => 16])</span>
                        <select class="rr-control__input rr-control__select" id="rr-location" name="location_id" data-rr-autosubmit>
                            <option value="">@lang('home_v2.all_locations')</option>
                            @foreach($all_locations as $id => $name)
                                <option value="{{ $id }}" @if($location_id == $id) selected @endif>{{ $name }}</option>
                            @endforeach
                        </select>
                        <span class="rr-control__chev">@include('partials.rr.icon', ['name' => 'chevron-down', 'size' => 16])</span>
                    </div>
                @endif
            </div>
            <p class="rr-period__range">
                @lang('home_v2.range', ['start' => \Carbon::parse($period['start'])->translatedFormat('j M Y'), 'end' => \Carbon::parse($period['end'])->translatedFormat('j M Y')])
                · @lang('home_v2.compared_to', ['start' => \Carbon::parse($period['prev_start'])->translatedFormat('j M Y'), 'end' => \Carbon::parse($period['prev_end'])->translatedFormat('j M Y')])
            </p>
        </form>

        {{-- ═══ المؤشرات الرئيسية ═══ --}}
        @if(! empty($kpis))
            <section class="rr-kpis" aria-label="@lang('home_v2.kpis')">
                @foreach($kpis as $key => $kpi)
                    @php $d = $delta($kpi['value'], $kpi['prev'], ! empty($kpi['inverse'])); $s = $spark($kpi['series']); @endphp
                    <article class="rr-kpi rr-kpi--{{ $kpi['color'] }}">
                        <div class="rr-kpi__top">
                            <span class="rr-kpi__icon" aria-hidden="true">@include('partials.rr.icon', ['name' => $kpi['icon']])</span>
                            <span class="rr-kpi__label">@lang('home_v2.kpi_' . $key)</span>
                            @if($d)
                                <span class="rr-delta rr-delta--{{ $d['good'] ? 'good' : 'bad' }}" title="@lang('home_v2.vs_previous')">
                                    @include('partials.rr.icon', ['name' => $d['pct'] >= 0 ? 'arrow-up' : 'arrow-down', 'size' => 14])
                                    <span class="rr-num">{{ number_format(abs($d['pct']), abs($d['pct']) < 10 ? 1 : 0) }}%</span>
                                </span>
                            @else
                                <span class="rr-delta" title="@lang('home_v2.no_previous')">—</span>
                            @endif
                        </div>
                        <div class="rr-kpi__value rr-money">@include('partials.rr.money', ['value' => $kpi['value']])</div>
                        <div class="rr-kpi__meta">{{ $kpi['meta'] }}</div>
                        <svg class="rr-kpi__spark" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                            <path class="rr-kpi__area" d="{{ $s['area'] }}"></path>
                            <path class="rr-kpi__line" d="{{ $s['line'] }}"></path>
                        </svg>
                    </article>
                @endforeach
            </section>

            <section class="rr-stat-grid rr-stat-grid--4" aria-label="@lang('home_v2.secondary')">
                @foreach($secondary as $key => $stat)
                    <div class="rr-stat rr-stat--card">
                        <span class="rr-chip rr-chip--{{ $stat['tone'] }}" aria-hidden="true">@include('partials.rr.icon', ['name' => $stat['icon']])</span>
                        <div class="rr-stat__body">
                            <div class="rr-stat__label">@lang('home_v2.stat_' . $key)</div>
                            <div class="rr-stat__value rr-money">@include('partials.rr.money', ['value' => $stat['value']])</div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- ═══ الرسم الرئيسي + محتاج انتباهك ═══ --}}
        <div class="rr-cols">
            <div class="rr-cols__main">
                @if($can_view_sales)
                    @php
                        $labels = array_values($buckets);
                        $count = count($labels);
                        $series = [
                            'sales' => ['seg' => 1, 'values' => $chart['sales']],
                            'purchases' => ['seg' => 4, 'values' => $chart['purchases']],
                            'expenses' => ['seg' => 2, 'values' => $chart['expenses']],
                        ];
                        $series = array_filter($series, fn ($item) => ! empty($item['values']));
                        $profit = $chart['profit'];
                        $allValues = array_merge([0], $profit, ...array_values(array_map(fn ($item) => $item['values'], $series)));
                        $axisMax = $niceMax(max($allValues));
                        $step = max(1, (int) ceil($count / 12));
                        $pct = fn ($v) => $axisMax > 0 ? round(max(0, $v) / $axisMax * 100, 2) : 0;
                        $linePoints = [];
                        foreach ($profit as $i => $v) {
                            $linePoints[] = round(($i + 0.5) / $count * 100, 3) . ',' . round(100 - $pct($v), 3);
                        }
                    @endphp
                    <section class="rr-card" aria-labelledby="rr-chart-title">
                        <header class="rr-card__head">
                            <span class="rr-chip rr-chip--info" aria-hidden="true">@include('partials.rr.icon', ['name' => 'bars'])</span>
                            <div class="rr-card__titles">
                                <h2 id="rr-chart-title" class="rr-card__title">@lang('home_v2.chart_title')</h2>
                                <p class="rr-card__desc">@lang('home_v2.chart_desc_' . $period['bucket'])</p>
                            </div>
                            <ul class="rr-legend rr-legend--inline">
                                @foreach($series as $key => $item)
                                    <li class="rr-legend__item"><span class="rr-legend__swatch rr-chart__seg--{{ $item['seg'] }}"></span><span class="rr-legend__label">@lang('home_v2.series_' . $key)</span></li>
                                @endforeach
                                @if(! empty($profit))
                                    <li class="rr-legend__item"><span class="rr-legend__swatch rr-legend__swatch--line rr-chart__seg--3"></span><span class="rr-legend__label">@lang('home_v2.series_profit')</span></li>
                                @endif
                            </ul>
                        </header>
                        <figure class="rr-chart rr-chart--grouped">
                            <div class="rr-chart__plot">
                                <div class="rr-chart__grid" aria-hidden="true">
                                    @foreach([0, 25, 50, 75, 100] as $tick)
                                        <div class="rr-chart__tick{{ $tick == 0 ? ' rr-chart__tick--base' : '' }}" style="--rr-tick: {{ $tick }}%"><span>{{ $compact($axisMax * $tick / 100) }}</span></div>
                                    @endforeach
                                </div>
                                <div class="rr-chart__cols">
                                    @foreach($labels as $i => $label)
                                        <div class="rr-chart__col" tabindex="0" data-rr-tip>
                                            <div class="rr-chart__group">
                                                @foreach($series as $item)
                                                    <span class="rr-chart__bar rr-chart__seg--{{ $item['seg'] }}" style="height: {{ $pct($item['values'][$i]) }}%"></span>
                                                @endforeach
                                            </div>
                                            @if(! empty($profit))
                                                <span class="rr-chart__dot" style="bottom: {{ $pct($profit[$i]) }}%"></span>
                                            @endif
                                            @if($i % $step == 0)<span class="rr-chart__x">{{ $label['short'] }}</span>@endif
                                            <div class="rr-chart__tip-src" hidden>
                                                <span class="rr-chart-tip__title">{{ $label['full'] }}</span>
                                                @foreach($series as $key => $item)
                                                    <div class="rr-chart-tip__row"><span class="rr-legend__swatch rr-chart__seg--{{ $item['seg'] }}"></span><span>@lang('home_v2.series_' . $key)</span><span class="rr-money">@include('partials.rr.money', ['value' => $item['values'][$i]])</span></div>
                                                @endforeach
                                                @if(! empty($profit))
                                                    <div class="rr-chart-tip__row rr-chart-tip__row--total"><span class="rr-legend__swatch rr-chart__seg--3"></span><span>@lang('home_v2.series_profit')</span><span class="rr-money">@include('partials.rr.money', ['value' => $profit[$i]])</span></div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                    @if(! empty($profit))
                                        <svg class="rr-chart__line" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false"><polyline points="{{ implode(' ', $linePoints) }}"></polyline></svg>
                                    @endif
                                </div>
                                @if($axisMax == 0)
                                    <div class="rr-chart__empty">@lang('home_v2.no_data')</div>
                                @endif
                            </div>
                        </figure>
                    </section>
                @endif

                <div class="rr-grid-2">
                    @if($can_view_sales)
                        <section class="rr-card" aria-labelledby="rr-top-title">
                            <header class="rr-card__head">
                                <span class="rr-chip rr-chip--success" aria-hidden="true">@include('partials.rr.icon', ['name' => 'box'])</span>
                                <div class="rr-card__titles">
                                    <h2 id="rr-top-title" class="rr-card__title">@lang('home_v2.top_products')</h2>
                                    <p class="rr-card__desc">@lang('home_v2.top_products_desc')</p>
                                </div>
                            </header>
                            @if($top_products->isEmpty())
                                <div class="rr-empty"><p class="rr-empty__text">@lang('home_v2.no_data')</p></div>
                            @else
                                @php $topMax = (float) $top_products->max('total') ?: 1; @endphp
                                <ol class="rr-rank">
                                    @foreach($top_products as $index => $product)
                                        <li class="rr-rank__row">
                                            <span class="rr-rank__n">{{ $index + 1 }}</span>
                                            <span class="rr-rank__name" title="{{ $product->name }}">{{ $product->name }}</span>
                                            <span class="rr-rank__value rr-money">@include('partials.rr.money', ['value' => $product->total])</span>
                                            <div class="rr-rank__bar"><span style="width: {{ round($product->total / $topMax * 100, 1) }}%"></span></div>
                                            <span class="rr-rank__sub">@lang('home_v2.qty_sold', ['qty' => (float) $product->qty + 0])</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </section>
                    @endif

                    @if(! is_null($forecast))
                        <section class="rr-card" aria-labelledby="rr-forecast-title">
                            <header class="rr-card__head">
                                <span class="rr-chip rr-chip--danger" aria-hidden="true">@include('partials.rr.icon', ['name' => 'box'])</span>
                                <div class="rr-card__titles">
                                    <h2 id="rr-forecast-title" class="rr-card__title">@lang('home_v2.forecast')</h2>
                                    <p class="rr-card__desc">@lang('home_v2.forecast_desc')</p>
                                </div>
                            </header>
                            @if($forecast->isEmpty())
                                <div class="rr-empty">
                                    <span class="rr-empty__icon">@include('partials.rr.icon', ['name' => 'check'])</span>
                                    <p class="rr-empty__text">@lang('home_v2.forecast_empty')</p>
                                </div>
                            @else
                                <ul class="rr-list">
                                    @foreach($forecast as $item)
                                        <li class="rr-list__row">
                                            <div class="rr-list__main">
                                                <span class="rr-list__title">{{ $item->label }}</span>
                                                <span class="rr-list__meta">
                                                    @lang('home_v2.forecast_meta', ['stock' => (float) $item->stock + 0, 'unit' => $item->unit, 'daily' => $item->daily + 0])
                                                </span>
                                            </div>
                                            <div class="rr-list__end">
                                                @if($item->stock <= 0)
                                                    <span class="rr-badge rr-badge--danger"><span class="rr-badge__dot"></span>@lang('home_v2.forecast_out')</span>
                                                @else
                                                    <span class="rr-badge rr-badge--{{ $item->days_left <= 7 ? 'danger' : 'warning' }}">@lang('home_v2.forecast_days', ['days' => $item->days_left])</span>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endif
                </div>
            </div>

            {{-- محتاج انتباهك --}}
            <aside class="rr-cols__side">
                <section class="rr-card" aria-labelledby="rr-attention-title">
                    <header class="rr-card__head">
                        <span class="rr-chip rr-chip--danger" aria-hidden="true">@include('partials.rr.icon', ['name' => 'info'])</span>
                        <div class="rr-card__titles">
                            <h2 id="rr-attention-title" class="rr-card__title">@lang('home_v2.attention')</h2>
                            <p class="rr-card__desc">@lang('home_v2.attention_desc')</p>
                        </div>
                    </header>
                    @if(empty($attention))
                        <div class="rr-empty"><p class="rr-empty__text">@lang('home_v2.no_data')</p></div>
                    @else
                        <ul class="rr-list">
                            @foreach($attention as $item)
                                <li>
                                    <a class="rr-list__row{{ $item['count'] == 0 ? ' rr-list__row--clear' : '' }}" href="{{ $item['url'] }}">
                                        <span class="rr-chip rr-chip--{{ $item['count'] == 0 ? 'success' : $item['tone'] }}" aria-hidden="true">@include('partials.rr.icon', ['name' => $item['count'] == 0 ? 'check' : $item['icon']])</span>
                                        <div class="rr-list__main">
                                            <span class="rr-list__title">@lang('home_v2.attention_' . $item['key'])</span>
                                            <span class="rr-list__meta">
                                                @if($item['count'] == 0)
                                                    @lang('home_v2.all_clear')
                                                @elseif(! is_null($item['amount']))
                                                    <span class="rr-money">@include('partials.rr.money', ['value' => $item['amount']])</span>
                                                @else
                                                    @lang('home_v2.attention_' . $item['key'] . '_hint')
                                                @endif
                                            </span>
                                        </div>
                                        <span class="rr-list__count rr-num">{{ $item['count'] }}</span>
                                        <span class="rr-list__go">@include('partials.rr.icon', ['name' => 'chevron-forward', 'size' => 18])</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="rr-card__foot-note">
                        <a href="{{ route('home') }}">@lang('home_v2.old_home')</a>
                    </p>
                </section>

                @if($aging)
                    <section class="rr-card" aria-labelledby="rr-aging-title">
                        <header class="rr-card__head">
                            <span class="rr-chip rr-chip--warning" aria-hidden="true">@include('partials.rr.icon', ['name' => 'clock'])</span>
                            <div class="rr-card__titles">
                                <h2 id="rr-aging-title" class="rr-card__title">@lang('home_v2.aging')</h2>
                                <p class="rr-card__desc">@lang('home_v2.aging_desc')</p>
                            </div>
                            <span class="rr-money rr-money--sm">@include('partials.rr.money', ['value' => $aging['total']])</span>
                        </header>
                        <div class="rr-card__body">
                            @if($aging['total'] <= 0)
                                <div class="rr-empty"><p class="rr-empty__text">@lang('home_v2.aging_empty')</p></div>
                            @else
                                <div class="rr-split rr-split--lg" aria-hidden="true">
                                    @foreach($aging['totals'] as $bucket => $amount)
                                        @if($amount > 0)<span class="rr-split__seg rr-age--{{ $loop->iteration }}" style="width: {{ round($amount / $aging['total'] * 100, 2) }}%"></span>@endif
                                    @endforeach
                                </div>
                                <ul class="rr-legend" style="margin-top: var(--rr-space-4)">
                                    @foreach($aging['totals'] as $bucket => $amount)
                                        <li class="rr-legend__item">
                                            <span class="rr-legend__swatch rr-age--{{ $loop->iteration }}"></span>
                                            <span class="rr-legend__label">@lang('home_v2.aging_' . $bucket)</span>
                                            <span class="rr-legend__count">@lang('home_v2.invoices_count', ['count' => $aging['counts'][$bucket]])</span>
                                            <span class="rr-legend__value rr-money">@include('partials.rr.money', ['value' => $amount])</span>
                                            <span class="rr-legend__pct">{{ round($amount / $aging['total'] * 100) }}%</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </section>
                @endif

                @if($is_admin)
                    <section class="rr-card" aria-labelledby="rr-methods-title">
                        <header class="rr-card__head">
                            <span class="rr-chip" aria-hidden="true">@include('partials.rr.icon', ['name' => 'wallet'])</span>
                            <div class="rr-card__titles">
                                <h2 id="rr-methods-title" class="rr-card__title">@lang('home_v2.payment_methods')</h2>
                                <p class="rr-card__desc">@lang('home_v2.payment_methods_desc')</p>
                            </div>
                        </header>
                        <div class="rr-card__body">
                            @if(empty($payment_methods))
                                <div class="rr-empty"><p class="rr-empty__text">@lang('home_v2.no_data')</p></div>
                            @else
                                @php $methodsTotal = array_sum($payment_methods); $seg = 0; @endphp
                                <div class="rr-split rr-split--lg" aria-hidden="true">
                                    @foreach($payment_methods as $method => $amount)
                                        <span class="rr-split__seg rr-chart__seg--{{ $loop->index % 5 + 1 }}" style="width: {{ round($amount / $methodsTotal * 100, 2) }}%"></span>
                                    @endforeach
                                </div>
                                <ul class="rr-legend" style="margin-top: var(--rr-space-4)">
                                    @foreach($payment_methods as $method => $amount)
                                        <li class="rr-legend__item">
                                            <span class="rr-legend__swatch rr-chart__seg--{{ $loop->index % 5 + 1 }}"></span>
                                            <span class="rr-legend__label">{{ $payment_types[$method] ?? $method }}</span>
                                            <span class="rr-legend__value rr-money">@include('partials.rr.money', ['value' => $amount])</span>
                                            <span class="rr-legend__pct">{{ round($amount / $methodsTotal * 100) }}%</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </section>
                @endif
            </aside>
        </div>

        {{-- ═══ خريطة حرارة المبيعات ═══ --}}
        @if($heatmap)
            @php
                $heatCells = $heatmap['cells'];
                $heatHours = collect($heatCells)->flatMap(fn ($row) => array_keys($row));
                $heatMax = (float) collect($heatCells)->flatMap(fn ($row) => array_column($row, 'total'))->max();
                $heatDayNames = [];
                foreach ($heatmap['days'] as $dow) {
                    // DAYOFWEEK: 1 = الأحد … 7 = السبت
                    $heatDayNames[$dow] = \Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays($dow - 1)->translatedFormat('l');
                }
                $peakDay = collect($heatCells)->map(fn ($row) => array_sum(array_column($row, 'total')))->sortDesc()->keys()->first();
                $peakHour = collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => collect($heatCells)->sum(fn ($row) => $row[$h]['total'] ?? 0)])->sortDesc()->keys()->first();
            @endphp
            <section class="rr-card" aria-labelledby="rr-heat-title">
                <header class="rr-card__head">
                    <span class="rr-chip rr-chip--info" aria-hidden="true">@include('partials.rr.icon', ['name' => 'calendar'])</span>
                    <div class="rr-card__titles">
                        <h2 id="rr-heat-title" class="rr-card__title">@lang('home_v2.heatmap')</h2>
                        <p class="rr-card__desc">@lang('home_v2.heatmap_desc', ['start' => \Carbon::parse($heatmap['from'])->translatedFormat('j M'), 'end' => \Carbon::parse($heatmap['to'])->translatedFormat('j M Y')])</p>
                    </div>
                    @if($heatMax > 0)
                        <span class="rr-heat-scale" aria-hidden="true">@lang('home_v2.less')<span class="rr-heat-scale__bar"></span>@lang('home_v2.more')</span>
                    @endif
                </header>
                @if($heatMax <= 0)
                    <div class="rr-empty"><p class="rr-empty__text">@lang('home_v2.no_data')</p></div>
                @else
                    @php $hours = range($heatHours->min(), $heatHours->max()); @endphp
                    <div class="rr-heat-scroll">
                        <div class="rr-heat" style="--rr-hours: {{ count($hours) }}">
                            <span></span>
                            @foreach($hours as $h)<span class="rr-heat__hour">{{ $h }}</span>@endforeach
                            @foreach($heatmap['days'] as $dow)
                                <span class="rr-heat__day">{{ $heatDayNames[$dow] }}</span>
                                @foreach($hours as $h)
                                    @php $cell = $heatCells[$dow][$h] ?? ['total' => 0, 'count' => 0]; @endphp
                                    <div class="rr-heat__cell" style="--rr-heat: {{ $cell['total'] > 0 ? max(8, round($cell['total'] / $heatMax * 100)) : 0 }}" tabindex="0" data-rr-tip>
                                        <div class="rr-chart__tip-src" hidden>
                                            <span class="rr-chart-tip__title">{{ $heatDayNames[$dow] }} · {{ sprintf('%02d:00', $h) }}</span>
                                            <div class="rr-chart-tip__row"><span class="rr-legend__swatch rr-chart__seg--1"></span><span>@lang('home_v2.series_sales')</span><span class="rr-money">@include('partials.rr.money', ['value' => $cell['total']])</span></div>
                                            <div class="rr-chart-tip__row"><span></span><span>@lang('home_v2.invoices')</span><span class="rr-num">{{ $cell['count'] }}</span></div>
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                    <p class="rr-card__note">
                        @include('partials.rr.icon', ['name' => 'info', 'size' => 16])
                        @lang('home_v2.heatmap_peak', ['day' => $heatDayNames[$peakDay], 'hour' => sprintf('%02d:00', $peakHour)])
                    </p>
                @endif
            </section>
        @endif

        {{-- ═══ ويدجتات الموديولات ═══ --}}
        @if(! empty($module_widgets))
            <section class="rr-section" aria-labelledby="rr-modules-title">
                <h2 id="rr-modules-title" class="rr-section-title">@lang('home_v2.modules')</h2>
                <div class="rr-grid-2">
                    @foreach($module_widgets as $widget)
                        <article class="rr-card">
                            <header class="rr-card__head">
                                <span class="rr-chip rr-chip--{{ $widget['tone'] }}" aria-hidden="true">@include('partials.rr.icon', ['name' => $widget['icon']])</span>
                                <div class="rr-card__titles"><h3 class="rr-card__title">{{ $widget['title'] }}</h3></div>
                                @if($widget['url'])
                                    <a class="rr-btn rr-btn--ghost rr-btn--sm" href="{{ $widget['url'] }}">@lang('home_v2.open')@include('partials.rr.icon', ['name' => 'chevron-forward', 'size' => 16])</a>
                                @endif
                            </header>
                            <div class="rr-minis">
                                @foreach($widget['items'] as $item)
                                    <div class="rr-mini">
                                        <span class="rr-mini__label">{{ $item['label'] }}</span>
                                        <span class="rr-mini__value{{ ! empty($item['tone']) ? ' rr-mini__value--' . $item['tone'] : '' }}">
                                            @if(! empty($item['money']))
                                                <span class="rr-money">@include('partials.rr.money', ['value' => $item['value']])</span>
                                            @else
                                                <span class="rr-num">{{ number_format((float) $item['value']) }}</span>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</main>
@endsection

@section('javascript')
    @include('partials.rr.chart_script')
    <script>
        document.querySelectorAll('[data-rr-autosubmit]').forEach(function (el) {
            el.addEventListener('change', function () { el.form.submit(); });
        });
    </script>
@endsection

@extends('construction::layouts.module')

@section('title', __('construction::lang.nav_costs'))

@php
    // المصادر بترتيب ثابت؛ رقم السلسلة = لونها في rr-chart (ما يتغيرش مع الفلترة)
    $sources = [
        'materials' => ['label' => 'cost_materials', 'series' => 1, 'icon' => 'folder-open', 'route' => 'construction.materials.index'],
        'labor' => ['label' => 'cost_labor', 'series' => 2, 'icon' => 'users', 'route' => 'construction.labor.index'],
        'subcontracts' => ['label' => 'cost_subcontracts', 'series' => 3, 'icon' => 'building', 'route' => 'construction.subcontractors.index'],
        'expenses' => ['label' => 'cost_expenses', 'series' => 4, 'icon' => 'file', 'route' => null],
    ];
    $sourceMeta = [
        'materials' => $summary['material_issue_lines'] . ' ' . __('construction::lang.issue_lines') . ' · ' . $summary['material_return_lines'] . ' ' . __('construction::lang.return_lines'),
        'labor' => $summary['labor_lines'] . ' ' . __('construction::lang.labor_lines'),
        'subcontracts' => $summary['subcontract_certificates'] . ' ' . __('construction::lang.approved_subcontract_certificates'),
        'expenses' => $summary['records_count'] . ' ' . __('construction::lang.expense_records'),
    ];
    $total = (float) $analytics['total'];
    $filterParams = array_filter(['project_id' => $selectedProjectId, 'from' => $fromDate, 'to' => $toDate]);
    $isFiltered = $selectedProjectId || $fromDate || $toDate;

    // فترات سريعة
    $today = now()->toDateString();
    $presets = [
        'period_this_month' => [now()->startOfMonth()->toDateString(), $today],
        'period_last_3_months' => [now()->startOfMonth()->subMonths(2)->toDateString(), $today],
        'period_this_year' => [now()->startOfYear()->toDateString(), $today],
        'period_all' => [null, null],
    ];

    // محور الرسم الشهري: أقرب رقم "مستدير" فوق أعلى شهر، و4 خطوط
    $months = $analytics['months'];
    $maxMonth = (float) collect($months)->max('total');
    $niceMax = 0;
    if ($maxMonth > 0) {
        $magnitude = 10 ** floor(log10($maxMonth));
        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($step * $magnitude >= $maxMonth) { $niceMax = $step * $magnitude; break; }
        }
    }
    // تسميات مختصرة للمحور (ألف/مليون) عشان ما تتلخبطش مع المبالغ الكاملة
    $compact = function ($value) {
        if ($value >= 1000000) return rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.') . ' ' . __('construction::lang.million_short');
        if ($value >= 1000) return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . ' ' . __('construction::lang.thousand_short');
        return (string) round($value);
    };
    $monthsWithCost = collect($months)->filter(fn ($month) => $month['total'] > 0);
    $peakMonth = $monthsWithCost->sortByDesc('total')->first();
    $largestSource = collect($analytics['sources'])->sortDesc()->keys()->first();
    $canCancelExpense = auth()->user()->can('construction.cancel');
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
@endphp

@section('module_content')
<main class="content rr-page ct-costs-page" aria-labelledby="rr-costs-title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 24])</span>
        <div class="rr-head__titles">
            <div class="rr-eyebrow">@lang('construction::lang.actual_costs')</div>
            <h1 id="rr-costs-title" class="rr-head__title">@lang('construction::lang.costs_overview_title')</h1>
            <p class="rr-head__sub">@lang('construction::lang.costs_overview_description')</p>
        </div>
        <div class="rr-head__actions">
            <button type="button" class="rr-btn rr-btn--primary" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr-record-cost-menu">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.record_cost')@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</button>
            <div class="rr-menu" id="rr-record-cost-menu" role="menu">
                @can('construction.material.manage')
                    <a class="rr-menu__item" role="menuitem" href="{{ route('construction.materials.create') }}"><span class="rr-legend__swatch rr-chart__seg--1" aria-hidden="true"></span>@lang('construction::lang.new_material_issue')</a>
                @endcan
                @can('construction.labor.manage')
                    <a class="rr-menu__item" role="menuitem" href="{{ route('construction.labor.create') }}"><span class="rr-legend__swatch rr-chart__seg--2" aria-hidden="true"></span>@lang('construction::lang.new_labor_sheet')</a>
                @endcan
                @can('construction.subcontract.view')
                    <a class="rr-menu__item" role="menuitem" href="{{ route('construction.subcontractors.index', array_filter(['project_id' => $selectedProjectId])) }}"><span class="rr-legend__swatch rr-chart__seg--3" aria-hidden="true"></span>@lang('construction::lang.cost_subcontracts')</a>
                @endcan
                @can('expense.add')
                    <a class="rr-menu__item" role="menuitem" href="{{ route('expenses.create') }}"><span class="rr-legend__swatch rr-chart__seg--4" aria-hidden="true"></span>@lang('construction::lang.project_expense_entry')</a>
                @endcan
            </div>
        </div>
    </header>

    {{-- ═══ التصفية: مشروع + فترة + فترات سريعة ═══ --}}
    <form class="rr-filter" role="search" method="GET" action="{{ route('construction.costs.index') }}">
        <div class="rr-filter__fields">
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="ct-cost-project">@lang('construction::lang.filter_by_project')</label>
                <select id="ct-cost-project" name="project_id" class="form-control select2">
                    <option value="">@lang('construction::lang.all_projects')</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rr-field">
                <label class="rr-field__label" for="ct-cost-from">@lang('construction::lang.from_date')</label>
                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="ct-cost-from" name="from" class="rr-control__input ct-date-picker" readonly value="{{ $fromDate ? @format_date($fromDate) : '' }}"></div>
            </div>
            <div class="rr-field">
                <label class="rr-field__label" for="ct-cost-to">@lang('construction::lang.to_date')</label>
                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="ct-cost-to" name="to" class="rr-control__input ct-date-picker" readonly value="{{ $toDate ? @format_date($toDate) : '' }}"></div>
            </div>
        </div>
        <div class="rr-filter__actions">
            <button type="submit" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'filter', 'size' => 16])@lang('construction::lang.apply_filter')</button>
            @if($isFiltered)
                <a href="{{ route('construction.costs.index') }}" class="rr-btn rr-btn--ghost">@lang('construction::lang.clear_filter')</a>
            @endif
        </div>
        <nav class="rr-presets rr-filter__presets" aria-label="@lang('construction::lang.period_label')">
            @foreach($presets as $label => [$presetFrom, $presetTo])
                <a class="rr-preset" href="{{ route('construction.costs.index', array_filter(['project_id' => $selectedProjectId, 'from' => $presetFrom, 'to' => $presetTo])) }}" @if($fromDate === $presetFrom && $toDate === $presetTo) aria-current="true" @endif>@lang('construction::lang.' . $label)</a>
            @endforeach
        </nav>
    </form>

    {{-- ═══ المؤشرات: الإجمالي + كل مصدر بنسبته ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--5" style="--rr-stats: 5">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.total_recorded_costs')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $total == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $summary['total']])</span></div>
                <div class="rr-stat__sub">
                    @if($fromDate || $toDate)
                        <span class="rr-num">{{ $fromDate ? @format_date($fromDate) : '…' }}</span> — <span class="rr-num">{{ $toDate ? @format_date($toDate) : '…' }}</span>
                    @else
                        @lang('construction::lang.cost_period_all_hint')
                    @endif
                </div>
            </div>
            @foreach($sources as $key => $source)
                @php
                    $sourceUrl = $source['route'] ? route($source['route'], array_filter(['project_id' => $selectedProjectId])) : '#ct-cost-expenses';
                    $isSubcontracts = $key === 'subcontracts';
                @endphp
                <a class="rr-stat" href="{{ $sourceUrl }}" @if($isSubcontracts) data-cost-source="subcontracts" data-cost-value="{{ $summary['subcontracts'] }}" data-document-count="{{ $summary['subcontract_certificates'] }}" @endif>
                    <div class="rr-stat__label"><span class="rr-stat__swatch rr-chart__seg--{{ $source['series'] }}" aria-hidden="true"></span>@lang('construction::lang.' . $source['label'])</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $summary[$key] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $summary[$key]])</span></div>
                    <div class="rr-stat__sub"><span class="rr-num">{{ @num_format($analytics['shares'][$key]) }}%</span> @lang('construction::lang.of_total_costs') · {{ $sourceMeta[$key] }}</div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ═══ الرسوم: الشهري + التوزيع ═══ --}}
    <div class="rr-cols">
        <section class="rr-card rr-cols__main" aria-labelledby="rr-monthly-costs-title">
            <header class="rr-card__head">
                <div class="rr-card__titles">
                    <h2 id="rr-monthly-costs-title" class="rr-card__title">@lang('construction::lang.monthly_costs_title')</h2>
                    <p class="rr-card__desc">{{ $fromDate ? __('construction::lang.monthly_costs_hint') : __('construction::lang.monthly_costs_last_12_hint') }}</p>
                </div>
            </header>
            <figure class="rr-chart" aria-label="@lang('construction::lang.monthly_costs_title')">
                <div class="rr-chart__plot">
                    <div class="rr-chart__grid" aria-hidden="true">
                        @foreach([0, 25, 50, 75, 100] as $tick)
                            <div class="rr-chart__tick {{ $tick === 0 ? 'rr-chart__tick--base' : '' }}" style="--rr-tick: {{ $tick }}%">@if($niceMax > 0 || $tick === 0)<span>{{ $compact($niceMax * $tick / 100) }}</span>@endif</div>
                        @endforeach
                    </div>
                    @if($niceMax <= 0)
                        <div class="rr-chart__empty">@lang('construction::lang.no_costs_in_period')</div>
                    @endif
                    <div class="rr-chart__cols">
                        @foreach($months as $month)
                            @php $monthLabel = $month['date']->locale(app()->getLocale())->translatedFormat('F Y'); @endphp
                            <div class="rr-chart__col" @if($month['total'] > 0) tabindex="0" data-rr-tip aria-label="{{ $monthLabel }}" @endif>
                                @if($month['total'] > 0)
                                    <div class="rr-chart__stack" style="height: {{ round($month['total'] / $niceMax * 100, 2) }}%">
                                        @foreach($sources as $key => $source)
                                            @if($month['values'][$key] > 0)
                                                <span class="rr-chart__seg rr-chart__seg--{{ $source['series'] }}" style="flex: {{ round($month['values'][$key] / $month['total'] * 100, 3) }} 1 0"></span>
                                            @endif
                                        @endforeach
                                    </div>
                                    <div class="rr-chart__tip-src" hidden>
                                        <strong class="rr-chart-tip__title">{{ $monthLabel }}</strong>
                                        @foreach($sources as $key => $source)
                                            @if($month['values'][$key] != 0)
                                                <div class="rr-chart-tip__row"><span class="rr-legend__swatch rr-chart__seg--{{ $source['series'] }}"></span><span>@lang('construction::lang.' . $source['label'])</span><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($month['values'][$key])['amount'] }}</span></div>
                                            @endif
                                        @endforeach
                                        <div class="rr-chart-tip__row rr-chart-tip__row--total"><span></span><span>@lang('construction::lang.total')</span><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($month['total'])['amount'] }}</span></div>
                                    </div>
                                @endif
                                <span class="rr-chart__x">{{ $month['date']->locale(app()->getLocale())->translatedFormat('M') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <figcaption>
                    <ul class="rr-legend rr-legend--inline">
                        @foreach($sources as $key => $source)
                            <li class="rr-legend__item"><span class="rr-legend__swatch rr-chart__seg--{{ $source['series'] }}" aria-hidden="true"></span><span class="rr-legend__label">@lang('construction::lang.' . $source['label'])</span></li>
                        @endforeach
                    </ul>
                </figcaption>
            </figure>
            <div class="rr-card__body">
                <details class="rr-help">
                    <summary>@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 16])@lang('construction::lang.show_as_table')</summary>
                    <div class="rr-table-scroll">
                        <table class="rr-table">
                            <caption class="rr-sr">@lang('construction::lang.monthly_costs_title')</caption>
                            <thead>
                                <tr>
                                    <th class="rr-th" scope="col">@lang('construction::lang.month_label')</th>
                                    @foreach($sources as $source)<th class="rr-th rr-th--num" scope="col">@lang('construction::lang.' . $source['label'])</th>@endforeach
                                    <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.total')</th>
                                </tr>
                            </thead>
                            <tbody class="rr-tbody">
                                @foreach(array_reverse($months) as $month)
                                    <tr class="rr-tr">
                                        <th class="rr-td" scope="row">{{ $month['date']->locale(app()->getLocale())->translatedFormat('F Y') }}</th>
                                        @foreach(array_keys($sources) as $key)<td class="rr-td rr-td--num"><span class="rr-num {{ $month['values'][$key] == 0 ? 'rr-td--muted' : '' }}">{{ $month['values'][$key] == 0 ? '—' : \Modules\Construction\Support\CurrencyFormatter::parts($month['values'][$key])['amount'] }}</span></td>@endforeach
                                        <td class="rr-td rr-td--num"><strong class="rr-num">{{ $month['total'] == 0 ? '—' : \Modules\Construction\Support\CurrencyFormatter::parts($month['total'])['amount'] }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </section>

        <aside class="rr-cols__side">
            <section class="rr-card" aria-labelledby="rr-cost-composition-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-cost-composition-title" class="rr-card__title">@lang('construction::lang.cost_composition_title')</h2>
                        <p class="rr-card__desc">@lang('construction::lang.cost_composition_hint')</p>
                    </div>
                </header>
                <div class="rr-card__body">
                    @if($total > 0)
                        <div class="rr-split" role="img" aria-label="@lang('construction::lang.cost_composition_title')">
                            @foreach($sources as $key => $source)
                                @if($analytics['sources'][$key] > 0)
                                    <span class="rr-split__seg rr-chart__seg--{{ $source['series'] }}" style="width: {{ $analytics['shares'][$key] }}%" data-rr-tip><span class="rr-chart__tip-src" hidden><strong class="rr-chart-tip__title">@lang('construction::lang.' . $source['label'])</strong><span class="rr-chart-tip__row"><span></span><span class="rr-num">{{ @num_format($analytics['shares'][$key]) }}%</span><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($analytics['sources'][$key])['amount'] }}</span></span></span></span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <ul class="rr-legend" style="margin-top: var(--rr-space-4)">
                        @foreach($sources as $key => $source)
                            <li class="rr-legend__item">
                                <span class="rr-legend__swatch rr-chart__seg--{{ $source['series'] }}" aria-hidden="true"></span>
                                <span class="rr-legend__label">@lang('construction::lang.' . $source['label'])</span>
                                <span class="rr-legend__value rr-money rr-money--sm {{ $analytics['sources'][$key] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $analytics['sources'][$key]])</span>
                                <span class="rr-legend__pct">{{ @num_format($analytics['shares'][$key]) }}%</span>
                            </li>
                        @endforeach
                    </ul>
                    @if($total > 0)
                        <dl class="rr-insights">
                            <div class="rr-insight"><dt>@lang('construction::lang.largest_cost_source')</dt><dd>@lang('construction::lang.' . $sources[$largestSource]['label'])</dd></div>
                            @if($peakMonth)
                                <div class="rr-insight"><dt>@lang('construction::lang.highest_cost_month')</dt><dd>{{ $peakMonth['date']->locale(app()->getLocale())->translatedFormat('F Y') }}</dd></div>
                            @endif
                            @if($monthsWithCost->count() > 0)
                                <div class="rr-insight"><dt>@lang('construction::lang.monthly_average')</dt><dd><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($monthsWithCost->sum('total') / $monthsWithCost->count())['amount'] }}</span></dd></div>
                            @endif
                        </dl>
                    @endif
                </div>
            </section>
        </aside>
    </div>

    {{-- ═══ التكاليف حسب المشروع مقابل الأعمال المعتمدة ═══ --}}
    <section class="rr-table-wrap" aria-labelledby="rr-cost-projects-title">
        <div class="rr-table-head">
            <div>
                <h2 id="rr-cost-projects-title" class="rr-table-head__title">{{ $selectedProjectId ? __('construction::lang.project_cost_result_title') : __('construction::lang.cost_by_project_title') }}</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.cost_by_project_hint')</p>
            </div>
            @if($selectedProjectId)
                <a class="rr-btn rr-btn--secondary rr-btn--sm" href="{{ route('construction.reports.boq-cost-profitability.index', array_filter(['project_id' => $selectedProjectId, 'to_date' => $toDate])) }}">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 16])@lang('construction::lang.open_cost_profitability_report')</a>
            @endif
        </div>
        @if(empty($analytics['projects']))
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_costs_in_period')</h3>
                <p class="rr-empty__text">@lang('construction::lang.no_costs_in_period_hint')</p>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.cost_by_project_title')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            @foreach($sources as $source)<th class="rr-th rr-th--num" scope="col">@lang('construction::lang.' . $source['label'])</th>@endforeach
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.total_actual_cost')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.certified_work')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.cost_result')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.cost_margin')</th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($analytics['projects'] as $row)
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--wrap">
                                    <a class="rr-entity rr-entity--link" href="{{ route('construction.costs.index', array_filter(['project_id' => $row['project']->id, 'from' => $fromDate, 'to' => $toDate])) }}">
                                        <span class="rr-entity__copy">
                                            <span class="rr-entity__name">{{ $row['project']->name }}</span>
                                            <span class="rr-code">{{ $row['project']->code }}</span>
                                        </span>
                                    </a>
                                    @if($total > 0)
                                        <div class="rr-split" style="height: 6px; margin-top: var(--rr-space-2); max-width: 220px" aria-hidden="true">
                                            @foreach($sources as $key => $source)
                                                @if($row['values'][$key] > 0)<span class="rr-split__seg rr-chart__seg--{{ $source['series'] }}" style="width: {{ round($row['values'][$key] / $total * 100, 2) }}%"></span>@endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                @foreach(array_keys($sources) as $key)
                                    <td class="rr-td rr-td--num"><span class="rr-num {{ $row['values'][$key] == 0 ? 'rr-td--muted' : '' }}">{{ $row['values'][$key] == 0 ? '—' : \Modules\Construction\Support\CurrencyFormatter::parts($row['values'][$key])['amount'] }}</span></td>
                                @endforeach
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $row['total']])</span><div class="rr-td__sub"><span class="rr-num">{{ $total > 0 ? @num_format($row['total'] / $total * 100) : 0 }}%</span></div></td>
                                <td class="rr-td rr-td--num"><span class="rr-num {{ $row['certified'] == 0 ? 'rr-td--muted' : '' }}">{{ $row['certified'] == 0 ? '—' : \Modules\Construction\Support\CurrencyFormatter::parts($row['certified'])['amount'] }}</span></td>
                                <td class="rr-td rr-td--num">
                                    @if($row['certified'] > 0)
                                        <strong class="rr-num {{ $row['result'] < 0 ? 'rr-text--danger' : 'rr-text--success' }}">{{ \Modules\Construction\Support\CurrencyFormatter::parts($row['result'])['amount'] }}</strong>
                                    @else
                                        <span class="rr-td--muted">—</span>
                                    @endif
                                </td>
                                <td class="rr-td rr-td--num">
                                    @if($row['margin'] !== null)
                                        <span class="rr-badge rr-badge--{{ $row['margin'] < 0 ? 'danger' : ($row['margin'] < 10 ? 'warning' : 'success') }}"><span class="rr-num">{{ @num_format($row['margin']) }}%</span></span>
                                    @else
                                        <span class="rr-td--muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ═══ أحدث المصروفات ═══ --}}
    <section class="rr-table-wrap" id="ct-cost-expenses" aria-labelledby="rr-cost-expenses-title">
        <div class="rr-table-head">
            <div>
                <h2 id="rr-cost-expenses-title" class="rr-table-head__title">@lang('construction::lang.expense_details')</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.expense_details_description', ['projects' => $summary['projects_count']])</p>
            </div>
            @if(auth()->user()->can('all_expense.access') || auth()->user()->can('view_own_expense'))
                <a class="rr-btn rr-btn--secondary rr-btn--sm" href="{{ route('expenses.index', array_filter(['construction_only' => 1, 'construction_project_id' => $selectedProjectId])) }}">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 16])@lang('construction::lang.open_expense_register')</a>
            @endif
        </div>
        @if($recentExpenses->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_cost_expenses')</h3>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.expense_details')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.date')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.expense_reference')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.project_item')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.location')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.amount')</th>
                            @if($canCancelExpense)<th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>@endif
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($recentExpenses as $expense)
                            <tr class="rr-tr" data-expense-reference="{{ $expense->ref_no }}">
                                <td class="rr-td"><span class="rr-num">{{ @format_date($expense->transaction_date) }}</span></td>
                                <td class="rr-td"><span class="rr-code">{{ $expense->ref_no ?: __('construction::lang.expense_without_reference') }}</span>@if($expense->type === 'expense_refund')<div><span class="rr-badge rr-badge--success">@lang('construction::lang.expense_refund_label')</span></div>@endif</td>
                                <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $expense->constructionProject?->name }}</span><div class="rr-td__sub"><span class="rr-code">{{ $expense->constructionProject?->code }}</span></div></td>
                                <td class="rr-td rr-td--wrap">
                                    @if($expense->constructionProjectItem)
                                        <span class="rr-code">{{ $expense->constructionProjectItem->code }}</span> {{ $expense->constructionProjectItem->description }}
                                    @else
                                        <span class="rr-td--muted">@lang('construction::lang.general_project_expense')</span>
                                    @endif
                                </td>
                                <td class="rr-td rr-td--muted">{{ $expense->location?->name ?: '—' }}</td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@if($expense->type === 'expense_refund')− @endif @include('construction::partials.money', ['value' => $expense->final_total])</span></td>
                                @if($canCancelExpense)
                                    <td class="rr-td rr-td--actions">
                                        @if($expense->type === 'expense')
                                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm" data-toggle="modal" data-target="#cancelExpense{{ $expense->id }}">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 16])@lang('construction::lang.cancel_and_reverse')</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>

{{-- ═══ نوافذ إلغاء المصروف (سبب + كلمة السر) ═══ --}}
@if($canCancelExpense)
    @foreach($recentExpenses->where('type', 'expense') as $expense)
        <div class="modal fade rr-scope" id="cancelExpense{{ $expense->id }}" tabindex="-1" role="dialog" aria-labelledby="cancelExpense{{ $expense->id }}-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.costs.expenses.cancel', $expense->id) }}">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="cancelExpense{{ $expense->id }}-title">@lang('construction::lang.cancel_project_expense')</h2>
                                <p class="rr-modal__desc">@lang('construction::lang.cancel_project_expense_hint', ['reference' => $expense->ref_no ?: $expense->id])</p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="cancelExpense{{ $expense->id }}-reason">@lang('construction::lang.cancellation_reason'){!! $req !!}</label>
                                    <div class="rr-control rr-control--area"><textarea id="cancelExpense{{ $expense->id }}-reason" name="cancellation_reason" class="rr-control__input rr-control__input--short" rows="3" required minlength="5"></textarea></div>
                                </div>
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="cancelExpense{{ $expense->id }}-password">@lang('construction::lang.current_password'){!! $req !!}</label>
                                    <div class="rr-control"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 18])</span><input type="password" id="cancelExpense{{ $expense->id }}-password" name="current_password" class="rr-control__input" required autocomplete="current-password"></div>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--danger" type="submit">@lang('construction::lang.confirm_cancel_and_reverse')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.close')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
@include('construction::partials.rr_menu_script')
@include('construction::partials.rr_chart_script')
@endsection

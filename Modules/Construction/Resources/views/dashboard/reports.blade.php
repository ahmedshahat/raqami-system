@extends('construction::layouts.module')

@section('title', __('construction::lang.financial_position_report'))

@php
    $costSources = ['materials' => 1, 'labor' => 2, 'subcontracts' => 3, 'expenses' => 4];
    $meta = $report ? [
        ['building', $selectedProject->code . ' — ' . $selectedProject->name],
        ['user', $selectedProject->customer?->name ?: '—'],
        ['calendar', ($fromDate ? app(\App\Utils\Util::class)->format_date($fromDate) : __('construction::lang.project_inception')) . ' — ' . app(\App\Utils\Util::class)->format_date($toDate)],
    ] : [];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-report-title">
    @include('construction::dashboard.partials.rr_report_switcher', ['active' => 'financial'])

    @include('construction::dashboard.partials.rr_report_head', [
        'icon' => 'pie',
        'eyebrow' => __('construction::lang.owner_accountant_report'),
        'title' => __('construction::lang.financial_position_report'),
        'description' => __('construction::lang.financial_position_report_description'),
        'printUrl' => $report ? route('construction.reports.financial-position.print', array_filter(['project_id' => $selectedProject->id, 'date_range' => $dateRange, 'from_date' => $fromDate, 'to_date' => $toDate])) : null,
        'printLabel' => __('construction::lang.print_financial_report'),
        'meta' => $meta,
    ])

    <form method="GET" action="{{ route('construction.reports.index') }}" class="rr-filter ct-date-range-filter {{ $dateRange === 'custom' ? 'is-custom-range' : 'is-preset-range' }}">
        <div class="rr-filter__fields">
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="ct-report-project">@lang('construction::lang.project')<span class="rr-field__req" aria-hidden="true">*</span></label>
                <select id="ct-report-project" name="project_id" class="form-control select2" required>
                    <option value="">@lang('construction::lang.select_project')</option>
                    @foreach($projects as $project)<option value="{{ $project->id }}" @selected($selectedProject?->id === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach
                </select>
            </div>
            @include('construction::dashboard.partials.date-range-filter')
        </div>
        <div class="rr-filter__actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 16])@lang('construction::lang.show_report')</button>
        </div>
    </form>

    @if(! $report)
        <div class="rr-card">
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 22])</span>
                <h2 class="rr-empty__title">@lang('construction::lang.financial_report_select_project')</h2>
                <p class="rr-empty__text">@lang('construction::lang.financial_report_select_project_hint')</p>
            </div>
        </div>
    @else
        @include('construction::dashboard.partials.rr_report_warnings', ['warnings' => $report['warnings']])

        {{-- ═══ المؤشرات الرئيسية ═══ --}}
        <div class="rr-statgroup-wrap">
            <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4">
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.adjusted_contract_value')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $report['contract']['value']])</span></div>
                    <div class="rr-stat__sub">@lang('construction::lang.approved_adjustments'): <span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($report['contract']['adjustments'])['amount'] }}</span></div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.approved_customer_work')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $report['customer']['approved_work']])</span></div>
                    <div class="rr-stat__sub"><span class="rr-num">{{ $report['customer']['certificates_count'] }}</span> @lang('construction::lang.approved_certificates')</div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.total_actual_cost')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $report['costs']['actual']])</span></div>
                    <div class="rr-stat__sub">@lang('construction::lang.actual_cost_to_date')</div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check', 'size' => 18])@lang('construction::lang.current_project_result')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $report['result']['current'] < 0 ? 'rr-text--danger' : 'rr-text--success' }}">@include('construction::partials.money', ['value' => $report['result']['current']])</span></div>
                    <div class="rr-stat__sub"><span class="rr-badge rr-badge--{{ $report['result']['margin_percent'] < 0 ? 'danger' : ($report['result']['margin_percent'] < 10 ? 'warning' : 'success') }}"><span class="rr-num">{{ @num_format($report['result']['margin_percent']) }}%</span></span> @lang('construction::lang.current_margin')</div>
                </div>
            </div>
        </div>

        <div class="rr-report-grid">
            {{-- تحليل التكلفة --}}
            <section class="rr-card" aria-labelledby="rr-cost-breakdown-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <div class="rr-eyebrow">@lang('construction::lang.cost_analysis')</div>
                        <h2 id="rr-cost-breakdown-title" class="rr-card__title">@lang('construction::lang.actual_cost_breakdown')</h2>
                    </div>
                    <span class="rr-money">@include('construction::partials.money', ['value' => $report['costs']['actual']])</span>
                </header>
                <div class="rr-card__body">
                    @if($report['costs']['actual'] > 0)
                        <div class="rr-split" aria-hidden="true">
                            @foreach($costSources as $key => $series)
                                @if($report['costs'][$key] > 0)<span class="rr-split__seg rr-chart__seg--{{ $series }}" style="width: {{ round($report['costs'][$key] / $report['costs']['actual'] * 100, 2) }}%"></span>@endif
                            @endforeach
                        </div>
                    @endif
                    <ul class="rr-legend" style="margin-top: var(--rr-space-4)">
                        @foreach($costSources as $key => $series)
                            <li class="rr-legend__item">
                                <span class="rr-legend__swatch rr-chart__seg--{{ $series }}" aria-hidden="true"></span>
                                <span class="rr-legend__label">@lang('construction::lang.cost_' . $key)</span>
                                <span class="rr-legend__value rr-money rr-money--sm {{ $report['costs'][$key] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $report['costs'][$key]])</span>
                                <span class="rr-legend__pct">{{ $report['costs']['actual'] > 0 ? @num_format($report['costs'][$key] / $report['costs']['actual'] * 100) : 0 }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- موقف المالك --}}
            <section class="rr-card" aria-labelledby="rr-customer-position-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <div class="rr-eyebrow">@lang('construction::lang.customer_position')</div>
                        <h2 id="rr-customer-position-title" class="rr-card__title">@lang('construction::lang.certification_and_collection')</h2>
                    </div>
                    <span class="rr-money">@include('construction::partials.money', ['value' => $report['customer']['receivable']])</span>
                </header>
                <dl class="rr-deflist">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.customer_net_due')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $report['customer']['net_due']])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.invoiced_amount')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $report['customer']['invoiced']])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.collected_amount')</dt><dd><span class="rr-money rr-money--sm rr-text--success">@include('construction::partials.money', ['value' => $report['customer']['collected']])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.customer_receivable')</dt><dd><span class="rr-money rr-money--sm {{ $report['customer']['receivable'] > 0 ? 'rr-text--danger' : '' }}">@include('construction::partials.money', ['value' => $report['customer']['receivable']])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.customer_retention')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $report['customer']['retention']])</span></dd></div>
                </dl>
            </section>

            {{-- التزامات مقاولي الباطن --}}
            <section class="rr-card" aria-labelledby="rr-subcontract-position-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <div class="rr-eyebrow">@lang('construction::lang.subcontractor_position')</div>
                        <h2 id="rr-subcontract-position-title" class="rr-card__title">@lang('construction::lang.subcontractor_obligations')</h2>
                    </div>
                    <span class="rr-money">@include('construction::partials.money', ['value' => $report['subcontracts']['balance']])</span>
                </header>
                <dl class="rr-deflist">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.approved_subcontract_work')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $report['subcontracts']['gross']])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.subcontractor_payable')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $report['subcontracts']['payable']])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.paid_amount')</dt><dd><span class="rr-money rr-money--sm rr-text--success">@include('construction::partials.money', ['value' => $report['subcontracts']['paid']])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.remaining_amount')</dt><dd><span class="rr-money rr-money--sm {{ $report['subcontracts']['balance'] > 0 ? 'rr-text--danger' : '' }}">@include('construction::partials.money', ['value' => $report['subcontracts']['balance']])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.remaining_retention')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $report['subcontracts']['retention']])</span></dd></div>
                </dl>
            </section>

            {{-- مؤشر نقدي --}}
            <section class="rr-card" aria-labelledby="rr-cash-position-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <div class="rr-eyebrow">@lang('construction::lang.cash_indicator')</div>
                        <h2 id="rr-cash-position-title" class="rr-card__title">@lang('construction::lang.partial_cash_position')</h2>
                    </div>
                    <span class="rr-money {{ $report['cash']['partial_net'] < 0 ? 'rr-text--danger' : '' }}">@include('construction::partials.money', ['value' => $report['cash']['partial_net']])</span>
                </header>
                <dl class="rr-deflist">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.customer_collections')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $report['cash']['customer_collections']])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.subcontractor_payments')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $report['cash']['subcontractor_payments']])</span></dd></div>
                </dl>
                <p class="rr-card__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.partial_cash_position_notice')</p>
            </section>
        </div>

        {{-- ═══ ملخص مقاولي الباطن ═══ --}}
        <section class="rr-table-wrap" aria-labelledby="rr-subcontractor-summary-title">
            <div class="rr-table-head">
                <div>
                    <div class="rr-eyebrow">@lang('construction::lang.accountant_details')</div>
                    <h2 id="rr-subcontractor-summary-title" class="rr-table-head__title">@lang('construction::lang.subcontractor_summary')</h2>
                </div>
            </div>
            @if($report['subcontractors']->isEmpty())
                <div class="rr-empty"><h3 class="rr-empty__title">@lang('construction::lang.no_approved_subcontract_costs')</h3></div>
            @else
                <div class="rr-table-scroll">
                    <table class="rr-table">
                        <caption class="rr-sr">@lang('construction::lang.subcontractor_summary')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.subcontractor')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.agreement_number')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.approved_subcontract_work')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.remaining_retention')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.subcontractor_payable')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.paid_amount')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.remaining_amount')</th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($report['subcontractors'] as $row)
                                <tr class="rr-tr">
                                    <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $row['name'] }}</span><div class="rr-td__sub"><span class="rr-num">{{ $row['certificates'] }}</span> @lang('construction::lang.certificates')</div></td>
                                    <td class="rr-td"><span class="rr-code">{{ $row['agreement'] }}</span></td>
                                    @foreach(['gross', 'retention', 'payable', 'paid'] as $key)
                                        <td class="rr-td rr-td--num"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($row[$key])['amount'] }}</span></td>
                                    @endforeach
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $row['balance']])</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</main>
@endsection

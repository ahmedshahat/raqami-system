@extends('construction::layouts.module')

@section('title', __('construction::lang.boq_cost_profitability'))

@php
    $costSources = ['materials' => 1, 'labor' => 2, 'subcontracts' => 3, 'expenses' => 4];
    $statusTones = ['over' => 'danger', 'healthy' => 'success', 'no_cost' => 'neutral'];
    $amount = fn ($value) => \Modules\Construction\Support\CurrencyFormatter::parts($value)['amount'];
    $meta = $report ? [
        ['building', $selectedProject->code . ' — ' . $selectedProject->name],
        ['user', $selectedProject->customer?->name ?: '—'],
        ['calendar', __('construction::lang.as_of_date') . ' ' . app(\App\Utils\Util::class)->format_date($toDate)],
    ] : [];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-report-title">
    @include('construction::dashboard.partials.rr_report_switcher', ['active' => 'profitability'])

    @include('construction::dashboard.partials.rr_report_head', [
        'icon' => 'list',
        'eyebrow' => __('construction::lang.cost_control_report'),
        'title' => __('construction::lang.boq_cost_profitability'),
        'description' => __('construction::lang.boq_cost_profitability_description'),
        'printUrl' => $report ? route('construction.reports.boq-cost-profitability.print', ['project_id' => $selectedProject->id, 'to_date' => $toDate]) : null,
        'printLabel' => __('construction::lang.print_cost_profitability'),
        'meta' => $meta,
    ])

    <form method="GET" action="{{ route('construction.reports.boq-cost-profitability.index') }}" class="rr-filter">
        <div class="rr-filter__fields">
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="ct-report-project">@lang('construction::lang.project')<span class="rr-field__req" aria-hidden="true">*</span></label>
                <select id="ct-report-project" name="project_id" class="form-control select2" required>
                    <option value="">@lang('construction::lang.select_project')</option>
                    @foreach($projects as $project)<option value="{{ $project->id }}" @selected($selectedProject?->id === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach
                </select>
            </div>
            <div class="rr-field">
                <label class="rr-field__label" for="ct-report-as-of">@lang('construction::lang.as_of_date')</label>
                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="ct-report-as-of" name="to_date" value="{{ @format_date($toDate) }}" class="rr-control__input ct-date-picker" readonly></div>
            </div>
        </div>
        <div class="rr-filter__actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 16])@lang('construction::lang.show_report')</button>
        </div>
    </form>

    @if(! $report)
        <div class="rr-card">
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 22])</span>
                <h2 class="rr-empty__title">@lang('construction::lang.cost_profitability_select_project')</h2>
                <p class="rr-empty__text">@lang('construction::lang.cost_profitability_select_project_hint')</p>
            </div>
        </div>
    @else
        @include('construction::dashboard.partials.rr_report_warnings', ['warnings' => $report['warnings']])

        <div class="rr-statgroup-wrap">
            <div class="rr-statgroup rr-statgroup--3" style="--rr-stats: 3">
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.total_sales_value')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $report['summary']['sales']])</span></div>
                    <div class="rr-stat__sub">@lang('construction::lang.approved_boq_value')</div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.actual_cost_to_date')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $report['summary']['actual']])</span></div>
                    <div class="rr-stat__sub"><span class="rr-num">{{ @num_format($report['summary']['cost_percent']) }}%</span> @lang('construction::lang.of_sales_value')</div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check', 'size' => 18])@lang('construction::lang.sale_value_less_actual_cost')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $report['summary']['sale_less_actual'] < 0 ? 'rr-text--danger' : 'rr-text--success' }}">@include('construction::partials.money', ['value' => $report['summary']['sale_less_actual']])</span></div>
                    <div class="rr-stat__sub">@if($report['summary']['loss_items'] > 0)<span class="rr-badge rr-badge--danger"><span class="rr-num">{{ $report['summary']['loss_items'] }}</span></span> @lang('construction::lang.loss_items')@else @lang('construction::lang.cost_within_sales')@endif</div>
                </div>
            </div>
        </div>

        @if($report['unlinked']['total'] != 0)
            <div class="rr-callout rr-callout--info">
                <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 20])</span>
                <div class="rr-callout__copy">
                    <strong>@lang('construction::lang.unlinked_costs'): <span class="rr-num">{{ $amount($report['unlinked']['total']) }}</span></strong>
                    <p>@lang('construction::lang.unlinked_costs_hint')</p>
                    <ul class="rr-legend rr-legend--inline" style="margin-top: var(--rr-space-2)">
                        @foreach($costSources as $key => $series)
                            <li class="rr-legend__item"><span class="rr-legend__swatch rr-chart__seg--{{ $series }}" aria-hidden="true"></span><span class="rr-legend__label">@lang('construction::lang.cost_' . $key)</span><span class="rr-num">{{ $amount($report['unlinked'][$key]) }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <section class="rr-table-wrap" aria-labelledby="rr-profit-items-title">
            <div class="rr-table-head rr-table-head--toolbar">
                <div class="rr-table-head__titles">
                    <div class="rr-eyebrow">@lang('construction::lang.item_level_analysis')</div>
                    <h2 id="rr-profit-items-title" class="rr-table-head__title">@lang('construction::lang.actual_cost_by_item')</h2>
                </div>
                <ul class="rr-legend rr-legend--inline">
                    <li class="rr-legend__item"><span class="rr-badge rr-badge--success">@lang('construction::lang.cost_within_sales')</span></li>
                    <li class="rr-legend__item"><span class="rr-badge rr-badge--danger">@lang('construction::lang.cost_exceeds_sales')</span></li>
                </ul>
            </div>
            @if($report['rows']->isEmpty())
                <div class="rr-empty"><h3 class="rr-empty__title">@lang('construction::lang.no_comparison_items')</h3></div>
            @else
                <div class="rr-table-scroll">
                    <table class="rr-table">
                        <caption class="rr-sr">@lang('construction::lang.actual_cost_by_item')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.boq_item')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.sales_value')</th>
                                @foreach($costSources as $key => $series)<th class="rr-th rr-th--num" scope="col"><span class="rr-th__in"><span class="rr-legend__swatch rr-chart__seg--{{ $series }}" aria-hidden="true"></span>@lang('construction::lang.cost_' . $key)</span></th>@endforeach
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.total_actual_cost')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.sale_less_actual')</th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($report['rows'] as $row)
                                <tr class="rr-tr {{ $row['status'] === 'over' ? 'rr-tr--danger' : '' }}">
                                    <td class="rr-td rr-td--wrap"><span class="rr-code">{{ $row['code'] }}</span> <span class="rr-entity__name">{{ $row['description'] }}</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($row['sales']) }}</span></td>
                                    @foreach(array_keys($costSources) as $key)
                                        <td class="rr-td rr-td--num"><span class="rr-num {{ $row['actual'][$key] == 0 ? 'rr-td--muted' : '' }}">{{ $row['actual'][$key] == 0 ? '—' : $amount($row['actual'][$key]) }}</span></td>
                                    @endforeach
                                    <td class="rr-td rr-td--num"><strong class="rr-num">{{ $amount($row['actual']['total']) }}</strong><div class="rr-td__sub"><span class="rr-num">{{ @num_format($row['cost_percent']) }}%</span></div></td>
                                    <td class="rr-td rr-td--num"><span class="rr-badge rr-badge--{{ $statusTones[$row['status']] ?? 'neutral' }}"><span class="rr-num">{{ $amount($row['sale_less_actual']) }}</span></span></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="rr-tfoot">
                            <tr>
                                <td class="rr-td">@lang('construction::lang.total')</td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($report['summary']['sales']) }}</span></td>
                                <td class="rr-td" colspan="4">@lang('construction::lang.linked_actual_cost'): <span class="rr-num">{{ $amount($report['summary']['linked_actual']) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($report['summary']['actual']) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($report['summary']['sale_less_actual']) }}</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
            <p class="rr-card__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.cost_profitability_not_final_profit_notice')</p>
        </section>
    @endif
</main>
@endsection

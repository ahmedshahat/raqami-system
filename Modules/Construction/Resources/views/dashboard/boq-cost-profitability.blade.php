@extends('construction::layouts.module')

@section('title', __('construction::lang.boq_cost_profitability'))

@section('module_content')
<section class="content ct-financial-report-page ct-cost-profit-page">
    <nav class="ct-report-switcher" aria-label="@lang('construction::lang.construction_reports')">
        <a href="{{ route('construction.reports.index') }}"><i class="fa fa-chart-line"></i> @lang('construction::lang.financial_position_report')</a>
        <a href="{{ route('construction.reports.subcontractor-statement.index') }}"><i class="fa fa-file-invoice-dollar"></i> @lang('construction::lang.subcontractor_statement')</a>
        <a href="{{ route('construction.reports.boq-subcontract-comparison.index') }}"><i class="fa fa-balance-scale"></i> @lang('construction::lang.boq_subcontract_comparison')</a>
        <a class="is-active" href="{{ route('construction.reports.boq-cost-profitability.index') }}"><i class="fa fa-coins"></i> @lang('construction::lang.boq_cost_profitability')</a>
        <a href="{{ route('construction.reports.customer-certificates-collection.index') }}"><i class="fa fa-hand-holding-usd"></i> @lang('construction::lang.customer_collection_report')</a>
        <a href="{{ route('construction.reports.retention-guarantees.index') }}"><i class="fa fa-shield-alt"></i> @lang('construction::lang.retention_guarantees_report')</a>
    </nav>

    <header class="ct-financial-report-heading ct-cost-profit-heading">
        <div><span><i class="fa fa-chart-area"></i> @lang('construction::lang.cost_control_report')</span><h1>@lang('construction::lang.boq_cost_profitability')</h1><p>@lang('construction::lang.boq_cost_profitability_description')</p></div>
        @if($report)<a class="btn ct-primary-action" target="_blank" href="{{ route('construction.reports.boq-cost-profitability.print',['project_id'=>$selectedProject->id,'to_date'=>$toDate]) }}"><i class="fa fa-print"></i> @lang('construction::lang.print_cost_profitability')</a>@endif
    </header>

    <form method="GET" action="{{ route('construction.reports.boq-cost-profitability.index') }}" class="ct-financial-report-filter ct-cost-profit-filter">
        <div class="form-group"><label>@lang('construction::lang.project') *</label><select name="project_id" class="form-control select2" required><option value="">@lang('construction::lang.select_project')</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($selectedProject?->id === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach</select></div>
        <div class="form-group"><label>@lang('construction::lang.as_of_date')</label><input name="to_date" value="{{ @format_date($toDate) }}" class="form-control ct-date-picker" readonly></div>
        <button class="btn btn-primary"><i class="fa fa-chart-bar"></i> @lang('construction::lang.show_report')</button>
    </form>

    @if(!$report)
        <div class="ct-financial-report-empty"><span><i class="fa fa-coins"></i></span><h2>@lang('construction::lang.cost_profitability_select_project')</h2><p>@lang('construction::lang.cost_profitability_select_project_hint')</p></div>
    @else
        <div class="ct-financial-project-strip"><div><small>@lang('construction::lang.project')</small><strong>{{ $selectedProject->code }} — {{ $selectedProject->name }}</strong></div><div><small>@lang('construction::lang.customer')</small><strong>{{ $selectedProject->customer?->name ?: '—' }}</strong></div><div><small>@lang('construction::lang.as_of_date')</small><strong>{{ @format_date($toDate) }}</strong></div></div>
        @if($report['warnings']->isNotEmpty())<div class="ct-financial-warnings">@foreach($report['warnings'] as $warning)<div><i class="fa fa-exclamation-triangle"></i><span>{{ $warning }}</span></div>@endforeach</div>@endif

        <div class="ct-cost-profit-kpis">
            <article><small>@lang('construction::lang.total_sales_value')</small><strong>@include('construction::partials.money',['value'=>$report['summary']['sales']])</strong><span>@lang('construction::lang.approved_boq_value')</span></article>
            <article><small>@lang('construction::lang.estimated_cost')</small><strong>@include('construction::partials.money',['value'=>$report['summary']['estimated']])</strong><span>@lang('construction::lang.budget_margin'): @include('construction::partials.money',['value'=>$report['summary']['budget_margin']])</span></article>
            <article><small>@lang('construction::lang.actual_cost_to_date')</small><strong>@include('construction::partials.money',['value'=>$report['summary']['actual']])</strong><span>{{ @num_format($report['summary']['usage_percent']) }}% @lang('construction::lang.of_estimated_cost')</span></article>
            <article class="{{ $report['summary']['remaining_budget'] < 0 ? 'is-loss' : 'is-profit' }}"><small>@lang('construction::lang.remaining_cost_budget')</small><strong>@include('construction::partials.money',['value'=>$report['summary']['remaining_budget']])</strong><span>{{ $report['summary']['over_budget_items'] }} @lang('construction::lang.over_budget_items')</span></article>
            <article class="{{ $report['summary']['sale_less_actual'] < 0 ? 'is-loss' : 'is-profit' }}"><small>@lang('construction::lang.sale_value_less_actual_cost')</small><strong>@include('construction::partials.money',['value'=>$report['summary']['sale_less_actual']])</strong><span>@lang('construction::lang.not_final_profit_notice_short')</span></article>
        </div>

        @if($report['unlinked']['total'] != 0)
        <section class="ct-unlinked-costs"><header><i class="fa fa-unlink"></i><div><strong>@lang('construction::lang.unlinked_costs')</strong><small>@lang('construction::lang.unlinked_costs_hint')</small></div><b>@include('construction::partials.money',['value'=>$report['unlinked']['total']])</b></header><div>@foreach(['materials','labor','subcontracts','expenses'] as $source)<span>@lang('construction::lang.cost_'.$source)<strong>@include('construction::partials.money',['value'=>$report['unlinked'][$source]])</strong></span>@endforeach</div></section>
        @endif

        <section class="ct-financial-table-panel ct-cost-profit-table-panel"><header><div><span>@lang('construction::lang.item_level_analysis')</span><h2>@lang('construction::lang.estimated_actual_cost_by_item')</h2></div><div class="ct-comparison-legend"><span class="is-healthy">@lang('construction::lang.within_budget')</span><span class="is-low">@lang('construction::lang.near_budget_limit')</span><span class="is-loss">@lang('construction::lang.over_budget')</span></div></header><div class="table-responsive"><table class="table ct-financial-table ct-cost-profit-table"><thead><tr><th>@lang('construction::lang.boq_item')</th><th>@lang('construction::lang.sales_value')</th><th>@lang('construction::lang.estimated_cost')</th><th>@lang('construction::lang.cost_materials')</th><th>@lang('construction::lang.cost_labor')</th><th>@lang('construction::lang.cost_subcontracts')</th><th>@lang('construction::lang.cost_expenses')</th><th>@lang('construction::lang.total_actual_cost')</th><th>@lang('construction::lang.budget_variance')</th><th>@lang('construction::lang.sale_less_actual')</th></tr></thead><tbody>@forelse($report['rows'] as $row)<tr><td><strong dir="ltr">{{ $row['code'] }}</strong><span>{{ $row['description'] }}</span></td><td>@include('construction::partials.money',['value'=>$row['sales']])</td><td>@include('construction::partials.money',['value'=>$row['estimated']['total']])</td><td>@include('construction::partials.money',['value'=>$row['actual']['materials']])</td><td>@include('construction::partials.money',['value'=>$row['actual']['labor']])</td><td>@include('construction::partials.money',['value'=>$row['actual']['subcontracts']])</td><td>@include('construction::partials.money',['value'=>$row['actual']['expenses']])</td><td><strong>@include('construction::partials.money',['value'=>$row['actual']['total']])</strong><small>{{ @num_format($row['usage_percent']) }}%</small></td><td><span class="ct-budget-pill is-{{ $row['status'] }}">@include('construction::partials.money',['value'=>$row['variance']])</span></td><td><strong>@include('construction::partials.money',['value'=>$row['sale_less_actual']])</strong></td></tr>@empty<tr><td colspan="10" class="text-center text-muted">@lang('construction::lang.no_comparison_items')</td></tr>@endforelse</tbody><tfoot><tr><th>@lang('construction::lang.total')</th><th>@include('construction::partials.money',['value'=>$report['summary']['sales']])</th><th>@include('construction::partials.money',['value'=>$report['summary']['estimated']])</th><th colspan="4">@lang('construction::lang.linked_actual_cost'): @include('construction::partials.money',['value'=>$report['summary']['linked_actual']])</th><th>@include('construction::partials.money',['value'=>$report['summary']['actual']])</th><th>@include('construction::partials.money',['value'=>$report['summary']['remaining_budget']])</th><th>@include('construction::partials.money',['value'=>$report['summary']['sale_less_actual']])</th></tr></tfoot></table></div><footer class="ct-report-note"><i class="fa fa-info-circle"></i> @lang('construction::lang.cost_profitability_not_final_profit_notice')</footer></section>
    @endif
</section>
@endsection

@push('ct_quick_css')
<style>.ct-financial-report-filter.ct-cost-profit-filter{grid-template-columns:2fr 1fr auto}@media(max-width:700px){.ct-financial-report-filter.ct-cost-profit-filter{grid-template-columns:1fr}}</style>
@endpush

@extends('construction::layouts.module')

@section('title', __('construction::lang.boq_subcontract_comparison'))

@section('module_content')
<section class="content ct-financial-report-page ct-comparison-page">
    <nav class="ct-report-switcher" aria-label="@lang('construction::lang.construction_reports')">
        <a href="{{ route('construction.reports.index') }}"><i class="fa fa-chart-line"></i> @lang('construction::lang.financial_position_report')</a>
        <a href="{{ route('construction.reports.subcontractor-statement.index') }}"><i class="fa fa-file-invoice-dollar"></i> @lang('construction::lang.subcontractor_statement')</a>
        <a class="is-active" href="{{ route('construction.reports.boq-subcontract-comparison.index') }}"><i class="fa fa-balance-scale"></i> @lang('construction::lang.boq_subcontract_comparison')</a>
        <a href="{{ route('construction.reports.boq-cost-profitability.index') }}"><i class="fa fa-coins"></i> @lang('construction::lang.boq_cost_profitability')</a>
        <a href="{{ route('construction.reports.customer-certificates-collection.index') }}"><i class="fa fa-hand-holding-usd"></i> @lang('construction::lang.customer_collection_report')</a>
        <a href="{{ route('construction.reports.retention-guarantees.index') }}"><i class="fa fa-shield-alt"></i> @lang('construction::lang.retention_guarantees_report')</a>
    </nav>

    <header class="ct-financial-report-heading ct-comparison-heading">
        <div><span><i class="fa fa-search-dollar"></i> @lang('construction::lang.owner_profitability_report')</span><h1>@lang('construction::lang.boq_subcontract_comparison')</h1><p>@lang('construction::lang.boq_subcontract_comparison_description')</p></div>
        @if($report)<a class="btn ct-primary-action" target="_blank" href="{{ route('construction.reports.boq-subcontract-comparison.print',['project_id'=>$selectedProject->id,'to_date'=>$toDate]) }}"><i class="fa fa-print"></i> @lang('construction::lang.print_comparison_report')</a>@endif
    </header>

    <form method="GET" action="{{ route('construction.reports.boq-subcontract-comparison.index') }}" class="ct-financial-report-filter ct-comparison-filter">
        <div class="form-group"><label>@lang('construction::lang.project') *</label><select name="project_id" class="form-control select2" required><option value="">@lang('construction::lang.select_project')</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($selectedProject?->id === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach</select></div>
        <div class="form-group"><label>@lang('construction::lang.as_of_date')</label><input name="to_date" value="{{ @format_date($toDate) }}" class="form-control ct-date-picker" readonly></div>
        <button class="btn btn-primary"><i class="fa fa-chart-bar"></i> @lang('construction::lang.show_report')</button>
    </form>

    @if(!$report)
        <div class="ct-financial-report-empty"><span><i class="fa fa-balance-scale"></i></span><h2>@lang('construction::lang.comparison_select_project')</h2><p>@lang('construction::lang.comparison_select_project_hint')</p></div>
    @else
        <div class="ct-financial-project-strip"><div><small>@lang('construction::lang.project')</small><strong>{{ $selectedProject->code }} — {{ $selectedProject->name }}</strong></div><div><small>@lang('construction::lang.customer')</small><strong>{{ $selectedProject->customer?->name ?: '—' }}</strong></div><div><small>@lang('construction::lang.as_of_date')</small><strong>{{ @format_date($toDate) }}</strong></div></div>
        @if($report['warnings']->isNotEmpty())<div class="ct-financial-warnings">@foreach($report['warnings'] as $warning)<div><i class="fa fa-exclamation-triangle"></i><span>{{ $warning }}</span></div>@endforeach</div>@endif

        <div class="ct-comparison-summary">
            <section><header><span>@lang('construction::lang.contractual_expected')</span><strong>{{ @num_format($report['contract']['margin_percent']) }}%</strong></header><div><article><small>@lang('construction::lang.customer_sale_value')</small><strong>@include('construction::partials.money',['value'=>$report['contract']['sales']])</strong></article><article><small>@lang('construction::lang.subcontract_assignment_value')</small><strong>@include('construction::partials.money',['value'=>$report['contract']['assignment']])</strong></article><article class="{{ $report['contract']['margin'] < 0 ? 'is-loss' : 'is-profit' }}"><small>@lang('construction::lang.expected_margin')</small><strong>@include('construction::partials.money',['value'=>$report['contract']['margin']])</strong></article></div><footer>{{ $report['contract']['loss_items'] }} @lang('construction::lang.loss_making_items')</footer></section>
            <section><header><span>@lang('construction::lang.actual_to_date')</span><strong>{{ @num_format($report['actual']['margin_percent']) }}%</strong></header><div><article><small>@lang('construction::lang.customer_certified_value')</small><strong>@include('construction::partials.money',['value'=>$report['actual']['sales']])</strong></article><article><small>@lang('construction::lang.subcontract_certified_value')</small><strong>@include('construction::partials.money',['value'=>$report['actual']['assignment']])</strong></article><article class="{{ $report['actual']['margin'] < 0 ? 'is-loss' : 'is-profit' }}"><small>@lang('construction::lang.actual_margin')</small><strong>@include('construction::partials.money',['value'=>$report['actual']['margin']])</strong></article></div><footer>{{ $report['actual']['loss_items'] }} @lang('construction::lang.loss_making_items')</footer></section>
        </div>

        <section class="ct-financial-table-panel ct-comparison-table-panel"><header><div><span>@lang('construction::lang.item_level_analysis')</span><h2>@lang('construction::lang.sale_assignment_margin_by_item')</h2></div><div class="ct-comparison-legend"><span class="is-healthy">@lang('construction::lang.margin_healthy')</span><span class="is-low">@lang('construction::lang.margin_low')</span><span class="is-loss">@lang('construction::lang.margin_loss')</span></div></header><div class="table-responsive"><table class="table ct-financial-table ct-comparison-table"><thead><tr><th rowspan="2">@lang('construction::lang.boq_item')</th><th rowspan="2">@lang('construction::lang.subcontractor')</th><th colspan="3">@lang('construction::lang.contractual_expected')</th><th colspan="3">@lang('construction::lang.actual_to_date')</th></tr><tr><th>@lang('construction::lang.customer_sale')</th><th>@lang('construction::lang.subcontract_assignment')</th><th>@lang('construction::lang.margin')</th><th>@lang('construction::lang.customer_certified')</th><th>@lang('construction::lang.subcontract_certified')</th><th>@lang('construction::lang.margin')</th></tr></thead><tbody>@forelse($report['rows'] as $row)<tr><td><strong dir="ltr">{{ $row['code'] }}</strong><span>{{ $row['description'] }}</span><small>{{ @num_format($row['contract_quantity']) }} {{ $row['unit'] }}</small></td><td>{{ $row['subcontractors'] }}</td><td>@include('construction::partials.money',['value'=>$row['contract']['sales']])</td><td>@include('construction::partials.money',['value'=>$row['contract']['assignment']])</td><td><span class="ct-margin-pill is-{{ $row['contract']['status'] }}">@include('construction::partials.money',['value'=>$row['contract']['margin']])<small>{{ @num_format($row['contract']['margin_percent']) }}%</small></span></td><td>@include('construction::partials.money',['value'=>$row['actual']['sales']])</td><td>@include('construction::partials.money',['value'=>$row['actual']['assignment']])</td><td><span class="ct-margin-pill is-{{ $row['actual']['status'] }}">@include('construction::partials.money',['value'=>$row['actual']['margin']])<small>{{ @num_format($row['actual']['margin_percent']) }}%</small></span></td></tr>@empty<tr><td colspan="8" class="text-center text-muted">@lang('construction::lang.no_comparison_items')</td></tr>@endforelse</tbody><tfoot><tr><th colspan="2">@lang('construction::lang.total')</th><th>@include('construction::partials.money',['value'=>$report['contract']['sales']])</th><th>@include('construction::partials.money',['value'=>$report['contract']['assignment']])</th><th>@include('construction::partials.money',['value'=>$report['contract']['margin']])</th><th>@include('construction::partials.money',['value'=>$report['actual']['sales']])</th><th>@include('construction::partials.money',['value'=>$report['actual']['assignment']])</th><th>@include('construction::partials.money',['value'=>$report['actual']['margin']])</th></tr></tfoot></table></div></section>
    @endif
</section>
@endsection

@push('ct_quick_css')
<style>
.ct-financial-report-filter.ct-comparison-filter{grid-template-columns:2fr 1fr auto}
@media(max-width:700px){.ct-financial-report-filter.ct-comparison-filter{grid-template-columns:1fr}}
</style>
@endpush

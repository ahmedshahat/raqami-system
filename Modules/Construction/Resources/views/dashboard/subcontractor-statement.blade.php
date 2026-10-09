@extends('construction::layouts.module')

@section('title', __('construction::lang.subcontractor_statement'))

@section('module_content')
<section class="content ct-financial-report-page ct-statement-page">
    <nav class="ct-report-switcher" aria-label="@lang('construction::lang.construction_reports')">
        <a href="{{ route('construction.reports.index') }}"><i class="fa fa-chart-line"></i> @lang('construction::lang.financial_position_report')</a>
        <a class="is-active" href="{{ route('construction.reports.subcontractor-statement.index') }}"><i class="fa fa-file-invoice-dollar"></i> @lang('construction::lang.subcontractor_statement')</a>
        <a href="{{ route('construction.reports.boq-subcontract-comparison.index') }}"><i class="fa fa-balance-scale"></i> @lang('construction::lang.boq_subcontract_comparison')</a>
        <a href="{{ route('construction.reports.boq-cost-profitability.index') }}"><i class="fa fa-coins"></i> @lang('construction::lang.boq_cost_profitability')</a>
        <a href="{{ route('construction.reports.customer-certificates-collection.index') }}"><i class="fa fa-hand-holding-usd"></i> @lang('construction::lang.customer_collection_report')</a>
        <a href="{{ route('construction.reports.retention-guarantees.index') }}"><i class="fa fa-shield-alt"></i> @lang('construction::lang.retention_guarantees_report')</a>
    </nav>

    <header class="ct-financial-report-heading">
        <div><span><i class="fa fa-calculator"></i> @lang('construction::lang.accountant_report')</span><h1>@lang('construction::lang.subcontractor_statement')</h1><p>@lang('construction::lang.subcontractor_statement_description')</p></div>
        @if($statement)<a class="btn ct-primary-action" target="_blank" href="{{ route('construction.reports.subcontractor-statement.print', array_filter(['subcontractor_id'=>$selectedSubcontractor->id,'project_id'=>$projectId,'date_range'=>$dateRange,'from_date'=>$fromDate,'to_date'=>$toDate])) }}"><i class="fa fa-print"></i> @lang('construction::lang.print_statement')</a>@endif
    </header>

    <form method="GET" action="{{ route('construction.reports.subcontractor-statement.index') }}" class="ct-financial-report-filter ct-statement-filter ct-date-range-filter {{ $dateRange === 'custom' ? 'is-custom-range' : 'is-preset-range' }}">
        <div class="form-group"><label>@lang('construction::lang.subcontractor') *</label><select name="subcontractor_id" class="form-control select2" required><option value="">@lang('construction::lang.select_subcontractor')</option>@foreach($subcontractors as $subcontractor)<option value="{{ $subcontractor->id }}" @selected($selectedSubcontractor?->id === $subcontractor->id)>{{ $subcontractor->supplier_business_name ?: $subcontractor->name }}</option>@endforeach</select></div>
        <div class="form-group"><label>@lang('construction::lang.project')</label><select name="project_id" class="form-control select2"><option value="">@lang('construction::lang.statement_all_projects')</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach</select></div>
        @include('construction::dashboard.partials.date-range-filter')
        <button class="btn btn-primary"><i class="fa fa-search"></i> @lang('construction::lang.show_statement')</button>
    </form>

    @if(!$statement)
        <div class="ct-financial-report-empty"><span><i class="fa fa-file-invoice-dollar"></i></span><h2>@lang('construction::lang.statement_select_subcontractor')</h2><p>@lang('construction::lang.statement_select_subcontractor_hint')</p></div>
    @else
        <div class="ct-financial-project-strip"><div><small>@lang('construction::lang.subcontractor')</small><strong>{{ $selectedSubcontractor->supplier_business_name ?: $selectedSubcontractor->name }}</strong></div><div><small>@lang('construction::lang.project')</small><strong>{{ $selectedProject ? $selectedProject->code.' — '.$selectedProject->name : __('construction::lang.statement_all_projects') }}</strong></div><div><small>@lang('construction::lang.report_period')</small><strong>{{ $fromDate ? @format_date($fromDate) : __('construction::lang.account_inception') }} — {{ @format_date($toDate) }}</strong></div></div>

        <div class="ct-financial-kpis ct-statement-kpis">
            <article><span class="is-blue"><i class="fa fa-file-signature"></i></span><small>@lang('construction::lang.contracts_value')</small><strong>@include('construction::partials.money',['value'=>$statement['summary']['contracts_value']])</strong><p>{{ $statement['agreements_count'] }} @lang('construction::lang.agreements')</p></article>
            <article><span class="is-green"><i class="fa fa-check-double"></i></span><small>@lang('construction::lang.total_payable')</small><strong>@include('construction::partials.money',['value'=>$statement['summary']['payable']])</strong><p>{{ $statement['summary']['certificates_count'] }} @lang('construction::lang.approved_certificates')</p></article>
            <article><span class="is-amber"><i class="fa fa-hand-holding-usd"></i></span><small>@lang('construction::lang.paid_amount')</small><strong>@include('construction::partials.money',['value'=>$statement['summary']['paid']])</strong><p>@lang('construction::lang.recorded_payments_only')</p></article>
            <article class="{{ $statement['summary']['balance'] > 0 ? 'is-negative' : 'is-positive' }}"><span><i class="fa fa-wallet"></i></span><small>@lang('construction::lang.current_balance')</small><strong>@include('construction::partials.money',['value'=>$statement['summary']['balance']])</strong><p>@lang('construction::lang.due_to_subcontractor')</p></article>
        </div>

        <section class="ct-financial-panel ct-statement-summary"><header><div><span>@lang('construction::lang.account_summary')</span><h2>@lang('construction::lang.certificate_retention_payment_summary')</h2></div></header><div class="ct-statement-summary-grid">
            @foreach(['gross'=>'approved_subcontract_work','deductions'=>'other_deductions','retention_original'=>'original_retention','retention_released'=>'released_retention','retention_remaining'=>'remaining_retention','paid'=>'paid_amount'] as $key=>$label)<div><span>@lang('construction::lang.'.$label)</span><strong>@include('construction::partials.money',['value'=>$statement['summary'][$key]])</strong></div>@endforeach
        </div></section>

        <section class="ct-financial-table-panel ct-statement-ledger"><header><div><span>@lang('construction::lang.accountant_details')</span><h2>@lang('construction::lang.statement_movements')</h2></div><div class="ct-statement-totals"><span>@lang('construction::lang.opening_balance'): <strong>@include('construction::partials.money',['value'=>$statement['summary']['opening_balance']])</strong></span><span>@lang('construction::lang.closing_balance'): <strong>@include('construction::partials.money',['value'=>$statement['summary']['closing_balance']])</strong></span></div></header><div class="table-responsive"><table class="table ct-financial-table"><thead><tr><th>@lang('construction::lang.date')</th><th>@lang('construction::lang.movement_type')</th><th>@lang('construction::lang.document_number')</th><th>@lang('construction::lang.project')</th><th>@lang('construction::lang.description')</th><th>@lang('construction::lang.debit_paid')</th><th>@lang('construction::lang.credit_due')</th><th>@lang('construction::lang.running_balance')</th></tr></thead><tbody>
            @if($fromDate)<tr class="ct-opening-row"><td>{{ @format_date($fromDate) }}</td><td colspan="6">@lang('construction::lang.opening_balance')</td><td><strong>@include('construction::partials.money',['value'=>$statement['summary']['opening_balance']])</strong></td></tr>@endif
            @forelse($statement['movements'] as $movement)<tr><td>{{ @format_date($movement['date']) }}</td><td><span class="ct-movement-badge is-{{ $movement['type'] }}">@lang('construction::lang.statement_type_'.$movement['type'])</span></td><td dir="ltr">{{ $movement['number'] }}</td><td>{{ $movement['project'] ?: '—' }}</td><td><strong>{{ $movement['description'] }}</strong><small>{{ $movement['agreement'] }}</small></td><td>@include('construction::partials.money',['value'=>$movement['debit']])</td><td>@include('construction::partials.money',['value'=>$movement['credit']])</td><td><strong>@include('construction::partials.money',['value'=>$movement['balance']])</strong></td></tr>@empty<tr><td colspan="8" class="text-center text-muted">@lang('construction::lang.no_statement_movements')</td></tr>@endforelse
        </tbody><tfoot><tr><th colspan="5">@lang('construction::lang.period_totals')</th><th>@include('construction::partials.money',['value'=>$statement['summary']['period_debit']])</th><th>@include('construction::partials.money',['value'=>$statement['summary']['period_credit']])</th><th>@include('construction::partials.money',['value'=>$statement['summary']['closing_balance']])</th></tr></tfoot></table></div></section>
    @endif
</section>
@endsection

@push('ct_quick_css')
<style>
.ct-financial-report-filter.ct-statement-filter{grid-template-columns:1.4fr 1.4fr .8fr .8fr auto}
@media(max-width:1100px){.ct-financial-report-filter.ct-statement-filter{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.ct-financial-report-filter.ct-statement-filter{grid-template-columns:1fr}}
</style>
@endpush

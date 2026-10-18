@extends('construction::layouts.module')

@section('title', __('construction::lang.accounting_reconciliation'))

@section('module_content')
<section class="content ct-accounting-reconciliation-page">
    <header class="ct-financial-report-heading">
        <div><h1>@lang('construction::lang.accounting_reconciliation')</h1><p>@lang('construction::lang.accounting_reconciliation_description')</p></div>
        <a href="{{ route('construction.settings.index') }}" class="btn btn-default"><i class="fa fa-arrow-right"></i> @lang('construction::lang.back_to_settings')</a>
    </header>

    <div class="ct-cost-cards">
        <article class="ct-cost-card ct-cost-card--total"><div><small>@lang('construction::lang.documents_checked')</small><strong>{{ $report['checked'] }}</strong></div></article>
        <article class="ct-cost-card ct-cost-card--materials"><div><small>@lang('construction::lang.matched_documents')</small><strong>{{ $report['healthy'] }}</strong></div></article>
        <article class="ct-cost-card ct-cost-card--expenses"><div><small>@lang('construction::lang.reconciliation_issues')</small><strong>{{ $report['issues']->count() }}</strong></div></article>
    </div>

    <section class="ct-cost-panel">
        <div class="ct-cost-panel__heading"><div><h2>@lang('construction::lang.reconciliation_results')</h2><p>@lang('construction::lang.reconciliation_results_hint')</p></div></div>
        <div class="table-responsive">
            <table class="table table-hover ct-cost-table">
                <thead><tr><th>@lang('construction::lang.document_type')</th><th>@lang('construction::lang.document_number')</th><th>@lang('construction::lang.project')</th><th>@lang('construction::lang.issue')</th><th>@lang('construction::lang.expected_amount')</th><th>@lang('construction::lang.debit')</th><th>@lang('construction::lang.credit')</th><th>@lang('construction::lang.journal_entry')</th></tr></thead>
                <tbody>
                @forelse($report['issues'] as $issue)
                    <tr>
                        <td>@lang('construction::lang.reconcile_type_'.$issue['source_type'])</td>
                        <td><strong>{{ $issue['number'] }}</strong></td><td>{{ $issue['project'] ?: '—' }}</td>
                        <td><span class="label label-danger">@lang('construction::lang.reconcile_'.$issue['status'])</span></td>
                        <td>@include('construction::partials.money', ['value' => $issue['expected']])</td>
                        <td>@include('construction::partials.money', ['value' => $issue['debit']])</td>
                        <td>@include('construction::partials.money', ['value' => $issue['credit']])</td>
                        <td>@if($issue['mapping_id'])<button type="button" class="btn btn-xs btn-default btn-modal" data-href="{{ route('journal-entry.show', $issue['mapping_id']) }}" data-container=".view_modal">@lang('construction::lang.view_entry')</button>@else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-success"><i class="fa fa-check-circle"></i> @lang('construction::lang.no_reconciliation_issues')</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection

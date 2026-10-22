@extends('construction::layouts.module')

@section('title', __('construction::lang.accounting_reconciliation'))

@php
    $amount = fn ($value) => \Modules\Construction\Support\CurrencyFormatter::parts($value)['amount'];
    $issuesCount = $report['issues']->count();
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-reconciliation-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg {{ $issuesCount ? 'rr-chip--warning' : 'rr-chip--success' }}" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.settings.index') }}">@lang('construction::lang.settings')</a></li>
                    <li><span aria-current="page">@lang('construction::lang.accounting_reconciliation')</span></li>
                </ol>
            </nav>
            <h1 id="rr-reconciliation-title" class="rr-head__title">@lang('construction::lang.accounting_reconciliation')</h1>
            <p class="rr-head__sub">@lang('construction::lang.accounting_reconciliation_description')</p>
        </div>
        <div class="rr-head__actions">
            <a href="{{ route('construction.settings.accounting.index') }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'list'])@lang('construction::lang.accounting_link_settings')</a>
        </div>
    </header>

    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--3" style="--rr-stats: 3">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.documents_checked')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $report['checked'] }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.matched_documents')</div>
                <div class="rr-stat__value"><span class="rr-num rr-text--success">{{ $report['healthy'] }}</span></div>
                @if($report['checked'] > 0)
                    <div class="rr-progress"><div class="rr-progress__fill rr-progress__fill--success" style="width: {{ round($report['healthy'] / $report['checked'] * 100) }}%"></div></div>
                @endif
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 18])@lang('construction::lang.reconciliation_issues')</div>
                <div class="rr-stat__value"><span class="rr-num {{ $issuesCount ? 'rr-text--danger' : 'rr-text--success' }}">{{ $issuesCount }}</span></div>
            </div>
        </div>
    </div>

    <section class="rr-table-wrap" aria-labelledby="rr-reconciliation-results-title">
        <div class="rr-table-head">
            <div>
                <h2 id="rr-reconciliation-results-title" class="rr-table-head__title">@lang('construction::lang.reconciliation_results')</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.reconciliation_results_hint')</p>
            </div>
        </div>
        @if($issuesCount === 0)
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_reconciliation_issues')</h3>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.reconciliation_results')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.document_type')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.document_number')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.issue')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.expected_amount')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.debit')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.credit')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.journal_entry')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($report['issues'] as $issue)
                            <tr class="rr-tr">
                                <td class="rr-td">@lang('construction::lang.reconcile_type_' . $issue['source_type'])</td>
                                <td class="rr-td"><span class="rr-code">{{ $issue['number'] }}</span></td>
                                <td class="rr-td rr-td--wrap rr-td--muted">{{ $issue['project'] ?: '—' }}</td>
                                <td class="rr-td"><span class="rr-badge rr-badge--danger"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.reconcile_' . $issue['status'])</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($issue['expected']) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($issue['debit']) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($issue['credit']) }}</span></td>
                                <td class="rr-td rr-td--actions">
                                    @if($issue['mapping_id'])
                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm btn-modal" data-href="{{ route('journal-entry.show', $issue['mapping_id']) }}" data-container=".view_modal">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 16])@lang('construction::lang.view_entry')</button>
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
</main>
@endsection

@extends('construction::layouts.module')
@section('title', __('construction::lang.tab_contracts'))

@section('module_content')
<section class="content ct-portfolio-page">
    <header class="ct-list-hero ct-list-hero--contracts">
        <div class="ct-list-hero__copy"><span class="ct-list-hero__icon" aria-hidden="true"><i class="fas fa-file-signature"></i></span><div><h1>@lang('construction::lang.contracts_and_terms')</h1><p>@lang('construction::lang.contracts_tab_description')</p></div></div>
        <div class="ct-list-hero__actions"><a href="{{ route('construction.projects.index') }}" class="ct-list-btn ct-list-btn--secondary"><i class="fa fa-building" aria-hidden="true"></i> @lang('construction::lang.all_projects')</a></div>
    </header>

    @if($contractSummary)
    <div class="ct-contract-metrics">
        <article><span class="is-blue"><i class="fa fa-file-signature" aria-hidden="true"></i></span><div><small>@lang('construction::lang.contracts_count')</small><strong>{{ $contractSummary->contracts_count }}</strong><em>{{ $contractSummary->active_contracts }} @lang('construction::lang.active')</em></div></article>
        <article><span class="is-green"><i class="fa fa-coins" aria-hidden="true"></i></span><div><small>@lang('construction::lang.total_contract_value')</small><strong>@include('construction::partials.money', ['value' => $contractSummary->total_value])</strong></div></article>
        <article><span class="is-amber"><i class="fa fa-hand-holding-usd" aria-hidden="true"></i></span><div><small>@lang('construction::lang.total_advance_payments')</small><strong>@include('construction::partials.money', ['value' => $contractSummary->advance_value])</strong></div></article>
        <article><span class="is-violet"><i class="fa fa-shield-alt" aria-hidden="true"></i></span><div><small>@lang('construction::lang.performance_bonds')</small><strong>@include('construction::partials.money', ['value' => $contractSummary->bond_value])</strong></div></article>
    </div>
    @endif

    <article class="ct-list-panel">
        <header class="ct-list-panel__header"><div><h2>@lang('construction::lang.contract_register')</h2><p>@lang('construction::lang.contract_register_hint')</p></div><span class="ct-result-count"><strong>{{ $contracts->total() }}</strong> @lang('construction::lang.contracts_count_suffix')</span></header>
        <form method="GET" class="ct-list-filters ct-contract-filters" role="search">
            <div class="ct-list-search"><label for="ct-contract-search">@lang('construction::lang.search_contracts')</label><span aria-hidden="true"><i class="fa fa-search"></i></span><input id="ct-contract-search" type="search" name="q" value="{{ $search }}" class="form-control" placeholder="@lang('construction::lang.search_contracts')"></div>
            <div class="ct-list-select"><label for="ct-contract-status-filter">@lang('construction::lang.status')</label><select id="ct-contract-status-filter" name="status" class="form-control"><option value="">@lang('construction::lang.all_statuses')</option><option value="draft" @selected($status === 'draft')>@lang('construction::lang.draft')</option><option value="active" @selected($status === 'active')>@lang('construction::lang.active')</option></select></div>
            <button class="ct-list-btn ct-list-btn--primary ct-filter-submit" type="submit"><i class="fa fa-filter" aria-hidden="true"></i> @lang('construction::lang.apply_filters')</button>
            @if($search !== '' || $status !== '')<a class="ct-clear-filter" href="{{ route('construction.contracts.index') }}"><i class="fa fa-times" aria-hidden="true"></i> @lang('construction::lang.clear_filter')</a>@endif
        </form>
        <div class="ct-responsive-table"><table class="ct-entity-table ct-contract-entity-table">
            <thead><tr><th>@lang('construction::lang.project')</th><th>@lang('construction::lang.contract_number')</th><th>@lang('construction::lang.contract_type')</th><th>@lang('construction::lang.original_value')</th><th>@lang('construction::lang.retention_percent')</th><th>@lang('construction::lang.status')</th><th class="ct-entity-table__actions">@lang('construction::lang.actions')</th></tr></thead>
            <tbody>
                @forelse($contracts as $contract)
                <tr>
                    <td data-label="@lang('construction::lang.project')"><a class="ct-entity-title" href="{{ route('construction.projects.show', $contract->project_id) }}"><span aria-hidden="true"><i class="fa fa-building"></i></span><span><strong>{{ $contract->project?->name }}</strong><small>{{ $contract->project?->code }}</small></span></a></td>
                    <td data-label="@lang('construction::lang.contract_number')">@can('construction.contract.view')<a class="ct-contract-number" href="{{ route('construction.projects.contracts.show', [$contract->project_id, $contract->id]) }}">{{ $contract->contract_number ?: '—' }}</a>@else{{ $contract->contract_number ?: '—' }}@endcan</td>
                    <td data-label="@lang('construction::lang.contract_type')">@lang('construction::lang.' . $contract->contract_type)</td>
                    <td data-label="@lang('construction::lang.original_value')"><strong class="ct-money-value">@include('construction::partials.money', ['value' => $contract->original_value])</strong></td>
                    <td data-label="@lang('construction::lang.retention_percent')"><span class="ct-percentage-value">{{ @num_format((float) $contract->retention_percent) }}%</span></td>
                    <td data-label="@lang('construction::lang.status')"><span class="ct-status ct-status--{{ $contract->status }}"><i class="fa fa-circle" aria-hidden="true"></i> @lang('construction::lang.' . $contract->status)</span></td>
                    <td data-label="@lang('construction::lang.actions')" class="ct-entity-table__actions"><div class="ct-contract-row-actions"><button type="button" class="ct-contract-menu-toggle" aria-label="@lang('construction::lang.actions')" aria-haspopup="true" aria-expanded="false" aria-controls="ct-contract-menu-{{ $contract->id }}"><i class="fa fa-ellipsis-v" aria-hidden="true"></i><span>@lang('construction::lang.actions')</span><i class="fa fa-chevron-down ct-caret-icon" aria-hidden="true"></i></button><div class="ct-contract-menu" id="ct-contract-menu-{{ $contract->id }}" role="menu">
                        @can('construction.contract.view')<a role="menuitem" href="{{ route('construction.projects.contracts.show', [$contract->project_id, $contract->id]) }}"><i class="fa fa-folder-open" aria-hidden="true"></i> @lang('construction::lang.view_contract')</a>@endcan
                        <a role="menuitem" href="{{ route('construction.projects.contracts.preview', [$contract->project_id, $contract->id]) }}" target="_blank" rel="noopener"><i class="fa fa-eye" aria-hidden="true"></i> @lang('construction::lang.print_preview')</a>
                        <a role="menuitem" href="{{ route('construction.projects.contracts.print', [$contract->project_id, $contract->id]) }}" target="_blank" rel="noopener"><i class="fa fa-print" aria-hidden="true"></i> @lang('construction::lang.print')</a>
                        <a role="menuitem" href="{{ route('construction.projects.contracts.pdf', [$contract->project_id, $contract->id]) }}"><i class="fa fa-file-pdf" aria-hidden="true"></i> PDF</a>
                        @if($contract->status === 'draft')
                            @can('construction.contract.manage')<a role="menuitem" href="{{ route('construction.projects.contracts.edit', [$contract->project_id, $contract->id]) }}"><i class="fa fa-edit" aria-hidden="true"></i> @lang('construction::lang.edit')</a>@endcan
                            @can('construction.contract.approve')<form method="POST" action="{{ route('construction.projects.contracts.activate', [$contract->project_id, $contract->id]) }}" onsubmit="return confirm('{{ __('construction::lang.activate_contract_confirmation') }}')">@csrf<button type="submit" role="menuitem" class="ct-contract-menu-approve"><i class="fa fa-check-circle" aria-hidden="true"></i> @lang('construction::lang.next_approve_contract')</button></form>@endcan
                        @endif
                        @if($contract->status === 'active')@can('construction.contract.manage')<a role="menuitem" href="{{ route('construction.projects.contracts.adjustments.index', [$contract->project_id, $contract->id]) }}"><i class="fa fa-plus-circle" aria-hidden="true"></i> @lang('construction::lang.contract_adjustments_short')</a>@endcan @endif
                    </div></div></td>
                </tr>
                @empty<tr><td colspan="7"><div class="ct-list-empty"><span><i class="fa fa-file-signature" aria-hidden="true"></i></span><h3>@lang('construction::lang.no_contracts')</h3><p>@lang('construction::lang.no_contracts_hint')</p><a href="{{ route('construction.projects.index') }}" class="ct-list-btn ct-list-btn--primary">@lang('construction::lang.all_projects')</a></div></td></tr>@endforelse
            </tbody>
        </table></div>
        @if($contracts->hasPages())<footer class="ct-list-pagination">{{ $contracts->links() }}</footer>@endif
    </article>
</section>
@endsection

@section('module_javascript')<script src="{{ asset('modules/construction/js/dashboard.js?v=' . config('construction.module_version')) }}"></script>@endsection

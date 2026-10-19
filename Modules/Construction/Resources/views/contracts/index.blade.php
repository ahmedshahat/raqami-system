@extends('construction::layouts.module')
@section('title', __('construction::lang.tab_contracts'))

@php
    $contractTones = ['draft' => 'warning', 'active' => 'success', 'completed' => 'info', 'cancelled' => 'neutral'];
    $isFiltered = $search !== '' || $status !== '';
    $currencySymbol = \Modules\Construction\Support\CurrencyFormatter::parts(0)['symbol'];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-contracts-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 24])</span>
        <div class="rr-head__titles">
            <h1 id="rr-contracts-title" class="rr-head__title">@lang('construction::lang.contracts_and_terms')</h1>
            <p class="rr-head__sub">@lang('construction::lang.contracts_tab_description')</p>
        </div>
        <div class="rr-head__actions">
            <a href="{{ route('construction.projects.index') }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'building'])@lang('construction::lang.all_projects')</a>
        </div>
    </header>

    @if($contractSummary)
        <div class="rr-statgroup-wrap">
            <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4">
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.contracts_count')</div>
                    <div class="rr-stat__value"><span class="rr-num">{{ (int) $contractSummary->contracts_count }}</span></div>
                    <div class="rr-stat__hint"><span class="rr-num">{{ (int) $contractSummary->active_contracts }}</span> @lang('construction::lang.active')</div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.total_contract_value')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $contractSummary->total_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contractSummary->total_value])</span></div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 18])@lang('construction::lang.total_advance_payments')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $contractSummary->advance_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contractSummary->advance_value])</span></div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 18])@lang('construction::lang.performance_bonds')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $contractSummary->bond_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contractSummary->bond_value])</span></div>
                </div>
            </div>
        </div>
    @endif

    <section class="rr-table-wrap" id="ct-quick-list" aria-labelledby="rr-contracts-list-title">
        <div class="rr-table-head rr-table-head--toolbar">
            <div class="rr-table-head__titles">
                <h2 id="rr-contracts-list-title" class="rr-table-head__title">@lang('construction::lang.contract_register')</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.contract_register_hint')</p>
            </div>
            <form method="GET" action="{{ route('construction.contracts.index') }}" class="rr-table-tools" role="search" data-rr-auto-filter>
                <div class="rr-control rr-control--sm rr-table-tools__search">
                    <span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'search', 'size' => 16])</span>
                    <label class="rr-sr" for="ct-contract-search">@lang('construction::lang.search_contracts')</label>
                    <input id="ct-contract-search" class="rr-control__input" type="search" name="q" value="{{ $search }}" placeholder="@lang('construction::lang.search_contracts')">
                </div>
                <div class="rr-control rr-control--sm rr-table-tools__select">
                    <label class="rr-sr" for="ct-contract-status-filter">@lang('construction::lang.status')</label>
                    <select id="ct-contract-status-filter" class="rr-control__input rr-control__select" name="status">
                        <option value="">@lang('construction::lang.all_statuses')</option>
                        <option value="draft" @selected($status === 'draft')>@lang('construction::lang.draft')</option>
                        <option value="active" @selected($status === 'active')>@lang('construction::lang.active')</option>
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
                <button class="rr-btn rr-btn--secondary rr-btn--sm ct-filter-submit" type="submit">@include('construction::partials.rr_icon', ['name' => 'filter', 'size' => 16])@lang('construction::lang.apply_filters')</button>
                @if($isFiltered)
                    <a class="rr-btn rr-btn--ghost rr-btn--sm" href="{{ route('construction.contracts.index') }}">@lang('construction::lang.clear_filter')</a>
                @endif
            </form>
        </div>

        @if($contracts->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => $isFiltered ? 'search' : 'file', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_contracts')</h3>
                <p class="rr-empty__text">@lang('construction::lang.no_contracts_hint')</p>
                @if($isFiltered)
                    <a href="{{ route('construction.contracts.index') }}" class="rr-btn rr-btn--secondary">@lang('construction::lang.clear_filter')</a>
                @else
                    <a href="{{ route('construction.projects.index') }}" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'building'])@lang('construction::lang.all_projects')</a>
                @endif
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table rr-table--wide">
                    <caption class="rr-sr">@lang('construction::lang.contract_register')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.contract_number')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.contract_type')</th>
                            <th class="rr-th rr-th--num" scope="col"><span class="rr-th__in">@lang('construction::lang.original_value')@if($currencySymbol !== '')<span class="rr-th__unit">({{ $currencySymbol }})</span>@endif</span></th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.retention_percent')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($contracts as $contract)
                            @php $canView = auth()->user()->can('construction.contract.view'); @endphp
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--wrap">
                                    <a class="rr-entity rr-entity--link" href="{{ route('construction.projects.show', $contract->project_id) }}">
                                        <span class="rr-entity__mark" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18])</span>
                                        <span class="rr-entity__copy">
                                            <span class="rr-entity__name">{{ $contract->project?->name }}</span>
                                            <span class="rr-code">{{ $contract->project?->code }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="rr-td">
                                    @if($canView)<a class="rr-code-link" href="{{ route('construction.projects.contracts.show', [$contract->project_id, $contract->id]) }}"><span class="rr-code">{{ $contract->contract_number ?: '—' }}</span></a>@else<span class="rr-code">{{ $contract->contract_number ?: '—' }}</span>@endif
                                </td>
                                <td class="rr-td rr-td--muted">@lang('construction::lang.' . $contract->contract_type)</td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ (float) $contract->original_value == 0 ? 'rr-money--zero' : '' }}"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($contract->original_value)['amount'] }}</span></span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format((float) $contract->retention_percent) }}%</span></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $contractTones[$contract->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $contract->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <div class="rr-row-actions">
                                        @if($canView)
                                            <a href="{{ route('construction.projects.contracts.show', [$contract->project_id, $contract->id]) }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 18])@lang('construction::lang.view_contract')</a>
                                        @endif
                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon ct-contract-menu-toggle" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="ct-contract-menu-{{ $contract->id }}" aria-label="@lang('construction::lang.actions'): {{ $contract->contract_number }}">@include('construction::partials.rr_icon', ['name' => 'more'])</button>
                                        <div class="rr-menu ct-contract-menu" id="ct-contract-menu-{{ $contract->id }}" role="menu">
                                            <a class="rr-menu__item" role="menuitem" href="{{ route('construction.projects.contracts.preview', [$contract->project_id, $contract->id]) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 18])@lang('construction::lang.print_preview')</a>
                                            <a class="rr-menu__item" role="menuitem" href="{{ route('construction.projects.contracts.print', [$contract->project_id, $contract->id]) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'print', 'size' => 18])@lang('construction::lang.print')</a>
                                            <a class="rr-menu__item" role="menuitem" href="{{ route('construction.projects.contracts.pdf', [$contract->project_id, $contract->id]) }}">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 18])PDF</a>
                                            @if($contract->status === 'draft')
                                                @can('construction.contract.manage')
                                                    <a class="rr-menu__item" role="menuitem" href="{{ route('construction.projects.contracts.edit', [$contract->project_id, $contract->id]) }}">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 18])@lang('construction::lang.edit')</a>
                                                @endcan
                                                @can('construction.contract.approve')
                                                    <div class="rr-menu__sep" role="separator"></div>
                                                    <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.contracts.activate', [$contract->project_id, $contract->id]) }}" onsubmit="return confirm(@js(__('construction::lang.activate_contract_confirmation')))">
                                                        @csrf
                                                        <button type="submit" role="menuitem" class="rr-menu__item rr-menu__item--success ct-contract-menu-approve">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.next_approve_contract')</button>
                                                    </form>
                                                @endcan
                                            @endif
                                            @if($contract->status === 'active')
                                                @can('construction.contract.manage')
                                                    <a class="rr-menu__item" role="menuitem" href="{{ route('construction.projects.contracts.adjustments.index', [$contract->project_id, $contract->id]) }}">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 18])@lang('construction::lang.contract_adjustments_short')</a>
                                                @endcan
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('construction::partials.rr_pager', ['paginator' => $contracts, 'itemLabel' => __('construction::lang.contracts_count_suffix')])
        @endif
    </section>
</main>
@include('construction::partials.rr_menu_script')
@endsection

@section('module_javascript')
<script>
$(function () {
    $('[data-rr-auto-filter] select[name="status"]').on('change', function () { this.form.submit(); });
});
</script>
@endsection

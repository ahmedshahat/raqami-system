@extends('construction::layouts.module')
@section('title', __('construction::lang.projects'))

@php
    $statusTones = ['active' => 'success', 'draft' => 'warning', 'on_hold' => 'danger', 'completed' => 'info', 'cancelled' => 'neutral'];
    $isFiltered = $search !== '' || $status !== '';
    $projectStats = [
        ['all', 'project_card_projects', 'building', ''],
        ['active', 'project_card_active', 'check-circle', 'active'],
        ['draft', 'project_card_draft', 'edit', 'draft'],
        ['completed', 'project_card_completed', 'check', 'completed'],
    ];
    $currencySymbol = \Modules\Construction\Support\CurrencyFormatter::parts(0)['symbol'];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-projects-title">

    {{-- رأس الصفحة --}}
    <header class="rr-head">
        <img class="rr-head__logo" src="{{ asset('img/logo-small.png') }}" width="56" height="56" alt="">
        <div class="rr-head__titles">
            <h1 id="rr-projects-title" class="rr-head__title">@lang('construction::lang.projects_management')</h1>
            <p class="rr-head__sub">@lang('construction::lang.projects_tab_description')</p>
        </div>
        @can('construction.project.create')
            <div class="rr-head__actions">
                <button type="button" class="rr-btn rr-btn--primary" data-toggle="modal" data-target="#ct-project-create">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_project')</button>
                <a href="{{ route('construction.quotes.index', ['open_create' => 1]) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.new_quote')</a>
            </div>
        @endcan
    </header>

    {{-- المؤشرات: على المحفظة كاملة، وكل مؤشر رابط تصفية --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4" id="ct-project-summary" aria-label="@lang('construction::lang.projects_by_status')">
            @foreach($projectStats as [$key, $label, $icon, $filterStatus])
                @php $isCurrent = $status === $filterStatus && ($filterStatus !== '' || $search === ''); @endphp
                <a class="rr-stat" href="{{ route('construction.projects.index', array_filter(['q' => $search, 'status' => $filterStatus])) }}" @if($isCurrent) aria-current="true" @endif>
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => $icon, 'size' => 18])@lang('construction::lang.' . $label)</div>
                    <div class="rr-stat__value"><span class="rr-num">{{ $summary[$key] }}</span></div>
                    <div class="rr-stat__hint">
                        @if($key === 'all')
                            @lang('construction::lang.all_statuses')
                        @else
                            <span class="rr-num">{{ $summary['all'] > 0 ? round(($summary[$key] / $summary['all']) * 100) : 0 }}%</span> @lang('construction::lang.of_portfolio')
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- جدول المشاريع --}}
    <section class="rr-table-wrap" id="ct-quick-list" aria-labelledby="rr-projects-list-title">
        <div class="rr-table-head rr-table-head--toolbar">
            <div class="rr-table-head__titles">
                <h2 id="rr-projects-list-title" class="rr-table-head__title">@lang('construction::lang.projects')</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.project_portfolio_hint')</p>
            </div>
            <form method="GET" action="{{ route('construction.projects.index') }}" class="rr-table-tools" role="search" data-rr-auto-filter>
                <div class="rr-control rr-control--sm rr-table-tools__search">
                    <span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'search', 'size' => 16])</span>
                    <label class="rr-sr" for="ct-project-search">@lang('construction::lang.search_projects')</label>
                    <input id="ct-project-search" class="rr-control__input" type="search" name="q" value="{{ $search }}" placeholder="@lang('construction::lang.search_projects')">
                </div>
                <div class="rr-control rr-control--sm rr-table-tools__select">
                    <label class="rr-sr" for="ct-project-status">@lang('construction::lang.status')</label>
                    <select id="ct-project-status" class="rr-control__input rr-control__select" name="status">
                        <option value="">@lang('construction::lang.all_statuses')</option>
                        @foreach(\Modules\Construction\Entities\ConstructionProject::STATUSES as $projectStatus)
                            <option value="{{ $projectStatus }}" @selected($status === $projectStatus)>@lang('construction::lang.' . $projectStatus)</option>
                        @endforeach
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
                <button class="rr-btn rr-btn--secondary rr-btn--sm ct-filter-submit" type="submit">@include('construction::partials.rr_icon', ['name' => 'filter', 'size' => 16])@lang('construction::lang.apply_filters')</button>
                @if($isFiltered)
                    <a class="rr-btn rr-btn--ghost rr-btn--sm" href="{{ route('construction.projects.index') }}">@lang('construction::lang.clear_filter')</a>
                @endif
            </form>
        </div>

        @if($projects->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => $isFiltered ? 'search' : 'building', 'size' => 22])</span>
                @if($isFiltered)
                    <h3 class="rr-empty__title">@lang('construction::lang.no_matching_projects')</h3>
                    <p class="rr-empty__text">@lang('construction::lang.no_matching_projects_hint')</p>
                    <a class="rr-btn rr-btn--secondary" href="{{ route('construction.projects.index') }}">@lang('construction::lang.clear_filter')</a>
                @else
                    <h3 class="rr-empty__title">@lang('construction::lang.no_projects_yet')</h3>
                    @can('construction.project.create')
                        <button type="button" class="rr-btn rr-btn--primary" data-toggle="modal" data-target="#ct-project-create">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_project')</button>
                    @endcan
                @endif
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table rr-table--projects">
                    <caption class="rr-sr">@lang('construction::lang.project_portfolio')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.customer')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.project_manager')</th>
                            <th class="rr-th rr-th--num" scope="col"><span class="rr-th__in">@lang('construction::lang.contract_value')@if($currencySymbol !== '')<span class="rr-th__unit">({{ $currencySymbol }})</span>@endif</span></th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($projects as $project)
                            @php
                                $contractValue = (float) optional($project->primaryContract)->original_value;
                                $canEdit = auth()->user()->can('construction.project.update');
                                $canDelete = auth()->user()->can('construction.project.delete') && $project->status === 'draft';
                            @endphp
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--wrap">
                                    <a class="rr-entity rr-entity--link" href="{{ route('construction.projects.show', $project->id) }}">
                                        <span class="rr-entity__mark" aria-hidden="true">{{ mb_substr($project->name, 0, 1) }}</span>
                                        <span class="rr-entity__copy">
                                            <span class="rr-entity__name">{{ $project->name }}</span>
                                            <span class="rr-td__sub"><span class="rr-code">{{ $project->code }}</span>@if($project->location) · {{ $project->location }}@endif</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="rr-td rr-td--wrap">{{ $project->customer->supplier_business_name ?: $project->customer->name }}</td>
                                <td class="rr-td rr-td--wrap">
                                    @if($project->manager?->user_full_name){{ $project->manager->user_full_name }}@else<span class="rr-td--muted">@lang('construction::lang.not_set')</span>@endif
                                </td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ $contractValue == 0 ? 'rr-money--zero' : '' }}"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($contractValue)['amount'] }}</span></span></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $statusTones[$project->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $project->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <div class="rr-row-actions">
                                        <a href="{{ route('construction.projects.show', $project->id) }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 18])@lang('construction::lang.open_project')</a>
                                        @if($canEdit || $canDelete)
                                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr-project-menu-{{ $project->id }}" aria-label="@lang('construction::lang.project_more_actions'): {{ $project->name }}">@include('construction::partials.rr_icon', ['name' => 'more'])</button>
                                            <div class="rr-menu" id="rr-project-menu-{{ $project->id }}" role="menu">
                                                @if($canEdit)
                                                    <a class="rr-menu__item" role="menuitem" href="{{ route('construction.projects.edit', $project->id) }}" aria-label="@lang('construction::lang.edit_project'): {{ $project->name }}">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 18])@lang('construction::lang.edit_project')</a>
                                                @endif
                                                @if($canDelete)
                                                    @if($canEdit)<div class="rr-menu__sep" role="separator"></div>@endif
                                                    <form method="POST" action="{{ route('construction.projects.destroy', $project->id) }}" class="ct-row-delete-form rr-inline-form" onsubmit="return confirm(@js(__($project->quote_id ? 'construction::lang.quote_project_delete_confirmation' : 'construction::lang.project_delete_confirmation', ['name' => $project->name, 'code' => $project->code])))">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rr-menu__item rr-menu__item--danger ct-row-action--danger" role="menuitem" title="@lang('construction::lang.delete_project')" aria-label="@lang('construction::lang.delete_project'): {{ $project->name }}">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])@lang('construction::lang.delete_project')</button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('construction::partials.rr_pager', ['paginator' => $projects, 'itemLabel' => __('construction::lang.project_unit')])
        @endif
    </section>
    <div id="ct-quick-workspace"></div>
</main>
@can('construction.project.create')@include('construction::projects.partials.create_modal')@endcan
@include('construction::partials.rr_menu_script')
@endsection
@include('construction::partials.quick_create')

@section('module_javascript')
<script>
$(function () {
    // التصفية: تغيير الحالة بيطبّق على طول، والبحث بيطبّق بـ Enter أو زرار التطبيق
    $('[data-rr-auto-filter] select[name="status"]').on('change', function () { this.form.submit(); });
});
</script>
@endsection

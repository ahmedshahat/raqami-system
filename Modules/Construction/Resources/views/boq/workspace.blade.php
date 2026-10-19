@extends('construction::layouts.module')

@section('title', __('construction::lang.tab_boq'))

@php
    $statusTones = ['active' => 'success', 'draft' => 'warning', 'on_hold' => 'danger', 'completed' => 'info', 'cancelled' => 'neutral'];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-boq-workspace-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 24])</span>
        <div class="rr-head__titles">
            <h1 id="rr-boq-workspace-title" class="rr-head__title">@lang('construction::lang.boq_workspace_title')</h1>
            <p class="rr-head__sub">@lang('construction::lang.boq_workspace_description')</p>
        </div>
        <div class="rr-head__actions">
            <a href="{{ route('construction.cost-codes.index') }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'list'])@lang('construction::lang.manage_cost_codes')</a>
        </div>
    </header>

    <section class="rr-table-wrap" id="ct-quick-list" aria-labelledby="rr-boq-projects-title">
        <div class="rr-table-head">
            <h2 id="rr-boq-projects-title" class="rr-table-head__title">@lang('construction::lang.projects')</h2>
            <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ method_exists($projects, 'total') ? $projects->total() : $projects->count() }}</span> @lang('construction::lang.projects_count_suffix')</span>
        </div>
        @if($projects->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_projects')</h3>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table rr-table--projects">
                    <caption class="rr-sr">@lang('construction::lang.boq_workspace_title')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.customer')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.approved_boqs')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($projects as $project)
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--wrap">
                                    <a class="rr-entity rr-entity--link" href="{{ route('construction.projects.boq.index', $project->id) }}">
                                        <span class="rr-entity__mark" aria-hidden="true">{{ mb_substr($project->name, 0, 1) }}</span>
                                        <span class="rr-entity__copy">
                                            <span class="rr-entity__name">{{ $project->name }}</span>
                                            <span class="rr-code">{{ $project->code }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="rr-td rr-td--wrap">{{ $project->customer?->supplier_business_name ?: $project->customer?->name }}</td>
                                <td class="rr-td rr-td--wrap">
                                    @if($project->approvedBoq)
                                        <span class="rr-badge rr-badge--success">@include('construction::partials.rr_icon', ['name' => 'check', 'size' => 14]){{ $project->approvedBoq->name }}</span>
                                    @else
                                        <span class="rr-badge"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.not_approved_yet')</span>
                                    @endif
                                </td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $statusTones[$project->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $project->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <a href="{{ route('construction.projects.boq.index', $project->id) }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.open_project_boq')</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($projects, 'links'))
                @include('construction::partials.rr_pager', ['paginator' => $projects, 'itemLabel' => __('construction::lang.project_unit')])
            @endif
        @endif
    </section>
</main>
@endsection

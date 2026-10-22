@extends('construction::layouts.module')

@section('title', __('construction::lang.tab_measurements'))

@php
    $statusTones = ['active' => 'success', 'draft' => 'warning', 'on_hold' => 'danger', 'completed' => 'info', 'cancelled' => 'neutral'];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-measurements-workspace-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'ruler', 'size' => 24])</span>
        <div class="rr-head__titles">
            <h1 id="rr-measurements-workspace-title" class="rr-head__title">@lang('construction::lang.measurements')</h1>
            <p class="rr-head__sub">@lang('construction::lang.measurements_description')</p>
        </div>
    </header>

    <section class="rr-table-wrap" id="ct-quick-list" aria-labelledby="rr-measurement-projects-title">
        <div class="rr-table-head">
            <h2 id="rr-measurement-projects-title" class="rr-table-head__title">@lang('construction::lang.projects')</h2>
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
                    <caption class="rr-sr">@lang('construction::lang.measurements')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.customer')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.tab_measurements')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($projects as $project)
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--wrap">
                                    <a class="rr-entity rr-entity--link" href="{{ route('construction.projects.measurements.index', $project->id) }}">
                                        <span class="rr-entity__mark" aria-hidden="true">{{ mb_substr($project->name, 0, 1) }}</span>
                                        <span class="rr-entity__copy">
                                            <span class="rr-entity__name">{{ $project->name }}</span>
                                            <span class="rr-code">{{ $project->code }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="rr-td rr-td--wrap">{{ $project->customer?->supplier_business_name ?: $project->customer?->name }}</td>
                                <td class="rr-td rr-td--num">
                                    @if($project->measurements_count > 0)
                                        <span class="rr-num">{{ $project->measurements_count }}</span>
                                    @else
                                        <span class="rr-td--muted">—</span>
                                    @endif
                                </td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $statusTones[$project->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $project->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <a href="{{ route('construction.projects.measurements.index', $project->id) }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'ruler', 'size' => 18])@lang('construction::lang.open_measurements')</a>
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

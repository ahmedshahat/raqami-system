@extends('construction::layouts.module')

@section('title', __('construction::lang.tab_certificates'))

@php
    $statusTones = ['active' => 'success', 'draft' => 'warning', 'on_hold' => 'danger', 'completed' => 'info', 'cancelled' => 'neutral'];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-certificates-workspace-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--success" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 24])</span>
        <div class="rr-head__titles">
            <h1 id="rr-certificates-workspace-title" class="rr-head__title">@lang('construction::lang.customer_certificates')</h1>
            <p class="rr-head__sub">@lang('construction::lang.certificates_description')</p>
        </div>
    </header>

    <section class="rr-table-wrap" id="ct-quick-list" aria-labelledby="rr-certificate-projects-title">
        <div class="rr-table-head">
            <h2 id="rr-certificate-projects-title" class="rr-table-head__title">@lang('construction::lang.projects')</h2>
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
                    <caption class="rr-sr">@lang('construction::lang.customer_certificates')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.customer')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.contract_value')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.tab_certificates')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($projects as $project)
                            @php $contractValue = (float) optional($project->primaryContract)->original_value; @endphp
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--wrap">
                                    <a class="rr-entity rr-entity--link" href="{{ route('construction.projects.certificates.index', $project->id) }}">
                                        <span class="rr-entity__mark" aria-hidden="true">{{ mb_substr($project->name, 0, 1) }}</span>
                                        <span class="rr-entity__copy">
                                            <span class="rr-entity__name">{{ $project->name }}</span>
                                            <span class="rr-code">{{ $project->code }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="rr-td rr-td--wrap">{{ $project->customer?->supplier_business_name ?: $project->customer?->name }}</td>
                                <td class="rr-td rr-td--num">
                                    @if($contractValue > 0)
                                        <span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $contractValue])</span>
                                    @else
                                        <span class="rr-td--muted">—</span>
                                    @endif
                                </td>
                                <td class="rr-td rr-td--num">
                                    @if($project->customer_certificates_count > 0)
                                        <span class="rr-num">{{ $project->customer_certificates_count }}</span>
                                    @else
                                        <span class="rr-td--muted">—</span>
                                    @endif
                                </td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $statusTones[$project->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $project->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <a href="{{ route('construction.projects.certificates.index', $project->id) }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.open_certificates')</a>
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

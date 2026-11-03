@extends('construction::layouts.module')

@section('title', __('construction::lang.dashboard'))

@section('module_content')
<section class="content">
    <div class="ct-kpi-grid">
        <article class="ct-kpi ct-kpi--blue">
            <div class="ct-kpi__top"><span class="ct-kpi__icon"><i class="fas fa-coins"></i></span><span class="ct-kpi__tag">@lang('construction::lang.portfolio')</span></div>
            <small>@lang('construction::lang.total_contract_value')</small>
            <strong>@include('construction::partials.money', ['value' => $contractSummary->total_value])</strong>
            <p>{{ $contractSummary->contracts_count }} @lang('construction::lang.registered_contracts')</p>
        </article>
        <article class="ct-kpi ct-kpi--green">
            <div class="ct-kpi__top"><span class="ct-kpi__icon"><i class="fas fa-building"></i></span><span class="ct-kpi__tag">@lang('construction::lang.execution')</span></div>
            <small>@lang('construction::lang.active_projects')</small>
            <strong>{{ $summary['active'] }}</strong>
            <p>@lang('construction::lang.out_of') {{ $summary['all'] }} @lang('construction::lang.projects_count_suffix')</p>
        </article>
        <article class="ct-kpi ct-kpi--amber">
            <div class="ct-kpi__top"><span class="ct-kpi__icon"><i class="fas fa-hand-holding-usd"></i></span><span class="ct-kpi__tag">@lang('construction::lang.cash_flow')</span></div>
            <small>@lang('construction::lang.total_advance_payments')</small>
            <strong>@include('construction::partials.money', ['value' => $contractSummary->advance_value])</strong>
            <p>@lang('construction::lang.retention_expected'): @include('construction::partials.money', ['value' => $contractSummary->retention_value])</p>
        </article>
        <article class="ct-kpi ct-kpi--violet">
            <div class="ct-kpi__top"><span class="ct-kpi__icon"><i class="fas fa-file-invoice"></i></span><span class="ct-kpi__tag">@lang('construction::lang.quotes')</span></div>
            <small>@lang('construction::lang.accepted_quotes_pending_conversion')</small>
            <strong>{{ $acceptedQuoteCount }}</strong>
            <p>@lang('construction::lang.ready_for_contracting')</p>
        </article>
    </div>

    <div class="ct-insight-strip">
        <div><span><i class="fas fa-file-contract"></i> @lang('construction::lang.active_contracts')</span><strong>{{ $contractSummary->active_contracts }}</strong></div>
        <div><span><i class="fas fa-shield-alt"></i> @lang('construction::lang.performance_bonds')</span><strong>@include('construction::partials.money', ['value' => $contractSummary->bond_value])</strong></div>
        <div><span><i class="fas fa-check-circle"></i> @lang('construction::lang.completion_rate')</span><strong>{{ $completionRate }}%</strong></div>
    </div>

    <div class="ct-overview-grid">
        <article class="ct-panel ct-panel--portfolio">
            <header class="ct-panel__header"><div><span>@lang('construction::lang.portfolio_health')</span><h2>@lang('construction::lang.projects_by_status')</h2></div><i class="fas fa-chart-bar"></i></header>
            <div class="ct-status-list">
                @foreach(['active' => 'green', 'draft' => 'amber', 'on_hold' => 'orange', 'completed' => 'blue', 'cancelled' => 'slate'] as $statusKey => $color)
                    <div class="ct-status-row">
                        <div class="ct-status-row__label"><span><i class="ct-dot ct-dot--{{ $color }}"></i>@lang('construction::lang.' . $statusKey)</span><b>{{ $statusBars[$statusKey]['count'] }}</b></div>
                        <div class="ct-progress"><span class="ct-progress--{{ $color }}" style="width: {{ $statusBars[$statusKey]['percent'] }}%"></span></div>
                        <small>{{ $statusBars[$statusKey]['percent'] }}%</small>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="ct-panel ct-panel--recent">
            <header class="ct-panel__header"><div><span>@lang('construction::lang.latest_activity')</span><h2>@lang('construction::lang.recent_projects')</h2></div><a href="{{ route('construction.projects.index') }}">@lang('construction::lang.view_all') <i class="fas fa-arrow-left"></i></a></header>
            <div class="ct-project-list">
                @forelse($recentProjects as $project)
                    <a class="ct-project-row" href="{{ route('construction.projects.show', $project->id) }}">
                        <span class="ct-project-row__mark">{{ mb_substr($project->name, 0, 1) }}</span>
                        <span class="ct-project-row__copy"><b>{{ $project->name }}</b><small>{{ $project->code }} · {{ $project->customer->supplier_business_name ?: $project->customer->name }}</small></span>
                        <span class="ct-project-row__value"><b>@include('construction::partials.money', ['value' => optional($project->primaryContract)->original_value ?? 0])</b><small class="ct-status ct-status--{{ $project->status }}">@lang('construction::lang.' . $project->status)</small></span>
                    </a>
                @empty
                    <div class="ct-empty"><i class="far fa-folder-open"></i><p>@lang('construction::lang.no_projects')</p></div>
                @endforelse
            </div>
        </article>

        <article class="ct-panel ct-panel--workflow">
            <header class="ct-panel__header"><div><span>@lang('construction::lang.workflow')</span><h2>@lang('construction::lang.contracting_cycle')</h2></div><i class="fas fa-route"></i></header>
            <div class="ct-workflow">
                @foreach([
                    ['fa-file-invoice', 'workflow_quote', __('construction::lang.optional'), true],
                    ['fa-city', 'workflow_project', null, true],
                    ['fa-list-ol', 'workflow_boq', null, true],
                    ['fa-file-signature', 'workflow_contract', null, true],
                    ['fa-ruler-combined', 'workflow_measurement', null, true],
                    ['fa-file-invoice-dollar', 'workflow_certificate', null, true],
                    ['fa-receipt', 'workflow_invoice', null, true],
                ] as [$icon, $label, $badge, $ready])
                    <div class="ct-workflow__step {{ $ready ? 'is-ready' : '' }}">
                        <span><i class="fas {{ $icon }}"></i></span>
                        <div>
                            <b>@lang('construction::lang.' . $label) @if($badge)<span class="ct-workflow-optional">({{ $badge }})</span>@endif</b>
                            <small>{{ $ready ? __('construction::lang.available_now') : __('construction::lang.next_stage') }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
    </div>
</section>
@endsection

@extends('construction::layouts.module')

@section('title', __('construction::lang.overview_center_title'))

@php
    $statusTones = ['active' => 'success', 'draft' => 'warning', 'on_hold' => 'danger', 'completed' => 'info', 'cancelled' => 'neutral'];
    $currencyParts = \Modules\Construction\Support\CurrencyFormatter::parts(0);
    $latestProject = $recentProjects->first();
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-overview-title">

    {{-- رأس الصفحة --}}
    <header class="rr-head">
        <img class="rr-head__logo" src="{{ asset('img/logo-small.png') }}" width="56" height="56" alt="">
        <div class="rr-head__titles">
            <h1 id="rr-overview-title" class="rr-head__title">@lang('construction::lang.overview_center_title')</h1>
            <p class="rr-head__sub">@lang('construction::lang.overview_center_subtitle')</p>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--success"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.live_local_data')</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])<span class="rr-num">{{ @format_date(now()) }}</span></span>
            </div>
        </div>
        <div class="rr-head__actions">
            @can('construction.project.create')
                <a class="rr-btn rr-btn--primary" href="{{ route('construction.projects.index', ['open_create' => 1]) }}">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_project')</a>
            @endcan
            @can('construction.project.view')
                <a class="rr-btn rr-btn--secondary" href="{{ route('construction.projects.index') }}">@include('construction::partials.rr_icon', ['name' => 'building'])@lang('construction::lang.all_projects')</a>
            @endcan
        </div>
    </header>

    {{-- الخطوة التالية --}}
    <section class="rr-card rr-card__body" aria-labelledby="rr-next-title">
        <div class="rr-next">
            <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'arrow-forward', 'size' => 22])</span>
            <div class="rr-next__copy">
                <div class="rr-eyebrow">@lang('construction::lang.next_action')</div>
                @if($acceptedQuoteCount > 0)
                    <h2 id="rr-next-title" class="rr-next__title">@lang('construction::lang.overview_quotes_action_title', ['count' => $acceptedQuoteCount])</h2>
                    <p class="rr-next__text">@lang('construction::lang.overview_quotes_action_hint')</p>
                @elseif($summary['all'] === 0)
                    <h2 id="rr-next-title" class="rr-next__title">@lang('construction::lang.overview_first_project_title')</h2>
                    <p class="rr-next__text">@lang('construction::lang.no_projects_yet')</p>
                @else
                    <h2 id="rr-next-title" class="rr-next__title">@lang('construction::lang.overview_projects_action_title')</h2>
                    <p class="rr-next__text">@lang('construction::lang.overview_projects_action_hint')</p>
                @endif
            </div>
            @if($acceptedQuoteCount > 0)
                @can('construction.project.create')
                    <a class="rr-btn rr-btn--secondary rr-btn--lg" href="{{ route('construction.quotes.index') }}">@lang('construction::lang.overview_review_quotes')@include('construction::partials.rr_icon', ['name' => 'arrow-forward'])</a>
                @endcan
            @elseif($summary['all'] === 0)
                @can('construction.project.create')
                    <a class="rr-btn rr-btn--secondary rr-btn--lg" href="{{ route('construction.projects.index', ['open_create' => 1]) }}">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_project')</a>
                @endcan
            @elseif($latestProject)
                @can('construction.project.view')
                    <a class="rr-btn rr-btn--secondary rr-btn--lg" href="{{ route('construction.projects.show', $latestProject->id) }}">@lang('construction::lang.overview_open_latest_project')@include('construction::partials.rr_icon', ['name' => 'arrow-forward'])</a>
                @endcan
            @endif
        </div>
    </section>

    <div class="rr-cols">
        <div class="rr-cols__main">

            {{-- محفظة المشاريع --}}
            <section class="rr-section" aria-labelledby="rr-portfolio-title">
                <div>
                    <div class="rr-eyebrow">@lang('construction::lang.portfolio')</div>
                    <h2 id="rr-portfolio-title" class="rr-section-title">@lang('construction::lang.overview_portfolio_snapshot')</h2>
                    <p class="rr-section-desc">@lang('construction::lang.overview_snapshot_hint')</p>
                </div>

                <div class="rr-statgroup-wrap">
                    <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4">
                        <div class="rr-stat">
                            <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 18])@lang('construction::lang.portfolio')</div>
                            <div class="rr-stat__value">
                                <div class="rr-stat__caption">@lang('construction::lang.total_contract_value')</div>
                                <span class="rr-money rr-money--lg {{ (float) $contractSummary->total_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contractSummary->total_value])</span>
                            </div>
                            <div class="rr-stat__hint"><span class="rr-num">{{ (int) $contractSummary->contracts_count }}</span> @lang('construction::lang.registered_contracts')</div>
                        </div>
                        <div class="rr-stat">
                            <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18])@lang('construction::lang.execution')</div>
                            <div class="rr-stat__value">
                                <div class="rr-stat__caption">@lang('construction::lang.active_projects')</div>
                                <span class="rr-num">{{ $summary['active'] }}</span>
                            </div>
                            <div class="rr-stat__hint">@lang('construction::lang.out_of') <span class="rr-num">{{ $summary['all'] }}</span> @lang('construction::lang.projects_count_suffix')</div>
                        </div>
                        <div class="rr-stat">
                            <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 18])@lang('construction::lang.cash_flow')</div>
                            <div class="rr-stat__value">
                                <div class="rr-stat__caption">@lang('construction::lang.total_advance_payments')</div>
                                <span class="rr-money rr-money--lg {{ (float) $contractSummary->advance_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contractSummary->advance_value])</span>
                                <div class="rr-stat__sub">
                                    <span>@lang('construction::lang.overview_works_retention')</span>
                                    <span class="rr-money rr-money--sm {{ (float) $contractSummary->retention_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contractSummary->retention_value])</span>
                                </div>
                            </div>
                        </div>
                        <div class="rr-stat">
                            <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check', 'size' => 18])@lang('construction::lang.completion_rate')</div>
                            <div class="rr-stat__value">
                                <div class="rr-stat__caption">@lang('construction::lang.overview_completed_projects_short')</div>
                                <span class="rr-num">{{ $completionRate }}%</span>
                            </div>
                            <div class="rr-stat__hint"><span class="rr-num">{{ $summary['completed'] }}</span> @lang('construction::lang.projects_count_suffix')</div>
                        </div>
                    </div>
                </div>

                <div class="rr-statgroup-wrap">
                    <div class="rr-statgroup rr-statgroup--3" style="--rr-stats: 3" aria-label="@lang('construction::lang.overview_supporting_indicators')">
                        <div class="rr-stat">
                            <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.active_contracts')</div>
                            <div class="rr-stat__value"><span class="rr-num">{{ (int) $contractSummary->active_contracts }}</span></div>
                        </div>
                        <div class="rr-stat">
                            <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 18])@lang('construction::lang.performance_bonds')</div>
                            <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $contractSummary->bond_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contractSummary->bond_value])</span></div>
                        </div>
                        <div class="rr-stat">
                            <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.accepted_quotes_pending_conversion')</div>
                            <div class="rr-stat__value"><span class="rr-num">{{ $acceptedQuoteCount }}</span></div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- المشروعات --}}
            <section class="rr-table-wrap" aria-labelledby="rr-projects-title">
                <div class="rr-table-head">
                    <h3 id="rr-projects-title" class="rr-table-head__title">@lang('construction::lang.overview_projects_table')</h3>
                    @can('construction.project.view')
                        <a class="rr-btn rr-btn--ghost rr-btn--sm" href="{{ route('construction.projects.index') }}">@lang('construction::lang.view_all')@include('construction::partials.rr_icon', ['name' => 'arrow-forward'])</a>
                    @endcan
                </div>
                @if($recentProjects->isEmpty())
                    <div class="rr-empty">
                        <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 22])</span>
                        <h4 class="rr-empty__title">@lang('construction::lang.no_projects')</h4>
                        <p class="rr-empty__text">@lang('construction::lang.no_projects_yet')</p>
                    </div>
                @else
                    <div class="rr-table-scroll">
                        <table class="rr-table">
                            <caption class="rr-sr">@lang('construction::lang.recent_projects')</caption>
                            <thead>
                                <tr>
                                    <th class="rr-th" scope="col">@lang('construction::lang.project')</th>
                                    <th class="rr-th" scope="col">@lang('construction::lang.customer')</th>
                                    <th class="rr-th rr-th--num" scope="col"><span class="rr-th__in">@lang('construction::lang.project_contract_value')@if($currencyParts['symbol'] !== '')<span class="rr-th__unit">({{ $currencyParts['symbol'] }})</span>@endif</span></th>
                                    <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                                    <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentProjects as $project)
                                    @php
                                        $projectValue = (float) (optional($project->primaryContract)->original_value ?? 0);
                                        $customer = $project->customer;
                                    @endphp
                                    <tr class="rr-tr">
                                        <td class="rr-td rr-td--wrap">
                                            <div class="rr-entity">
                                                <span class="rr-entity__mark" aria-hidden="true">{{ mb_substr($project->name, 0, 1) }}</span>
                                                <div>
                                                    <div class="rr-entity__name">{{ $project->name }}</div>
                                                    <span class="rr-code">{{ $project->code }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="rr-td rr-td--wrap rr-td--muted">{{ $customer ? ($customer->supplier_business_name ?: $customer->name) : '—' }}</td>
                                        <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ $projectValue == 0 ? 'rr-money--zero' : '' }}"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($projectValue)['amount'] }}</span></span></td>
                                        <td class="rr-td"><span class="rr-badge rr-badge--{{ $statusTones[$project->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $project->status)</span></td>
                                        <td class="rr-td rr-td--actions">
                                            @can('construction.project.view')
                                                <a class="rr-btn rr-btn--secondary rr-btn--sm" href="{{ route('construction.projects.show', $project->id) }}">@include('construction::partials.rr_icon', ['name' => 'folder-open'])@lang('construction::lang.overview_open_file')</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- دورة تنفيذ المشروع --}}
            <section class="rr-card" aria-labelledby="rr-flow-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h3 id="rr-flow-title" class="rr-card__title">@lang('construction::lang.contracting_cycle')</h3>
                        <p class="rr-card__desc">@lang('construction::lang.overview_workflow_hint')</p>
                    </div>
                </header>
                <div class="rr-card__body rr-steps-scroll">
                    <ol class="rr-steps" aria-label="@lang('construction::lang.contracting_cycle')">
                        @foreach(['workflow_quote', 'workflow_project', 'workflow_boq', 'workflow_contract', 'workflow_measurement', 'workflow_certificate', 'workflow_invoice'] as $step)
                            <li class="rr-step">
                                <span class="rr-step__dot"><span class="rr-num">{{ $loop->iteration }}</span></span>
                                <span class="rr-step__label">@lang('construction::lang.' . $step)</span>
                                @if($loop->first)<span class="rr-step__note">@lang('construction::lang.optional')</span>@endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        </div>

        <div class="rr-cols__side">

            {{-- الإجراءات الرئيسية --}}
            <section class="rr-card" aria-labelledby="rr-quick-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <div class="rr-eyebrow">@lang('construction::lang.workspace_navigation')</div>
                        <h3 id="rr-quick-title" class="rr-card__title">@lang('construction::lang.workspace_quick_actions')</h3>
                    </div>
                </header>
                @php
                    $quickLinks = array_filter([
                        auth()->user()->can('construction.project.view') ? ['building', 'projects', route('construction.projects.index')] : null,
                        auth()->user()->can('construction.project.view') ? ['file', 'quotes', route('construction.quotes.index')] : null,
                        auth()->user()->can('construction.boq.manage') ? ['list', 'tab_boq', route('construction.project-items.index')] : null,
                        auth()->user()->can('construction.measurement.view') ? ['ruler', 'measurements', route('construction.measurements.index')] : null,
                        ['pie', 'tab_costs', route('construction.costs.index')],
                        auth()->user()->can('construction.reports.view') ? ['bars', 'nav_reports', route('construction.reports.index')] : null,
                    ]);
                @endphp
                <ul>
                    @foreach($quickLinks as [$icon, $label, $url])
                        <li>
                            <a class="rr-nav-row" href="{{ $url }}">
                                <span class="rr-nav-row__icon">@include('construction::partials.rr_icon', ['name' => $icon])</span>
                                <span class="rr-nav-row__label">@lang('construction::lang.' . $label)</span>
                                @if($label === 'quotes' && $acceptedQuoteCount > 0)<span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $acceptedQuoteCount }}</span></span>@endif
                                <span class="rr-nav-row__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-forward', 'size' => 18])</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- موقف المشاريع --}}
            <section class="rr-card" aria-labelledby="rr-dist-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <div class="rr-eyebrow">@lang('construction::lang.portfolio_health')</div>
                        <h3 id="rr-dist-title" class="rr-card__title">@lang('construction::lang.projects_by_status')</h3>
                    </div>
                    <span class="rr-badge rr-badge--brand rr-badge--lg"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.total') <span class="rr-num">{{ $summary['all'] }}</span></span>
                </header>
                <ul>
                    @foreach($statusTones as $statusKey => $tone)
                        @php $bar = $statusBars[$statusKey]; @endphp
                        <li>
                            <div class="rr-dist-row">
                                <div class="rr-dist-row__line">
                                    <span class="rr-badge rr-badge--{{ $tone }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $statusKey)</span>
                                    <span class="rr-num rr-dist-row__count">{{ $bar['count'] }}</span>
                                    <span class="rr-num rr-dist-row__pct">{{ $bar['percent'] }}%</span>
                                </div>
                                <div class="rr-progress" role="img" aria-label="@lang('construction::lang.' . $statusKey): {{ $bar['percent'] }}%"><div class="rr-progress__fill rr-progress__fill--{{ $tone }}" style="width: {{ $bar['percent'] }}%"></div></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>

</main>
@endsection

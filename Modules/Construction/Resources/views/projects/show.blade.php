@extends('construction::layouts.module')
@section('title', $project->name)
@section('module_css')
@include('construction::projects.partials.workspace_styles')
@endsection

@section('module_content')
@php
    $latestBoq = $project->boqVersions->first();
    $latestContract = $project->contracts->first();
    $primaryContract = $project->contracts->firstWhere('is_primary', true);
    $latestMeasurement = $project->measurements->first();
    $latestCertificate = $project->customerCertificates->first();
    $fromQuote = (bool) $project->quote_id;
    $stageStatus = static function ($record) {
        if (! $record) return __('construction::lang.not_started');
        if (in_array($record->status, ['approved', 'active', 'partially_approved'], true)) return __('construction::lang.approved');
        if (in_array($record->status, ['completed', 'cancelled', 'paid', 'posted', 'superseded'], true)) return __('construction::lang.workspace_closed');
        return __('construction::lang.under_review');
    };
    $visibleWorkflow = collect($workflow)->except('invoice');
    $currentStep = $visibleWorkflow->search(false, true);
    $currentStep = $currentStep === false ? 'customer_certificates' : $currentStep;
    $boqUrl = route('construction.projects.boq.index', $project->id);
    $contractUrl = $primaryContract
        ? route('construction.projects.contracts.show', [$project->id, $primaryContract->id])
        : route('construction.projects.contracts.create', $project->id);
    $measurementUrl = route('construction.projects.measurements.index', $project->id);
    $certificateUrl = route('construction.projects.certificates.index', $project->id);
    $steps = [
        ['key' => 'project', 'label' => __('construction::lang.project'), 'url' => route('construction.projects.show', $project->id), 'available' => true, 'permission' => 'construction.project.view', 'reason' => ''],
        ['key' => 'boq', 'label' => __('construction::lang.boq'), 'url' => $boqUrl, 'available' => true, 'permission' => $latestBoq ? 'construction.boq.view' : 'construction.boq.manage', 'reason' => ''],
        ['key' => 'contract', 'label' => __('construction::lang.contract'), 'url' => $contractUrl, 'available' => $workflow['boq'], 'permission' => $primaryContract ? 'construction.contract.view' : 'construction.contract.manage', 'reason' => __('construction::lang.workspace_requires_boq')],
        ['key' => 'execution', 'label' => __('construction::lang.execution'), 'url' => $measurementUrl, 'available' => $workflow['contract'], 'permission' => 'construction.measurement.view', 'reason' => __('construction::lang.workspace_requires_contract')],
        ['key' => 'customer_certificates', 'label' => __('construction::lang.customer_certificates'), 'url' => $certificateUrl, 'available' => $workflow['execution'], 'permission' => 'construction.certificate.view', 'reason' => __('construction::lang.workspace_requires_measurement')],
    ];
    $actions = [
        ['label' => __('construction::lang.boq'), 'description' => __('construction::lang.workspace_boq_hint'), 'icon' => 'fa-list-ol', 'tone' => 'blue', 'status' => $workflow['boq'] ? __('construction::lang.workspace_complete') : __('construction::lang.not_started'), 'url' => $boqUrl, 'cta' => __('construction::lang.open_boq'), 'available' => true, 'permission' => $latestBoq ? 'construction.boq.view' : 'construction.boq.manage', 'reason' => ''],
        ['label' => __('construction::lang.contract'), 'description' => __('construction::lang.workspace_contract_hint'), 'icon' => 'fa-file-signature', 'tone' => 'violet', 'status' => $stageStatus($latestContract), 'url' => $contractUrl, 'cta' => $primaryContract ? __('construction::lang.open_contract') : __('construction::lang.create_contract_draft'), 'available' => $workflow['boq'], 'permission' => $primaryContract ? 'construction.contract.view' : 'construction.contract.manage', 'reason' => __('construction::lang.workspace_requires_boq')],
        ['label' => __('construction::lang.measurements'), 'description' => __('construction::lang.workspace_measurement_hint'), 'icon' => 'fa-ruler-combined', 'tone' => 'amber', 'status' => $stageStatus($latestMeasurement), 'url' => $measurementUrl, 'cta' => __('construction::lang.open_measurements'), 'available' => $workflow['contract'], 'permission' => 'construction.measurement.view', 'reason' => __('construction::lang.workspace_requires_contract')],
        ['label' => __('construction::lang.customer_certificates'), 'description' => __('construction::lang.workspace_certificate_hint'), 'icon' => 'fa-file-invoice-dollar', 'tone' => 'green', 'status' => $stageStatus($latestCertificate), 'url' => $certificateUrl, 'cta' => __('construction::lang.open_certificates'), 'available' => $workflow['execution'], 'permission' => 'construction.certificate.view', 'reason' => __('construction::lang.workspace_requires_measurement')],
    ];
    if (! $workflow['boq']) {
        $next = ['title' => __('construction::lang.workspace_next_boq'), 'detail' => __('construction::lang.workspace_boq_hint'), 'url' => $boqUrl, 'cta' => __('construction::lang.open_boq'), 'permission' => $latestBoq ? 'construction.boq.view' : 'construction.boq.manage'];
    } elseif (! $workflow['contract']) {
        $next = ['title' => __('construction::lang.workspace_next_contract'), 'detail' => __('construction::lang.workspace_contract_hint'), 'url' => $contractUrl, 'cta' => $primaryContract ? __('construction::lang.open_contract') : __('construction::lang.create_contract_draft'), 'permission' => $primaryContract ? 'construction.contract.view' : 'construction.contract.manage'];
    } elseif (! $workflow['execution']) {
        $next = ['title' => __('construction::lang.workspace_next_measurement'), 'detail' => __('construction::lang.workspace_measurement_hint'), 'url' => $measurementUrl, 'cta' => __('construction::lang.open_measurements'), 'permission' => 'construction.measurement.view'];
    } elseif (! $workflow['customer_certificates']) {
        $next = ['title' => __('construction::lang.workspace_next_certificate'), 'detail' => __('construction::lang.workspace_certificate_hint'), 'url' => $certificateUrl, 'cta' => __('construction::lang.open_certificates'), 'permission' => 'construction.certificate.view'];
    } elseif (! $workflow['invoice'] && $latestCertificate && in_array($latestCertificate->status, ['approved','partially_approved','posted','paid'], true)) {
        $next = ['title' => __('construction::lang.workspace_next_invoice'), 'detail' => __('construction::lang.workspace_invoice_hint'), 'url' => route('construction.projects.certificates.show', [$project->id, $latestCertificate->id]), 'cta' => __('construction::lang.open_certificate'), 'permission' => 'construction.certificate.view'];
    } elseif ($workflow['invoice'] && $latestCertificate?->invoice) {
        $next = ['title' => __('construction::lang.invoice_created_with_number', ['number' => $latestCertificate->invoice->invoice_no]), 'detail' => __('construction::lang.workspace_invoice_created_hint'), 'url' => url('/sells/'.$latestCertificate->invoice->id).'?construction=1', 'cta' => __('construction::lang.open_invoice'), 'permission' => 'construction.certificate.view'];
    } else {
        $next = ['title' => __('construction::lang.workspace_next_measurement'), 'detail' => __('construction::lang.workspace_measurement_hint'), 'url' => $measurementUrl, 'cta' => __('construction::lang.open_measurements'), 'permission' => 'construction.measurement.view'];
    }
    $summary = [
        ['label' => __('construction::lang.workspace_boq_count'), 'value' => $project->boq_versions_count, 'icon' => 'fa-list-ol'],
        ['label' => __('construction::lang.workspace_contract_count'), 'value' => $project->contracts_count, 'icon' => 'fa-file-signature'],
        ['label' => __('construction::lang.workspace_measurement_count'), 'value' => $project->measurements_count, 'icon' => 'fa-ruler-combined'],
        ['label' => __('construction::lang.workspace_certificate_count'), 'value' => $project->customer_certificates_count, 'icon' => 'fa-file-invoice-dollar'],
        ['label' => __('construction::lang.workspace_latest_boq_value'), 'value' => (float) ($latestBoq?->sales_total ?? 0), 'icon' => 'fa-coins', 'money' => true],
        ['label' => __('construction::lang.workspace_latest_certificate_value'), 'value' => (float) ($latestCertificate?->net_due ?? 0), 'icon' => 'fa-receipt', 'money' => true],
    ];
    if ($fromQuote) array_unshift($summary, ['label' => __('construction::lang.quote_total'), 'value' => (float) ($project->quote?->total ?? 0), 'icon' => 'fa-file-invoice', 'money' => true]);
    $activities = collect([
        $latestBoq ? ['label' => __('construction::lang.workspace_latest_boq'), 'name' => $latestBoq->name, 'status' => $workflow['boq'] ? __('construction::lang.workspace_complete') : __('construction::lang.not_started'), 'date' => $latestBoq->approved_at ?: $latestBoq->created_at, 'icon' => 'fa-list-ol', 'url' => route('construction.projects.boq.show', [$project->id, $latestBoq->id]), 'permission' => 'construction.boq.view'] : null,
        $latestContract ? ['label' => __('construction::lang.workspace_latest_contract'), 'name' => $latestContract->contract_number ?: $latestContract->title, 'status' => __('construction::lang.'.$latestContract->status), 'date' => $latestContract->activated_at ?: $latestContract->created_at, 'icon' => 'fa-file-signature', 'url' => route('construction.projects.contracts.show', [$project->id, $latestContract->id]), 'permission' => 'construction.contract.view'] : null,
        $latestMeasurement ? ['label' => __('construction::lang.workspace_latest_measurement'), 'name' => $latestMeasurement->number, 'status' => __('construction::lang.'.$latestMeasurement->status), 'date' => $latestMeasurement->approved_at ?: $latestMeasurement->created_at, 'icon' => 'fa-ruler-combined', 'url' => route('construction.projects.measurements.show', [$project->id, $latestMeasurement->id]), 'permission' => 'construction.measurement.view'] : null,
        $latestCertificate ? ['label' => __('construction::lang.workspace_latest_certificate'), 'name' => $latestCertificate->number, 'status' => __('construction::lang.'.$latestCertificate->status), 'date' => $latestCertificate->approved_at ?: $latestCertificate->created_at, 'icon' => 'fa-file-invoice-dollar', 'url' => route('construction.projects.certificates.show', [$project->id, $latestCertificate->id]), 'permission' => 'construction.certificate.view'] : null,
        ['label' => __('construction::lang.workspace_project_created'), 'name' => $project->code, 'status' => '', 'date' => $project->created_at, 'icon' => 'fa-building', 'url' => route('construction.projects.show', $project->id), 'permission' => 'construction.project.view'],
    ])->filter()->sortByDesc('date')->values();
    if ($fromQuote) {
        $activities = $activities->reject(fn ($activity) => $activity['url'] === route('construction.projects.boq.show', [$project->id, $latestBoq?->id ?? 0]))->values();
    }
    $teamMembers = $project->members->reject(fn ($member) => $member->id === $project->manager_id);
@endphp
<section class="content ct-project-workspace">
    <header class="ct-project-hero">
        <div class="ct-project-hero__main">
            <div class="ct-project-hero__eyebrow"><i class="fa fa-building" aria-hidden="true"></i> @lang('construction::lang.project_workspace')</div>
            <h1>{{ $project->name }}</h1>
            <div class="ct-project-hero__identity"><span class="ct-project-code">{{ $project->code }}</span><span class="ct-project-state ct-project-state--{{ $project->status }}">@lang('construction::lang.'.$project->status)</span></div>
            @if($project->quote)<p><i class="fa fa-file-invoice"></i> @lang('construction::lang.quote_number'): <a href="{{ route('construction.quotes.show', $project->quote->id) }}">{{ $project->quote->number }}</a></p>@endif
        </div>
        <div class="ct-project-hero__actions">
            @if($project->quote)<a class="ct-project-button ct-project-button--quiet" href="{{ route('construction.quotes.show', $project->quote->id) }}"><i class="fa fa-file-invoice" aria-hidden="true"></i> @lang('construction::lang.view_original_quote')</a>@endif
            @if($primaryContract && $primaryContract->status === 'active')@can('construction.contract.manage')<a class="ct-project-button ct-project-button--primary" href="{{ route('construction.projects.contracts.adjustments.index', [$project->id,$primaryContract->id]) }}"><i class="fa fa-plus-circle"></i> @lang('construction::lang.add_contract_adjustment')</a>@endcan @else @can('construction.boq.manage')<a class="ct-project-button ct-project-button--primary" href="{{ $boqUrl }}"><i class="fa fa-list"></i> @lang('construction::lang.edit_project_items')</a>@endcan @endif
            @can('construction.project.update')<a class="ct-project-button ct-project-button--primary" href="{{ route('construction.projects.edit', $project->id) }}"><i class="fa fa-pen" aria-hidden="true"></i> @lang('construction::lang.edit_project')</a>@endcan
            <a class="ct-project-button ct-project-button--quiet" href="{{ route('construction.projects.index') }}"><i class="fa fa-arrow-right" aria-hidden="true"></i> @lang('construction::lang.back_to_projects')</a>
        </div>
        <dl class="ct-project-hero__facts">
            <div><dt>@lang('construction::lang.customer')</dt><dd>{{ $project->customer?->supplier_business_name ?: ($project->customer?->name ?: __('construction::lang.not_set')) }}</dd></div>
            <div><dt>@lang('construction::lang.project_manager')</dt><dd>{{ $project->manager?->user_full_name ?: __('construction::lang.not_set') }}</dd></div>
            <div><dt>@lang('construction::lang.start_date')</dt><dd>{{ $project->start_date ? @format_date($project->start_date) : __('construction::lang.not_set') }}</dd></div>
            <div><dt>@lang('construction::lang.end_date')</dt><dd>{{ $project->end_date ? @format_date($project->end_date) : __('construction::lang.not_set') }}</dd></div>
            <div><dt>@lang('construction::lang.location')</dt><dd>{{ $project->location ?: __('construction::lang.not_set') }}</dd></div>
            <div><dt>@lang('construction::lang.consultant')</dt><dd>{{ $project->consultantContact?->supplier_business_name ?: ($project->consultantContact?->name ?: ($project->consultant_name ?: __('construction::lang.not_set'))) }}</dd></div>
        </dl>
    </header>

    <section class="ct-project-panel ct-project-panel--workflow" aria-labelledby="ct-project-workflow-title">
        <div class="ct-project-panel__heading"><div><span class="ct-project-kicker">@lang('construction::lang.workspace_progress')</span><h2 id="ct-project-workflow-title">@lang('construction::lang.project_workflow')</h2></div></div>
        <ol class="ct-project-steps">
            @foreach($steps as $step)
                @php $isDone = $workflow[$step['key']]; $isCurrent = $currentStep === $step['key']; $canOpen = $step['available'] && auth()->user()->can($step['permission']); @endphp
                <li class="ct-project-step {{ $isDone ? 'is-complete' : ($isCurrent ? 'is-current' : '') }} {{ $canOpen ? '' : 'is-disabled' }}">
                    @if($canOpen)<a href="{{ $step['url'] }}" aria-label="{{ $step['label'] }}">@else<div title="{{ $step['available'] ? __('construction::lang.workspace_no_permission') : $step['reason'] }}" aria-disabled="true">@endif
                        <span class="ct-project-step__number">@if($isDone)<i class="fa fa-check" aria-hidden="true"></i>@else{{ $loop->iteration }}@endif</span>
                        <span class="ct-project-step__label">{{ $step['label'] }}</span>
                        <small>{{ $isDone ? __('construction::lang.workspace_complete') : (! $step['available'] ? $step['reason'] : (! $canOpen ? __('construction::lang.workspace_no_permission') : ($isCurrent ? __('construction::lang.workspace_current') : __('construction::lang.workspace_available')))) }}</small>
                    @if($canOpen)</a>@else</div>@endif
                </li>
            @endforeach
        </ol>
    </section>

    <section class="ct-project-next" aria-labelledby="ct-project-next-title">
        <div class="ct-project-next__icon"><i class="fa fa-compass" aria-hidden="true"></i></div>
        <div class="ct-project-next__copy"><span class="ct-project-kicker">@lang('construction::lang.workspace_next_step')</span><h2 id="ct-project-next-title">{{ $next['title'] }}</h2><p>{{ $next['detail'] }}</p></div>
        @can($next['permission'])<a class="ct-project-button ct-project-button--primary" href="{{ $next['url'] }}">{{ $next['cta'] }} <i class="fa fa-arrow-left" aria-hidden="true"></i></a>@endcan
    </section>

    <section aria-labelledby="ct-project-actions-title">
        <div class="ct-project-section-heading"><div><span class="ct-project-kicker">@lang('construction::lang.workspace_navigation')</span><h2 id="ct-project-actions-title">@lang('construction::lang.workspace_quick_actions')</h2></div></div>
        <div class="ct-project-actions">
            @foreach($actions as $action)
                @php $canOpenAction = $action['available'] && auth()->user()->can($action['permission']); @endphp
                <article class="ct-project-action ct-project-action--{{ $action['tone'] }} {{ $canOpenAction ? '' : 'is-disabled' }}">
                    <div class="ct-project-action__top"><span class="ct-project-action__icon"><i class="fa {{ $action['icon'] }}" aria-hidden="true"></i></span><span class="ct-project-action__status">{{ $action['status'] }}</span></div>
                    <h3>{{ $action['label'] }}</h3><p>{{ $action['description'] }}</p>
                    @if($canOpenAction)<a href="{{ $action['url'] }}" class="ct-project-action__link">{{ $action['cta'] }} <i class="fa fa-arrow-left" aria-hidden="true"></i></a>
                    @else<span class="ct-project-action__lock" title="{{ $action['available'] ? __('construction::lang.workspace_no_permission') : $action['reason'] }}"><i class="fa fa-lock" aria-hidden="true"></i> {{ $action['available'] ? __('construction::lang.workspace_no_permission') : $action['reason'] }}</span>@endif
                </article>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="ct-project-summary-title">
        <div class="ct-project-section-heading"><div><span class="ct-project-kicker">@lang('construction::lang.workspace_at_a_glance')</span><h2 id="ct-project-summary-title">@lang('construction::lang.workspace_summary')</h2></div></div>
        <div class="ct-project-summary">
            @foreach($summary as $item)<div class="ct-project-stat"><span class="ct-project-stat__icon"><i class="fa {{ $item['icon'] }}" aria-hidden="true"></i></span><div><span>{{ $item['label'] }}</span><strong>@if(!empty($item['money']))@include('construction::partials.money', ['value' => $item['value']])@else{{ $item['value'] }}@endif</strong></div></div>@endforeach
        </div>
    </section>

    <section aria-labelledby="ct-project-costs-title">
        <div class="ct-project-section-heading"><div><span class="ct-project-kicker">@lang('construction::lang.registered_costs_to_date')</span><h2 id="ct-project-costs-title">@lang('construction::lang.project_costs')</h2></div><a class="ct-project-button ct-project-button--quiet" href="{{ route('construction.costs.index', ['project_id' => $project->id]) }}">@lang('construction::lang.view_all_project_costs') <i class="fa fa-arrow-left"></i></a></div>
        <div class="ct-project-cost-grid">
            <article class="ct-project-panel ct-project-cost-summary">
                <div class="ct-project-panel__heading"><div><span class="ct-project-kicker">@lang('construction::lang.cost_snapshot')</span><h3>@lang('construction::lang.project_cost_summary')</h3></div><i class="fa fa-chart-pie"></i></div>
                <div class="ct-project-cost-numbers">
                    <div><span>@lang('construction::lang.project_contract_value')</span><strong>@include('construction::partials.money', ['value' => $projectCostSummary['project_value']])</strong></div>
                    <div><span>@lang('construction::lang.total_recorded_costs')</span><strong>@include('construction::partials.money', ['value' => $projectCostSummary['registered_total']])</strong></div>
                    <div><span>@lang('construction::lang.registered_expenses')</span><strong>@include('construction::partials.money', ['value' => $projectCostSummary['expense_total']])</strong></div>
                    <div><span>@lang('construction::lang.cost_materials')</span><strong>@include('construction::partials.money', ['value' => $projectCostSummary['material_total']])</strong></div>
                    <div><span>@lang('construction::lang.cost_labor')</span><strong>@include('construction::partials.money', ['value' => $projectCostSummary['labor_total']])</strong></div>
                    <div><span>@lang('construction::lang.expenses_to_project_value')</span><strong>{{ @num_format($projectCostSummary['expense_percent']) }}%</strong></div>
                </div>
                <div class="ct-project-cost-progress" role="progressbar" aria-valuenow="{{ min(100, $projectCostSummary['expense_percent']) }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ min(100, $projectCostSummary['expense_percent']) }}%"></span></div>
                <p>@lang('construction::lang.cost_summary_not_profit')</p>
            </article>
            <article class="ct-project-panel">
                <div class="ct-project-panel__heading"><div><span class="ct-project-kicker">{{ $projectCostSummary['expense_count'] }} @lang('construction::lang.expense_records')</span><h3>@lang('construction::lang.recent_project_expenses')</h3></div><i class="fa fa-receipt"></i></div>
                <div class="ct-project-expense-list">
                    @forelse($recentProjectExpenses as $expense)
                        @php
                            $expenseNote = trim(strip_tags((string) $expense->additional_notes));
                            $expenseNotePreview = \Illuminate\Support\Str::limit($expenseNote, 90);
                        @endphp
                        <div class="ct-project-expense-row">
                            <div class="ct-project-expense-row__details">
                                <strong>{{ $expense->ref_no ?: __('construction::lang.expense_without_reference') }}</strong>
                                <small class="ct-project-expense-item">{{ $expense->constructionProjectItem ? $expense->constructionProjectItem->code.' — '.$expense->constructionProjectItem->description : __('construction::lang.general_project_expense') }}</small>
                                @if($expenseNote !== '')<small class="ct-project-expense-note" title="{{ $expenseNote }}">{{ $expenseNotePreview }}</small>@endif
                            </div>
                            <div class="ct-project-expense-row__amount"><strong class="{{ $expense->type === 'expense_refund' ? 'text-success' : '' }}">@if($expense->type === 'expense_refund')− @endif @include('construction::partials.money', ['value' => $expense->final_total])</strong><time>{{ @format_date($expense->transaction_date) }}</time></div>
                        </div>
                    @empty<div class="ct-project-empty"><i class="fa fa-receipt"></i> @lang('construction::lang.no_project_expenses')</div>@endforelse
                </div>
            </article>
        </div>
    </section>

    <div class="ct-project-lower">
        <section class="ct-project-panel" aria-labelledby="ct-project-team-title">
            <div class="ct-project-panel__heading"><div><span class="ct-project-kicker">@lang('construction::lang.workspace_people')</span><h2 id="ct-project-team-title">@lang('construction::lang.project_members')</h2></div><i class="fa fa-users" aria-hidden="true"></i></div>
            <div class="ct-project-people">
                @if($project->manager)<div class="ct-project-person"><span class="ct-project-avatar">{{ mb_substr($project->manager->user_full_name ?: $project->manager->username, 0, 1) }}</span><div><strong>{{ $project->manager->user_full_name ?: $project->manager->username }}</strong><small>@lang('construction::lang.project_manager')</small></div><span class="ct-project-person__badge">@lang('construction::lang.workspace_manager')</span></div>@endif
                @forelse($teamMembers as $member)<div class="ct-project-person"><span class="ct-project-avatar">{{ mb_substr($member->user_full_name ?: $member->username, 0, 1) }}</span><div><strong>{{ $member->user_full_name ?: $member->username }}</strong><small>{{ $member->pivot->access_level === 'manage' ? __('construction::lang.workspace_team_manage') : __('construction::lang.workspace_team_view') }}</small></div></div>
                @empty @if($project->manager)<p class="ct-project-empty">@lang('construction::lang.workspace_manager_only')</p>@else<p class="ct-project-empty">@lang('construction::lang.workspace_no_team')</p>@endif
                @endforelse
            </div>
        </section>
        <section class="ct-project-panel" aria-labelledby="ct-project-activity-title">
            <div class="ct-project-panel__heading"><div><span class="ct-project-kicker">@lang('construction::lang.workspace_recent_updates')</span><h2 id="ct-project-activity-title">@lang('construction::lang.workspace_recent_activity')</h2></div><i class="fa fa-history" aria-hidden="true"></i></div>
            <div class="ct-project-activity">
                @foreach($activities as $activity)<div class="ct-project-activity__row"><span class="ct-project-activity__icon"><i class="fa {{ $activity['icon'] }}" aria-hidden="true"></i></span><div><small>{{ $activity['label'] }}</small>@can($activity['permission'])<a href="{{ $activity['url'] }}">{{ $activity['name'] ?: __('construction::lang.not_set') }}</a>@else<strong>{{ $activity['name'] ?: __('construction::lang.not_set') }}</strong>@endcan</div><div class="ct-project-activity__meta"><span>{{ $activity['status'] }}</span><time>{{ $activity['date'] ? @format_date($activity['date']) : '' }}</time></div></div>@endforeach
            </div>
        </section>
    </div>
    <section class="ct-project-panel" aria-labelledby="ct-audit-title">
        <div class="ct-project-panel__heading"><div><span class="ct-project-kicker">@lang('construction::lang.audit_trail')</span><h2 id="ct-audit-title">@lang('construction::lang.important_changes')</h2></div><i class="fa fa-shield-alt"></i></div>
        <div class="table-responsive"><table class="table table-striped"><thead><tr><th>@lang('construction::lang.operation_description')</th><th>@lang('construction::lang.date_time')</th></tr></thead><tbody>@forelse($auditLogs as $log)<tr><td>{{ $log->display_description }}</td><td>{{ @format_datetime($log->created_at) }}</td></tr>@empty<tr><td colspan="2" class="text-center text-muted">@lang('construction::lang.no_audit_records')</td></tr>@endforelse</tbody></table></div>
    </section>
</section>
@endsection

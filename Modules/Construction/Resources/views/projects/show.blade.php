@extends('construction::layouts.module')
@section('title', $project->name)

@section('module_content')
@php
    $latestBoq = $project->boqVersions->first();
    $latestContract = $project->contracts->first();
    $primaryContract = $project->contracts->firstWhere('is_primary', true);
    $latestMeasurement = $project->measurements->first();
    $latestCertificate = $project->customerCertificates->first();
    $fromQuote = (bool) $project->quote_id;
    $stageState = static function ($record) {
        if (! $record) return ['label' => __('construction::lang.not_started'), 'tone' => 'neutral'];
        if (in_array($record->status, ['approved', 'active', 'partially_approved'], true)) return ['label' => __('construction::lang.approved'), 'tone' => 'success'];
        if (in_array($record->status, ['completed', 'cancelled', 'paid', 'posted', 'superseded'], true)) return ['label' => __('construction::lang.workspace_closed'), 'tone' => 'info'];
        return ['label' => __('construction::lang.under_review'), 'tone' => 'warning'];
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

    // المراحل: كل مرحلة كارت فيه حالتها وعدد مستنداتها وإجراؤها، والمرحلة الحالية فيها الخطوة التالية.
    // لون ثابت لكل مرحلة: البنود أزرق، العقد بنفسجي، الحصر برتقالي، المستخلصات أخضر.
    $stages = [
        ['key' => 'boq', 'label' => __('construction::lang.boq'), 'description' => __('construction::lang.workspace_boq_hint'), 'tone' => 'info',
            'state' => $workflow['boq'] ? ['label' => __('construction::lang.workspace_complete'), 'tone' => 'success'] : ['label' => __('construction::lang.not_started'), 'tone' => 'neutral'],
            'count' => $project->boq_versions_count, 'count_label' => __('construction::lang.workspace_boq_count'),
            'url' => $boqUrl, 'cta' => __('construction::lang.open_boq'), 'available' => true, 'permission' => $latestBoq ? 'construction.boq.view' : 'construction.boq.manage', 'reason' => ''],
        ['key' => 'contract', 'label' => __('construction::lang.contract'), 'description' => __('construction::lang.workspace_contract_hint'), 'tone' => 'brand',
            'state' => $stageState($latestContract),
            'count' => $project->contracts_count, 'count_label' => __('construction::lang.workspace_contract_count'),
            'url' => $contractUrl, 'cta' => $primaryContract ? __('construction::lang.open_contract') : __('construction::lang.create_contract_draft'), 'available' => $workflow['boq'], 'permission' => $primaryContract ? 'construction.contract.view' : 'construction.contract.manage', 'reason' => __('construction::lang.workspace_requires_boq')],
        ['key' => 'execution', 'label' => __('construction::lang.measurements'), 'description' => __('construction::lang.workspace_measurement_hint'), 'tone' => 'warning',
            'state' => $stageState($latestMeasurement),
            'count' => $project->measurements_count, 'count_label' => __('construction::lang.workspace_measurement_count'),
            'url' => $measurementUrl, 'cta' => __('construction::lang.open_measurements'), 'available' => $workflow['contract'], 'permission' => 'construction.measurement.view', 'reason' => __('construction::lang.workspace_requires_contract')],
        ['key' => 'customer_certificates', 'label' => __('construction::lang.customer_certificates'), 'description' => __('construction::lang.workspace_certificate_hint'), 'tone' => 'success',
            'state' => $stageState($latestCertificate),
            'count' => $project->customer_certificates_count, 'count_label' => __('construction::lang.workspace_certificate_count'),
            'url' => $certificateUrl, 'cta' => __('construction::lang.open_certificates'), 'available' => $workflow['execution'], 'permission' => 'construction.certificate.view', 'reason' => __('construction::lang.workspace_requires_measurement')],
    ];
    $hasCurrentStage = collect($stages)->contains(fn ($stage) => ! $workflow[$stage['key']] && $currentStep === $stage['key']);

    $activities = collect([
        $latestBoq ? ['label' => __('construction::lang.workspace_latest_boq'), 'name' => $latestBoq->name, 'status' => $workflow['boq'] ? __('construction::lang.workspace_complete') : __('construction::lang.not_started'), 'date' => $latestBoq->approved_at ?: $latestBoq->created_at, 'icon' => 'list', 'tone' => 'info', 'url' => route('construction.projects.boq.show', [$project->id, $latestBoq->id]), 'permission' => 'construction.boq.view'] : null,
        $latestContract ? ['label' => __('construction::lang.workspace_latest_contract'), 'name' => $latestContract->contract_number ?: $latestContract->title, 'status' => __('construction::lang.'.$latestContract->status), 'date' => $latestContract->activated_at ?: $latestContract->created_at, 'icon' => 'file', 'tone' => 'brand', 'url' => route('construction.projects.contracts.show', [$project->id, $latestContract->id]), 'permission' => 'construction.contract.view'] : null,
        $latestMeasurement ? ['label' => __('construction::lang.workspace_latest_measurement'), 'name' => $latestMeasurement->number, 'status' => __('construction::lang.'.$latestMeasurement->status), 'date' => $latestMeasurement->approved_at ?: $latestMeasurement->created_at, 'icon' => 'ruler', 'tone' => 'warning', 'url' => route('construction.projects.measurements.show', [$project->id, $latestMeasurement->id]), 'permission' => 'construction.measurement.view'] : null,
        $latestCertificate ? ['label' => __('construction::lang.workspace_latest_certificate'), 'name' => $latestCertificate->number, 'status' => __('construction::lang.'.$latestCertificate->status), 'date' => $latestCertificate->approved_at ?: $latestCertificate->created_at, 'icon' => 'check-circle', 'tone' => 'success', 'url' => route('construction.projects.certificates.show', [$project->id, $latestCertificate->id]), 'permission' => 'construction.certificate.view'] : null,
        ['label' => __('construction::lang.workspace_project_created'), 'name' => $project->code, 'status' => '', 'date' => $project->created_at, 'icon' => 'building', 'tone' => 'neutral', 'url' => route('construction.projects.show', $project->id), 'permission' => 'construction.project.view'],
    ])->filter()->sortByDesc('date')->values();
    if ($fromQuote) {
        $activities = $activities->reject(fn ($activity) => $activity['url'] === route('construction.projects.boq.show', [$project->id, $latestBoq?->id ?? 0]))->values();
    }
    $teamMembers = $project->members->reject(fn ($member) => $member->id === $project->manager_id);
    $statusTones = ['active' => 'success', 'draft' => 'warning', 'on_hold' => 'danger', 'completed' => 'info', 'cancelled' => 'neutral'];
    $customerName = $project->customer?->supplier_business_name ?: $project->customer?->name;
    $consultantName = $project->consultantContact?->supplier_business_name ?: ($project->consultantContact?->name ?: $project->consultant_name);

    // بيانات المشروع: نعرض المتسجل بس، والناقص يتجمع في رابط واحد
    $facts = collect([
        ['label' => __('construction::lang.customer'), 'value' => $customerName, 'icon' => 'building'],
        ['label' => __('construction::lang.project_manager'), 'value' => $project->manager?->user_full_name, 'icon' => 'user'],
        ['label' => __('construction::lang.consultant'), 'value' => $consultantName, 'icon' => 'user'],
        ['label' => __('construction::lang.location'), 'value' => $project->location, 'icon' => 'map-pin'],
        ['label' => __('construction::lang.start_date'), 'value' => $project->start_date, 'icon' => 'calendar', 'date' => true],
        ['label' => __('construction::lang.end_date'), 'value' => $project->end_date, 'icon' => 'calendar', 'date' => true],
    ]);
    $filledFacts = $facts->filter(fn ($fact) => filled($fact['value']));
    $missingFactsCount = $facts->count() - $filledFacts->count();

    $costPercent = (float) $projectCostSummary['expense_percent'];
    $costTone = $costPercent >= 100 ? 'danger' : ($costPercent >= 80 ? 'warning' : 'success');
    $costRows = [
        ['label' => __('construction::lang.registered_expenses'), 'value' => $projectCostSummary['expense_total'], 'icon' => 'file', 'tone' => 'neutral', 'source' => null],
        ['label' => __('construction::lang.cost_materials'), 'value' => $projectCostSummary['material_total'], 'icon' => 'list', 'tone' => 'info', 'source' => null],
        ['label' => __('construction::lang.cost_labor'), 'value' => $projectCostSummary['labor_total'], 'icon' => 'users', 'tone' => 'warning', 'source' => null],
        ['label' => __('construction::lang.cost_subcontracts'), 'value' => $projectCostSummary['subcontract_total'], 'icon' => 'building', 'tone' => 'brand', 'source' => 'subcontracts'],
    ];
@endphp
<main class="content rr-page" aria-labelledby="rr-project-title">

    {{-- ═══ 1. رأس الصفحة ═══ --}}
    <header class="rr-head ct-project-hero">
        <span class="rr-chip rr-chip--lg" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.overview.index') }}">@lang('construction::lang.tab_overview')</a></li>
                    <li><a href="{{ route('construction.projects.index') }}">@lang('construction::lang.projects')</a></li>
                    <li><span aria-current="page" class="rr-num">{{ $project->code }}</span></li>
                </ol>
            </nav>
            <h1 id="rr-project-title" class="rr-head__title">{{ $project->name }}</h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $statusTones[$project->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $project->status)</span>
                <span class="rr-code">{{ $project->code }}</span>
                @if($customerName)<span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $customerName }}</span>@endif
                @if($project->quote)
                    <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])<span>@lang('construction::lang.view_original_quote')</span><a class="rr-code-link" href="{{ route('construction.quotes.show', $project->quote->id) }}"><span class="rr-code">{{ $project->quote->number }}</span></a></span>
                @endif
            </div>
        </div>
        <div class="rr-head__actions">
            @if($primaryContract && $primaryContract->status === 'active')
                @can('construction.contract.manage')
                    <a class="rr-btn rr-btn--primary" href="{{ route('construction.projects.contracts.adjustments.index', [$project->id, $primaryContract->id]) }}">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_contract_adjustment')</a>
                @endcan
            @else
                @can('construction.boq.manage')
                    <a class="rr-btn rr-btn--primary" href="{{ $boqUrl }}">@include('construction::partials.rr_icon', ['name' => 'list'])@lang('construction::lang.edit_project_items')</a>
                @endcan
            @endif
            @can('construction.project.update')
                <a class="rr-btn rr-btn--secondary" href="{{ route('construction.projects.edit', $project->id) }}">@include('construction::partials.rr_icon', ['name' => 'edit'])@lang('construction::lang.edit_project')</a>
            @endcan
        </div>
    </header>

    {{-- ═══ 2. أرقام سريعة: 4 أرقام مالية بس ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4" aria-label="@lang('construction::lang.workspace_summary')">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.project_contract_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $projectCostSummary['project_value'] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $projectCostSummary['project_value']])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.total_recorded_costs')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $projectCostSummary['registered_total'] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $projectCostSummary['registered_total']])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'bars', 'size' => 18])@lang('construction::lang.expenses_to_project_value')</div>
                <div class="rr-stat__value rr-stat__value--{{ $costTone === 'success' ? 'success' : 'danger' }}"><span class="rr-num">{{ @num_format($projectCostSummary['expense_percent']) }}%</span></div>
                <div class="rr-progress" role="progressbar" aria-label="@lang('construction::lang.expenses_to_project_value')" aria-valuenow="{{ min(100, $costPercent) }}" aria-valuemin="0" aria-valuemax="100"><div class="rr-progress__fill rr-progress__fill--{{ $costTone }}" style="width: {{ min(100, $costPercent) }}%"></div></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.workspace_latest_certificate_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) ($latestCertificate?->net_due ?? 0) == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => (float) ($latestCertificate?->net_due ?? 0)])</span></div>
                @if($latestCertificate)<div class="rr-stat__hint"><span class="rr-code">{{ $latestCertificate->number }}</span> · @lang('construction::lang.' . $latestCertificate->status)</div>@endif
            </div>
        </div>
    </div>

    {{-- لو كل المراحل خلصت، الخطوة التالية (الفاتورة مثلًا) بتظهر هنا لوحدها --}}
    @unless($hasCurrentStage)
        <section class="rr-card rr-card--tint rr-card__body" id="ct-project-next" aria-labelledby="rr-project-next-title">
            <div class="rr-next">
                <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'arrow-forward', 'size' => 22])</span>
                <div class="rr-next__copy">
                    <div class="rr-eyebrow">@lang('construction::lang.workspace_next_step')</div>
                    <h2 id="rr-project-next-title" class="rr-next__title">{{ $next['title'] }}</h2>
                    <p class="rr-next__text">{{ $next['detail'] }}</p>
                </div>
                @can($next['permission'])<a class="rr-btn rr-btn--primary rr-btn--lg" href="{{ $next['url'] }}">{{ $next['cta'] }}@include('construction::partials.rr_icon', ['name' => 'arrow-forward'])</a>@endcan
            </div>
        </section>
    @endunless

    {{-- ═══ 3. مراحل المشروع: حالة + عدد + إجراء، والمرحلة الحالية فيها الخطوة التالية ═══ --}}
    <section class="rr-section" aria-labelledby="rr-project-stages-title">
        <div>
            <div class="rr-eyebrow">@lang('construction::lang.project_workflow')</div>
            <h2 id="rr-project-stages-title" class="rr-section-title">@lang('construction::lang.workspace_progress')</h2>
        </div>
        <div class="rr-stages-wrap {{ $hasCurrentStage ? 'rr-stages-wrap--6' : 'rr-stages-wrap--5' }}">
            <ol class="ct-project-steps">
                <li class="rr-stage rr-stage--done">
                    <div class="rr-stage__head">
                        <span class="rr-step__dot">@include('construction::partials.rr_icon', ['name' => 'check', 'size' => 14])</span>
                        <span class="rr-stage__label">@lang('construction::lang.project')</span>
                        <span class="rr-badge rr-badge--success">@lang('construction::lang.workspace_complete')</span>
                    </div>
                    <span class="rr-stage__meta">@lang('construction::lang.workspace_project_created') · <span class="rr-num">{{ @format_date($project->created_at) }}</span></span>
                </li>
                @foreach($stages as $stage)
                    @php
                        $isDone = $workflow[$stage['key']];
                        $isCurrent = ! $isDone && $currentStep === $stage['key'];
                        $canOpen = $stage['available'] && auth()->user()->can($stage['permission']);
                        $state = $isDone ? 'done' : ($isCurrent ? 'current' : ($canOpen ? 'upcoming' : 'locked'));
                        $badge = $isDone ? ['label' => __('construction::lang.workspace_complete'), 'tone' => 'success'] : ($isCurrent ? ['label' => __('construction::lang.workspace_current'), 'tone' => 'brand'] : $stage['state']);
                    @endphp
                    <li class="rr-stage rr-stage--{{ $state }} {{ $state === 'locked' ? '' : 'rr-stage--' . $stage['tone'] }}" @if($isCurrent) id="ct-project-next" aria-current="step" @endif>
                        <article class="ct-project-action rr-stage__body" aria-labelledby="rr-stage-{{ $stage['key'] }}">
                            <div class="rr-stage__head">
                                <span class="rr-step__dot">@if($isDone)@include('construction::partials.rr_icon', ['name' => 'check', 'size' => 14])@elseif($state === 'locked')@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 12])@else<span class="rr-num">{{ $loop->iteration + 1 }}</span>@endif</span>
                                <span class="rr-stage__label" id="rr-stage-{{ $stage['key'] }}">{{ $stage['label'] }}</span>
                                <span class="rr-badge rr-badge--{{ $badge['tone'] }}">{{ $badge['label'] }}</span>
                            </div>
                            <span class="rr-stage__meta"><span class="rr-num">{{ (int) $stage['count'] }}</span> · {{ $stage['count_label'] }}</span>
                            @if($isCurrent)
                                <div class="rr-eyebrow">@lang('construction::lang.workspace_next_step')</div>
                                <h3 class="rr-stage__title">{{ $next['title'] }}</h3>
                                <p class="rr-stage__text">{{ $next['detail'] }}</p>
                                @can($next['permission'])<a class="rr-btn rr-btn--primary" href="{{ $next['url'] }}">{{ $next['cta'] }}@include('construction::partials.rr_icon', ['name' => 'arrow-forward', 'size' => 18])</a>@endcan
                            @elseif($state === 'locked')
                                <span class="rr-stage__lock">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 14]){{ $stage['available'] ? __('construction::lang.workspace_no_permission') : $stage['reason'] }}</span>
                            @else
                                @unless($isDone)<p class="rr-stage__text">{{ $stage['description'] }}</p>@endunless
                                <a class="rr-btn rr-btn--secondary rr-btn--sm" href="{{ $stage['url'] }}">{{ $stage['cta'] }}@include('construction::partials.rr_icon', ['name' => 'arrow-forward', 'size' => 16])</a>
                            @endif
                        </article>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ═══ 4. التكاليف (رئيسي) + بيانات المشروع والفريق والنشاط (جانبي) ═══ --}}
    <div class="rr-cols">
        <div class="rr-cols__main">
            <section class="rr-card" aria-labelledby="rr-project-costs-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <div class="rr-eyebrow">@lang('construction::lang.registered_costs_to_date')</div>
                        <h2 id="rr-project-costs-title" class="rr-card__title">@lang('construction::lang.project_costs')</h2>
                    </div>
                    <a class="rr-btn rr-btn--ghost rr-btn--sm" href="{{ route('construction.costs.index', ['project_id' => $project->id]) }}">@lang('construction::lang.view_all_project_costs')@include('construction::partials.rr_icon', ['name' => 'arrow-forward', 'size' => 16])</a>
                </header>
                <ul class="rr-list" aria-label="@lang('construction::lang.project_cost_summary')">
                    @foreach($costRows as $costRow)
                        <li class="rr-list__row" @if($costRow['source']) data-cost-source="{{ $costRow['source'] }}" data-cost-value="{{ $projectCostSummary['subcontract_total'] }}" @endif>
                            <span class="rr-chip rr-chip--sm rr-chip--{{ $costRow['tone'] }}" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => $costRow['icon'], 'size' => 16])</span>
                            <div class="rr-list__main"><span>{{ $costRow['label'] }}</span></div>
                            <div class="rr-list__end"><span class="rr-money rr-money--sm {{ (float) $costRow['value'] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $costRow['value']])</span></div>
                        </li>
                    @endforeach
                </ul>
                <div class="rr-card__subhead"><span class="rr-num">{{ $projectCostSummary['expense_count'] }}</span> @lang('construction::lang.expense_records') · @lang('construction::lang.recent_project_expenses')</div>
                @if($recentProjectExpenses->isEmpty())
                    <div class="rr-empty-line">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 16])@lang('construction::lang.no_project_expenses')</div>
                @else
                    <ul class="rr-list">
                        @foreach($recentProjectExpenses as $expense)
                            @php
                                $expenseNote = trim(strip_tags((string) $expense->additional_notes));
                                $expenseNotePreview = \Illuminate\Support\Str::limit($expenseNote, 90);
                                $isRefund = $expense->type === 'expense_refund';
                            @endphp
                            <li class="rr-list__row">
                                <div class="rr-list__main">
                                    <span class="rr-list__title">{{ $expense->ref_no ?: __('construction::lang.expense_without_reference') }}</span>
                                    <span class="rr-list__meta">{{ $expense->constructionProjectItem ? $expense->constructionProjectItem->code.' — '.$expense->constructionProjectItem->description : __('construction::lang.general_project_expense') }}</span>
                                    @if($expenseNote !== '')<span class="rr-list__meta ct-project-expense-note" title="{{ $expenseNote }}">{{ $expenseNotePreview }}</span>@endif
                                </div>
                                <div class="rr-list__end">
                                    <span class="rr-money rr-money--sm {{ $isRefund ? 'rr-money--pos' : '' }}">@if($isRefund)<span aria-hidden="true">−</span>@endif @include('construction::partials.money', ['value' => $expense->final_total])</span>
                                    <time class="rr-list__meta rr-num">{{ @format_date($expense->transaction_date) }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="rr-card__foot-note">@lang('construction::lang.cost_summary_not_profit')</p>
            </section>
        </div>

        <aside class="rr-cols__side">

            {{-- بيانات المشروع: المتسجل بس --}}
            <section class="rr-card" aria-labelledby="rr-project-facts-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles"><h2 id="rr-project-facts-title" class="rr-card__title">@lang('construction::lang.project_details')</h2></div>
                </header>
                @if($filledFacts->isNotEmpty())
                    <dl class="rr-facts">
                        @foreach($filledFacts as $fact)
                            <div class="rr-fact">
                                <span class="rr-chip rr-chip--sm rr-chip--neutral" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => $fact['icon'], 'size' => 16])</span>
                                <div>
                                    <dt>{{ $fact['label'] }}</dt>
                                    <dd>@if(! empty($fact['date']))<span class="rr-num">{{ @format_date($fact['value']) }}</span>@else{{ $fact['value'] }}@endif</dd>
                                </div>
                            </div>
                        @endforeach
                    </dl>
                @endif
                @if($missingFactsCount > 0)
                    @can('construction.project.update')
                        <a class="rr-card__link" href="{{ route('construction.projects.edit', $project->id) }}">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 16])@lang('construction::lang.complete_missing_details', ['count' => $missingFactsCount])</a>
                    @else
                        <div class="rr-empty-line">@lang('construction::lang.missing_details_count', ['count' => $missingFactsCount])</div>
                    @endcan
                @endif
            </section>

            {{-- فريق المشروع --}}
            <section class="rr-card" aria-labelledby="rr-project-team-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles"><h2 id="rr-project-team-title" class="rr-card__title">@lang('construction::lang.project_members')</h2></div>
                </header>
                @if($project->manager || $teamMembers->isNotEmpty())
                    <ul class="rr-list">
                        @if($project->manager)
                            <li class="rr-list__row">
                                <span class="rr-avatar" aria-hidden="true">{{ mb_substr($project->manager->user_full_name ?: $project->manager->username, 0, 1) }}</span>
                                <div class="rr-list__main"><span class="rr-list__title">{{ $project->manager->user_full_name ?: $project->manager->username }}</span><span class="rr-list__meta">@lang('construction::lang.project_manager')</span></div>
                                <span class="rr-badge rr-badge--brand">@lang('construction::lang.workspace_manager')</span>
                            </li>
                        @endif
                        @foreach($teamMembers as $member)
                            <li class="rr-list__row">
                                <span class="rr-avatar" aria-hidden="true">{{ mb_substr($member->user_full_name ?: $member->username, 0, 1) }}</span>
                                <div class="rr-list__main"><span class="rr-list__title">{{ $member->user_full_name ?: $member->username }}</span><span class="rr-list__meta">{{ $member->pivot->access_level === 'manage' ? __('construction::lang.workspace_team_manage') : __('construction::lang.workspace_team_view') }}</span></div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="rr-empty-line">@include('construction::partials.rr_icon', ['name' => 'users', 'size' => 16])@lang('construction::lang.workspace_no_team')</div>
                @endif
            </section>

            {{-- النشاط + السجل الكامل في نفس الكارت --}}
            <section class="rr-card" aria-labelledby="rr-project-activity-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles"><h2 id="rr-project-activity-title" class="rr-card__title">@lang('construction::lang.workspace_recent_activity')</h2></div>
                </header>
                <ul class="rr-list">
                    @foreach($activities as $activity)
                        <li class="rr-list__row">
                            <span class="rr-chip rr-chip--sm rr-chip--{{ $activity['tone'] }}" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => $activity['icon'], 'size' => 16])</span>
                            <div class="rr-list__main">
                                <span class="rr-list__meta">{{ $activity['label'] }}</span>
                                @can($activity['permission'])<a class="rr-list__title" href="{{ $activity['url'] }}">{{ $activity['name'] ?: __('construction::lang.not_set') }}</a>@else<span class="rr-list__title">{{ $activity['name'] ?: __('construction::lang.not_set') }}</span>@endcan
                            </div>
                            <div class="rr-list__end">
                                @if($activity['status'])<span class="rr-list__meta">{{ $activity['status'] }}</span>@endif
                                <time class="rr-list__meta rr-num">{{ $activity['date'] ? @format_date($activity['date']) : '' }}</time>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <details class="rr-card__details">
                    <summary class="rr-card__link">
                        @include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 16])
                        <span>@lang('construction::lang.view_full_log') (<span class="rr-num">{{ $auditLogs->count() }}</span>)</span>
                        <span class="rr-collapse__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                    </summary>
                    @if($auditLogs->isEmpty())
                        <div class="rr-empty-line">@lang('construction::lang.no_audit_records')</div>
                    @else
                        <ul class="rr-list" aria-label="@lang('construction::lang.important_changes')">
                            @foreach($auditLogs as $log)
                                <li class="rr-list__row">
                                    <div class="rr-list__main"><span>{{ $log->display_description }}</span><time class="rr-list__meta rr-num">{{ @format_datetime($log->created_at) }}</time></div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </details>
            </section>
        </aside>
    </div>
</main>
@endsection

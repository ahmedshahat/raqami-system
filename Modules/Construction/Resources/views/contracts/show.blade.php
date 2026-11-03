@extends('construction::layouts.module')
@section('title', $contract->title ?: __('construction::lang.contract'))

@php
    $contractTones = ['draft' => 'warning', 'active' => 'success', 'completed' => 'info', 'cancelled' => 'neutral'];
    $isDraft = $contract->status === 'draft';
    $contractTitle = $contract->title ?: __('construction::lang.construction_contract') . ' - ' . $project->name;
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-contract-title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.contracts.index') }}">@lang('construction::lang.tab_contracts')</a></li>
                    <li><a href="{{ route('construction.projects.show', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><span aria-current="page" class="rr-num">{{ $contract->contract_number }}</span></li>
                </ol>
            </nav>
            <h1 id="rr-contract-title" class="rr-head__title">{{ $contractTitle }}</h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $contractTones[$contract->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $contract->status)</span>
                <span class="rr-code">{{ $contract->contract_number }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $project->name }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.' . $contract->contract_type)</span>
            </div>
        </div>
        <div class="rr-head__actions">
            @if($isDraft)
                @can('construction.contract.manage')
                    <a href="{{ route('construction.projects.contracts.edit', [$project->id, $contract->id]) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'edit'])@lang('construction::lang.edit')</a>
                @endcan
            @elseif($contract->status === 'active')
                @can('construction.contract.manage')
                    <a href="{{ route('construction.projects.contracts.adjustments.index', [$project->id, $contract->id]) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_contract_adjustment')</a>
                @endcan
            @endif
            <a href="{{ route('construction.projects.contracts.preview', [$project->id, $contract->id]) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.preview')</a>
            <a href="{{ route('construction.projects.contracts.print', [$project->id, $contract->id]) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'print'])@lang('construction::lang.print')</a>
            <a href="{{ route('construction.projects.contracts.pdf', [$project->id, $contract->id]) }}" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'download'])PDF</a>
        </div>
    </header>

    {{-- ═══ الحالة + الخطوة التالية ═══ --}}
    <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-contract-next-title">
        <div class="rr-next">
            <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => $isDraft ? 'info' : 'check-circle', 'size' => 22])</span>
            <div class="rr-next__copy">
                <div class="rr-eyebrow">{{ $isDraft ? __('construction::lang.draft_preview_notice_title') : __('construction::lang.contract_approved') }}</div>
                <h2 id="rr-contract-next-title" class="rr-next__title">@lang('construction::lang.' . ($isDraft ? 'next_approve_contract_hint' : 'next_create_measurement_hint'))</h2>
                <p class="rr-next__text">
                    @if($isDraft)
                        @lang('construction::lang.draft_preview_notice')
                    @else
                        @lang('construction::lang.active_contract_is_locked')@if($contract->activated_at) · <span class="rr-num">{{ @format_datetime($contract->activated_at) }}</span>@endif · <span class="rr-num">{{ $contract->approved_adjustments_count }}</span> @lang('construction::lang.approved_adjustments')
                    @endif
                </p>
            </div>
            @if($isDraft)
                @can('construction.contract.approve')
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.contracts.activate', [$project->id, $contract->id]) }}" onsubmit="return confirm(@js(__('construction::lang.activate_contract_confirmation')))">
                        @csrf
                        <button class="rr-btn rr-btn--primary rr-btn--lg" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.next_approve_contract')</button>
                    </form>
                @endcan
            @else
                @can('construction.measurement.manage')
                    <a href="{{ route('construction.projects.measurements.index', ['project' => $project->id, 'open_create' => 1]) }}" class="rr-btn rr-btn--primary rr-btn--lg">@include('construction::partials.rr_icon', ['name' => 'ruler'])@lang('construction::lang.next_create_measurement')</a>
                @endcan
            @endif
        </div>
    </section>

    {{-- ═══ أرقام العقد ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.original_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $contract->original_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contract->original_value])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 18])@lang('construction::lang.advance_payment_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $contract->advance_payment_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contract->advance_payment_value])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 18])@lang('construction::lang.retention_percent')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ @num_format((float) $contract->retention_percent) }}%</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 18])@lang('construction::lang.performance_bond_value')</div>
                <div class="rr-stat__value">
                    @if((float) $contract->performance_bond_value > 0)
                        <span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $contract->performance_bond_value])</span>
                    @else
                        <span class="rr-money--zero">—</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="rr-cols">
        <div class="rr-cols__main">
            {{-- بيانات العقد --}}
            <section class="rr-card" aria-labelledby="rr-contract-data-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-contract-data-title" class="rr-card__title">@lang('construction::lang.contract_data_section')</h2>
                        <p class="rr-card__desc">@lang('construction::lang.contract_data_section_hint')</p>
                    </div>
                </header>
                <dl class="rr-deflist">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.contract_number')</dt><dd><span class="rr-code">{{ $contract->contract_number }}</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.contract_title')</dt><dd>{{ $contract->title }}</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.contract_type')</dt><dd>@lang('construction::lang.' . $contract->contract_type)</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.signed_at')</dt><dd><span class="rr-num">{{ @format_date($contract->signed_at ?: now()) }}</span></dd></div>
                    <div class="rr-deflist__item">
                        <dt>@lang('construction::lang.' . ($project->quote_id ? 'quote_number' : 'linked_boq'))</dt>
                        <dd>
                            @if($project->quote_id)
                                <a class="rr-code-link" href="{{ route('construction.quotes.show', $project->quote_id) }}">@lang('construction::lang.view_original_quote')</a>
                            @elseif($contract->boqVersion)
                                <a class="rr-code-link" href="{{ route('construction.projects.boq.show', [$project->id, $contract->boqVersion->id]) }}">{{ $contract->boqVersion->name }}</a>
                            @else
                                @lang('construction::lang.not_linked')
                            @endif
                        </dd>
                    </div>
                    @if($project->quote)
                        <div class="rr-deflist__item"><dt>@lang('construction::lang.quote_number')</dt><dd><a class="rr-code-link" href="{{ route('construction.quotes.show', $project->quote->id) }}"><span class="rr-code">{{ $project->quote->number }}</span></a></dd></div>
                    @endif
                </dl>
            </section>

            {{-- الشروط والملاحظات --}}
            <section class="rr-card" aria-labelledby="rr-contract-terms-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-contract-terms-title" class="rr-card__title">@lang('construction::lang.terms_notes_section')</h2>
                        <p class="rr-card__desc">@lang('construction::lang.terms_notes_section_hint')</p>
                    </div>
                </header>
                @if($contract->notes)
                    <p class="rr-prose">{{ $contract->notes }}</p>
                @else
                    <div class="rr-empty-line">@lang('construction::lang.no_additional_terms')</div>
                @endif
            </section>
        </div>

        <aside class="rr-cols__side">
            {{-- الشروط المالية --}}
            <section class="rr-card" aria-labelledby="rr-contract-financial-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-contract-financial-title" class="rr-card__title">@lang('construction::lang.financial_terms_section')</h2>
                        <p class="rr-card__desc">@lang('construction::lang.financial_terms_section_hint')</p>
                    </div>
                </header>
                <dl class="rr-deflist">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.advance_payment_value')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $contract->advance_payment_value])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.retention_percent')</dt><dd><span class="rr-num">{{ @num_format((float) $contract->retention_percent) }}%</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.performance_bond_value')</dt><dd>@if((float) $contract->performance_bond_value > 0)<span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $contract->performance_bond_value])</span>@else<span class="rr-td--muted">@lang('construction::lang.not_set')</span>@endif</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.warranty_months')</dt><dd>@if($contract->warranty_months)<span class="rr-num">{{ $contract->warranty_months }}</span>@else<span class="rr-td--muted">@lang('construction::lang.not_set')</span>@endif</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.payment_terms_days')</dt><dd>@if($contract->payment_terms_days)<span class="rr-num">{{ $contract->payment_terms_days }}</span> @lang('construction::lang.days')@else<span class="rr-td--muted">@lang('construction::lang.not_set')</span>@endif</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</main>
@endsection

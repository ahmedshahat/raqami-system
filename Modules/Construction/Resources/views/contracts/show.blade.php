@extends('construction::layouts.module')
@section('title', $contract->title ?: __('construction::lang.contract'))

@section('module_content')
<section class="content ct-contract-details">
    <div class="ct-contract-detail-head">
        <div class="ct-contract-detail-head__copy"><span><i class="fa fa-file-signature"></i> @lang('construction::lang.project_contract')</span><h2>{{ $contract->title ?: __('construction::lang.construction_contract').' - '.$project->name }}</h2><p>{{ $project->name }} · {{ $contract->contract_number }}</p></div>
        <span class="ct-contract-status ct-contract-status--{{ $contract->status }}"><i class="fa fa-{{ $contract->status === 'active' ? 'check-circle' : 'pencil-ruler' }}"></i> @lang('construction::lang.'.$contract->status)</span>
    </div>

    <div class="ct-contract-detail-actions">
        @if($contract->status === 'draft')
            @can('construction.contract.manage')<a href="{{ route('construction.projects.contracts.edit', [$project->id,$contract->id]) }}" class="ct-contract-button ct-contract-button--soft"><i class="fa fa-edit"></i> @lang('construction::lang.edit')</a>@endcan
        @endif
        @if($contract->status === 'active')@can('construction.contract.manage')<a href="{{ route('construction.projects.contracts.adjustments.index', [$project->id,$contract->id]) }}" class="ct-contract-button ct-contract-button--soft"><i class="fa fa-plus-circle"></i> @lang('construction::lang.add_contract_adjustment')</a>@endcan @endif
        <a href="{{ route('construction.projects.contracts.preview', [$project->id,$contract->id]) }}" target="_blank" class="ct-contract-button ct-contract-button--soft"><i class="fa fa-eye"></i> @lang('construction::lang.preview')</a>
        <a href="{{ route('construction.projects.contracts.print', [$project->id,$contract->id]) }}" target="_blank" class="ct-contract-button ct-contract-button--soft"><i class="fa fa-print"></i> @lang('construction::lang.print')</a>
        <a href="{{ route('construction.projects.contracts.pdf', [$project->id,$contract->id]) }}" class="ct-contract-button ct-contract-button--soft"><i class="fa fa-file-pdf"></i> PDF</a>
        <a href="{{ route('construction.projects.show', $project->id) }}" class="ct-contract-button ct-contract-button--link"><i class="fa fa-arrow-right"></i> @lang('construction::lang.back_to_project')</a>
    </div>

    @if($contract->status === 'draft')
        <div class="ct-contract-state-note ct-contract-state-note--draft"><strong><i class="fa fa-info-circle"></i> @lang('construction::lang.draft_preview_notice_title')</strong><span>@lang('construction::lang.draft_preview_notice')</span></div>
    @else
        <div class="ct-contract-state-note ct-contract-state-note--active"><strong><i class="fa fa-check-circle"></i> @lang('construction::lang.contract_approved')</strong><span>@lang('construction::lang.active_contract_is_locked') @if($contract->activated_at) · {{ @format_datetime($contract->activated_at) }}@endif · {{ $contract->approved_adjustments_count }} @lang('construction::lang.approved_adjustments')</span></div>
    @endif

    <div class="ct-contract-next-action">
        <div class="ct-contract-next-action__copy"><i class="fa fa-arrow-circle-left"></i><div><strong>@lang('construction::lang.next_action')</strong><span>@lang('construction::lang.'.($contract->status === 'draft' ? 'next_approve_contract_hint' : 'next_create_measurement_hint'))</span></div></div>
        @if($contract->status === 'draft')
            @can('construction.contract.approve')<form method="POST" action="{{ route('construction.projects.contracts.activate', [$project->id,$contract->id]) }}" onsubmit="return confirm('{{ __('construction::lang.activate_contract_confirmation') }}')">@csrf<button class="ct-contract-button ct-contract-button--success" type="submit"><i class="fa fa-check"></i> @lang('construction::lang.next_approve_contract')</button></form>@endcan
        @else
            @can('construction.measurement.manage')<a href="{{ route('construction.projects.measurements.index', ['project' => $project->id, 'open_create' => 1]) }}" class="ct-contract-button ct-contract-button--success"><i class="fa fa-ruler-combined"></i> @lang('construction::lang.next_create_measurement')</a>@endcan
        @endif
    </div>

    <div class="ct-contract-detail-grid">
        <section class="ct-contract-summary-card ct-contract-summary-card--wide">
            <header><span>1</span><div><h3>@lang('construction::lang.contract_data_section')</h3><p>@lang('construction::lang.contract_data_section_hint')</p></div></header>
            <div class="ct-contract-fields">
                <div><small>@lang('construction::lang.contract_number')</small><strong>{{ $contract->contract_number }}</strong></div>
                <div><small>@lang('construction::lang.contract_title')</small><strong>{{ $contract->title }}</strong></div>
                <div><small>@lang('construction::lang.contract_type')</small><strong>@lang('construction::lang.'.$contract->contract_type)</strong></div>
                <div><small>@lang('construction::lang.signed_at')</small><strong>{{ @format_date($contract->signed_at ?: now()) }}</strong></div>
                <div><small>@lang('construction::lang.'.($project->quote_id ? 'quote_number' : 'linked_boq'))</small><strong>@if($project->quote_id)<a href="{{ route('construction.quotes.show', $project->quote_id) }}">@lang('construction::lang.view_original_quote')</a>@elseif($contract->boqVersion)<a href="{{ route('construction.projects.boq.show', [$project->id,$contract->boqVersion->id]) }}">{{ $contract->boqVersion->name }}</a>@else @lang('construction::lang.not_linked') @endif</strong></div>
                @if($project->quote)<div><small>@lang('construction::lang.quote_number')</small><strong><a href="{{ route('construction.quotes.show', $project->quote->id) }}">{{ $project->quote->number }}</a></strong></div>@endif
                <div class="ct-contract-fields__value"><small>@lang('construction::lang.original_value')</small><strong>@include('construction::partials.money', ['value' => $contract->original_value])</strong></div>
            </div>
        </section>

        <section class="ct-contract-summary-card">
            <header><span>2</span><div><h3>@lang('construction::lang.financial_terms_section')</h3><p>@lang('construction::lang.financial_terms_section_hint')</p></div></header>
            <div class="ct-contract-fields ct-contract-fields--financial">
                <div><small>@lang('construction::lang.advance_payment_value')</small><strong>@include('construction::partials.money', ['value' => $contract->advance_payment_value])</strong></div>
                <div><small>@lang('construction::lang.retention_percent')</small><strong>{{ @num_format((float)$contract->retention_percent) }}%</strong></div>
                <div><small>@lang('construction::lang.performance_bond_value')</small><strong>@if((float)$contract->performance_bond_value > 0)@include('construction::partials.money', ['value' => $contract->performance_bond_value])@else @lang('construction::lang.not_set') @endif</strong></div>
                <div><small>@lang('construction::lang.warranty_months')</small><strong>{{ $contract->warranty_months ?: __('construction::lang.not_set') }}</strong></div>
                <div><small>@lang('construction::lang.payment_terms_days')</small><strong>{{ $contract->payment_terms_days ?: __('construction::lang.not_set') }}</strong></div>
            </div>
        </section>

        <section class="ct-contract-summary-card">
            <header><span>3</span><div><h3>@lang('construction::lang.terms_notes_section')</h3><p>@lang('construction::lang.terms_notes_section_hint')</p></div></header>
            <div class="ct-contract-terms">{{ $contract->notes ?: __('construction::lang.no_additional_terms') }}</div>
        </section>
    </div>
</section>
@endsection

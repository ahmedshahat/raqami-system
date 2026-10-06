@php
    $editing = isset($contract);
    $selectedBoqId = old('boq_version_id', $contract->boq_version_id ?? request('boq_version_id', ($project->quote_id || $approvedBoqs->count() === 1) ? $approvedBoqs->first()?->id : null));
    $selectedBoq = $approvedBoqs->firstWhere('id', (int) $selectedBoqId);
    $suggestedTitle = $contract->title ?? (__('construction::lang.construction_contract') . ' - ' . $project->name);
@endphp
@if($errors->any())<div class="alert alert-danger ct-contract-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="ct-contract-editor">
    <div class="ct-contract-editor__intro">
        <div><span><i class="fa fa-file-signature"></i> @lang('construction::lang.contract_draft')</span><h2>{{ $editing ? __('construction::lang.edit_contract_draft') : __('construction::lang.create_contract_draft') }}</h2><p>@lang('construction::lang.contract_form_intro')</p></div>
        <a href="{{ route('construction.projects.show', $project->id) }}" class="ct-contract-button ct-contract-button--soft"><i class="fa fa-arrow-right"></i> @lang('construction::lang.back_to_project')</a>
    </div>
    @if($project->quote)<div class="ct-contract-alert"><i class="fa fa-file-invoice"></i><div><strong>@lang('construction::lang.quote_number'): <a href="{{ route('construction.quotes.show', $project->quote->id) }}">{{ $project->quote->number }}</a></strong><p>@lang('construction::lang.quote_contract_source')</p></div></div>@endif

    @if($approvedBoqs->isEmpty())
        <div class="ct-contract-alert"><i class="fa fa-info-circle"></i><div><strong>@lang('construction::lang.no_approved_boq_for_contract_simple')</strong><p>@lang('construction::lang.approve_boq_before_contract')</p><a href="{{ route('construction.projects.boq.index', $project->id) }}">@lang('construction::lang.prepare_boq_first')</a></div></div>
    @endif

    <section class="ct-contract-card">
        <header><span class="ct-contract-card__number">1</span><div><h3>@lang('construction::lang.contract_data_section')</h3><p>@lang('construction::lang.contract_data_section_hint')</p></div></header>
        <div class="ct-contract-grid">
            <div class="form-group"><label>@lang('construction::lang.contract_number')</label><div class="ct-contract-value"><i class="fa fa-lock"></i><input class="form-control" readonly value="{{ $contract->contract_number ?? $suggestedContractNumber }}"></div><small>@lang('construction::lang.contract_number_auto_hint')</small></div>
            <div class="form-group ct-contract-grid__wide"><label>@lang('construction::lang.contract_title') *</label><input name="title" class="form-control" maxlength="190" required value="{{ old('title', $suggestedTitle) }}"></div>
            @if($project->quote_id)<input type="hidden" name="boq_version_id" value="{{ $selectedBoqId }}"><div class="form-group ct-contract-grid__wide"><label>@lang('construction::lang.view_original_quote')</label><div><a href="{{ route('construction.quotes.show', $project->quote_id) }}">@lang('construction::lang.view_original_quote') <i class="fa fa-arrow-left"></i></a></div></div>@else
            <div class="form-group ct-contract-grid__wide"><label>@lang('construction::lang.linked_approved_boq') *</label><select name="boq_version_id" id="contract-boq-version" class="form-control" required><option value="" data-total="">@lang('construction::lang.choose_approved_boq')</option>@foreach($approvedBoqs as $boq)<option value="{{ $boq->id }}" data-total="{{ $boq->sales_total }}" @selected((string)$selectedBoqId === (string)$boq->id)>#{{ $boq->version_number }} — {{ $boq->name }} ({{ @num_format((float)$boq->sales_total) }})</option>@endforeach</select><small>@lang('construction::lang.boq_controls_contract_value_strict')</small></div>@endif
            <div class="form-group"><label class="ct-label-with-info">@lang('construction::lang.contract_type') * <button type="button" class="ct-info-button" aria-label="@lang('construction::lang.contract_type_help')"><i class="fa fa-info"></i><span class="ct-info-popover"><b>@lang('construction::lang.fixed_price')</b> — @lang('construction::lang.fixed_price_help')<br><b>@lang('construction::lang.remeasurement')</b> — @lang('construction::lang.remeasurement_help')<br><b>@lang('construction::lang.mixed')</b> — @lang('construction::lang.mixed_help')<br><b>@lang('construction::lang.cost_plus')</b> — @lang('construction::lang.cost_plus_help')</span></button></label><select name="contract_type" class="form-control" required>@foreach(\Modules\Construction\Entities\ConstructionContract::TYPES as $value)<option value="{{ $value }}" @selected(old('contract_type',$contract->contract_type ?? 'remeasurement') === $value)>@lang('construction::lang.'.$value)</option>@endforeach</select></div>
            <div class="form-group"><label>@lang('construction::lang.signed_at')</label><input type="text" name="signed_at" class="form-control ct-date-picker" readonly value="{{ old('signed_at', !empty($contract->signed_at) ? @format_date($contract->signed_at) : @format_date(now())) }}"><small>@lang('construction::lang.signed_date_required_for_activation')</small></div>
            <div class="form-group"><label>@lang('construction::lang.original_value')</label><div class="ct-contract-value {{ session('business.currency_symbol_placement') === 'before' ? 'ct-contract-value--before' : '' }}"><i class="fa fa-lock"></i><input type="text" name="original_value" id="contract-original-value" class="form-control input_number" value="{{ @num_format((float) ($selectedBoq?->sales_total ?? 0)) }}" readonly>@if(strtoupper((string) session('currency.code')) === 'SAR')<img class="sar-currency-icon" src="{{ asset('img/saudi-riyal-new.svg') }}" alt="ريال سعودي" width="10" height="11">@else<span>{{ session('currency.symbol') }}</span>@endif</div><small>@lang('construction::lang.contract_value_readonly_hint')</small></div>
        </div>
    </section>

    <section class="ct-contract-card">
        <header><span class="ct-contract-card__number">2</span><div><h3>@lang('construction::lang.financial_terms_section')</h3><p>@lang('construction::lang.financial_terms_section_hint')</p></div></header>
        <div class="ct-contract-grid ct-contract-grid--five">
            <div class="form-group"><label>@lang('construction::lang.advance_payment_value')</label><input type="text" name="advance_payment_value" class="form-control input_number" value="{{ old('advance_payment_value', @num_format($contract->advance_payment_value ?? 0)) }}"></div>
            <div class="form-group"><label>@lang('construction::lang.retention_percent')</label><div class="ct-contract-suffix"><input type="text" name="retention_percent" class="form-control input_number" value="{{ old('retention_percent', @num_format($contract->retention_percent ?? 0)) }}"><span>%</span></div></div>
            <div class="form-group"><label>@lang('construction::lang.performance_bond_value') <small class="ct-optional">@lang('construction::lang.optional')</small></label><input type="text" name="performance_bond_value" class="form-control input_number" value="{{ old('performance_bond_value', isset($contract) && $contract->performance_bond_value !== null ? @num_format($contract->performance_bond_value) : '') }}"></div>
            <div class="form-group"><label>@lang('construction::lang.warranty_months') <small class="ct-optional">@lang('construction::lang.optional')</small></label><input type="number" min="0" name="warranty_months" class="form-control" value="{{ old('warranty_months',$contract->warranty_months ?? '') }}"></div>
            <div class="form-group"><label>@lang('construction::lang.payment_terms_days')</label><input type="number" min="0" name="payment_terms_days" class="form-control" value="{{ old('payment_terms_days',$contract->payment_terms_days ?? '') }}"></div>
        </div>
    </section>

    <section class="ct-contract-card">
        <header><span class="ct-contract-card__number">3</span><div><h3>@lang('construction::lang.terms_notes_section')</h3><p>@lang('construction::lang.terms_notes_section_hint')</p></div></header>
        <div class="form-group ct-contract-notes"><textarea name="notes" class="form-control" rows="8" placeholder="@lang('construction::lang.contract_notes_placeholder')">{{ old('notes',$contract->notes ?? '') }}</textarea></div>
    </section>

    <div class="ct-contract-editor__footer"><a href="{{ route('construction.projects.show', $project->id) }}" class="ct-contract-button ct-contract-button--soft">@lang('messages.cancel')</a><button class="ct-contract-button ct-contract-button--primary" type="submit" @disabled($approvedBoqs->isEmpty())><i class="fa fa-save"></i> @lang('construction::lang.save_contract_draft')</button></div>
</div>

@section('module_javascript')
<script>
jQuery(function ($) {
    var boq = document.getElementById('contract-boq-version');
    var value = document.getElementById('contract-original-value');
    if (!boq || !value) return;
    function syncValue() {
        var option = boq.options[boq.selectedIndex];
        __write_number($(value), option && option.dataset.total !== '' ? Number(option.dataset.total) : 0);
    }
    boq.addEventListener('change', syncValue);
    syncValue();
});
</script>
@endsection

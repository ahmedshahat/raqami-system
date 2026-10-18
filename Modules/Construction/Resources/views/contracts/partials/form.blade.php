@php
    $editing = isset($contract);
    $selectedBoqId = old('boq_version_id', $contract->boq_version_id ?? request('boq_version_id', ($project->quote_id || $approvedBoqs->count() === 1) ? $approvedBoqs->first()?->id : null));
    $selectedBoq = $approvedBoqs->firstWhere('id', (int) $selectedBoqId);
    $suggestedTitle = $contract->title ?? (__('construction::lang.construction_contract') . ' - ' . $project->name);
@endphp
@if($errors->any())<div class="alert alert-danger ct-contract-errors" id="ct-contract-errors" role="alert" aria-labelledby="ct-contract-errors-title" tabindex="-1"><strong id="ct-contract-errors-title"><i class="fa fa-exclamation-circle" aria-hidden="true"></i> @lang('construction::lang.contract_validation_summary')</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

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
            <div class="form-group ct-contract-field--number"><label for="contract-number">@lang('construction::lang.contract_number')</label><div class="ct-contract-value"><i class="fa fa-lock" aria-hidden="true"></i><input id="contract-number" class="form-control" readonly value="{{ $contract->contract_number ?? $suggestedContractNumber }}"></div><small>@lang('construction::lang.contract_number_auto_hint')</small></div>
            <div class="form-group ct-contract-field--title"><label for="contract-title">@lang('construction::lang.contract_title') <span class="ct-required" aria-hidden="true">*</span></label><input id="contract-title" name="title" class="form-control @error('title') is-invalid @enderror" maxlength="190" required aria-describedby="contract-title-error" value="{{ old('title', $suggestedTitle) }}">@error('title')<small id="contract-title-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>
            @if($project->quote_id)<input type="hidden" name="boq_version_id" value="{{ $selectedBoqId }}"><div class="form-group ct-contract-field--source"><label>@lang('construction::lang.view_original_quote')</label><a class="ct-contract-source-link" href="{{ route('construction.quotes.show', $project->quote_id) }}"><i class="fa fa-file-invoice" aria-hidden="true"></i><span><strong>{{ $project->quote->number }}</strong><small>@lang('construction::lang.view_original_quote')</small></span><i class="fa fa-arrow-left" aria-hidden="true"></i></a></div>@else
            <div class="form-group ct-contract-field--source"><label for="contract-boq-version">@lang('construction::lang.linked_approved_boq') <span class="ct-required" aria-hidden="true">*</span></label><select name="boq_version_id" id="contract-boq-version" class="form-control @error('boq_version_id') is-invalid @enderror" required aria-describedby="contract-boq-version-hint contract-boq-version-error"><option value="" data-total="">@lang('construction::lang.choose_approved_boq')</option>@foreach($approvedBoqs as $boq)<option value="{{ $boq->id }}" data-total="{{ $boq->sales_total }}" @selected((string)$selectedBoqId === (string)$boq->id)>#{{ $boq->version_number }} — {{ $boq->name }} ({{ @num_format((float)$boq->sales_total) }})</option>@endforeach</select><small id="contract-boq-version-hint">@lang('construction::lang.boq_controls_contract_value_strict')</small>@error('boq_version_id')<small id="contract-boq-version-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>@endif
            <div class="form-group ct-contract-field--type"><label for="contract-type" class="ct-label-with-info">@lang('construction::lang.contract_type') <span class="ct-required" aria-hidden="true">*</span> <button type="button" class="ct-info-button" aria-label="@lang('construction::lang.contract_type_help')"><i class="fa fa-info" aria-hidden="true"></i><span class="ct-info-popover"><b>@lang('construction::lang.fixed_price')</b> — @lang('construction::lang.fixed_price_help')<br><b>@lang('construction::lang.remeasurement')</b> — @lang('construction::lang.remeasurement_help')<br><b>@lang('construction::lang.mixed')</b> — @lang('construction::lang.mixed_help')<br><b>@lang('construction::lang.cost_plus')</b> — @lang('construction::lang.cost_plus_help')</span></button></label><select id="contract-type" name="contract_type" class="form-control @error('contract_type') is-invalid @enderror" required aria-describedby="contract-type-error">@foreach(\Modules\Construction\Entities\ConstructionContract::TYPES as $value)<option value="{{ $value }}" @selected(old('contract_type',$contract->contract_type ?? 'remeasurement') === $value)>@lang('construction::lang.'.$value)</option>@endforeach</select>@error('contract_type')<small id="contract-type-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>
            <div class="form-group ct-contract-field--date"><label for="contract-signed-at">@lang('construction::lang.signed_at')</label><input id="contract-signed-at" type="text" name="signed_at" class="form-control ct-date-picker @error('signed_at') is-invalid @enderror" readonly aria-describedby="contract-signed-at-hint contract-signed-at-error" value="{{ old('signed_at', !empty($contract->signed_at) ? @format_date($contract->signed_at) : @format_date(now())) }}"><small id="contract-signed-at-hint">@lang('construction::lang.signed_date_required_for_activation')</small>@error('signed_at')<small id="contract-signed-at-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>
            @php($contractMoney = \Modules\Construction\Support\CurrencyFormatter::parts(0))
            <div class="form-group ct-contract-field--value"><label for="contract-original-value">@lang('construction::lang.original_value')</label><div class="ct-contract-value {{ $contractMoney['position'] === 'before' ? 'ct-contract-value--before' : 'ct-contract-value--after' }}"><i class="fa fa-lock" aria-hidden="true"></i><input type="text" name="original_value" id="contract-original-value" class="form-control input_number" value="{{ @num_format((float) ($selectedBoq?->sales_total ?? 0)) }}" readonly><span class="ct-contract-currency-symbol">@include('construction::partials.money_symbol', ['money' => $contractMoney, 'isSaudiRiyal' => $contractMoney['code'] === 'SAR', 'symbolSource' => asset('img/saudi-riyal-new.svg')])</span></div><small>@lang('construction::lang.contract_value_readonly_hint')</small></div>
        </div>
    </section>

    <section class="ct-contract-card">
        <header><span class="ct-contract-card__number">2</span><div><h3>@lang('construction::lang.financial_terms_section')</h3><p>@lang('construction::lang.financial_terms_section_hint')</p></div></header>
        <div class="ct-contract-grid ct-contract-grid--five">
            <div class="form-group"><label for="contract-advance">@lang('construction::lang.advance_payment_value')</label><input id="contract-advance" type="text" name="advance_payment_value" class="form-control input_number @error('advance_payment_value') is-invalid @enderror" aria-describedby="contract-advance-error" value="{{ old('advance_payment_value', @num_format($contract->advance_payment_value ?? 0)) }}">@error('advance_payment_value')<small id="contract-advance-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label for="contract-retention">@lang('construction::lang.retention_percent')</label><div class="ct-contract-suffix"><input id="contract-retention" type="text" name="retention_percent" class="form-control input_number @error('retention_percent') is-invalid @enderror" aria-describedby="contract-retention-error" value="{{ old('retention_percent', @num_format($contract->retention_percent ?? 0)) }}"><span>%</span></div>@error('retention_percent')<small id="contract-retention-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label for="contract-bond">@lang('construction::lang.performance_bond_value') <small class="ct-optional">@lang('construction::lang.optional')</small></label><input id="contract-bond" type="text" name="performance_bond_value" class="form-control input_number @error('performance_bond_value') is-invalid @enderror" aria-describedby="contract-bond-error" value="{{ old('performance_bond_value', isset($contract) && $contract->performance_bond_value !== null ? @num_format($contract->performance_bond_value) : '') }}">@error('performance_bond_value')<small id="contract-bond-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label for="contract-warranty">@lang('construction::lang.warranty_months') <small class="ct-optional">@lang('construction::lang.optional')</small></label><input id="contract-warranty" type="number" min="0" inputmode="numeric" name="warranty_months" class="form-control @error('warranty_months') is-invalid @enderror" aria-describedby="contract-warranty-error" value="{{ old('warranty_months',$contract->warranty_months ?? '') }}">@error('warranty_months')<small id="contract-warranty-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label for="contract-payment-days">@lang('construction::lang.payment_terms_days')</label><input id="contract-payment-days" type="number" min="0" inputmode="numeric" name="payment_terms_days" class="form-control @error('payment_terms_days') is-invalid @enderror" aria-describedby="contract-payment-days-error" value="{{ old('payment_terms_days',$contract->payment_terms_days ?? '') }}">@error('payment_terms_days')<small id="contract-payment-days-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>
        </div>
    </section>

    <section class="ct-contract-card">
        <header><span class="ct-contract-card__number">3</span><div><h3>@lang('construction::lang.terms_notes_section')</h3><p>@lang('construction::lang.terms_notes_section_hint')</p></div></header>
        <div class="form-group ct-contract-notes"><label for="contract-notes">@lang('construction::lang.terms_notes_section')</label><textarea id="contract-notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="5" aria-describedby="contract-notes-error" placeholder="@lang('construction::lang.contract_notes_placeholder')">{{ old('notes',$contract->notes ?? '') }}</textarea>@error('notes')<small id="contract-notes-error" class="ct-field-error" role="alert">{{ $message }}</small>@enderror</div>
    </section>

    <div class="ct-contract-editor__footer"><span class="ct-contract-save-hint"><i class="fa fa-shield-alt" aria-hidden="true"></i> @lang('construction::lang.contract_draft_save_hint')</span><a href="{{ route('construction.projects.show', $project->id) }}" class="ct-contract-button ct-contract-button--soft">@lang('messages.cancel')</a><button class="ct-contract-button ct-contract-button--primary" type="submit" @disabled($approvedBoqs->isEmpty())><i class="fa fa-save" aria-hidden="true"></i> <span>@lang('construction::lang.save_contract_draft')</span></button></div>
</div>

@section('module_javascript')
<script>
jQuery(function ($) {
    var errorSummary = document.getElementById('ct-contract-errors');
    if (errorSummary) errorSummary.focus();
    $('.ct-contract-form').on('submit', function () {
        var button = $(this).find('button[type="submit"]');
        if (!this.checkValidity() || button.prop('disabled')) return;
        button.prop('disabled', true).attr('aria-busy', 'true');
        button.find('i').attr('class', 'fa fa-spinner fa-spin');
    });
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

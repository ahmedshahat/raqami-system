@php
    $editing = isset($contract);
    $selectedBoqId = old('boq_version_id', $contract->boq_version_id ?? request('boq_version_id', ($project->quote_id || $approvedBoqs->count() === 1) ? $approvedBoqs->first()?->id : null));
    $selectedBoq = $approvedBoqs->firstWhere('id', (int) $selectedBoqId);
    $suggestedTitle = $contract->title ?? (__('construction::lang.construction_contract') . ' - ' . $project->name);
    $contractMoney = \Modules\Construction\Support\CurrencyFormatter::parts(0);
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
    $fieldError = fn ($field) => $errors->has($field) ? 'rr-control--invalid' : '';
@endphp

@if($errors->any())
    <div class="rr-alert rr-alert--danger" id="ct-contract-errors" role="alert" aria-labelledby="ct-contract-errors-title" tabindex="-1">
        <div>
            <strong id="ct-contract-errors-title">@lang('construction::lang.contract_validation_summary')</strong>
            <ul class="rr-error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    </div>
@endif

@if($project->quote)
    <div class="rr-callout rr-callout--info">
        <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 20])</span>
        <div class="rr-callout__copy">
            <strong>@lang('construction::lang.quote_number'): <a class="rr-code-link" href="{{ route('construction.quotes.show', $project->quote->id) }}"><span class="rr-code">{{ $project->quote->number }}</span></a></strong>
            <p>@lang('construction::lang.quote_contract_source')</p>
        </div>
    </div>
@endif

@if($approvedBoqs->isEmpty())
    <div class="rr-callout rr-callout--danger">
        <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 20])</span>
        <div class="rr-callout__copy">
            <strong>@lang('construction::lang.no_approved_boq_for_contract_simple')</strong>
            <p>@lang('construction::lang.approve_boq_before_contract')</p>
        </div>
        <div class="rr-callout__actions"><a class="rr-btn rr-btn--primary rr-btn--sm" href="{{ route('construction.projects.boq.index', $project->id) }}">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 16])@lang('construction::lang.prepare_boq_first')</a></div>
    </div>
@endif

{{-- 1. بيانات العقد --}}
<section class="rr-card rr-form-section" aria-labelledby="rr-contract-data">
    <header class="rr-card__head">
        <span class="rr-step-num" aria-hidden="true">1</span>
        <div class="rr-card__titles">
            <h2 id="rr-contract-data" class="rr-card__title">@lang('construction::lang.contract_data_section')</h2>
            <p class="rr-card__desc">@lang('construction::lang.contract_data_section_hint')</p>
        </div>
    </header>
    <div class="rr-card__body">
        <div class="rr-form-wrap">
            <div class="rr-form-grid">
                <div class="rr-field">
                    <label class="rr-field__label" for="contract-number">@lang('construction::lang.contract_number')</label>
                    <div class="rr-locked">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 16])<input id="contract-number" class="rr-control__input rr-control__input--num" readonly value="{{ $contract->contract_number ?? $suggestedContractNumber }}" aria-describedby="contract-number-hint"></div>
                    <div class="rr-field__hint" id="contract-number-hint">@lang('construction::lang.contract_number_auto_hint')</div>
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="contract-signed-at">@lang('construction::lang.signed_at')</label>
                    <div class="rr-control rr-control--visible {{ $fieldError('signed_at') }}"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="contract-signed-at" type="text" name="signed_at" class="rr-control__input ct-date-picker" readonly aria-describedby="contract-signed-at-hint contract-signed-at-error" value="{{ old('signed_at', ! empty($contract->signed_at) ? @format_date($contract->signed_at) : @format_date(now())) }}"></div>
                    <div class="rr-field__hint" id="contract-signed-at-hint">@lang('construction::lang.signed_date_required_for_activation')</div>
                    @error('signed_at')<div class="rr-field__error" id="contract-signed-at-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="rr-field rr-field--full">
                    <label class="rr-field__label" for="contract-title">@lang('construction::lang.contract_title'){!! $req !!}</label>
                    <div class="rr-control {{ $fieldError('title') }}"><input id="contract-title" name="title" class="rr-control__input" maxlength="190" required aria-describedby="contract-title-error" value="{{ old('title', $suggestedTitle) }}"></div>
                    @error('title')<div class="rr-field__error" id="contract-title-error" role="alert">{{ $message }}</div>@enderror
                </div>

                @if($project->quote_id)
                    <input type="hidden" name="boq_version_id" value="{{ $selectedBoqId }}">
                    <div class="rr-field">
                        <span class="rr-field__label">@lang('construction::lang.view_original_quote')</span>
                        <a class="rr-locked rr-code-link" href="{{ route('construction.quotes.show', $project->quote_id) }}">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 16])<span class="rr-code">{{ $project->quote->number }}</span></a>
                    </div>
                @else
                    <div class="rr-field">
                        <label class="rr-field__label" for="contract-boq-version">@lang('construction::lang.linked_approved_boq'){!! $req !!}</label>
                        <div class="rr-control {{ $fieldError('boq_version_id') }}">
                            <select name="boq_version_id" id="contract-boq-version" class="rr-control__input rr-control__select" required aria-describedby="contract-boq-version-hint contract-boq-version-error">
                                <option value="" data-total="">@lang('construction::lang.choose_approved_boq')</option>
                                @foreach($approvedBoqs as $boq)<option value="{{ $boq->id }}" data-total="{{ $boq->sales_total }}" @selected((string) $selectedBoqId === (string) $boq->id)>#{{ $boq->version_number }} — {{ $boq->name }} ({{ @num_format((float) $boq->sales_total) }})</option>@endforeach
                            </select>
                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                        </div>
                        <div class="rr-field__hint" id="contract-boq-version-hint">@lang('construction::lang.boq_controls_contract_value_strict')</div>
                        @error('boq_version_id')<div class="rr-field__error" id="contract-boq-version-error" role="alert">{{ $message }}</div>@enderror
                    </div>
                @endif

                <div class="rr-field">
                    <label class="rr-field__label" for="contract-original-value">@lang('construction::lang.original_value')</label>
                    <div class="rr-locked">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 16])<input type="text" name="original_value" id="contract-original-value" class="rr-control__input rr-control__input--num input_number" value="{{ @num_format((float) ($selectedBoq?->sales_total ?? 0)) }}" readonly aria-describedby="contract-original-value-hint"><span class="rr-money__cur">@include('construction::partials.money_symbol', ['money' => $contractMoney, 'isSaudiRiyal' => $contractMoney['code'] === 'SAR', 'symbolSource' => asset('img/saudi-riyal-new.svg')])</span></div>
                    <div class="rr-field__hint" id="contract-original-value-hint">@lang('construction::lang.contract_value_readonly_hint')</div>
                </div>

                <div class="rr-field rr-field--full">
                    <label class="rr-field__label" for="contract-type">@lang('construction::lang.contract_type'){!! $req !!}</label>
                    <div class="rr-control {{ $fieldError('contract_type') }}">
                        <select id="contract-type" name="contract_type" class="rr-control__input rr-control__select" required aria-describedby="contract-type-help contract-type-error">
                            @foreach(\Modules\Construction\Entities\ConstructionContract::TYPES as $value)<option value="{{ $value }}" @selected(old('contract_type', $contract->contract_type ?? 'remeasurement') === $value)>@lang('construction::lang.' . $value)</option>@endforeach
                        </select>
                        <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                    </div>
                    @error('contract_type')<div class="rr-field__error" id="contract-type-error" role="alert">{{ $message }}</div>@enderror
                    <details class="rr-help" id="contract-type-help">
                        <summary>@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.contract_type_help')</summary>
                        <dl>
                            <div><dt>@lang('construction::lang.fixed_price')</dt><dd>@lang('construction::lang.fixed_price_help')</dd></div>
                            <div><dt>@lang('construction::lang.remeasurement')</dt><dd>@lang('construction::lang.remeasurement_help')</dd></div>
                            <div><dt>@lang('construction::lang.mixed')</dt><dd>@lang('construction::lang.mixed_help')</dd></div>
                            <div><dt>@lang('construction::lang.cost_plus')</dt><dd>@lang('construction::lang.cost_plus_help')</dd></div>
                        </dl>
                    </details>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 2. الشروط المالية --}}
<section class="rr-card rr-form-section" aria-labelledby="rr-contract-financial">
    <header class="rr-card__head">
        <span class="rr-step-num" aria-hidden="true">2</span>
        <div class="rr-card__titles">
            <h2 id="rr-contract-financial" class="rr-card__title">@lang('construction::lang.financial_terms_section')</h2>
            <p class="rr-card__desc">@lang('construction::lang.financial_terms_section_hint')</p>
        </div>
    </header>
    <div class="rr-card__body">
        <div class="rr-form-wrap">
            <div class="rr-form-grid">
                <div class="rr-field">
                    <label class="rr-field__label" for="contract-advance">@lang('construction::lang.advance_payment_value')</label>
                    <div class="rr-control {{ $fieldError('advance_payment_value') }}"><input id="contract-advance" type="text" name="advance_payment_value" class="rr-control__input rr-control__input--num input_number" aria-describedby="contract-advance-error" value="{{ old('advance_payment_value', @num_format($contract->advance_payment_value ?? 0)) }}"></div>
                    @error('advance_payment_value')<div class="rr-field__error" id="contract-advance-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="contract-retention">@lang('construction::lang.retention_percent')</label>
                    <div class="rr-control {{ $fieldError('retention_percent') }}"><input id="contract-retention" type="text" name="retention_percent" class="rr-control__input rr-control__input--num input_number" aria-describedby="contract-retention-error" value="{{ old('retention_percent', @num_format($contract->retention_percent ?? 0)) }}"><span class="rr-control__affix">%</span></div>
                    @error('retention_percent')<div class="rr-field__error" id="contract-retention-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="contract-bond">@lang('construction::lang.performance_bond_value') <span class="rr-field__opt">(@lang('construction::lang.optional'))</span></label>
                    <div class="rr-control {{ $fieldError('performance_bond_value') }}"><input id="contract-bond" type="text" name="performance_bond_value" class="rr-control__input rr-control__input--num input_number" aria-describedby="contract-bond-error" value="{{ old('performance_bond_value', isset($contract) && $contract->performance_bond_value !== null ? @num_format($contract->performance_bond_value) : '') }}"></div>
                    @error('performance_bond_value')<div class="rr-field__error" id="contract-bond-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="contract-warranty">@lang('construction::lang.warranty_months') <span class="rr-field__opt">(@lang('construction::lang.optional'))</span></label>
                    <div class="rr-control {{ $fieldError('warranty_months') }}"><input id="contract-warranty" type="number" min="0" inputmode="numeric" name="warranty_months" class="rr-control__input rr-control__input--num" aria-describedby="contract-warranty-error" value="{{ old('warranty_months', $contract->warranty_months ?? '') }}"></div>
                    @error('warranty_months')<div class="rr-field__error" id="contract-warranty-error" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="contract-payment-days">@lang('construction::lang.payment_terms_days')</label>
                    <div class="rr-control {{ $fieldError('payment_terms_days') }}"><input id="contract-payment-days" type="number" min="0" inputmode="numeric" name="payment_terms_days" class="rr-control__input rr-control__input--num" aria-describedby="contract-payment-days-error" value="{{ old('payment_terms_days', $contract->payment_terms_days ?? '') }}"><span class="rr-control__affix">@lang('construction::lang.days')</span></div>
                    @error('payment_terms_days')<div class="rr-field__error" id="contract-payment-days-error" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 3. الشروط والملاحظات --}}
<section class="rr-card rr-form-section" aria-labelledby="rr-contract-terms">
    <header class="rr-card__head">
        <span class="rr-step-num" aria-hidden="true">3</span>
        <div class="rr-card__titles">
            <h2 id="rr-contract-terms" class="rr-card__title">@lang('construction::lang.terms_notes_section')</h2>
            <p class="rr-card__desc">@lang('construction::lang.terms_notes_section_hint')</p>
        </div>
    </header>
    <div class="rr-card__body">
        <div class="rr-field">
            <label class="rr-field__label rr-sr" for="contract-notes">@lang('construction::lang.terms_notes_section')</label>
            <div class="rr-control rr-control--area {{ $fieldError('notes') }}"><textarea id="contract-notes" name="notes" class="rr-control__input" rows="5" aria-describedby="contract-notes-error" placeholder="@lang('construction::lang.contract_notes_placeholder')">{{ old('notes', $contract->notes ?? '') }}</textarea></div>
            @error('notes')<div class="rr-field__error" id="contract-notes-error" role="alert">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

<footer class="rr-form-actions">
    <button class="rr-btn rr-btn--primary" type="submit" @disabled($approvedBoqs->isEmpty())>@include('construction::partials.rr_icon', ['name' => 'check'])<span>@lang('construction::lang.save_contract_draft')</span></button>
    <a href="{{ route('construction.projects.show', $project->id) }}" class="rr-btn rr-btn--secondary">@lang('messages.cancel')</a>
    <span class="rr-form-actions__note">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 16])@lang('construction::lang.contract_draft_save_hint')</span>
</footer>

@section('module_javascript')
<script>
jQuery(function ($) {
    var errorSummary = document.getElementById('ct-contract-errors');
    if (errorSummary) errorSummary.focus();
    $('.ct-contract-form').on('submit', function () {
        var button = $(this).find('button[type="submit"]');
        if (!this.checkValidity() || button.prop('disabled')) return;
        button.prop('disabled', true).attr('aria-busy', 'true');
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

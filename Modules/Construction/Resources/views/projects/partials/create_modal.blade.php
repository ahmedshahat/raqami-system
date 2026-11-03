@php $req = '<span class="rr-field__req" aria-hidden="true">*</span>'; @endphp
<div class="modal fade rr-scope" id="ct-project-create" tabindex="-1" role="dialog" aria-labelledby="rr-project-create-title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <form method="POST" action="{{ route('construction.projects.store') }}" data-ct-quick-create data-ct-open-mode="redirect">
                @csrf
                <input type="hidden" name="code" value="{{ $suggestedCode }}">
                <input type="hidden" name="status" value="draft">
                <input type="hidden" name="consultant_name" value="">

                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr-project-create-title">@lang('construction::lang.new_project')</h2>
                        <p class="rr-modal__desc"><span class="rr-code">{{ $suggestedCode }}</span> · @lang('construction::lang.project_activates_with_contract')</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                </header>

                <div class="rr-modal__body">
                    <div class="rr-alert rr-alert--danger ct-quick-errors" role="alert"></div>
                    <div class="rr-form-wrap">
                        <div class="rr-form-grid">
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-project-quote">@lang('construction::lang.import_from_quote') <span class="rr-field__opt">(@lang('construction::lang.optional'))</span></label>
                                <div class="rr-control">
                                    <select name="quote_id" id="ct-project-quote" class="rr-control__input rr-control__select" aria-describedby="ct-project-quote-hint">
                                        <option value="">@lang('construction::lang.without_quote')</option>
                                        @foreach($acceptedQuotes as $quote)<option value="{{ $quote->id }}" data-customer="{{ $quote->customer_id }}" data-title="{{ $quote->title }}" @selected((string) request('quote_id') === (string) $quote->id)>{{ $quote->number }} — {{ $quote->title }} — {{ $quote->customer?->supplier_business_name ?: $quote->customer?->name }}</option>@endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                                <div class="rr-field__hint" id="ct-project-quote-hint">@lang('construction::lang.accepted_quotes_only_hint')</div>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-project-name">@lang('construction::lang.project_name'){!! $req !!}</label>
                                <div class="rr-control"><input name="name" id="ct-project-name" class="rr-control__input" maxlength="190" required value="{{ old('name') }}"></div>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-project-customer">@lang('construction::lang.customer'){!! $req !!}</label>
                                <div class="rr-control">
                                    <select name="customer_id" id="ct-project-customer" class="rr-control__input rr-control__select" required>
                                        <option value="">@lang('construction::lang.choose')</option>
                                        @foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-project-manager">@lang('construction::lang.project_manager')</label>
                                <div class="rr-control">
                                    <select id="ct-project-manager" name="manager_id" class="rr-control__input rr-control__select">
                                        <option value="">@lang('construction::lang.choose')</option>
                                        @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->user_full_name ?: $user->username }}</option>@endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-project-consultant">@lang('construction::lang.consultant')</label>
                                <div class="rr-control">
                                    <select id="ct-project-consultant" name="consultant_selection" class="rr-control__input rr-control__select" required>
                                        <option value="none">@lang('construction::lang.no_consultant')</option>
                                        @foreach($consultants as $contact)<option value="{{ $contact->id }}">{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-project-location">@lang('construction::lang.location')</label>
                                <div class="rr-control"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'map-pin', 'size' => 18])</span><input id="ct-project-location" name="location" class="rr-control__input" maxlength="255"></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-project-start-date">@lang('construction::lang.start_date')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="ct-project-start-date" type="text" name="start_date" class="rr-control__input ct-date-picker" readonly></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-project-end-date">@lang('construction::lang.end_date')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="ct-project-end-date" type="text" name="end_date" class="rr-control__input ct-date-picker" readonly></div>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-project-description">@lang('construction::lang.notes') <span class="rr-field__opt">(@lang('construction::lang.optional'))</span></label>
                                <div class="rr-control rr-control--area"><textarea id="ct-project-description" name="description" class="rr-control__input rr-control__input--short" rows="2" maxlength="5000"></textarea></div>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="rr-modal__foot">
                    <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.create_project')</button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                </footer>
            </form>
        </div>
    </div>
</div>
@push('ct_quick_js')
<script>
$(function () {
    $('.ct-date-picker').datetimepicker({format: moment_date_format, ignoreReadonly: true});
    // تواريخ النافذة في آخرها، فالتقويم يفتح لفوق عشان جسم النافذة ما يقصّوش
    $('#ct-project-create .ct-date-picker').each(function () {
        var picker = $(this).data('DateTimePicker');
        if (picker) picker.widgetPositioning({horizontal: 'auto', vertical: 'top'});
    });
    function applyQuote() {
        var option = $('#ct-project-quote option:selected');
        if (!option.val()) { $('#ct-project-customer').prop('disabled', false); return; }
        $('#ct-project-customer').val(String(option.data('customer'))).trigger('change').prop('disabled', false);
        if (!$('#ct-project-name').val()) $('#ct-project-name').val(option.data('title'));
    }
    $('#ct-project-quote').on('change', applyQuote); applyQuote();
    @if(request('quote_id') || request('open_create')) $('#ct-project-create').modal('show'); @endif
});
</script>
@endpush

{{--
    نافذة "إضافة مصروف" من الكاشير بتصميم رقمي (pos.js بيحمّلها في #expense_modal من /expenses/create وبيحفظها بالـ ajax).
    المهم للكاشير قدام: المبلغ والنوع والوصف وطريقة الدفع. المدفوع بيمشي مع المبلغ لحد ما الكاشير يغيّره بإيده.
    الباقي (الفرع لو معروف، المرجع، التاريخ، المصروف لـ، الضريبة، ربط المقاولات) جوه "بيانات إضافية".
    نفس أسماء الحقول والـ ids القديمة اللي pos.js و app.js بيعتمدوا عليها.
--}}
@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $default_location = count($business_locations) == 1 ? current(array_keys($business_locations->toArray())) : request()->input('location_id');
    $location_name = $default_location ? ($business_locations[$default_location] ?? null) : null;
    $location_in_main = count($business_locations) > 1 && empty($location_name);
    $row_index = 0;
    // لو الفرع معروف: طرق الدفع المفعّلة في الفرع بس (مش الـ 7 المخصصة كلها)
    if (!empty($default_location)) {
        $payment_types = (new \App\Utils\Util())->payment_types($default_location, false, session('user.business_id')) ?: $payment_types;
    }
    $method = array_key_exists('cash', $payment_types) ? 'cash' : array_key_first($payment_types);
    $payment_line['method'] = $method;
    $dialog_id = 'rr_exp_' . uniqid();
    $location_field = function () use ($business_locations, $default_location, $bl_attributes) {
        return '<div class="rr-field"><label class="rr-field__label" for="expense_location_id">' . e(__('expense_quick.location')) . '<span class="rr-field__req" aria-hidden="true">*</span></label>'
            . Form::select('location_id', $business_locations, $default_location, ['class' => 'form-control', 'placeholder' => __('messages.please_select'), 'required', 'id' => 'expense_location_id'], $bl_attributes)
            . '</div>';
    };
@endphp
<div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact rr-scope rr-exp" role="document" id="{{ $dialog_id }}">
    <div class="modal-content rr-modal">
        {!! Form::open(['url' => action([\App\Http\Controllers\ExpenseController::class, 'store']), 'method' => 'post', 'id' => 'add_expense_modal_form', 'files' => true]) !!}
        <header class="rr-modal__head">
            <div class="rr-modal__titles">
                <h2 class="rr-modal__title">@lang('expense_quick.title')</h2>
                <p class="rr-modal__desc">
                    @if ($location_name){{ $location_name }} · @endif
                    @lang('expense_quick.desc')
                </p>
            </div>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
        </header>

        <div class="rr-modal__body">
            @if ($location_in_main)
                {!! $location_field() !!}
            @endif

            {{-- ═══ المبلغ والنوع ═══ --}}
            <div class="rr-exp__grid">
                <div class="rr-field rr-exp__amount">
                    <label class="rr-field__label" for="expense_final_total">@lang('expense_quick.amount')<span class="rr-field__req" aria-hidden="true">*</span></label>
                    {!! Form::text('final_total', null, ['class' => 'form-control input_number', 'placeholder' => '0', 'required', 'id' => 'expense_final_total', 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="expense_category_id">@lang('expense_quick.category')</label>
                    {!! Form::select('expense_category_id', $expense_categories, null, ['class' => 'form-control', 'placeholder' => __('expense_quick.no_category'), 'id' => 'expense_category_id']) !!}
                </div>
                <div class="rr-field rr-field--full" data-rr-exp-sub hidden>
                    <label class="rr-field__label" for="expense_sub_category_id">@lang('expense_quick.sub_category')</label>
                    {!! Form::select('expense_sub_category_id', [], null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control', 'id' => 'expense_sub_category_id']) !!}
                </div>
                <div class="rr-field rr-field--full">
                    <label class="rr-field__label" for="expense_additional_notes">@lang('expense_quick.note')</label>
                    {!! Form::textarea('additional_notes', null, ['class' => 'form-control', 'rows' => 2, 'id' => 'expense_additional_notes', 'placeholder' => __('expense_quick.note_placeholder')]) !!}
                </div>
            </div>

            {{-- ═══ الدفع ═══ --}}
            <section class="rr-exp__box payment_row" aria-labelledby="{{ $dialog_id }}_pay">
                <input type="hidden" class="payment_row_index" value="{{ $row_index }}">
                <h3 class="rr-exp__h" id="{{ $dialog_id }}_pay">{!! $icon('wallet', 16) !!}@lang('expense_quick.payment')</h3>
                <div class="rr-exp__grid">
                    <div class="rr-field">
                        <label class="rr-field__label" for="method_0">@lang('expense_quick.method')</label>
                        {!! Form::select("payment[$row_index][method]", $payment_types, $method, ['class' => 'form-control payment_types_dropdown', 'required', 'id' => 'method_0']) !!}
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="amount_0">@lang('expense_quick.paid_now')</label>
                        {!! Form::text("payment[$row_index][amount]", @num_format(0), ['class' => 'form-control payment-amount input_number', 'required', 'id' => 'amount_0', 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
                    </div>
                    @if (!empty($accounts))
                        <div class="rr-field rr-field--full">
                            <label class="rr-field__label" for="account_0">@lang('expense_quick.account')</label>
                            {!! Form::select("payment[$row_index][account_id]", $accounts, '', ['class' => 'form-control account-dropdown', 'id' => 'account_0']) !!}
                        </div>
                    @endif
                </div>
                <div class="rr-exp__method-details">
                    @include('sale_pos.partials.payment_type_details')
                </div>
                <p class="rr-exp__cash-hint rr-field__hint" data-rr-exp-cash-hint>{!! $icon('info', 14) !!}@lang('expense_quick.cash_hint')</p>
                <div class="rr-exp__due" data-rr-exp-due hidden>
                    <span>@lang('expense_quick.due')</span>
                    <span class="rr-badge rr-badge--warning rr-num" id="expense_payment_due">{{ @num_format(0) }}</span>
                </div>
            </section>

            {{-- ═══ بيانات إضافية (مطوية) ═══ --}}
            <details class="rr-exp__box rr-exp__more">
                <summary class="rr-exp__h">{!! $icon('sliders', 16) !!}@lang('expense_quick.more'){!! $icon('chevron-down', 16) !!}</summary>
                <div class="rr-exp__grid">
                    @unless ($location_in_main)
                        {!! $location_field() !!}
                    @endunless
                    <div class="rr-field">
                        <label class="rr-field__label" for="expense_transaction_date">@lang('expense_quick.date')<span class="rr-field__req" aria-hidden="true">*</span></label>
                        {!! Form::text('transaction_date', @format_datetime('now'), ['class' => 'form-control', 'readonly', 'required', 'id' => 'expense_transaction_date']) !!}
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="expense_ref_no">@lang('expense_quick.ref_no')</label>
                        {!! Form::text('ref_no', null, ['class' => 'form-control', 'id' => 'expense_ref_no', 'placeholder' => __('expense_quick.ref_hint')]) !!}
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="expense_for">@lang('expense_quick.expense_for')</label>
                        {!! Form::select('expense_for', $users, null, ['class' => 'form-control', 'placeholder' => __('messages.please_select'), 'id' => 'expense_for']) !!}
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="expense_tax_id">@lang('expense_quick.tax')</label>
                        {!! Form::select('tax_id', $taxes['tax_rates'], null, ['class' => 'form-control', 'id' => 'expense_tax_id'], $taxes['attributes']) !!}
                        <input type="hidden" name="tax_calculation_amount" id="tax_calculation_amount" value="0">
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="paid_on_0">@lang('expense_quick.paid_on')</label>
                        {!! Form::text("payment[$row_index][paid_on]", @format_datetime('now'), ['class' => 'form-control paid_on', 'readonly', 'required', 'id' => 'paid_on_0']) !!}
                    </div>
                    <div class="rr-field rr-field--full">
                        <label class="rr-field__label" for="note_0">@lang('expense_quick.payment_note')</label>
                        {!! Form::text("payment[$row_index][note]", null, ['class' => 'form-control', 'id' => 'note_0']) !!}
                    </div>
                    @if ($construction_expense_integration_enabled)
                        <div class="rr-field--full rr-exp__legacy">
                            @include('expense.partials.construction_fields')
                        </div>
                    @endif
                </div>
            </details>
        </div>

        <footer class="rr-modal__foot">
            <button type="submit" class="rr-btn rr-btn--primary" data-rr-exp-save>{!! $icon('check', 16) !!}@lang('expense_quick.save')</button>
            <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('messages.cancel')</button>
        </footer>
        {!! Form::close() !!}
    </div>
</div>
<script>
    (function() {
        var $dialog = $('#{{ $dialog_id }}'), $form = $dialog.find('form');
        var $total = $dialog.find('#expense_final_total'), $paid = $dialog.find('#amount_0'), $method = $dialog.find('#method_0');
        var paidTouched = false, ns = '.{{ $dialog_id }}';

        $dialog.closest('.modal').one('shown.bs.modal', function() { $total.trigger('focus'); });

        // المدفوع بيمشي مع المبلغ (الغالب إن المصروف بيتدفع كامل) لحد ما الكاشير يكتب فيه بإيده
        function paintDue() {
            var due = Math.round((__read_number($total) - __read_number($paid)) * 100) / 100;
            $dialog.find('#expense_payment_due').text(__currency_trans_from_en(due, true, false));
            $dialog.find('[data-rr-exp-due]').prop('hidden', due === 0);
        }
        $total.on('input change', function() {
            if (!paidTouched) __write_number($paid, __read_number($total));
            paintDue();
        });
        $paid.on('input', function() { paidTouched = true; paintDue(); }).on('change', paintDue);

        // تنبيه "بيتخصم من الدرج" للنقدي بس
        function paintMethod() { $dialog.find('[data-rr-exp-cash-hint]').prop('hidden', $method.val() !== 'cash'); }
        $method.on('change', paintMethod);
        paintMethod();

        // التصنيف الفرعي يظهر بس لو النوع ليه تصنيفات فرعية (app.js بيملا القائمة)
        var $sub = $dialog.find('#expense_sub_category_id');
        $dialog.find('#expense_category_id').on('change', function() {
            // بيتنفذ قبل app.js، فلو النوع الجديد مالوش فرعي ميفضلش فرعي النوع القديم
            $sub.html('');
            $dialog.find('[data-rr-exp-sub]').prop('hidden', true);
        });
        $(document).on('ajaxSuccess' + ns, function(e, xhr, settings) {
            if (!$.contains(document, $dialog[0])) return $(document).off(ns);
            if ((settings.url || '').indexOf('/get-expense-sub-categories') !== -1) {
                $dialog.find('[data-rr-exp-sub]').prop('hidden', $sub.find('option[value!=""]').length === 0);
            }
        });

        // منع الحفظ مرتين: الزرار بيتقفل لحد ما الطلب يخلص
        var $save = $dialog.find('[data-rr-exp-save]');
        $(document).on('ajaxSend' + ns, function(e, xhr, settings) {
            if (settings.type === 'POST' && settings.url === $form.attr('action')) $save.prop('disabled', true);
        }).on('ajaxComplete' + ns, function(e, xhr, settings) {
            if (settings.type === 'POST' && settings.url === $form.attr('action')) $save.prop('disabled', false);
        });
    })();
</script>

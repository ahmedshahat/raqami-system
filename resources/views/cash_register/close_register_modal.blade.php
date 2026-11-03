{{--
    نافذة إغلاق الوردية بتصميم رقمي (بتتحمّل بالـ ajax في .close_register_modal من الكاشير وقائمة الورديات).
    أهم رقم فوق: المتوقع في الدرج = الافتتاح + النقدي − المرتجع النقدي − المصروف النقدي،
    والكاشير بيكتب الفعلي (أو يعدّ الفئات) والفرق بيظهر على طول.
    نفس حقول الفورم القديم: closing_amount، total_card_slips، total_cheques، denominations[]، closing_note، user_id.
--}}
@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $money = fn ($value) => view('partials.rr.money', ['value' => $value])->render();
    $r = $register_details;
    $can_view = auth()->user()->can('view_cash_register');
    $opened = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $r->open_time);
    $opened_text = (new \App\Utils\Util())->format_date($r->open_time, true);
    $expected = $r->cash_in_hand + $r->total_cash - $r->total_cash_refund - $r->total_cash_expense;
    $denominations = !empty($pos_settings['cash_denominations']) ? array_filter(array_map('trim', explode(',', $pos_settings['cash_denominations'])), 'strlen') : [];
@endphp
<div class="modal-dialog rr-modal-dialog rr-modal-dialog--lg rr-modal-dialog--compact rr-scope rr-shift no-print" role="document">
    <div class="modal-content rr-modal">
        {!! Form::open(['url' => action([\App\Http\Controllers\CashRegisterController::class, 'postCloseRegister']), 'method' => 'post', 'class' => 'rr-shift__form' ]) !!}
        {!! Form::hidden('user_id', $r->user_id); !!}

        <header class="rr-modal__head">
            <div class="rr-modal__titles">
                <h2 class="rr-modal__title">@lang('shift_close.title')</h2>
                <p class="rr-modal__desc">
                    {{ $r->user_name }} · {{ $r->location_name }} ·
                    {!! __('shift_close.since', ['from' => '<span class="rr-num">' . e($opened_text) . '</span>', 'duration' => e($opened->locale(app()->getLocale())->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE))]) !!}
                </p>
            </div>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
        </header>

        <div class="rr-modal__body">
            @if ($can_view)
                @include('cash_register.partials.rr_summary')

                {{-- ═══ العدّ: الفعلي والفرق ═══ --}}
                <section class="rr-shift__box rr-shift__count" aria-labelledby="rr_shift_count">
                    <h3 class="rr-shift__h" id="rr_shift_count">{!! $icon('coins', 16) !!}@lang('shift_close.count')</h3>
                    <p class="rr-shift__muted">@lang('shift_close.count_hint')</p>
                    <div class="rr-shift__fields">
                        <div class="rr-field rr-shift__actual">
                            <label class="rr-field__label" for="closing_amount">@lang('shift_close.actual')</label>
                            {!! Form::text('closing_amount', @num_format($expected), ['class' => 'form-control input_number', 'id' => 'closing_amount', 'required', 'data-rr-shift-expected' => $expected, 'autocomplete' => 'off']); !!}
                            <span class="rr-badge rr-badge--success rr-shift__diff" data-rr-shift-diff aria-live="polite">@lang('shift_close.diff_ok')</span>
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="total_card_slips">@lang('shift_close.card_slips')</label>
                            {!! Form::number('total_card_slips', $r->total_card_slips, ['class' => 'form-control', 'id' => 'total_card_slips', 'required', 'min' => 0]); !!}
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="total_cheques">@lang('shift_close.cheques')</label>
                            {!! Form::number('total_cheques', $r->total_cheques, ['class' => 'form-control', 'id' => 'total_cheques', 'required', 'min' => 0]); !!}
                        </div>
                    </div>

                    @if (count($denominations))
                        <details class="rr-shift__denoms">
                            <summary>{!! $icon('chevron-down', 16) !!}@lang('shift_close.denominations') <span class="rr-shift__muted">— @lang('shift_close.denominations_hint')</span></summary>
                            <table class="rr-shift__table rr-shift__denom-table">
                                <thead><tr><th>@lang('shift_close.denomination')</th><th>@lang('shift_close.qty')</th><th>@lang('sale.subtotal')</th></tr></thead>
                                <tbody>
                                    @foreach ($denominations as $dnm)
                                        <tr>
                                            <th scope="row" class="rr-num">{{ $dnm }}</th>
                                            <td>{!! Form::number("denominations[$dnm]", null, ['class' => 'form-control cash_denomination', 'min' => 0, 'data-denomination' => $dnm, 'aria-label' => __('shift_close.qty') . ' ' . $dnm]); !!}</td>
                                            <td class="rr-num"><span class="denomination_subtotal">0</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot><tr><th colspan="2">@lang('sale.total')</th><td class="rr-num"><span class="denomination_total">0</span></td></tr></tfoot>
                            </table>
                        </details>
                    @endif
                </section>

                {{-- الأصناف المباعة (مطوية): أعلى 10 بالقيمة بس، والباقي سطر واحد — القائمة الكاملة في "تفاصيل الوردية" --}}
                @include('cash_register.partials.rr_products', ['limit' => 10])
            @endif

            <div class="rr-field">
                <label class="rr-field__label" for="closing_note">@lang('shift_close.note')</label>
                {!! Form::textarea('closing_note', null, ['class' => 'form-control', 'id' => 'closing_note', 'placeholder' => __('shift_close.note_placeholder'), 'rows' => 2]); !!}
            </div>
        </div>

        <footer class="rr-modal__foot">
            <button type="submit" class="rr-btn rr-btn--danger">{!! $icon('power', 16) !!}@lang('shift_close.confirm')</button>
            @if ($can_view)
                <button type="button" class="rr-btn rr-btn--secondary" data-rr-shift-print-btn>{!! $icon('print', 16) !!}@lang('shift_close.print')</button>
            @endif
            <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('messages.cancel')</button>
        </footer>
        {!! Form::close() !!}
    </div>
</div>
<script>
    (function() {
        var $dialog = $('.rr-shift').last();
        var $actual = $dialog.find('#closing_amount'), $diff = $dialog.find('[data-rr-shift-diff]');
        if (!$actual.length) return;
        var expected = parseFloat($actual.data('rr-shift-expected')) || 0;
        var TXT = { ok: @json(__('shift_close.diff_ok')), short: @json(__('shift_close.diff_short')), over: @json(__('shift_close.diff_over')) };

        // الفرق بين الفعلي والمتوقع: مطابق (أخضر) / عجز (أحمر) / زيادة (أصفر)
        function paintDiff() {
            var diff = Math.round((__read_number($actual) - expected) * 100) / 100;
            var amount = __currency_trans_from_en(Math.abs(diff), true);
            $diff.removeClass('rr-badge--success rr-badge--danger rr-badge--warning')
                .addClass(diff === 0 ? 'rr-badge--success' : (diff < 0 ? 'rr-badge--danger' : 'rr-badge--warning'))
                .text(diff === 0 ? TXT.ok : (diff < 0 ? TXT.short : TXT.over).replace(':amount', amount));
        }
        $actual.on('input change', paintDiff).on('focus', function() { this.select(); });

        // عدّ الفئات بيملا خانة الفعلي
        $dialog.on('input change', '.cash_denomination', function() {
            var total = 0;
            $dialog.find('.cash_denomination').each(function() {
                total += (parseFloat($(this).data('denomination')) || 0) * (parseInt(this.value, 10) || 0);
            });
            __write_number($actual, total);
            paintDiff();
        });

        // طباعة الملخص (الأرقام والدرج وطرق الدفع + الفعلي والفرق) في #receipt_section
        $dialog.find('[data-rr-shift-print-btn]').on('click', function() {
            var $section = $('#receipt_section');
            if (!$section.length) return window.print();
            var $copy = $('<div class="rr-scope rr-shift-printout"></div>')
                .append($('<h2>').text($dialog.find('.rr-modal__title').text()))
                .append($('<p>').html($dialog.find('.rr-modal__desc').html()))
                .append($dialog.find('[data-rr-shift-print]').clone())
                .append($('<dl class="rr-shift__calc">').append(
                    $('<div>').append($('<dt>').text($dialog.find('label[for="closing_amount"]').text()), $('<dd>').text($actual.val())),
                    $('<div>').append($('<dt>').text(''), $('<dd>').text($diff.text()))
                ));
            $section.html($copy);
            $('body').addClass('rr-shift-printing');
            window.print();
            setTimeout(function() { $section.html(''); $('body').removeClass('rr-shift-printing'); }, 1000);
        });
    })();
</script>

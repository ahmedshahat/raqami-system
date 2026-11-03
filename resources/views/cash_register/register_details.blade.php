{{--
    تفاصيل الوردية بتصميم رقمي (بتتحمّل بالـ ajax في .register_details_modal من الكاشير وقائمة الورديات).
    الوردية المفتوحة: الأرقام لحد دلوقتي. المقفولة: + المعدود وقت الإغلاق والفرق وعدّ الفئات وملاحظة الإغلاق.
    الأصناف كلها هنا (مع بحث لو أكتر من 10)، عكس نافذة الإغلاق اللي بتعرض أعلى 10 بس.
--}}
@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $money = fn ($value) => view('partials.rr.money', ['value' => $value])->render();
    $r = $register_details;
    $util = new \App\Utils\Util();
    $is_closed = ($r->register_status ?? null) === 'close' && !empty($r->closed_at);
    $opened = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $r->open_time);
    $closed = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $close_time);
    $duration = $opened->locale(app()->getLocale())->diffForHumans($closed, \Carbon\CarbonInterface::DIFF_ABSOLUTE);
    $period = __($is_closed ? 'shift_close.period_closed' : 'shift_close.period_open', [
        'from' => '<span class="rr-num">' . e($util->format_date($r->open_time, true)) . '</span>',
        'to' => '<span class="rr-num">' . e($util->format_date($close_time, true)) . '</span>',
        'duration' => e($duration),
    ]);
    $denoms = collect(is_array($r->denominations) ? $r->denominations : (json_decode($r->denominations ?? '', true) ?: []))
        ->filter(fn ($qty) => (float) $qty != 0);
    $dialog_id = 'rr_shift_details_' . uniqid();
@endphp
<div class="modal-dialog rr-modal-dialog rr-modal-dialog--lg rr-modal-dialog--compact rr-scope rr-shift no-print" role="document" id="{{ $dialog_id }}">
    <div class="modal-content rr-modal">
        <header class="rr-modal__head">
            <div class="rr-modal__titles">
                <h2 class="rr-modal__title">
                    @lang('shift_close.details_title')
                    <span class="rr-badge {{ $is_closed ? '' : 'rr-badge--success' }}">{{ $is_closed ? __('shift_close.status_closed') : __('shift_close.status_open') }}</span>
                </h2>
                <p class="rr-modal__desc">{{ $r->user_name }} · {{ $r->location_name }} · {!! $period !!}</p>
            </div>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
        </header>

        <div class="rr-modal__body">
            @include('cash_register.partials.rr_summary', $is_closed ? ['counted' => (float) $r->closing_amount] : [])

            @if ($is_closed)
                {{-- ═══ بيانات الإغلاق ═══ --}}
                <section class="rr-shift__box" aria-labelledby="{{ $dialog_id }}_closing" data-rr-shift-closing>
                    <h3 class="rr-shift__h" id="{{ $dialog_id }}_closing">{!! $icon('coins', 16) !!}@lang('shift_close.closing')</h3>
                    <dl class="rr-shift__calc">
                        <div><dt>@lang('shift_close.card_slips')</dt><dd class="rr-num">{{ (int) ($r->closing_card_slips ?? 0) }}</dd></div>
                        <div><dt>@lang('shift_close.cheques')</dt><dd class="rr-num">{{ (int) ($r->closing_cheques ?? 0) }}</dd></div>
                    </dl>
                    @if (count($denoms))
                        <h4 class="rr-shift__sub">@lang('shift_close.denominations')</h4>
                        <table class="rr-shift__table rr-shift__denom-table">
                            <thead><tr><th>@lang('shift_close.denomination')</th><th>@lang('shift_close.qty')</th><th>@lang('sale.subtotal')</th></tr></thead>
                            <tbody>
                                @foreach ($denoms as $dnm => $qty)
                                    <tr><th scope="row" class="rr-num">{{ $dnm }}</th><td class="rr-num">{{ (float) $qty }}</td><td class="rr-money">{!! $money((float) $dnm * (float) $qty) !!}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr class="rr-shift__rest"><th colspan="2">@lang('sale.total')</th><td class="rr-money">{!! $money($denoms->map(fn ($qty, $dnm) => (float) $dnm * (float) $qty)->sum()) !!}</td></tr></tfoot>
                        </table>
                    @endif
                    @if (!empty($r->closing_note))
                        <h4 class="rr-shift__sub">@lang('shift_close.note')</h4>
                        <p class="rr-shift__note">{{ $r->closing_note }}</p>
                    @endif
                </section>
            @endif

            @include('cash_register.partials.rr_products', ['limit' => null])
        </div>

        <footer class="rr-modal__foot">
            <button type="button" class="rr-btn rr-btn--primary" data-rr-shift-print-btn="summary">{!! $icon('print', 16) !!}@lang('shift_close.print')</button>
            @if (count($details['product_details']))
                <button type="button" class="rr-btn rr-btn--secondary" data-rr-shift-print-btn="full">{!! $icon('print', 16) !!}@lang('shift_close.print_full')</button>
            @endif
            <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('messages.close')</button>
        </footer>
    </div>
</div>
<script>
    (function() {
        var $dialog = $('#{{ $dialog_id }}');

        // بحث في الأصناف (بالاسم أو الكود)
        var $rows = $dialog.find('[data-rr-shift-product]'), $none = $dialog.find('[data-rr-shift-none]');
        $dialog.find('[data-rr-shift-search]').on('input', function() {
            var q = $.trim(this.value).toLowerCase(), shown = 0;
            $rows.each(function() {
                var hit = !q || String($(this).data('rr-shift-product')).indexOf(q) !== -1;
                this.hidden = !hit;
                shown += hit ? 1 : 0;
            });
            $none.prop('hidden', shown > 0);
        });

        // طباعة: الملخص بس، أو الملخص + بيانات الإغلاق + الأصناف كلها
        $dialog.find('[data-rr-shift-print-btn]').on('click', function() {
            var full = $(this).data('rr-shift-print-btn') === 'full';
            var $copy = $('<div class="rr-scope rr-shift-printout"></div>')
                .append($('<h2>').text($.trim($dialog.find('.rr-modal__title').text().replace(/\s+/g, ' '))))
                .append($('<p>').html($dialog.find('.rr-modal__desc').html()))
                .append($dialog.find('[data-rr-shift-print]').clone())
                .append($dialog.find('[data-rr-shift-closing]').clone());
            if (full) {
                var $products = $dialog.find('[data-rr-shift-products]').clone().attr('open', true);
                $products.find('.rr-shift__search, [data-rr-shift-none]').remove();
                $products.find('[data-rr-shift-product]').prop('hidden', false);
                $copy.append($products);
            }
            var $area = $('<div class="rr-shift-print-area"></div>').append($copy).appendTo('body');
            $('body').addClass('rr-shift-printing');
            window.print();
            setTimeout(function() { $area.remove(); $('body').removeClass('rr-shift-printing'); }, 1000);
        });
    })();
</script>

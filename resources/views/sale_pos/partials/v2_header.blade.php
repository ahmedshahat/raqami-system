{{--
    الشريط العلوي لشاشة الكاشير الجديدة (بدل layouts.partials.header-pos).
    نفس الـ ids اللي pos.js و app.js بيعتمدوا عليها: select_location_id، pos_redirect_url، transaction_sub_type،
    close_register، register_details، view_suspended_sales، full_screen، add_expense، recent-transactions.
    الاستخدام اليومي ظاهر (الفرع، الوردية، المعلّقة، آخر العمليات، المرتجع، الإغلاق)، والباقي في "المزيد".
--}}
@php
    $go_back_url = action([\App\Http\Controllers\SellPosController::class, 'index']);
    $transaction_sub_type = '';
    $view_suspended_sell_url = action([\App\Http\Controllers\SellController::class, 'index']) . '?suspended=1';
    $pos_redirect_url = action([\App\Http\Controllers\SellPosController::class, 'create']);
    foreach ($pos_module_data ?? [] as $value) {
        if (!empty($value['go_back_url'])) {
            $go_back_url = $value['go_back_url'];
        }
        if (!empty($value['transaction_sub_type'])) {
            $transaction_sub_type = $value['transaction_sub_type'];
            $view_suspended_sell_url .= '&transaction_sub_type=' . $transaction_sub_type;
            $pos_redirect_url .= '?sub_type=' . $transaction_sub_type;
        }
    }
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $show_recent = !isset($pos_settings['hide_recent_trans']) || $pos_settings['hide_recent_trans'] == 0;
    $has_service_staff = !empty($pos_settings['inline_service_staff']) || in_array('tables', $enabled_modules) || in_array('service_staff', $enabled_modules);
@endphp
<input type="hidden" name="transaction_sub_type" id="transaction_sub_type" value="{{ $transaction_sub_type }}">
<input type="hidden" id="pos_redirect_url" value="{{ $pos_redirect_url }}">

<header class="rr-scope rr-pos-bar no-print">
    <div class="rr-pos-bar__start">
        <a href="{{ $go_back_url }}" class="rr-btn rr-btn--ghost rr-btn--icon" title="@lang('pos_screen.back')" aria-label="@lang('pos_screen.back')">{!! $icon('arrow-forward') !!}</a>
        <h1 class="rr-pos-bar__title">
            @if (!empty($transaction->id))
                @lang('pos_screen.edit_heading') <span class="rr-code">{{ $transaction->invoice_no }}</span>
                @if ($transaction->is_suspend == 1)<span class="rr-badge rr-badge--warning">@lang('pos_screen.suspended_badge')</span>@endif
            @else
                @lang('pos_screen.title')
            @endif
        </h1>

        <div class="rr-pos-bar__location">
            <span class="rr-pos-bar__muted">{!! $icon('map-pin', 16) !!}<span class="rr-pos-bar__label">@lang('pos_screen.location')</span></span>
            @if (empty($transaction->location_id) && count($business_locations) > 1)
                {!! Form::select('select_location_id', $business_locations, $default_location->id ?? null,
                    ['class' => 'form-control', 'id' => 'select_location_id', 'required', 'aria-label' => __('pos_screen.location')], $bl_attributes) !!}
            @else
                <strong>{{ !empty($transaction->location_id) ? $transaction->location->name : $default_location->name }}</strong>
            @endif
        </div>

        @if (!empty($register_details))
            @php
                // وردية من يوم سابق ما اتقفلتش: التاريخ بيظهر جنب الوقت والشارة بتبقى تحذير
                $shift_since = \Carbon\Carbon::parse($register_details->created_at);
                $shift_time = session('business.time_format') == 12 ? 'h:i A' : 'H:i';
                $shift_today = $shift_since->isToday();
            @endphp
            <span class="rr-badge {{ $shift_today ? 'rr-badge--success' : 'rr-badge--warning' }} rr-pos-bar__shift" title="{{ @format_datetime($register_details->created_at) }}">
                <span class="rr-badge__dot" aria-hidden="true"></span>
                @lang('pos_screen.shift_open')
                <span class="rr-pos-bar__label">@lang('pos_screen.shift_since', ['time' => $shift_since->format($shift_today ? $shift_time : 'd/m ' . $shift_time)])</span>
            </span>
        @endif
        <span class="rr-pos-bar__muted rr-pos-bar__label rr-num curr_datetime">{{ @format_datetime('now') }}</span>
    </div>

    <div class="rr-pos-bar__end">
        <button type="button" id="view_suspended_sales" class="rr-btn rr-btn--secondary btn-modal" data-container=".view_modal" data-href="{{ $view_suspended_sell_url }}" title="{{ __('lang_v1.view_suspended_sales') }}">
            {!! $icon('pause') !!}<span class="rr-pos-bar__label">@lang('pos_screen.suspended')</span>
            @if (!empty($suspended_count))<span class="rr-pos-bar__count rr-num">{{ $suspended_count }}</span>@endif
        </button>

        @if ($show_recent)
            <button type="button" id="recent-transactions" class="rr-btn rr-btn--secondary" data-toggle="modal" data-target="#recent_transactions_modal" title="{{ __('lang_v1.recent_transactions') }} (F3)" aria-keyshortcuts="F3">
                {!! $icon('clock') !!}<span class="rr-pos-bar__label">@lang('pos_screen.recent')</span>
            </button>
        @endif

        <button type="button" id="rr_pos_return_btn" class="rr-btn rr-btn--secondary" title="@lang('lang_v1.sell_return')" data-toggle="modal" data-target="#rr_pos_return_modal">
            {!! $icon('undo') !!}<span class="rr-pos-bar__label">@lang('pos_screen.return')</span>
        </button>

        <details class="rr-pos-bar__menu">
            <summary class="rr-btn rr-btn--secondary" title="@lang('pos_screen.more')">{!! $icon('more') !!}<span class="rr-pos-bar__label">@lang('pos_screen.more')</span></summary>
            <div class="rr-pos-bar__dropdown" role="menu">
                @can('view_cash_register')
                    <button type="button" id="register_details" class="rr-pos-bar__item btn-modal" role="menuitem" data-container=".register_details_modal"
                        data-href="{{ action([\App\Http\Controllers\CashRegisterController::class, 'getRegisterDetails']) }}">{!! $icon('wallet') !!}@lang('pos_screen.shift_details')</button>
                @endcan
                @can('expense.add')
                    <button type="button" id="add_expense" class="rr-pos-bar__item" role="menuitem">{!! $icon('receipt') !!}@lang('pos_screen.add_expense')</button>
                @endcan
                @if (!empty($pos_settings['inline_service_staff']))
                    <button type="button" id="show_service_staff_availability" class="rr-pos-bar__item" role="menuitem" data-container=".view_modal"
                        data-href="{{ action([\App\Http\Controllers\SellPosController::class, 'showServiceStaffAvailibility']) }}">{!! $icon('users') !!}{{ __('lang_v1.service_staff_availability') }}</button>
                @endif
                @if ($has_service_staff)
                    <button type="button" id="service_staff_replacement" class="rr-pos-bar__item popover-default" role="menuitem"
                        data-toggle="popover" data-trigger="click" data-html="true" data-placement="bottom" data-container="body"
                        data-content='<div class="m-8"><input type="text" class="form-control" placeholder="@lang('sale.invoice_no')" id="send_for_sell_service_staff_invoice_no"></div><div class="w-100 text-center"><button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error" id="send_for_sercice_staff_replacement">@lang('lang_v1.send')</button></div>'>{!! $icon('user') !!}{{ __('restaurant.service_staff_replacement') }}</button>
                @endif
                @if (!empty($pos_settings['customer_display_screen']))
                    <a href="{{ route('pos_display') }}" id="customer_display_screen" class="rr-pos-bar__item" role="menuitem"
                        onclick="window.open(this.href, 'customer_display', 'width='+screen.width+',height='+screen.height+',top=0,left=0'); return false;">{!! $icon('monitor') !!}@lang('pos_screen.customer_display')</a>
                @endif
                <button type="button" id="full_screen" class="rr-pos-bar__item" role="menuitem">{!! $icon('maximize') !!}@lang('pos_screen.full_screen')</button>
                <button type="button" class="rr-pos-bar__item" role="menuitemcheckbox" aria-checked="true" data-rr-pos-sound>
                    <span class="rr-pos-sound__on">{!! $icon('volume') !!}</span><span class="rr-pos-sound__off">{!! $icon('volume-off') !!}</span>@lang('pos_screen.scan_sound')<span class="rr-pos-bar__switch" aria-hidden="true"></span></button>
                <button type="button" class="rr-pos-bar__item" role="menuitemcheckbox" aria-checked="false" data-rr-pos-autoprint title="@lang('pos_screen.auto_print_hint')">
                    {!! $icon('print') !!}@lang('pos_screen.auto_print')<span class="rr-pos-bar__switch" aria-hidden="true"></span></button>
                <button type="button" class="rr-pos-bar__item" role="menuitem" data-toggle="modal" data-target="#rr_pos_keys_modal" aria-keyshortcuts="F1">{!! $icon('keyboard') !!}@lang('pos_screen.shortcuts')<kbd class="rr-pos-kbd">F1</kbd></button>
                @if (Module::has('Repair') && $transaction_sub_type != 'repair')
                    <div class="rr-pos-bar__module">@include('repair::layouts.partials.pos_header')</div>
                @endif
                @if (in_array('pos_sale', $enabled_modules) && !empty($transaction_sub_type))
                    @can('sell.create')
                        <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}" class="rr-pos-bar__item" role="menuitem">{!! $icon('grid') !!}@lang('sale.pos_sale')</a>
                    @endcan
                @endif
            </div>
        </details>

        @can('close_cash_register')
            <button type="button" id="close_register" class="rr-btn rr-btn--secondary rr-pos-bar__close btn-modal" data-container=".close_register_modal"
                data-href="{{ action([\App\Http\Controllers\CashRegisterController::class, 'getCloseRegister']) }}" title="{{ __('cash_register.close_register') }}">
                {!! $icon('power') !!}<span class="rr-pos-bar__label">@lang('pos_screen.close_shift')</span>
            </button>
        @endcan
    </div>
</header>

<div class="modal fade" id="service_staff_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

<script>
    // قائمة "المزيد": تقفل لما تدوس براها أو تختار منها أو تدوس Esc
    (function() {
        var menu = document.querySelector('.rr-pos-bar__menu');
        if (!menu) return;
        document.addEventListener('click', function(e) {
            if (!menu.open) return;
            if (!menu.contains(e.target) || (e.target.closest('.rr-pos-bar__item') && !e.target.closest('[data-toggle="popover"]'))) menu.open = false;
        });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); } });
    })();
</script>

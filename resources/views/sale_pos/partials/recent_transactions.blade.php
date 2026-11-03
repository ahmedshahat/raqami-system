{{--
    محتوى تبويب في نافذة "آخر العمليات" (SellPosController::getRecentTransactions) بتصميم رقمي.
    روابط الطباعة a.print-invoice-link (pos.js) والحذف a.delete-sale (app.js بيحذف ويعيد تحميل التبويبات) لازم تفضل بنفس الكلاسات.
--}}
@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $subtype = !empty($transaction_sub_type) ? '?sub_type=' . $transaction_sub_type : '';
    $can_edit = auth()->user()->can('sell.update') || auth()->user()->can('direct_sell.update');
    $can_delete = auth()->user()->can('sell.delete') || auth()->user()->can('direct_sell.delete');
    $can_payment = !auth()->user()->can('sell.update') && auth()->user()->can('edit_pos_payment');
    $time_format = session('business.time_format') == 12 ? 'h:i A' : 'H:i';
    $pay_tones = ['paid' => 'success', 'partial' => 'warning', 'due' => 'danger', 'overdue' => 'danger'];
@endphp
<div class="rr-scope">
    @if (!empty($transactions) && count($transactions))
        <ul class="rr-susp__list rr-recent__list" role="list">
            @foreach ($transactions as $transaction)
                @php
                    $date = \Carbon\Carbon::parse($transaction->transaction_date);
                    $is_final = $transaction->status == 'final';
                    $from_certificate = $transaction->source === 'construction_certificate';
                    $kind = $is_final ? 'final' : ($transaction->sub_status == 'quotation' ? 'quotation' : 'draft');
                    $customer = $transaction->contact;
                    $show_mobile = !empty($customer->mobile) && empty($customer->is_default);
                @endphp
                <li class="rr-susp__item rr-recent__item rr-recent__item--{{ $kind }}" data-rr-recent-item>
                    {{-- سطرين بس (الرقم والعميل، ثم الوقت والتفاصيل) عشان 6 فواتير تظهر من غير scroll --}}
                    <div class="rr-susp__main">
                        <div class="rr-susp__top">
                            <span class="rr-code rr-susp__no">{{ $transaction->invoice_no }}</span>
                            <span class="rr-recent__customer">{{ $customer->name ?? '' }}</span>
                            @if ($is_final && !empty($transaction->payment_status))
                                <span class="rr-badge{{ isset($pay_tones[$transaction->payment_status]) ? ' rr-badge--' . $pay_tones[$transaction->payment_status] : '' }}">@lang('lang_v1.' . $transaction->payment_status)</span>
                            @endif
                        </div>
                        <div class="rr-susp__meta">
                            <span title="{{ @format_datetime($transaction->transaction_date) }}">{!! $icon('clock', 14) !!}<span class="rr-num">{{ $date->isToday() ? $date->format($time_format) : $date->format('d/m ' . $time_format) }}</span> ({{ $date->locale(app()->getLocale())->diffForHumans() }})</span>
                            @if (isset($transaction->sell_lines_count))
                                <span>{!! $icon('box', 14) !!}@lang('pos_screen.suspended_items', ['count' => $transaction->sell_lines_count])</span>
                            @endif
                            @if ($show_mobile)
                                <span>{!! $icon('phone', 14) !!}<span class="rr-num">{{ $customer->mobile }}</span></span>
                            @endif
                            @if (!empty($transaction->table))
                                <span>{!! $icon('grid', 14) !!}@lang('restaurant.table'): {{ $transaction->table->name }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="rr-susp__total"><span class="rr-money">@include('partials.rr.money', ['value' => $transaction->final_total])</span></div>
                    <div class="rr-susp__actions">
                        @if (!$from_certificate && $can_edit)
                            <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'edit'], [$transaction->id]) . $subtype }}" class="rr-btn rr-btn--secondary rr-btn--sm rr-recent__edit">
                                {!! $icon('edit', 16) !!}{{ $is_final ? __('messages.edit') : __('pos_screen.recent_open') }}
                            </a>
                        @endif
                        @if ($can_payment)
                            <a href="{{ route('edit-pos-payment', ['id' => $transaction->id]) }}" class="rr-btn rr-btn--secondary rr-btn--sm rr-btn--icon" title="@lang('lang_v1.add_edit_payment')" aria-label="@lang('lang_v1.add_edit_payment')">{!! $icon('wallet', 18) !!}</a>
                        @endif
                        <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'printInvoice'], [$transaction->id]) }}" class="rr-btn rr-btn--secondary rr-btn--sm rr-btn--icon print-invoice-link" title="@lang('messages.print')" aria-label="@lang('messages.print') {{ $transaction->invoice_no }}">{!! $icon('print', 18) !!}</a>
                        @if (!$from_certificate && $can_delete)
                            <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$transaction->id]) }}" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon rr-susp__delete delete-sale" title="@lang('messages.delete')" aria-label="@lang('messages.delete') {{ $transaction->invoice_no }}">{!! $icon('trash', 18) !!}</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <div class="rr-empty">
            <span class="rr-empty__icon" aria-hidden="true">{!! $icon('receipt', 22) !!}</span>
            <span class="rr-empty__title">@lang('pos_screen.recent_empty')</span>
            <span class="rr-empty__text">@lang('pos_screen.recent_empty_hint')</span>
        </div>
    @endif
</div>

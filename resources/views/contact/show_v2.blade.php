{{--
    صفحة جهة الاتصال بتصميم رقمي (/contacts/{id})، والقديمة بتفتح بـ ?classic=1.
    الجافاسكربت مشترك مع القديمة في contact.partials.show_js، فالـ ids بتاعة الفلاتر والجداول لازم تفضل زي ما هي.
    الملخص من getLedgerDetails عشان يطابق "الملخص العام" في كشف الحساب: الإجمالي بعد المرتجعات وشامل الرصيد الافتتاحي.
--}}
@extends('layouts.app')
@section('title', $contact->supplier_business_name ?: $contact->name)

@php
    $is_customer = in_array($contact->type, ['customer', 'both']);
    $is_supplier = in_array($contact->type, ['supplier', 'both']);
    $list_type = $is_supplier && ! $is_customer ? 'supplier' : 'customer';
    $icon = function ($name, $size = 16) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $money = function ($value) {
        return '<span class="rr-money">' . view('partials.rr.money', ['value' => $value])->render() . '</span>';
    };

    $total_kind = $is_customer && $is_supplier ? 'both' : $list_type;
    $total = $summary['all_total_invoice'] + $summary['all_total_purchase'];
    $paid = $summary['all_invoice_paid'] + $summary['all_purchase_paid'];
    $due = $summary['all_balance_due'];
    $opening_due = $contact->opening_balance - $contact->opening_balance_paid;
    $can_edit = auth()->user()->can($list_type . '.update') || ($is_supplier && auth()->user()->can('supplier.update'));
    $pay_sell = $is_customer && ($contact->total_invoice - $contact->invoice_received + $opening_due) > 0;
    $pay_purchase = $is_supplier && ($contact->total_purchase - $contact->purchase_paid + $opening_due) > 0;
    $pay_url = action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$contact->id]);

    // البيانات اللي ليها قيمة بس
    $address = collect([$contact->building_number, $contact->street_name, $contact->address_line_1, $contact->address_line_2, $contact->state, $contact->city, $contact->zip_code, $contact->country])->filter()->implode('، ');
    $facts = array_filter([
        __('contact_show.mobile') => $contact->mobile,
        __('contact_show.alternate') => $contact->alternate_number,
        __('contact_show.landline') => $contact->landline,
        __('contact_show.email') => $contact->email,
        __('contact_show.tax_number') => $contact->tax_number,
        __('contact_show.group') => $customer_group_name,
        __('contact_show.pay_term') => ! empty($contact->pay_term_number) ? $contact->pay_term_number . ' ' . __('lang_v1.' . $contact->pay_term_type) : null,
        __('contact_show.dob') => ! empty($contact->dob) ? \Carbon::parse($contact->dob)->format(session('business.date_format')) : null,
        __('contact_show.land_mark') => $contact->land_mark,
        __('contact_show.shipping_address') => $contact->shipping_address,
    ]);
    // البيانات الأساسية بتظهر في رأس الصفحة، والباقي تحت "كل البيانات"
    $main_facts = array_filter([
        ['phone', $contact->mobile, ! empty($contact->mobile) ? 'tel:' . $contact->mobile : null, __('contact_show.mobile')],
        ['mail', $contact->email, ! empty($contact->email) ? 'mailto:' . $contact->email : null, __('contact_show.email')],
        ['receipt', $contact->tax_number, null, __('contact_show.tax_number')],
    ], fn ($fact) => ! empty($fact[1]));
    unset($facts[__('contact_show.mobile')], $facts[__('contact_show.email')], $facts[__('contact_show.tax_number')]);
    $title = $contact->supplier_business_name ?: $contact->name;
    $has_credit_limit = $is_customer && $contact->credit_limit > 0;
    $custom_labels = json_decode(session('business.custom_labels'), true) ?: [];
    for ($i = 1; $i <= 10; $i++) {
        if (! empty($contact->{'custom_field' . $i})) {
            $facts[$custom_labels['contact']['custom_field_' . $i] ?? __('lang_v1.custom_field', ['number' => $i])] = $contact->{'custom_field' . $i};
        }
    }

    $tabs = ['ledger' => ['ledger_tab', 'list', __('contact_show.tab_ledger')]];
    if ($is_supplier) {
        $tabs['purchase'] = ['purchases_tab', 'cart', __('contact_show.tab_purchases')];
        $tabs['stock_report'] = ['stock_report_tab', 'box', __('contact_show.tab_stock')];
    }
    if ($is_customer) {
        $tabs['sales'] = ['sales_tab', 'receipt', __('contact_show.tab_sales')];
        if (in_array('subscription', $enabled_modules)) {
            $tabs['subscriptions'] = ['subscriptions_tab', 'clock', __('lang_v1.subscriptions')];
        }
    }
    $tabs['payments'] = ['payments_tab', 'cash', __('contact_show.tab_payments')];
    $tabs['documents_and_notes'] = ['documents_and_notes_tab', 'file', __('contact_show.tab_documents')];
    if ($is_customer && session('business.enable_rp')) {
        $tabs['reward_point'] = ['reward_point_tab', 'gift', session('business.rp_name') ?? __('lang_v1.reward_points')];
    }
    $tabs['activities'] = ['activities_tab', 'clock', __('contact_show.tab_activities')];
    if (! array_key_exists($view_type, $tabs)) {
        $view_type = 'ledger';
    }
    $active = fn ($key) => $view_type == $key ? 'active' : '';

    // أعمدة المبيعات اللي بتتخفي في الأول وتتفتح من زر الأعمدة. المفتاح رقم العمود في sale_table_javascript
    $sales_optional_columns = [
        3 => __('sale.customer_name'),
        4 => __('lang_v1.contact_no'),
        5 => __('sale.location'),
        7 => __('lang_v1.payment_method'),
        11 => __('lang_v1.sell_return_due'),
        12 => __('lang_v1.shipping_status'),
        13 => __('lang_v1.total_items'),
        16 => __('lang_v1.added_by'),
        17 => __('sale.sell_note'),
        18 => __('sale.staff_note'),
        19 => __('sale.shipping_details'),
    ];

    // نفس الفكرة لتاب المشتريات، والمفتاح رقم العمود في public/js/purchase.js. الحقول المخصصة بتظهر بس لو ليها اسم
    $purchase_optional_columns = [
        3 => __('purchase.location'),
        4 => __('purchase.supplier'),
    ];
    $purchase_custom_labels = json_decode(session('business.custom_labels'), true);
    foreach ([1, 2, 3, 4] as $custom_number) {
        if (! empty($purchase_custom_labels['purchase']['custom_field_'.$custom_number])) {
            $purchase_optional_columns[8 + $custom_number] = $purchase_custom_labels['purchase']['custom_field_'.$custom_number];
        }
    }
    $purchase_optional_columns[13] = __('lang_v1.added_by');
@endphp

@section('content')
<main class="content rr-page rr-list-page rr-cs no-print" aria-labelledby="rr_cs_title">
    <span id="view_contact_page"></span>
    <input type="hidden" id="sell_list_filter_customer_id" value="{{ $contact->id }}">
    <input type="hidden" id="purchase_list_filter_supplier_id" value="{{ $contact->id }}">

    {{-- ═══ رأس الصفحة ═══ --}}
    <div class="rr-cs__top">
        <nav aria-label="@lang('contact_show.breadcrumb')">
            <ol class="rr-crumbs">
                <li><a href="{{ action([\App\Http\Controllers\ContactController::class, 'index'], ['type' => $list_type]) }}">@lang('contact_list.title_' . $list_type)</a></li>
                <li aria-current="page">{{ $contact->contact_id }}</li>
            </ol>
        </nav>
        <label class="rr-sr" for="rr_contact_switch">@lang('contact_show.switch')</label>
        <div class="rr-cs__switch">
            {!! Form::select('rr_contact_switch', $contact_dropdown, $contact->id, ['class' => 'form-control select2', 'id' => 'rr_contact_switch', 'style' => 'width: 100%;']) !!}
        </div>
    </div>

    <section class="rr-card rr-cs__hero">
        <div class="rr-cs__hero-main">
            <span class="rr-avatar rr-cs__avatar rr-avatar--tone-{{ abs(crc32((string) $contact->contact_id)) % 5 }}" aria-hidden="true">{{ mb_substr(trim($title), 0, 1) }}</span>
            <div class="rr-cs__identity">
                <div class="rr-cs__name">
                    <h1 id="rr_cs_title" class="rr-head__title">{{ $title }}</h1>
                    <span class="rr-badge rr-badge--brand">@lang('contact_show.type_' . $contact->type)</span>
                    @if($contact->contact_status == 'inactive')
                        <span class="rr-badge rr-badge--warning"><span class="rr-badge__dot"></span>@lang('contact_show.inactive')</span>
                    @endif
                </div>
                @if(! empty($contact->supplier_business_name) && ! empty(trim($contact->name)))
                    <span class="rr-head__sub">{{ $contact->name }}</span>
                @endif
                @if(! empty($main_facts))
                    <ul class="rr-cs__chips">
                        @foreach($main_facts as $fact)
                            <li title="{{ $fact[3] }}">
                                {!! $icon($fact[0], 16) !!}
                                <span class="rr-sr">{{ $fact[3] }}:</span>
                                @if(! empty($fact[2]))
                                    <a href="{{ $fact[2] }}" dir="ltr">{{ $fact[1] }}</a>
                                @else
                                    <span dir="ltr">{{ $fact[1] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="rr-cs__hero-actions">
                @if($pay_sell)
                    <a class="rr-btn rr-btn--primary pay_sale_due" href="{{ $pay_url }}?type=sell">{!! $icon('cash', 18) !!}@lang('contact_show.receive')</a>
                @endif
                @if($pay_purchase)
                    <a class="rr-btn rr-btn--primary pay_purchase_due" href="{{ $pay_url }}?type=purchase">{!! $icon('cash', 18) !!}@lang('contact_show.pay')</a>
                @endif
                @can('contact.ledger_discount')
                    <button type="button" class="rr-btn rr-btn--tint" data-toggle="modal" data-target="#add_discount_modal">{!! $icon('tag', 18) !!}@lang('contact_show.add_discount')</button>
                @endcan
                @if($can_edit)
                    <a class="rr-btn rr-btn--secondary edit_contact_button" href="{{ action([\App\Http\Controllers\ContactController::class, 'edit'], [$contact->id]) }}">{!! $icon('edit', 18) !!}@lang('contact_show.edit')</a>
                @endif
            </div>
        </div>
        @if(! empty($facts) || ! empty($address) || $has_credit_limit)
            <details class="rr-cs__facts">
                <summary>@lang('contact_show.all_details'){!! $icon('chevron-down', 16) !!}</summary>
                <dl>
                    @foreach($facts as $label => $value)
                        <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                    @if($has_credit_limit)
                        <div><dt>@lang('contact_show.credit_limit')</dt><dd>{!! $money($contact->credit_limit) !!}</dd></div>
                    @endif
                    @if(! empty($address))
                        <div class="rr-cs__fact-wide"><dt>@lang('contact_show.address')</dt><dd>{{ $address }}</dd></div>
                    @endif
                </dl>
            </details>
        @endif
    </section>

    {{-- ═══ الملخص: المستحق أول حاجة لأنه أكتر رقم بيتبص عليه ═══ --}}
    <section class="rr-statgroup-wrap" aria-label="@lang('contact_show.summary')">
        <div class="rr-statgroup rr-statgroup--toned" style="--rr-stats: {{ 4 + count($pending_cheques) }}">
            <div class="rr-stat {{ $due > 0 ? 'rr-stat--warning' : ($due < 0 ? 'rr-stat--info' : 'rr-stat--success') }}">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('clock') !!}</span>@lang('contact_show.due_' . $total_kind)</span>
                <span class="rr-stat__value" data-rr-cs="due">{!! $money($due) !!}</span>
                @if($opening_due > 0)
                    <span class="rr-stat__hint">@lang('contact_show.opening_part') {!! $money($opening_due) !!}</span>
                @elseif($due == 0)
                    <span class="rr-stat__hint">@lang('contact_show.settled')</span>
                @endif
            </div>
            <div class="rr-stat rr-stat--brand">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('receipt') !!}</span>@lang('contact_show.total_' . $total_kind)</span>
                <span class="rr-stat__value" data-rr-cs="total">{!! $money($total) !!}</span>
                <span class="rr-stat__hint">@lang('contact_show.total_hint')</span>
            </div>
            <div class="rr-stat rr-stat--success">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('check-circle') !!}</span>@lang('contact_show.paid')</span>
                <span class="rr-stat__value" data-rr-cs="paid">{!! $money($paid) !!}</span>
            </div>
            <div class="rr-stat {{ $contact->balance > 0 ? 'rr-stat--info' : 'rr-stat--neutral' }}">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('wallet') !!}</span>@lang('contact_show.advance')</span>
                <span class="rr-stat__value" data-rr-cs="advance">{!! $money($contact->balance) !!}</span>
            </div>
            @foreach($pending_cheques as $cheque_type => $cheque)
                @php
                    // جهة النوعين: المستحق بيتحسب لكل نوع لوحده لأن المستحق الكلي بيجمع الاتجاهين
                    $cheque_due = $total_kind != 'both' ? $due : ($cheque_type == 'receivable'
                        ? $summary['all_total_invoice'] - $summary['all_invoice_paid']
                        : $summary['all_total_purchase'] - $summary['all_purchase_paid']);
                @endphp
                <div class="rr-stat rr-stat--info">
                    <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('cheque') !!}</span>@lang('cheque.pending_cheques_on_contact')</span>
                    <span class="rr-stat__value">{!! $money($cheque->total) !!}</span>
                    <span class="rr-stat__hint">@lang('cheque.next_due'): {{ @format_date($cheque->next_due) }}</span>
                    <span class="rr-stat__hint">@lang('cheque.uncovered_due'): {!! $money(max($cheque_due - $cheque->total, 0)) !!}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ═══ التابات: nav-tabs من Bootstrap عشان الجافاسكربت القديم بيسمع shown.bs.tab ═══ --}}
    <section class="rr-card rr-cs__tabs-card">
        <ul class="nav rr-cs__tabs">
            @foreach($tabs as $key => $tab)
                <li class="{{ $active($key) }}"><a href="#{{ $tab[0] }}" data-toggle="tab" @if($key == 'purchase') id="purchases-link" @endif>{!! $icon($tab[1]) !!}{{ $tab[2] }}</a></li>
            @endforeach
            @if(! empty($contact_view_tabs))
                @foreach($contact_view_tabs as $module_tabs)
                    @foreach($module_tabs as $value)
                        @if(! empty($value['tab_menu_path']))
                            @include($value['tab_menu_path'], $value['tab_data'] ?? [])
                        @endif
                    @endforeach
                @endforeach
            @endif
        </ul>

        <div class="tab-content rr-cs__panes">
            <div class="tab-pane {{ $active('ledger') }}" id="ledger_tab">
                <div class="rr-cs__tools rr-cs__tools--band">
                    <div class="rr-field">
                        <label class="rr-field__label" for="ledger_date_range">@lang('contact_show.period')</label>
                        <div class="rr-control"><span class="rr-control__lead">{!! $icon('calendar') !!}</span><input class="rr-control__input" type="text" id="ledger_date_range" readonly placeholder="@lang('contact_show.period_ph')"></div>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="ledger_location">@lang('contact_show.location')</label>
                        <div class="rr-control">
                            {!! Form::select('ledger_location', $business_locations, null, ['class' => 'rr-control__input rr-control__select', 'id' => 'ledger_location']) !!}
                            <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                        </div>
                    </div>
                    <div class="rr-field rr-cs__formats-field">
                        <span class="rr-field__label">@lang('contact_show.ledger_format')</span>
                        <div class="rr-qc__kind rr-cs__formats">
                            @foreach(['format_1', 'format_2', 'format_3', 'format_4'] as $format)
                                <label class="rr-qc__opt"><input type="radio" name="ledger_format" value="{{ $format }}" @checked($loop->first)><span>@lang('lang_v1.' . $format)</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="rr-cs__tools-end">
                        <button type="button" class="rr-btn rr-btn--secondary" id="print_ledger" data-href="{{ action([\App\Http\Controllers\ContactController::class, 'getLedger']) }}?contact_id={{ $contact->id }}&action=print">{!! $icon('print', 18) !!}@lang('contact_show.print')</button>
                        <button type="button" class="rr-btn rr-btn--secondary" id="print_ledger_pdf" data-href="{{ action([\App\Http\Controllers\ContactController::class, 'getLedger']) }}?contact_id={{ $contact->id }}&action=pdf">{!! $icon('download', 18) !!}PDF</button>
                        <button type="button" class="rr-btn rr-btn--secondary" id="send_ledger">{!! $icon('mail', 18) !!}@lang('contact_show.send')</button>
                    </div>
                </div>
                <div id="contact_ledger_div" class="rr-cs__legacy rr-cs__ledger" data-ui="v2"></div>
            </div>

            @if($is_supplier)
                <div class="tab-pane {{ $active('purchase') }}" id="purchases_tab">
                    <div class="rr-cs__tools rr-cs__tools--band">
                        <div class="rr-field">
                            <label class="rr-field__label" for="purchase_list_filter_date_range">@lang('contact_show.period')</label>
                            <div class="rr-control"><span class="rr-control__lead">{!! $icon('calendar') !!}</span><input class="rr-control__input" type="text" id="purchase_list_filter_date_range" readonly placeholder="@lang('contact_show.period_ph')"></div>
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="purchase_list_filter_payment_status">@lang('contact_show.payment_status')</label>
                            <div class="rr-control">
                                {!! Form::select('purchase_list_filter_payment_status', ['paid' => __('lang_v1.paid'), 'due' => __('lang_v1.due'), 'partial' => __('lang_v1.partial'), 'overdue' => __('lang_v1.overdue')], null, ['class' => 'rr-control__input rr-control__select', 'id' => 'purchase_list_filter_payment_status', 'placeholder' => __('lang_v1.all')]) !!}
                                <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                            </div>
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="rr_cs_purchase_search">@lang('contact_show.search')</label>
                            <div class="rr-control"><span class="rr-control__lead">{!! $icon('search') !!}</span><input class="rr-control__input" type="search" id="rr_cs_purchase_search" placeholder="@lang('contact_show.search_purchases')"></div>
                        </div>
                        <div class="rr-cs__tools-end">
                            <button type="button" class="rr-btn rr-btn--secondary" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr_cs_purchase_columns">{!! $icon('columns', 18) !!}@lang('contact_show.columns')<span class="rr-list-tools__count rr-num" id="rr_cs_purchase_columns_count" hidden></span></button>
                            <div class="rr-menu rr-menu--checks" id="rr_cs_purchase_columns" role="menu" aria-label="@lang('contact_show.columns_title')">
                                <p class="rr-menu__note">@lang('contact_show.columns_title')</p>
                                @foreach($purchase_optional_columns as $column_index => $column_label)
                                    <label class="rr-menu__item rr-menu__check"><input type="checkbox" class="rr-toggle__input" role="menuitemcheckbox" data-rr-purchase-col="{{ $column_index }}">{{ $column_label }}</label>
                                @endforeach
                            </div>
                            @can('view_export_buttons')
                                <button type="button" class="rr-btn rr-btn--secondary" data-rr-purchase-export="print">{!! $icon('print', 18) !!}@lang('contact_show.print')</button>
                                <button type="button" class="rr-btn rr-btn--secondary" data-rr-purchase-export="excel">{!! $icon('download', 18) !!}Excel</button>
                            @endcan
                        </div>
                    </div>
                    <div class="rr-cs__legacy rr-cs__dt-tab">
                        @include('purchase.partials.purchase_table', ['rr_plain_footer' => true])
                    </div>
                </div>
                <div class="tab-pane {{ $active('stock_report') }}" id="stock_report_tab">
                    <div class="rr-cs__tools">
                        <div class="rr-field">
                            <label class="rr-field__label" for="sr_location_id">@lang('contact_show.location')</label>
                            <div class="rr-control">
                                {!! Form::select('sr_location_id', $business_locations, null, ['class' => 'rr-control__input rr-control__select', 'id' => 'sr_location_id']) !!}
                                <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                            </div>
                        </div>
                    </div>
                    <div class="rr-cs__legacy">
                        <table class="table" id="supplier_stock_report_table" width="100%">
                            <thead>
                                <tr>
                                    <th>@lang('sale.product')</th>
                                    <th>@lang('product.sku')</th>
                                    <th>@lang('purchase.purchase_quantity')</th>
                                    <th>@lang('lang_v1.total_sold')</th>
                                    <th>@lang('lang_v1.total_unit_transfered')</th>
                                    <th>@lang('lang_v1.total_returned')</th>
                                    <th>@lang('report.current_stock')</th>
                                    <th>@lang('lang_v1.total_stock_price')</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            @endif

            @if($is_customer)
                <div class="tab-pane {{ $active('sales') }}" id="sales_tab">
                    <div class="rr-cs__tools rr-cs__tools--band">
                        <div class="rr-field">
                            <label class="rr-field__label" for="sell_list_filter_date_range">@lang('contact_show.period')</label>
                            <div class="rr-control"><span class="rr-control__lead">{!! $icon('calendar') !!}</span><input class="rr-control__input" type="text" id="sell_list_filter_date_range" readonly placeholder="@lang('contact_show.period_ph')"></div>
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="sell_list_filter_payment_status">@lang('contact_show.payment_status')</label>
                            <div class="rr-control">
                                {!! Form::select('sell_list_filter_payment_status', ['paid' => __('lang_v1.paid'), 'due' => __('lang_v1.due'), 'partial' => __('lang_v1.partial'), 'overdue' => __('lang_v1.overdue')], null, ['class' => 'rr-control__input rr-control__select', 'id' => 'sell_list_filter_payment_status', 'placeholder' => __('lang_v1.all')]) !!}
                                <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                            </div>
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="rr_cs_sales_search">@lang('contact_show.search')</label>
                            <div class="rr-control"><span class="rr-control__lead">{!! $icon('search') !!}</span><input class="rr-control__input" type="search" id="rr_cs_sales_search" placeholder="@lang('contact_show.search_sales')"></div>
                        </div>
                        <div class="rr-cs__tools-end">
                            <button type="button" class="rr-btn rr-btn--secondary" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr_cs_sales_columns">{!! $icon('columns', 18) !!}@lang('contact_show.columns')<span class="rr-list-tools__count rr-num" id="rr_cs_sales_columns_count" hidden></span></button>
                            <div class="rr-menu rr-menu--checks" id="rr_cs_sales_columns" role="menu" aria-label="@lang('contact_show.columns_title')">
                                <p class="rr-menu__note">@lang('contact_show.columns_title')</p>
                                @foreach($sales_optional_columns as $column_index => $column_label)
                                    <label class="rr-menu__item rr-menu__check"><input type="checkbox" class="rr-toggle__input" role="menuitemcheckbox" data-rr-sales-col="{{ $column_index }}">{{ $column_label }}</label>
                                @endforeach
                            </div>
                            @can('view_export_buttons')
                                <button type="button" class="rr-btn rr-btn--secondary" data-rr-sales-export="print">{!! $icon('print', 18) !!}@lang('contact_show.print')</button>
                                <button type="button" class="rr-btn rr-btn--secondary" data-rr-sales-export="excel">{!! $icon('download', 18) !!}Excel</button>
                            @endcan
                        </div>
                    </div>
                    <div class="rr-cs__legacy rr-cs__dt-tab">
                        @include('sale_pos.partials.sales_table', ['rr_plain_footer' => true])
                    </div>
                </div>
                @if(in_array('subscription', $enabled_modules))
                    @include('contact.partials.subscriptions')
                @endif
            @endif

            <div class="tab-pane {{ $active('payments') }}" id="payments_tab">
                <div id="contact_payments_div" class="rr-cs__legacy"></div>
            </div>

            <div class="tab-pane {{ $active('documents_and_notes') }}" id="documents_and_notes_tab">
                <div class="rr-cs__legacy">
                    @include('contact.partials.documents_and_notes_tab')
                </div>
            </div>

            @if($is_customer && session('business.enable_rp'))
                <div class="tab-pane {{ $active('reward_point') }}" id="reward_point_tab">
                    @if($reward_enabled)
                        <p class="rr-cs__points"><span class="rr-num">{{ $contact->total_rp ?? 0 }}</span> {{ session('business.rp_name') }}</p>
                    @endif
                    <div class="rr-cs__legacy">
                        <table class="table" id="rp_log_table" width="100%">
                            <thead>
                                <tr>
                                    <th>@lang('messages.date')</th>
                                    <th>@lang('sale.invoice_no')</th>
                                    <th>@lang('lang_v1.earned')</th>
                                    <th>@lang('lang_v1.redeemed')</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            @endif

            <div class="tab-pane {{ $active('activities') }}" id="activities_tab">
                <div class="rr-cs__legacy">
                    @include('activity_log.activities')
                </div>
            </div>

            @if(! empty($contact_view_tabs))
                @foreach($contact_view_tabs as $module_tabs)
                    @foreach($module_tabs as $value)
                        @if(! empty($value['tab_content_path']))
                            @include($value['tab_content_path'], $value['tab_data'] ?? [])
                        @endif
                    @endforeach
                @endforeach
            @endif
        </div>
    </section>

    <div class="rr-list-page__foot">
        <a class="rr-linkbtn" href="{{ action([\App\Http\Controllers\ContactController::class, 'show'], [$contact->id, 'classic' => 1]) }}">@lang('contact_show.old_page')</a>
    </div>
</main>

<div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade pay_contact_due_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade rr-scope" id="edit_ledger_discount_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
@can('contact.ledger_discount')
    @include('ledger_discount.create')
@endcan
@stop

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        // قبل ما الجداول القديمة تتبني: نفس شريط البحث والترقيم بتاع رقمي بدل row و col-sm
        $.fn.dataTable.defaults.dom = '<"rr-cs__dtbar"Bf>r<"rr-table-scroll"t><"rr-pager"<"rr-pager__sum"il><"rr-pager__pages"p>>';
    });
    // بعد تعديل البيانات: الاسم والملخص محتاجين يتحملوا من جديد
    window.addEventListener('contactAdded', function() {
        setTimeout(function() { window.location.reload(); }, 500);
    });
</script>
@include('contact.partials.show_js')
@include('partials.rr.menu_js')
<script type="text/javascript">
    // تابي المبيعات والمشتريات: الأعمدة الأساسية بس ظاهرة، والباقي من زر الأعمدة. الطباعة وExcel تقرير مستقل لكل الفترة
    // قائمة عمليات الصف بتتثبت على الشاشة، عشان حاوية السكرول ما تقصّهاش في آخر صفوف الجدول
    (function() {
        var closeOnScroll = null;
        $(document).on('shown.bs.dropdown', '.rr-cs__dt-tab .btn-group', function() {
            var group = $(this);
            var menu = group.children('.dropdown-menu');
            var button = this.getBoundingClientRect();
            var height = menu.outerHeight();
            var width = menu.outerWidth();
            var top = button.bottom + height + 8 > window.innerHeight ? Math.max(8, button.top - height) : button.bottom;
            var left = Math.min(Math.max(8, $('html').attr('dir') === 'rtl' || $('body').css('direction') === 'rtl' ? button.right - width : button.left), window.innerWidth - width - 8);
            menu.css({ position: 'fixed', top: top, left: left, right: 'auto', maxHeight: window.innerHeight - 16, overflowY: 'auto' });

            closeOnScroll = function(event) {
                if (! menu[0].contains(event.target)) group.removeClass('open');
            };
            document.addEventListener('scroll', closeOnScroll, true);
        });
        $(document).on('hidden.bs.dropdown', '.rr-cs__dt-tab .btn-group', function() {
            $(this).children('.dropdown-menu').css({ position: '', top: '', left: '', right: '', maxHeight: '', overflowY: '' });
            document.removeEventListener('scroll', closeOnScroll, true);
        });
    })();

    function rrContactListTab(o) {
        var table = o.table;
        var shown = [];
        try { shown = JSON.parse(localStorage.getItem(o.storeKey)) || []; } catch (e) {}
        shown = shown.filter(function(index) { return o.optional.indexOf(index) !== -1; });

        function applyColumns() {
            o.optional.forEach(function(index) {
                table.column(index).visible(shown.indexOf(index) !== -1, false);
            });
            table.columns.adjust();
            $('#rr_cs_' + o.kind + '_columns_count').text(shown.length).prop('hidden', shown.length === 0);
        }

        var columnChecks = $('[data-rr-' + o.kind + '-col]');
        columnChecks.each(function() {
            this.checked = shown.indexOf(Number($(this).attr('data-rr-' + o.kind + '-col'))) !== -1;
        });
        applyColumns();

        columnChecks.on('change', function() {
            var index = Number($(this).attr('data-rr-' + o.kind + '-col'));
            shown = shown.filter(function(value) { return value !== index; });
            if (this.checked) shown.push(index);
            try { localStorage.setItem(o.storeKey, JSON.stringify(shown)); } catch (e) {}
            applyColumns();
        });

        $('a[href="' + o.tab + '"]').on('shown.bs.tab', function() {
            table.columns.adjust();
        });

        var searchInput = $('#rr_cs_' + o.kind + '_search');
        var searchTimer = null;
        searchInput.on('input', function() {
            var value = $.trim(this.value);
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() { table.search(value).draw(); }, 350);
        });

        $(document).on('click', '[data-rr-' + o.kind + '-export]', function() {
            var format = $(this).attr('data-rr-' + o.kind + '-export');
            var status = $(o.status);
            var search = $.trim(searchInput.val());
            var note = [o.label.party + ': ' + @json($title)];
            if (status.val()) note.push(@json(__('contact_show.payment_status')) + ': ' + status.find('option:selected').text());
            if (search) note.push(@json(__('contact_show.search')) + ': ' + search);

            var params = { format: format, payment_status: status.val() || '', search: search, note: note.join('، ') };
            params[o.contactParam] = {{ (int) $contact->id }};
            var range = $(o.range);
            if (range.val()) {
                params.start_date = range.data('daterangepicker').startDate.format('YYYY-MM-DD');
                params.end_date = range.data('daterangepicker').endDate.format('YYYY-MM-DD');
            }
            var url = o.exportUrl + '?' + $.param(params);
            if (format === 'print') { window.open(url, '_blank'); } else { window.location.href = url; }
        });
    }

    $(document).ready(function() {
        @if($is_customer)
            if (typeof sell_table !== 'undefined') {
                rrContactListTab({
                    kind: 'sales', table: sell_table, tab: '#sales_tab', storeKey: 'rr_contact_sales_cols',
                    optional: @json(array_keys($sales_optional_columns)),
                    exportUrl: @json(route('sells.v2.export')), contactParam: 'customer_id',
                    status: '#sell_list_filter_payment_status', range: '#sell_list_filter_date_range',
                    label: { party: @json(__('sale.customer_name')) }
                });
            }
        @endif
        @if($is_supplier)
            if (typeof purchase_table !== 'undefined') {
                rrContactListTab({
                    kind: 'purchase', table: purchase_table, tab: '#purchases_tab', storeKey: 'rr_contact_purchase_cols',
                    optional: @json(array_keys($purchase_optional_columns)),
                    exportUrl: @json(route('purchases.export')), contactParam: 'supplier_id',
                    status: '#purchase_list_filter_payment_status', range: '#purchase_list_filter_date_range',
                    label: { party: @json(__('purchase.supplier')) }
                });
            }
        @endif
    });
</script>
@endsection

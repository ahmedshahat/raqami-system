@extends('layouts.app')
@section('title', __('sell_list.title'))

@php
    $can_select = auth()->user()->can('print_invoice') || auth()->user()->can('view_export_buttons');
    $periods = ['today', 'yesterday', 'week', 'month', 'year', 'custom'];
    $tabs = ['all', 'paid', 'partial', 'due', 'overdue'];
    $icon = function ($name, $size = 16) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $optional_columns = [
        'return_due' => __('sell_list.col_return_due'),
        'shipping_status' => __('sell_list.col_shipping'),
        'total_items' => __('sell_list.col_items'),
        'added_by' => __('sell_list.col_added_by'),
        'additional_notes' => __('sell_list.col_sell_note'),
        'staff_note' => __('sell_list.col_staff_note'),
        'shipping_details' => __('sell_list.col_shipping_details'),
    ];
    // أعمدة الموديولات والحقول المخصصة بتظهر في قائمة الأعمدة بس لو مفعلة، زي الشاشة القديمة
    $custom_labels = json_decode(session('business.custom_labels'), true) ?: [];
    $extra_columns = [];
    if (! empty($is_types_service_enabled)) {
        $extra_columns['types_of_service_name'] = ['data' => 'types_of_service_name', 'name' => 'tos.name', 'label' => __('sell_list.col_service_type')];
        $extra_columns['service_custom_field_1'] = ['data' => 'service_custom_field_1', 'name' => 'service_custom_field_1', 'label' => $custom_labels['types_of_service']['custom_field_1'] ?? __('sell_list.col_service_field')];
    }
    foreach (['custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4'] as $field) {
        if (! empty($custom_labels['sell'][$field])) {
            $extra_columns[$field] = ['data' => $field, 'name' => 'transactions.'.$field, 'label' => $custom_labels['sell'][$field]];
        }
    }
    if (! empty($is_tables_enabled)) {
        $extra_columns['table_name'] = ['data' => 'table_name', 'name' => 'tables.name', 'label' => __('sell_list.col_table')];
    }
    if (! empty($is_service_staff_enabled)) {
        $extra_columns['waiter'] = ['data' => 'waiter', 'name' => 'ss.first_name', 'label' => __('sell_list.col_waiter')];
    }
    $optional_columns += array_map(fn ($c) => $c['label'], $extra_columns);
    $extra_columns_js = [];
    foreach ($extra_columns as $key => $c) {
        $extra_columns_js[] = ['key' => $key, 'data' => $c['data'], 'name' => $c['name']];
    }
@endphp

@section('css')
    <style>
        @media print {
            #bulk_receipt_section,
            #bulk_receipt_section .bulk-invoice-page,
            #bulk_receipt_section .bulk-invoice-page * {
                background-color: #fff !important;
                color: #000 !important;
                -webkit-text-fill-color: #000 !important;
                color-scheme: light !important;
                opacity: 1 !important;
            }

            #bulk_receipt_section .bulk-invoice-page-break {
                break-after: page;
                page-break-after: always;
            }
        }
    </style>
@endsection

@section('content')
<main class="content rr-page rr-list-page no-print" aria-labelledby="rr_sells_title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-list-page__head">
        <div class="rr-list-page__titles">
            <h1 id="rr_sells_title" class="rr-head__title">@lang('sell_list.title')</h1>
            <p class="rr-head__sub" id="rr_sells_range" aria-live="polite"></p>
            {{-- الرجوع للشاشة القديمة لليوزر ده بس (users.use_classic_sells) --}}
            <form method="POST" action="{{ route('sells.switch') }}" class="rr-inline-form">
                @csrf
                <input type="hidden" name="version" value="classic">
                <button type="submit" class="rr-linkbtn">@lang('sell_list.old_screen')</button>
            </form>
        </div>
        <div class="rr-head__actions">
            @can('direct_sell.access')
                <a class="rr-btn rr-btn--secondary" href="{{ action([\App\Http\Controllers\SellController::class, 'create']) }}">{!! $icon('plus', 20) !!}@lang('sell_list.new_invoice')</a>
            @endcan
            @can('sell.create')
                <a class="rr-btn rr-btn--secondary" href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}">{!! $icon('monitor', 20) !!}@lang('sell_list.pos')</a>
            @endcan
            @can('view_export_buttons')
                <button type="button" class="rr-btn rr-btn--secondary" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr_sells_export_menu">{!! $icon('download', 20) !!}@lang('sell_list.export'){!! $icon('chevron-down') !!}</button>
                <div class="rr-menu" id="rr_sells_export_menu" role="menu">
                    <button type="button" class="rr-menu__item" role="menuitem" data-rr-export="excel">@lang('sell_list.export_excel')</button>
                    <button type="button" class="rr-menu__item" role="menuitem" data-rr-export="csv">@lang('sell_list.export_csv')</button>
                    <button type="button" class="rr-menu__item" role="menuitem" data-rr-export="print">@lang('sell_list.export_print')</button>
                    <div class="rr-menu__sep" role="separator"></div>
                    <p class="rr-menu__note">@lang('sell_list.export_note')</p>
                </div>
            @endcan
        </div>
    </header>

    {{-- ═══ الفترة ═══ --}}
    <section class="rr-period" aria-label="@lang('sell_list.period')">
        <div class="rr-period__presets" role="group" aria-label="@lang('sell_list.period')">
            @foreach($periods as $period)
                <button type="button" class="rr-chip-btn" data-rr-period="{{ $period }}" aria-pressed="{{ $period == 'year' ? 'true' : 'false' }}">@lang('sell_list.period_' . $period)</button>
            @endforeach
        </div>
        <form class="rr-period__custom" id="rr_sells_custom" hidden>
            <label class="rr-period__date">@lang('sell_list.from')
                <span class="rr-control"><input class="rr-control__input" type="date" id="rr_sells_start" required></span>
            </label>
            <label class="rr-period__date">@lang('sell_list.to')
                <span class="rr-control"><input class="rr-control__input" type="date" id="rr_sells_end" required></span>
            </label>
            <button type="submit" class="rr-btn rr-btn--secondary">@lang('sell_list.apply')</button>
        </form>
    </section>

    {{-- ═══ ملخص الفترة كلها (من السيرفر، مش من الصفحة الظاهرة) ═══ --}}
    <section class="rr-statgroup-wrap" aria-label="@lang('sell_list.kpis')">
        <div class="rr-statgroup rr-statgroup--6" style="--rr-stats: 6" id="rr_sells_kpis" aria-busy="true">
            <div class="rr-stat rr-stat--brand">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('receipt') !!}</span>@lang('sell_list.kpi_total')</span>
                <span class="rr-stat__value" data-rr-kpi="total">—</span>
            </div>
            <div class="rr-stat rr-stat--success">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('wallet') !!}</span>@lang('sell_list.kpi_paid')</span>
                <span class="rr-stat__value" data-rr-kpi="paid">—</span>
            </div>
            <a href="#" class="rr-stat rr-stat--warning" id="rr_sells_due_kpi" aria-current="false" title="@lang('sell_list.kpi_due_hint')">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('clock') !!}</span>@lang('sell_list.kpi_due')</span>
                <span class="rr-stat__value" data-rr-kpi="due">—</span>
                <span class="rr-stat__hint">@lang('sell_list.kpi_due_hint')</span>
            </a>
            <div class="rr-stat rr-stat--danger">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('undo') !!}</span>@lang('sell_list.kpi_returns')</span>
                <span class="rr-stat__value" data-rr-kpi="returns">—</span>
            </div>
            <div class="rr-stat rr-stat--info">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('file') !!}</span>@lang('sell_list.kpi_invoices')</span>
                <span class="rr-stat__value" data-rr-kpi="invoices">—</span>
            </div>
            <div class="rr-stat rr-stat--neutral">
                <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('trend') !!}</span>@lang('sell_list.kpi_average')</span>
                <span class="rr-stat__value" data-rr-kpi="average">—</span>
            </div>
        </div>
    </section>

    {{-- ═══ الجدول: تابات الحالة + أدوات التصفية + التحديد ═══ --}}
    <section class="rr-table-wrap" aria-labelledby="rr_sells_title">
        <nav class="rr-scope rr-tabs rr-tabs--inset" aria-label="@lang('sell_list.status_tabs')">
            @foreach($tabs as $tab)
                <button type="button" class="rr-tab" data-rr-tab="{{ $tab == 'all' ? '' : $tab }}" @if($tab == 'all') aria-current="page" @endif>
                    @lang('sell_list.tab_' . $tab)
                    <span class="rr-tab__count rr-num" data-rr-count="{{ $tab }}"></span>
                </button>
            @endforeach
        </nav>

        <div class="rr-table-head rr-table-head--toolbar">
            <div class="rr-table-tools rr-list-tools">
                <label class="rr-sr" for="rr_sells_search">@lang('sell_list.search')</label>
                <div class="rr-control rr-control--sm rr-table-tools__search">
                    <span class="rr-control__lead">{!! $icon('search') !!}</span>
                    <input class="rr-control__input" type="search" id="rr_sells_search" placeholder="@lang('sell_list.search_ph')" autocomplete="off">
                </div>
                {{-- موبايل بس: باقي الفلاتر متلمومة ورا الزرار ده --}}
                <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm rr-list-tools__fold" id="rr_sells_filters_toggle" aria-expanded="false">{!! $icon('filter') !!}@lang('sell_list.filters')<span class="rr-list-tools__count rr-num" id="rr_sells_filters_count" hidden></span></button>

                @if(count($business_locations) > 1)
                    <label class="rr-sr" for="rr_sells_location">@lang('sell_list.location')</label>
                    <div class="rr-control rr-control--sm rr-table-tools__select">
                        <select class="rr-control__input rr-control__select" id="rr_sells_location" data-rr-filter="location_id" data-rr-filter-label="@lang('sell_list.location')">
                            <option value="">@lang('sell_list.all_locations')</option>
                            @foreach($business_locations as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                    </div>
                @endif

                <div class="rr-field rr-list-tools__customer">
                    <label class="rr-sr" for="rr_sells_customer">@lang('sell_list.customer')</label>
                    <select id="rr_sells_customer" data-rr-filter="customer_id" data-rr-filter-label="@lang('sell_list.customer')" style="width: 100%">
                        <option value="">@lang('sell_list.all_customers')</option>
                        @foreach($customers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                @if(! empty($payment_types))
                    <label class="rr-sr" for="rr_sells_method">@lang('sell_list.payment_method')</label>
                    <div class="rr-control rr-control--sm rr-table-tools__select">
                        <select class="rr-control__input rr-control__select" id="rr_sells_method" data-rr-filter="payment_method" data-rr-filter-label="@lang('sell_list.payment_method')">
                            <option value="">@lang('sell_list.all_methods')</option>
                            @foreach($payment_types as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                    </div>
                @endif

                <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" id="rr_sells_more_toggle" aria-expanded="false" aria-controls="rr_sells_more">{!! $icon('filter') !!}@lang('sell_list.more_filters')</button>

                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr_sells_columns_menu">{!! $icon('columns') !!}@lang('sell_list.columns')</button>
                <div class="rr-menu rr-menu--checks" id="rr_sells_columns_menu" role="menu" aria-label="@lang('sell_list.columns_title')">
                    <p class="rr-menu__note">@lang('sell_list.columns_title')</p>
                    @foreach(['customer' => __('sell_list.col_customer'), 'location' => __('sell_list.col_location'), 'total' => __('sell_list.col_total'), 'paid' => __('sell_list.col_paid'), 'due' => __('sell_list.col_due'), 'status' => __('sell_list.col_status'), 'method' => __('sell_list.col_method')] + $optional_columns as $key => $label)
                        <label class="rr-menu__item rr-menu__check"><input type="checkbox" class="rr-toggle__input" role="menuitemcheckbox" data-rr-col="{{ $key }}">{{ $label }}</label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- فلاتر أكثر --}}
        <div class="rr-list-more" id="rr_sells_more" hidden>
            @if(! empty($sales_representative))
                <div class="rr-field">
                    <label class="rr-field__label" for="rr_sells_created_by">@lang('sell_list.created_by')</label>
                    <div class="rr-control rr-control--sm">
                        <select class="rr-control__input rr-control__select" id="rr_sells_created_by" data-rr-filter="created_by" data-rr-filter-label="@lang('sell_list.created_by')">
                            <option value="">@lang('sell_list.all')</option>
                            @foreach($sales_representative as $id => $name)
                                @if($id !== '' && $id !== null)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                    </div>
                </div>
            @endif
            @if(! empty($is_cmsn_agent_enabled))
                <div class="rr-field">
                    <label class="rr-field__label" for="rr_sells_cmsn">@lang('sell_list.commission_agent')</label>
                    <div class="rr-control rr-control--sm">
                        <select class="rr-control__input rr-control__select" id="rr_sells_cmsn" data-rr-filter="sales_cmsn_agnt" data-rr-filter-label="@lang('sell_list.commission_agent')">
                            <option value="">@lang('sell_list.all')</option>
                            @foreach($commission_agents as $id => $name)
                                @if($id !== '' && $id !== null)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                    </div>
                </div>
            @endif
            @if(! empty($service_staffs))
                <div class="rr-field">
                    <label class="rr-field__label" for="rr_sells_staff">@lang('sell_list.service_staff')</label>
                    <div class="rr-control rr-control--sm">
                        <select class="rr-control__input rr-control__select" id="rr_sells_staff" data-rr-filter="service_staffs" data-rr-filter-label="@lang('sell_list.service_staff')">
                            <option value="">@lang('sell_list.all')</option>
                            @foreach($service_staffs as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                    </div>
                </div>
            @endif
            <div class="rr-field">
                <label class="rr-field__label" for="rr_sells_shipping">@lang('sell_list.shipping_status')</label>
                <div class="rr-control rr-control--sm">
                    <select class="rr-control__input rr-control__select" id="rr_sells_shipping" data-rr-filter="shipping_status" data-rr-filter-label="@lang('sell_list.shipping_status')">
                        <option value="">@lang('sell_list.all')</option>
                        @foreach($shipping_statuses as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                </div>
            </div>
            @if(! empty($sources))
                <div class="rr-field">
                    <label class="rr-field__label" for="rr_sells_source">@lang('sell_list.source')</label>
                    <div class="rr-control rr-control--sm">
                        <select class="rr-control__input rr-control__select" id="rr_sells_source" data-rr-filter="source" data-rr-filter-label="@lang('sell_list.source')">
                            <option value="">@lang('sell_list.all')</option>
                            @foreach($sources as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                    </div>
                </div>
            @endif
            <label class="rr-toggle rr-list-more__toggle">
                <input type="checkbox" class="rr-toggle__input" id="rr_sells_subscriptions" data-rr-filter="only_subscriptions" data-rr-filter-label="@lang('sell_list.only_subscriptions')" value="1">
                <span>@lang('sell_list.only_subscriptions')</span>
            </label>
        </div>

        {{-- الفلاتر المطبقة --}}
        <div class="rr-filter-chips" id="rr_sells_chips" aria-label="@lang('sell_list.active_filters')" hidden></div>

        {{-- شريط التحديد --}}
        @if($can_select)
            <div class="rr-bulkbar" id="rr_sells_bulkbar" hidden>
                <span class="rr-bulkbar__count" id="rr_sells_selected_count" aria-live="polite"></span>
                @can('print_invoice')
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" id="print_selected_invoices">{!! $icon('print') !!}@lang('sell_list.print_selected')</button>
                @endcan
                @can('view_export_buttons')
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" data-rr-export="excel" data-rr-selected>{!! $icon('download') !!}@lang('sell_list.export_selected')</button>
                @endcan
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm" id="rr_sells_clear_selection">@lang('sell_list.clear_selection')</button>
            </div>
        @endif

        <table class="rr-table rr-table--sells rr-table--cards" id="sell_table" data-rr-datatable>
            <thead>
                <tr>
                    @if($can_select)
                        <th class="rr-th rr-th--check rr-noexport"><input type="checkbox" class="rr-toggle__input" id="select_all_invoices" aria-label="@lang('sell_list.select_page')"></th>
                    @endif
                    <th class="rr-th"><span class="rr-th__in">@lang('sell_list.col_invoice')</span></th>
                    <th class="rr-th"><span class="rr-th__in">@lang('sell_list.col_customer')</span></th>
                    <th class="rr-th"><span class="rr-th__in">@lang('sell_list.col_location')</span></th>
                    <th class="rr-th rr-th--num"><span class="rr-th__in">@lang('sell_list.col_total')</span></th>
                    <th class="rr-th rr-th--num"><span class="rr-th__in">@lang('sell_list.col_paid')</span></th>
                    <th class="rr-th rr-th--num"><span class="rr-th__in">@lang('sell_list.col_due')</span></th>
                    <th class="rr-th"><span class="rr-th__in">@lang('sell_list.col_status')</span></th>
                    <th class="rr-th"><span class="rr-th__in">@lang('sell_list.col_method')</span></th>
                    @foreach($optional_columns as $label)
                        <th class="rr-th"><span class="rr-th__in">{{ $label }}</span></th>
                    @endforeach
                    <th class="rr-th rr-noexport"><span class="rr-sr">@lang('sell_list.col_actions')</span></th>
                    <th class="rr-th rr-noexport"></th>
                    <th class="rr-th rr-noexport"></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        {{-- موبايل بس: بدل أرقام الصفحات --}}
        <div class="rr-loadmore" id="rr_sells_loadmore" hidden>
            <button type="button" class="rr-btn rr-btn--secondary" id="rr_sells_more_rows">@lang('sell_list.load_more') <span class="rr-loadmore__left rr-num"></span></button>
        </div>
    </section>

    <p class="rr-list-page__foot">
        <a class="rr-linkbtn" href="{{ action([\App\Http\Controllers\SellController::class, 'index']) }}">@lang('sell_list.old_page')</a>
    </p>
</main>

<div class="modal fade payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

<div class="modal fade rr-scope" id="invoice_image_share_modal" tabindex="-1" role="dialog" aria-labelledby="invoiceImageShareTitle">
    <div class="modal-dialog rr-modal-dialog" role="document">
        <div class="modal-content rr-modal">
            <header class="rr-modal__head">
                <div class="rr-modal__titles">
                    <h2 class="rr-modal__title" id="invoiceImageShareTitle">@lang('sell_list.share_title')</h2>
                    <p class="rr-modal__desc" id="invoice_whatsapp_recipient"></p>
                </div>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('sell_list.close')">{!! $icon('close', 20) !!}</button>
            </header>
            <div class="rr-modal__body">
                <img id="invoice_image_preview" class="rr-list-share__img" alt="@lang('sell_list.share_image_alt')">
                <p id="invoice_share_help" class="rr-field__hint"></p>
            </div>
            <footer class="rr-modal__foot">
                <button type="button" class="rr-btn rr-btn--primary" id="share_invoice_image">{!! $icon('share', 20) !!}@lang('sell_list.share_btn')</button>
                <button type="button" class="rr-btn rr-btn--secondary" id="open_invoice_whatsapp">@lang('sell_list.open_whatsapp')</button>
                <button type="button" class="rr-btn rr-btn--secondary" id="download_invoice_image">{!! $icon('download', 20) !!}@lang('sell_list.download_image')</button>
                <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('sell_list.close')</button>
            </footer>
        </div>
    </div>
</div>

<section class="invoice print_section" id="receipt_section"></section>
<section class="invoice print_section" id="bulk_receipt_section"></section>
@stop

@section('javascript')
    @include('sell.partials.invoice_share_print_js')
    <script type="text/javascript">
        var selectedInvoices = {};

        $(document).ready(function() {
            var canSelect = @json($can_select);
            var colsKey = 'rr-sells-v2-cols-{{ auth()->id() }}';
            var STR = @json(__('sell_list'));
            var EXPORT_URL = @json(route('sells.v2.export'));
            var STATUS = {
                paid: @json(__('lang_v1.paid')),
                due: @json(__('lang_v1.due')),
                partial: @json(__('lang_v1.partial')),
                overdue: @json(__('lang_v1.overdue')),
                'partial-overdue': @json(__('lang_v1.partial-overdue'))
            };
            var ICON = {
                print: @json($icon('print')),
                share: @json($icon('share')),
                more: @json($icon('more')),
                close: @json($icon('close', 14))
            };
            var state = { period: 'year', start: null, end: null, tab: '' };

            function esc(value) {
                return $('<div>').text(value == null ? '' : String(value)).html();
            }
            function textOf(html) {
                return $.trim($('<div>').html(html || '').text().replace(/\s+/g, ' '));
            }
            function origValue(html) {
                var value = parseFloat($('<div>').html(html || '').find('[data-orig-value]').addBack('[data-orig-value]').first().attr('data-orig-value'));
                return isNaN(value) ? 0 : value;
            }
            function money(value, withSymbol) {
                return __currency_trans_from_en(value, withSymbol);
            }
            function moneyCell(html, type) {
                var value = origValue(html);
                if (type !== 'display') {
                    return type === 'export' ? money(value, false) : value;
                }
                return '<span class="rr-num' + (value == 0 ? ' rr-money--zero' : '') + '">' + money(value, false) + '</span>';
            }
            function plural(key, count) {
                var forms = STR[key].split('|');
                for (var i = 0; i < forms.length; i++) {
                    var match = forms[i].match(/^\s*(\{(\d+)\}|\[(\d+),(\d+|\*)\])\s*(.*)$/);
                    if (!match) continue;
                    var ok = match[2] !== undefined ? count == match[2] : (count >= match[3] && (match[4] === '*' || count <= match[4]));
                    if (ok) return match[5].replace(':count', count);
                }
                return count;
            }

            // ─── الفترة ───
            function periodRange(period) {
                var today = moment();
                switch (period) {
                    case 'today': return [today.clone(), today.clone()];
                    case 'yesterday': return [today.clone().subtract(1, 'day'), today.clone().subtract(1, 'day')];
                    case 'week': return [today.clone().startOf('week'), today.clone()];
                    case 'year': return [today.clone().startOf('year'), today.clone()];
                    case 'custom': return [moment(state.start), moment(state.end)];
                    default: return [today.clone().startOf('month'), today.clone()];
                }
            }
            function applyPeriod() {
                var range = periodRange(state.period);
                state.start = range[0].format('YYYY-MM-DD');
                state.end = range[1].format('YYYY-MM-DD');
                $('#rr_sells_range').text(STR.range.replace(':start', range[0].format(moment_date_format)).replace(':end', range[1].format(moment_date_format)));
            }

            $('[data-rr-period]').on('click', function() {
                var period = $(this).data('rr-period');
                $('[data-rr-period]').attr('aria-pressed', 'false');
                $(this).attr('aria-pressed', 'true');
                $('#rr_sells_custom').prop('hidden', period !== 'custom');
                if (period === 'custom') {
                    $('#rr_sells_start').val(state.start);
                    $('#rr_sells_end').val(state.end);
                    $('#rr_sells_start').trigger('focus');
                    return;
                }
                state.period = period;
                applyPeriod();
                reload();
            });
            $('#rr_sells_custom').on('submit', function(event) {
                event.preventDefault();
                var start = $('#rr_sells_start').val(), end = $('#rr_sells_end').val();
                if (!start || !end) return;
                if (start > end) { var swap = start; start = end; end = swap; }
                state.period = 'custom';
                state.start = start;
                state.end = end;
                applyPeriod();
                reload();
            });

            // ─── التصفية ───
            function filterParams() {
                var params = {
                    is_direct_sale: 1,
                    start_date: state.start,
                    end_date: state.end,
                    payment_status: state.tab
                };
                $('[data-rr-filter]').each(function() {
                    var key = $(this).data('rr-filter');
                    if ($(this).is(':checkbox')) {
                        if (this.checked) params[key] = 1;
                    } else if ($(this).val()) {
                        params[key] = $(this).val();
                    }
                });
                return params;
            }

            function renderChips() {
                var chips = [];
                $('[data-rr-filter]').each(function() {
                    var active = $(this).is(':checkbox') ? this.checked : !!$(this).val();
                    if (!active) return;
                    var label = $(this).data('rr-filter-label');
                    var value = $(this).is(':checkbox') ? STR.yes : $(this).find('option:selected').text();
                    chips.push('<button type="button" class="rr-filter-chip" data-rr-clear="' + esc(this.id) + '" aria-label="' + esc(STR.remove_filter.replace(':name', label)) + '">' +
                        '<span class="rr-filter-chip__label">' + esc(label) + ':</span> ' + esc(value) + ICON.close + '</button>');
                });
                if (chips.length > 1) {
                    chips.push('<button type="button" class="rr-linkbtn" data-rr-clear-all>' + esc(STR.clear_filters) + '</button>');
                }
                $('#rr_sells_chips').html(chips.join('')).prop('hidden', !chips.length);
                $('#rr_sells_filters_count').text(chips.length > 1 ? chips.length - 1 : chips.length).prop('hidden', !chips.length);
            }

            $(document).on('change', '[data-rr-filter]', function() {
                renderChips();
                reload();
            });
            $('#rr_sells_chips').on('click', '[data-rr-clear]', function() {
                var input = $('#' + $(this).data('rr-clear'));
                if (input.is(':checkbox')) { input.prop('checked', false); } else { input.val(''); }
                input.trigger('change');
            });
            $('#rr_sells_chips').on('click', '[data-rr-clear-all]', function() {
                $('[data-rr-filter]').each(function() {
                    if ($(this).is(':checkbox')) { this.checked = false; } else { $(this).val(''); }
                });
                $('#rr_sells_customer').trigger('change.select2');
                renderChips();
                reload();
            });

            $('#rr_sells_more_toggle').on('click', function() {
                var open = $(this).attr('aria-expanded') !== 'true';
                $(this).attr('aria-expanded', open ? 'true' : 'false');
                $('#rr_sells_more').prop('hidden', !open);
            });

            $('#rr_sells_filters_toggle').on('click', function() {
                var open = $(this).attr('aria-expanded') !== 'true';
                $(this).attr('aria-expanded', open ? 'true' : 'false');
                $(this).closest('.rr-table-wrap').toggleClass('is-filters-open', open);
            });

            $('#rr_sells_customer').select2({ dropdownAutoWidth: true });

            var searchTimer = null;
            $('#rr_sells_search').on('input', function() {
                var value = this.value;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() { resetMobileLength(); sell_table.search(value).draw(); }, 350);
            });

            // ─── تابات حالة الدفع + مؤشر المتبقي ───
            function setTab(tab) {
                state.tab = tab;
                $('[data-rr-tab]').each(function() {
                    if ($(this).data('rr-tab') === tab) { $(this).attr('aria-current', 'page'); } else { $(this).removeAttr('aria-current'); }
                });
                $('#rr_sells_due_kpi').attr('aria-current', tab === 'unpaid' ? 'true' : 'false');
                // الملخص مش بيتأثر بالتاب، فمفيش داعي نحمّله تاني
                tabSwitch = true;
                sell_table.ajax.reload();
            }
            $('[data-rr-tab]').on('click', function() { setTab($(this).data('rr-tab')); });
            $('#rr_sells_due_kpi').on('click', function(event) {
                event.preventDefault();
                setTab(state.tab === 'unpaid' ? '' : 'unpaid');
            });

            // ─── الملخص ───
            var summaryRequest = null;
            function loadSummary() {
                if (summaryRequest) summaryRequest.abort();
                var params = filterParams();
                params.search = $('#rr_sells_search').val();
                $('#rr_sells_kpis').attr('aria-busy', 'true');
                summaryRequest = $.getJSON(@json(route('sells.v2.summary')), params).done(function(data) {
                    $('[data-rr-kpi="total"]').text(money(data.total, true));
                    $('[data-rr-kpi="paid"]').text(money(data.paid, true));
                    $('[data-rr-kpi="due"]').text(money(data.due, true));
                    $('[data-rr-kpi="returns"]').text(money(data.returns, true));
                    $('[data-rr-kpi="invoices"]').text(__number_f(data.invoices, false, false, 0));
                    $('[data-rr-kpi="average"]').text(money(data.average, true));
                    $.each(data.counts, function(key, count) {
                        $('[data-rr-count="' + key + '"]').text(__number_f(count, false, false, 0));
                    });
                }).fail(function(xhr, status) {
                    if (status !== 'abort') toastr.error(STR.kpi_failed);
                }).always(function() {
                    $('#rr_sells_kpis').attr('aria-busy', 'false');
                });
            }

            // ─── الجدول ───
            function buildActions(row) {
                var source = $('<div>').html(row.action || '');
                var list = source.find('ul.dropdown-menu');
                if (!list.length) {
                    return row.action || '';
                }
                var id = row.transaction_id;
                var number = row.invoice_no_text || id;
                var items = [], danger = null, lastSep = true, quick = '';
                var printLink = source.find('a.print-invoice').filter(function() { return String($(this).data('href')).indexOf('?') === -1; }).first();
                var shareLink = source.find('a.share-invoice-image').first();

                if (printLink.length) {
                    quick += '<a href="#" class="print-invoice rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-href="' + esc(printLink.data('href')) + '" title="' + esc(STR.print) + '" aria-label="' + esc(STR.print) + '">' + ICON.print + '</a>';
                }
                if (shareLink.length) {
                    quick += '<button type="button" class="share-invoice-image rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-href="' + esc(shareLink.data('href')) + '" data-invoice-no="' + esc(number) + '" title="' + esc(STR.share) + '" aria-label="' + esc(STR.share) + '">' + ICON.share + '</button>';
                }

                list.children('li').each(function() {
                    var li = $(this);
                    if (li.hasClass('divider')) {
                        if (!lastSep) { items.push('<div class="rr-menu__sep" role="separator"></div>'); lastSep = true; }
                        return;
                    }
                    var link = li.children('a').first();
                    if (!link.length) return;
                    var label = $.trim(link.text().replace(/\s+/g, ' '));
                    link.empty().text(label).removeClass('btn btn-xs').addClass('rr-menu__item').attr('role', 'menuitem');
                    // "عرض" بيفتح نافذة الفاتورة الجديدة
                    if (link.hasClass('btn-modal') && /\/sells\/\d+$/.test(String(link.attr('data-href')))) {
                        link.attr('data-href', link.attr('data-href') + '?v2=1');
                    }
                    if (link.hasClass('delete-sale')) {
                        danger = link.addClass('rr-menu__item--danger').prop('outerHTML');
                        return;
                    }
                    items.push(link.prop('outerHTML'));
                    lastSep = false;
                });
                if (items.length && lastSep) items.pop();
                if (danger) items.push('<div class="rr-menu__sep" role="separator"></div>', danger);

                return '<div class="rr-row-actions">' + quick +
                    '<button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr_sell_menu_' + id + '" aria-label="' + esc(STR.more_actions.replace(':number', number)) + '">' + ICON.more + '</button>' +
                    '<div class="rr-menu" id="rr_sell_menu_' + id + '" role="menu">' + items.join('') + '</div></div>';
            }

            function statusBadge(html) {
                var source = $('<div>').html(html || '');
                var node = source.find('[data-orig-value]').first();
                var status = node.attr('data-orig-value') || '';
                var tone = { paid: 'success', partial: 'info', due: 'warning', overdue: 'danger', 'partial-overdue': 'danger' }[status] || '';
                var badge = '<span class="rr-badge' + (tone ? ' rr-badge--' + tone : '') + '"><span class="rr-badge__dot"></span>' + esc(STATUS[status] || node.attr('data-status-name') || status) + '</span>';
                var href = source.find('a.view_payment_modal').attr('href');
                return href ? '<a href="' + esc(href) + '" class="view_payment_modal rr-list-badge-link">' + badge + '</a>' : badge;
            }

            function invoiceCell(row) {
                var flags = '';
                $('<div>').html(row.invoice_no || '').find('small.label').each(function() {
                    var cls = this.className;
                    var tone = /bg-red/.test(cls) ? 'danger' : (/bg-info/.test(cls) ? 'info' : (/bg-yellow/.test(cls) ? 'warning' : ''));
                    flags += ' <span class="rr-badge' + (tone ? ' rr-badge--' + tone : '') + '">' + esc($(this).attr('title') || $(this).text()) + '</span>';
                });
                return '<div class="rr-cell-stack"><span class="rr-cell-stack__main"><span class="rr-code">' + esc(row.invoice_no_text || textOf(row.invoice_no)) + '</span>' + flags + '</span>' +
                    '<span class="rr-cell-stack__sub rr-num">' + esc(row.transaction_date) + '</span></div>';
            }

            var columns = [];
            if (canSelect) {
                columns.push({
                    data: 'transaction_id', name: 'transactions.id', orderable: false, searchable: false, className: 'rr-td rr-td--check selectable_td rr-noexport',
                    render: function(data, type, row) {
                        if (type !== 'display' || !data) return data || '';
                        var number = esc(row.invoice_no_text || data);
                        return '<input type="checkbox" class="select-invoice rr-toggle__input" value="' + data + '" data-invoice-no="' + number + '" data-print-url="' + esc(row.print_url || '') + '"' +
                            (selectedInvoices[data] ? ' checked' : '') + ' aria-label="' + esc(STR.select_row.replace(':number', row.invoice_no_text || data)) + '">';
                    }
                });
            }
            columns.push(
                { key: 'invoice', data: 'invoice_no', name: 'transactions.invoice_no', orderable: false, className: 'rr-td rr-m-title',
                    render: function(data, type, row) { return type === 'display' ? invoiceCell(row) : (row.invoice_no_text || textOf(data)) + (type === 'export' ? ' — ' + row.transaction_date : ''); } },
                { key: 'customer', data: 'conatct_name', name: 'conatct_name', orderable: false, className: 'rr-td rr-m-sub',
                    render: function(data, type, row) {
                        var name = textOf(data).replace(/^,\s*|,\s*$/g, '');
                        if (type !== 'display') return name + (type === 'export' && row.mobile ? ' — ' + row.mobile : '');
                        return '<div class="rr-cell-stack"><span class="rr-cell-stack__main">' + esc(name) + '</span>' +
                            (row.mobile ? '<span class="rr-cell-stack__sub rr-num">' + esc(row.mobile) + '</span>' : '') + '</div>';
                    } },
                { key: 'location', data: 'business_location', name: 'bl.name', searchable: false, className: 'rr-td rr-td--muted' },
                { key: 'total', data: 'final_total', name: 'final_total', searchable: false, className: 'rr-td rr-td--num rr-m-amount', render: function(d, t) { return moneyCell(d, t); } },
                { key: 'paid', data: 'total_paid', name: 'total_paid', searchable: false, orderable: false, className: 'rr-td rr-td--num', render: function(d, t) { return moneyCell(d, t); } },
                { key: 'due', data: 'total_remaining', name: 'total_remaining', searchable: false, orderable: false, className: 'rr-td rr-td--num', render: function(d, t) { return moneyCell(d, t); } },
                { key: 'status', data: 'payment_status', name: 'payment_status', searchable: false, className: 'rr-td rr-m-status',
                    render: function(d, t, row) {
                        if (t !== 'display') return textOf(d);
                        // على الموبايل الفاتورة اللي عليها باقي بتعرض المبلغ مكان الشارة
                        var due = origValue(row.total_remaining);
                        if (due <= 0) return statusBadge(d);
                        var late = /overdue/.test($('<div>').html(d || '').find('[data-orig-value]').attr('data-orig-value') || '');
                        return '<span class="rr-m-status__badge">' + statusBadge(d) + '</span>' +
                            '<span class="rr-badge rr-badge--' + (late ? 'danger' : 'warning') + ' rr-m-due"><span class="rr-badge__dot"></span>' + esc(STR.m_remaining.replace(':amount', money(due, false))) + '</span>';
                    } },
                { key: 'method', data: 'payment_methods', orderable: false, searchable: false, className: 'rr-td rr-td--muted',
                    render: function(d) { return esc(textOf(d)); } },
                { key: 'return_due', data: 'return_due', orderable: false, searchable: false, className: 'rr-td rr-td--num', visible: false,
                    render: function(d, t) { return t === 'display' ? (d ? '<span class="rr-num">' + d + '</span>' : '') : textOf(d); } },
                { key: 'shipping_status', data: 'shipping_status', name: 'shipping_status', searchable: false, className: 'rr-td', visible: false,
                    render: function(d, t) { return t === 'display' ? d : textOf(d); } },
                { key: 'total_items', data: 'total_items', name: 'total_items', searchable: false, orderable: false, className: 'rr-td rr-td--num', visible: false },
                { key: 'added_by', data: 'added_by', name: 'u.first_name', searchable: false, className: 'rr-td rr-td--muted', visible: false },
                { key: 'additional_notes', data: 'additional_notes', name: 'additional_notes', searchable: false, orderable: false, className: 'rr-td rr-td--wrap', visible: false,
                    render: function(d, t) { return t === 'display' ? d : textOf(d); } },
                { key: 'staff_note', data: 'staff_note', name: 'staff_note', searchable: false, orderable: false, className: 'rr-td rr-td--wrap', visible: false,
                    render: function(d, t) { return t === 'display' ? d : textOf(d); } },
                { key: 'shipping_details', data: 'shipping_details', name: 'shipping_details', searchable: false, orderable: false, className: 'rr-td rr-td--wrap', visible: false,
                    render: function(d) { return esc(textOf(d)); } },
            );
            $.each(@json($extra_columns_js), function(i, col) {
                columns.push({ key: col.key, data: col.data, name: col.name, searchable: false, className: 'rr-td', visible: false,
                    render: function(d, t) { return t === 'display' ? d : textOf(d); } });
            });
            columns.push(
                { data: 'action', orderable: false, searchable: false, className: 'rr-td rr-td--actions rr-noexport',
                    render: function(d, t, row) { return t === 'display' ? buildActions(row) : ''; } },
                // أعمدة مخفية: الترتيب الافتراضي بالتاريخ، والبحث برقم الهاتف
                { data: 'transaction_date', name: 'transaction_date', searchable: false, visible: false, className: 'rr-noexport' },
                { data: 'mobile', name: 'contacts.mobile', orderable: false, visible: false, className: 'rr-noexport' }
            );
            var dateColumn = columns.length - 2;

            // الأعمدة المحفوظة لليوزر ده
            var savedCols = {};
            try { savedCols = JSON.parse(localStorage.getItem(colsKey) || '{}') || {}; } catch (e) {}
            $.each(columns, function(i, col) {
                if (col.key && savedCols.hasOwnProperty(col.key)) col.visible = !!savedCols[col.key];
            });

            applyPeriod();

            var lastSummaryKey = null, lastPageKey = null, tabSwitch = false;
            sell_table = $('#sell_table').DataTable({
                processing: true,
                serverSide: true,
                fixedHeader: false,
                autoWidth: false,
                dom: 'r<"rr-table-scroll"t><"rr-pager"<"rr-pager__sum"il><"rr-pager__pages"p>>',
                order: [[dateColumn, 'desc']],
                language: $.extend({}, $.fn.dataTable.defaults.language, { emptyTable: STR.empty, zeroRecords: STR.empty }),
                ajax: {
                    url: '/sells',
                    data: function(d) {
                        $.extend(d, filterParams());
                        d = __datatable_ajax_callback(d);
                        // الملخص بيتحدّث لما الفلاتر تتغير أو الجدول يتعمله reload (بعد دفعة أو حذف)، مش مع تقليب الصفحات
                        var summaryParams = filterParams();
                        delete summaryParams.payment_status;
                        var summaryKey = JSON.stringify([summaryParams, d.search.value]);
                        var pageKey = JSON.stringify([d.start, d.length, d.order]);
                        if (summaryKey !== lastSummaryKey || (pageKey === lastPageKey && !tabSwitch)) {
                            lastSummaryKey = summaryKey;
                            loadSummary();
                        }
                        lastPageKey = pageKey;
                        tabSwitch = false;
                    }
                },
                columns: columns,
                createdRow: function(row, data) {
                    $(row).addClass('rr-tr').attr('tabindex', 0).attr('data-rr-id', data.transaction_id)
                        .toggleClass('rr-tr--selected', !!selectedInvoices[String(data.transaction_id)]);
                },
                drawCallback: function() {
                    updateSelectedInvoicesUi();
                    var info = this.api().page.info();
                    var left = info.recordsDisplay - info.end;
                    $('#rr_sells_loadmore').prop('hidden', !(info.start === 0 && left > 0))
                        .find('.rr-loadmore__left').text(STR.load_more_left.replace(':count', left));
                    $('#rr_sells_more_rows').prop('disabled', false);
                }
            });

            // ─── عرض المزيد (موبايل): بيكبّر طول الصفحة بدل التقليب ───
            var mobileQuery = window.matchMedia('(max-width: 767px)');
            var baseLength = sell_table.page.len();
            function resetMobileLength() {
                if (mobileQuery.matches && sell_table.page.len() !== baseLength) sell_table.page.len(baseLength);
            }
            $('#rr_sells_more_rows').on('click', function() {
                $(this).prop('disabled', true);
                sell_table.page.len(sell_table.page.len() + 25).draw(false);
            });

            function reload() {
                resetMobileLength();
                sell_table.ajax.reload();
            }

            // ─── اختيار الأعمدة ───
            $.each(columns, function(i, col) {
                if (!col.key) return;
                $('[data-rr-col="' + col.key + '"]').prop('checked', sell_table.column(i).visible());
            });
            $('[data-rr-col]').on('change', function() {
                var key = $(this).data('rr-col');
                var index = columns.findIndex(function(col) { return col.key === key; });
                if (index < 0) return;
                sell_table.column(index).visible(this.checked);
                savedCols[key] = this.checked;
                try { localStorage.setItem(colsKey, JSON.stringify(savedCols)); } catch (e) {}
            });

            // ─── التصدير والطباعة: من السيرفر لكل الفترة والفلاتر ───
            function exportNote() {
                var parts = [];
                var tab = $('[data-rr-tab][aria-current="page"]');
                if (state.tab && tab.length) parts.push(STR.col_status + ': ' + textOf(tab.clone().find('[data-rr-count]').remove().end().html()));
                if (state.tab === 'unpaid') parts.push(STR.unpaid_only);
                $('[data-rr-filter]').each(function() {
                    var active = $(this).is(':checkbox') ? this.checked : !!$(this).val();
                    if (!active) return;
                    parts.push($(this).data('rr-filter-label') + ': ' + ($(this).is(':checkbox') ? STR.yes : $(this).find('option:selected').text()));
                });
                var search = $.trim($('#rr_sells_search').val());
                if (search) parts.push(STR.search + ': ' + search);
                return parts.join('، ');
            }
            $(document).on('click', '[data-rr-export]', function() {
                var format = $(this).data('rr-export');
                var selectedOnly = $(this).is('[data-rr-selected]');
                if (selectedOnly && !Object.keys(selectedInvoices).length) {
                    toastr.warning(STR.select_one);
                    return;
                }
                var params = $.extend(filterParams(), {
                    format: format,
                    search: $.trim($('#rr_sells_search').val()),
                    note: exportNote()
                });
                if (selectedOnly) params.ids = Object.keys(selectedInvoices);
                var url = EXPORT_URL + '?' + $.param(params);
                if (format === 'print') { window.open(url, '_blank'); } else { window.location.href = url; }
            });

            // ─── التحديد ───
            window.updateSelectedInvoicesUi = function() {
                var count = Object.keys(selectedInvoices).length;
                $('#rr_sells_selected_count').text(count ? plural('selected', count) : '');
                $('#rr_sells_bulkbar').prop('hidden', count === 0);

                var boxes = $('#sell_table tbody .select-invoice');
                var checked = boxes.filter(':checked').length;
                $('#select_all_invoices')
                    .prop('checked', boxes.length > 0 && checked === boxes.length)
                    .prop('indeterminate', checked > 0 && checked < boxes.length);
            };

            $('#sell_table').on('click', 'tbody td.selectable_td', function(event) {
                event.stopPropagation();
                if ($(event.target).is('.select-invoice')) return;
                event.preventDefault();
                $(this).find('.select-invoice').trigger('click');
            });
            $(document).on('change', '.select-invoice', function() {
                var id = String($(this).val());
                if (this.checked) {
                    selectedInvoices[id] = { id: id, number: String($(this).data('invoice-no') || id), url: String($(this).data('print-url') || '') };
                } else {
                    delete selectedInvoices[id];
                }
                $(this).closest('tr').toggleClass('rr-tr--selected', this.checked);
                updateSelectedInvoicesUi();
            });
            $('#select_all_invoices').on('change', function() {
                var select = this.checked;
                $('#sell_table tbody .select-invoice').each(function() {
                    if (this.checked !== select) $(this).prop('checked', select).trigger('change');
                });
            });
            $('#rr_sells_clear_selection').on('click', function() {
                selectedInvoices = {};
                $('#sell_table tbody .select-invoice').prop('checked', false).closest('tr').removeClass('rr-tr--selected');
                updateSelectedInvoicesUi();
            });
            $('#print_selected_invoices').on('click', async function() {
                var invoices = Object.keys(selectedInvoices).map(function(id) { return selectedInvoices[id]; });
                if (!invoices.length) {
                    toastr.warning(STR.select_one);
                    return;
                }
                await printInvoicesBulk(invoices, $(this));
                updateSelectedInvoicesUi();
            });

            // ─── الضغط على الصف (أو Enter) بيفتح الفاتورة كاملة، إلا لو الضغط على زرار أو حقل جوه الصف ───
            function openInvoice(row) {
                var href = $(row).attr('data-href');
                if (!href) return;
                $.ajax({ url: href, data: { v2: 1 }, dataType: 'html' }).done(function(html) {
                    $('.view_modal').html(html).modal('show');
                });
            }
            $('#sell_table').on('click', 'tbody tr.rr-tr', function(event) {
                if ($(event.target).closest('a, button, input, label, .rr-menu, .selectable_td').length) return;
                openInvoice(this);
            });
            $('#sell_table').on('keydown', 'tbody tr.rr-tr', function(event) {
                if (event.key === 'Enter' && event.target === this) {
                    event.preventDefault();
                    openInvoice(this);
                }
            });

            // ─── قوائم rr-menu (الإجراءات، الأعمدة، التصدير) ───
            var activeMenu = null, activeToggle = null;
            function closeMenu(returnFocus) {
                if (!activeMenu) return;
                activeMenu.style.display = 'none';
                activeToggle.setAttribute('aria-expanded', 'false');
                if (returnFocus) activeToggle.focus();
                activeMenu = activeToggle = null;
            }
            // القائمة بتتحط تحت الزرار أو فوقه حسب المساحة، وبتاخد سكرول جواها لو أطول من الشاشة
            function placeMenu(toggle, menu) {
                menu.style.maxHeight = '';
                var box = toggle.getBoundingClientRect(), width = menu.offsetWidth, height = menu.offsetHeight;
                var below = window.innerHeight - box.bottom - 12, above = box.top - 12;
                var down = height <= below || below >= above;
                var room = Math.max(160, down ? below : above);
                if (height > room) { menu.style.maxHeight = room + 'px'; height = room; }
                var left = getComputedStyle(toggle).direction === 'rtl' ? box.left : box.right - width;
                menu.style.left = Math.max(8, Math.min(window.innerWidth - width - 8, left)) + 'px';
                menu.style.top = (down ? box.bottom + 4 : box.top - height - 4) + 'px';
            }
            function openMenu(toggle, menu) {
                menu.style.display = 'block';
                menu.style.visibility = 'hidden';
                placeMenu(toggle, menu);
                menu.style.visibility = 'visible';
                toggle.setAttribute('aria-expanded', 'true');
                activeMenu = menu;
                activeToggle = toggle;
                var first = menu.querySelector('[role^="menuitem"]');
                if (first) first.focus({ preventScroll: true });
            }
            document.addEventListener('click', function(event) {
                var toggle = event.target.closest('[data-rr-menu-toggle]');
                if (toggle) {
                    event.stopPropagation();
                    var menu = document.getElementById(toggle.getAttribute('aria-controls'));
                    if (!menu) return;
                    if (activeMenu === menu) { closeMenu(); return; }
                    closeMenu();
                    openMenu(toggle, menu);
                    return;
                }
                if (!activeMenu) return;
                if (!event.target.closest('.rr-menu') || event.target.closest('a.rr-menu__item, button.rr-menu__item')) closeMenu();
            }, true);
            document.addEventListener('keydown', function(event) {
                if (!activeMenu) return;
                if (event.key === 'Escape') { closeMenu(true); return; }
                if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
                var items = Array.prototype.slice.call(activeMenu.querySelectorAll('[role^="menuitem"]'));
                var index = items.indexOf(document.activeElement);
                items[(index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
                event.preventDefault();
            });
            window.addEventListener('resize', function() { closeMenu(); });
            // سكرول الصفحة أو الجدول بيحرّك القائمة مع زرارها؛ أي سكرول تاني (القائمة الجانبية مثلًا) ما يقفلهاش
            window.addEventListener('scroll', function(event) {
                if (!activeMenu || activeMenu.contains(event.target)) return;
                if (event.target !== document && !event.target.contains(activeToggle)) return;
                var box = activeToggle.getBoundingClientRect();
                if (box.bottom < 0 || box.top > window.innerHeight) { closeMenu(); return; }
                placeMenu(activeToggle, activeMenu);
            }, true);
        });
    </script>
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection

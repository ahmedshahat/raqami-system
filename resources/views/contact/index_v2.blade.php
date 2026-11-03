@extends('layouts.app')
@section('title', __('contact_list.title_' . $type))

@php
    $api_key = env('GOOGLE_MAP_API_KEY');
    $is_customer = $type == 'customer';
    $can_view = auth()->user()->can($type . '.view') || auth()->user()->can($type . '.view_own');
    $can_add = auth()->user()->can('supplier.create') || auth()->user()->can('customer.create') || auth()->user()->can('supplier.view_own') || auth()->user()->can('customer.view_own');
    $can_import = auth()->user()->can('supplier.create') || auth()->user()->can('customer.create');
    $can_assign = config('constants.enable_contact_assign') === true;
    $tabs = ['all', 'due', 'advance', 'returns', 'opening'];
    $tab_params = [
        'due' => $is_customer ? 'has_sell_due' : 'has_purchase_due',
        'advance' => 'has_advance_balance',
        'returns' => $is_customer ? 'has_sell_return' : 'has_purchase_return',
        'opening' => 'has_opening_balance',
    ];
    // لون عداد كل تاب نفس لون مؤشره في الملخص
    $tab_tones = ['due' => 'warning', 'advance' => 'success', 'returns' => 'danger', 'opening' => 'info'];
    $icon = function ($name, $size = 16) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };

    // الأعمدة: key للقائمة، data/name لـ DataTables. الحقول المخصصة بتظهر في القائمة بس لو ليها اسم في إعدادات النشاط
    $optional_columns = ['mobile' => __('contact_list.col_mobile'), 'tax' => __('contact_list.col_tax')];
    if ($is_customer) {
        $optional_columns += ['group' => __('contact_list.col_group'), 'credit_limit' => __('contact_list.col_credit_limit')];
    }
    $optional_columns += [
        'due' => __('contact_list.col_due_' . $type),
        'advance' => __('contact_list.col_advance'),
        'return_due' => __('contact_list.col_return_due'),
        'opening' => __('contact_list.col_opening'),
        'pay_term' => __('contact_list.col_pay_term'),
        'email' => __('contact_list.col_email'),
        'address' => __('contact_list.col_address'),
        'created_at' => __('contact_list.col_added_on'),
    ];
    if (! empty($reward_enabled)) {
        $optional_columns['total_rp'] = session('business.rp_name') ?: __('contact_list.col_points');
    }
    $custom_labels = json_decode(session('business.custom_labels'), true) ?: [];
    $custom_columns = [];
    foreach (range(1, 10) as $number) {
        if (! empty($custom_labels['contact']['custom_field_' . $number])) {
            $custom_columns[] = 'custom_field' . $number;
            $optional_columns['custom_field' . $number] = $custom_labels['contact']['custom_field_' . $number];
        }
    }
@endphp

@if (! empty($api_key))
    @section('css')
        @include('contact.partials.google_map_styles')
    @endsection
@endif

@section('content')
<main class="content rr-page rr-list-page" aria-labelledby="rr_contacts_title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-list-page__head">
        <div class="rr-list-page__titles">
            <h1 id="rr_contacts_title" class="rr-head__title">@lang('contact_list.title_' . $type)</h1>
            <p class="rr-head__sub">@lang('contact_list.sub_' . $type)</p>
        </div>
        <div class="rr-head__actions">
            @if($can_import)
                <button type="button" class="rr-btn rr-btn--secondary" id="rr_contacts_import_btn">{!! $icon('upload', 20) !!}@lang('contact_list.import')</button>
            @endif
            @if($can_view)
                @can('view_export_buttons')
                    <button type="button" class="rr-btn rr-btn--secondary" id="rr_contacts_report_btn">{!! $icon('print', 20) !!}@lang('contact_list.report_print')</button>
                @endcan
            @endif
            @if($can_add)
                <button type="button" class="rr-btn rr-btn--primary btn-modal" data-href="{{ action([\App\Http\Controllers\ContactController::class, 'create'], ['type' => $type]) }}" data-container=".contact_modal">{!! $icon('plus', 20) !!}@lang('contact_list.add_' . $type)</button>
            @endif
        </div>
    </header>

    @if($can_view)
        {{-- ═══ الملخص: لكل النتائج من السيرفر، مش من الصفحة الظاهرة ═══ --}}
        <section class="rr-statgroup-wrap" aria-label="@lang('contact_list.kpis')">
            <div class="rr-statgroup rr-statgroup--5 rr-statgroup--toned" style="--rr-stats: 5" id="rr_contacts_kpis" aria-busy="true">
                <div class="rr-stat rr-stat--brand">
                    <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon($is_customer ? 'users' : 'truck') !!}</span>@lang('contact_list.kpi_count_' . $type)</span>
                    <span class="rr-stat__value" data-rr-kpi="contacts">—</span>
                    <span class="rr-stat__hint" data-rr-kpi="active"></span>
                </div>
                <a href="#" class="rr-stat rr-stat--warning" id="rr_contacts_due_kpi" aria-current="false">
                    <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('clock') !!}</span>@lang('contact_list.kpi_due_' . $type)</span>
                    <span class="rr-stat__value" data-rr-kpi="due">—</span>
                    <span class="rr-stat__hint">@lang('contact_list.kpi_due_hint')</span>
                </a>
                <div class="rr-stat rr-stat--danger">
                    <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('undo') !!}</span>@lang('contact_list.kpi_return_due')</span>
                    <span class="rr-stat__value" data-rr-kpi="return_due">—</span>
                </div>
                <div class="rr-stat rr-stat--success">
                    <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('wallet') !!}</span>@lang('contact_list.kpi_advance')</span>
                    <span class="rr-stat__value" data-rr-kpi="advance">—</span>
                </div>
                <div class="rr-stat rr-stat--info">
                    <span class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('coins') !!}</span>@lang('contact_list.kpi_opening_left')</span>
                    <span class="rr-stat__value" data-rr-kpi="opening_left">—</span>
                </div>
            </div>
        </section>

        {{-- ═══ الجدول: تابات الرصيد + أدوات التصفية ═══ --}}
        <section class="rr-table-wrap" aria-labelledby="rr_contacts_title">
            <nav class="rr-scope rr-tabs rr-tabs--inset" aria-label="@lang('contact_list.tabs')">
                @foreach($tabs as $tab)
                    <button type="button" class="rr-tab" data-rr-tab="{{ $tab == 'all' ? '' : $tab }}" @if($tab == 'all') aria-current="page" @endif>
                        @lang('contact_list.tab_' . $tab)
                        <span class="rr-tab__count rr-num{{ isset($tab_tones[$tab]) ? ' rr-tab__count--' . $tab_tones[$tab] : '' }}" data-rr-count="{{ $tab }}"></span>
                    </button>
                @endforeach
            </nav>

            <div class="rr-table-head rr-table-head--toolbar">
                <div class="rr-table-tools rr-list-tools">
                    <label class="rr-sr" for="rr_contacts_search">@lang('contact_list.search')</label>
                    <div class="rr-control rr-control--sm rr-table-tools__search">
                        <span class="rr-control__lead">{!! $icon('search') !!}</span>
                        <input class="rr-control__input" type="search" id="rr_contacts_search" placeholder="@lang('contact_list.search_ph')" autocomplete="off">
                    </div>
                    {{-- موبايل بس: باقي الفلاتر متلمومة ورا الزرار ده --}}
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm rr-list-tools__fold" id="rr_contacts_filters_toggle" aria-expanded="false">{!! $icon('filter') !!}@lang('contact_list.filters')<span class="rr-list-tools__count rr-num" id="rr_contacts_filters_count" hidden></span></button>

                    <label class="rr-sr" for="rr_contacts_status">@lang('contact_list.status')</label>
                    <div class="rr-control rr-control--sm rr-table-tools__select">
                        <select class="rr-control__input rr-control__select" id="rr_contacts_status" data-rr-filter="contact_status" data-rr-filter-label="@lang('contact_list.status')">
                            <option value="">@lang('contact_list.all_statuses')</option>
                            <option value="active">@lang('contact_list.active')</option>
                            <option value="inactive">@lang('contact_list.inactive')</option>
                        </select>
                        <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                    </div>

                    @if($is_customer)
                        <label class="rr-sr" for="rr_contacts_group">@lang('contact_list.group')</label>
                        <div class="rr-control rr-control--sm rr-table-tools__select">
                            <select class="rr-control__input rr-control__select" id="rr_contacts_group" data-rr-filter="customer_group_id" data-rr-filter-label="@lang('contact_list.group')">
                                <option value="">@lang('contact_list.all_groups')</option>
                                @foreach($customer_groups as $id => $name)
                                    @if($id !== '' && $id !== null)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                        </div>

                        <label class="rr-sr" for="rr_contacts_no_sell">@lang('contact_list.no_sell_from')</label>
                        <div class="rr-control rr-control--sm rr-table-tools__select">
                            <select class="rr-control__input rr-control__select" id="rr_contacts_no_sell" data-rr-filter="has_no_sell_from" data-rr-filter-label="@lang('contact_list.no_sell_from')">
                                <option value="">@lang('contact_list.no_sell_from'): @lang('contact_list.any_time')</option>
                                <option value="one_month">@lang('lang_v1.one_month')</option>
                                <option value="three_months">@lang('lang_v1.three_months')</option>
                                <option value="six_months">@lang('lang_v1.six_months')</option>
                                <option value="one_year">@lang('lang_v1.one_year')</option>
                            </select>
                            <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                        </div>
                    @endif

                    @if($can_assign)
                        <label class="rr-sr" for="rr_contacts_assigned">@lang('contact_list.assigned_to')</label>
                        <div class="rr-control rr-control--sm rr-table-tools__select">
                            <select class="rr-control__input rr-control__select" id="rr_contacts_assigned" data-rr-filter="assigned_to" data-rr-filter-label="@lang('contact_list.assigned_to')">
                                <option value="">@lang('contact_list.all_users')</option>
                                @foreach($users as $id => $name)
                                    @if($id !== '' && $id !== null)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <span class="rr-control__chev">{!! $icon('chevron-down') !!}</span>
                        </div>
                    @endif

                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr_contacts_columns_menu">{!! $icon('columns') !!}@lang('contact_list.columns')</button>
                    <div class="rr-menu rr-menu--checks" id="rr_contacts_columns_menu" role="menu" aria-label="@lang('contact_list.columns_title')">
                        <p class="rr-menu__note">@lang('contact_list.columns_title')</p>
                        @foreach($optional_columns as $key => $label)
                            <label class="rr-menu__item rr-menu__check"><input type="checkbox" class="rr-toggle__input" role="menuitemcheckbox" data-rr-col="{{ $key }}">{{ $label }}</label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- الفلاتر المطبقة --}}
            <div class="rr-filter-chips" id="rr_contacts_chips" aria-label="@lang('contact_list.active_filters')" hidden></div>

            <table class="rr-table rr-table--contacts rr-table--cards rr-table--tinted" id="rr_contact_table">
                <thead>
                    <tr>
                        <th class="rr-th"><span class="rr-th__in">@lang('contact_list.col_contact')</span></th>
                        @foreach($optional_columns as $key => $label)
                            <th class="rr-th{{ in_array($key, ['due', 'advance', 'return_due', 'opening', 'credit_limit', 'total_rp']) ? ' rr-th--num' : '' }}"><span class="rr-th__in">{{ $label }}</span></th>
                        @endforeach
                        <th class="rr-th"><span class="rr-sr">@lang('contact_list.col_actions')</span></th>
                        <th class="rr-th"></th>
                        <th class="rr-th"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            {{-- موبايل بس: بدل أرقام الصفحات --}}
            <div class="rr-loadmore" id="rr_contacts_loadmore" hidden>
                <button type="button" class="rr-btn rr-btn--secondary" id="rr_contacts_more_rows">@lang('contact_list.load_more') <span class="rr-loadmore__left rr-num"></span></button>
            </div>
        </section>

        <p class="rr-list-page__foot">
            <a class="rr-linkbtn" href="{{ action([\App\Http\Controllers\ContactController::class, 'index'], ['type' => $type, 'classic' => 1]) }}">@lang('contact_list.old_page')</a>
        </p>
    @endif
</main>

<div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade pay_contact_due_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
@if($can_import)
    @include('contact.partials.import_modal')
@endif
@can('view_export_buttons')
    <div class="modal fade rr-scope" id="rr_contacts_report_modal" tabindex="-1" role="dialog" aria-labelledby="rr_contacts_report_title">
        <div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm" role="document">
            <div class="modal-content rr-modal">
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr_contacts_report_title">@lang('contact_list.report_title_' . $type)</h2>
                        <p class="rr-modal__desc">@lang('contact_list.report_desc')</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('contact_list.close')">{!! $icon('close') !!}</button>
                </header>
                <div class="rr-modal__body">
                    <div class="rr-field">
                        <span class="rr-field__label">@lang('contact_list.report_scope')</span>
                        <div class="rr-segmented" role="group" aria-label="@lang('contact_list.report_scope')">
                            <button type="button" class="rr-segmented__item" data-rr-report-scope="all" aria-pressed="true">@lang('contact_list.report_scope_all_' . $type)</button>
                            <button type="button" class="rr-segmented__item" data-rr-report-scope="due" aria-pressed="false">@lang('contact_list.report_scope_due')</button>
                        </div>
                    </div>
                    <div class="rr-field">
                        <span class="rr-field__label">@lang('contact_list.report_columns')</span>
                        <div class="rr-form-grid rr-report-cols">
                            @foreach(\App\Exports\ContactsListExport::reportColumnOptions($type) as $key => $option)
                                <label class="rr-toggle"><input type="checkbox" class="rr-toggle__input" data-rr-report-col="{{ $key }}" @checked($option[1])>{{ $option[0] }}</label>
                            @endforeach
                        </div>
                        <span class="rr-field__hint">@lang('contact_list.report_columns_hint')</span>
                    </div>
                </div>
                <footer class="rr-modal__foot">
                    <button type="button" class="rr-btn rr-btn--primary" data-rr-report-go="print">{!! $icon('print', 20) !!}@lang('contact_list.print')</button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-rr-report-go="report_xlsx">{!! $icon('download', 20) !!}@lang('contact_list.report_excel')</button>
                    <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('contact_list.close')</button>
                </footer>
            </div>
        </div>
    </div>
@endcan
@stop

@section('javascript')
    @include('contact.partials.google_map_js')
    @include('partials.rr.menu_js')
    @if($can_import)
    <script type="text/javascript">
        $(document).ready(function() {
            var modal = $('#rr_contacts_import_modal');
            var form = $('#rr_contacts_import_form');
            var errorBox = $('#rr_contacts_import_error');
            var submit = $('#rr_contacts_import_go');
            var FAILED = @json(__('contact_list.import_failed'));
            var BUSY = @json(__('contact_list.import_busy'));
            var IDLE = submit.find('span').text();

            function openImport() {
                errorBox.empty();
                modal.modal('show');
            }
            $('#rr_contacts_import_btn').on('click', openImport);
            // جاي من رابط الاستيراد القديم: بتفتح مرة واحدة، والتحديث بعدها ما يفتحهاش تاني
            @if(request()->boolean('import'))
                openImport();
                window.history.replaceState(null, '', window.location.pathname + '?type=' + @json($type));
            @endif

            form.on('submit', function(event) {
                event.preventDefault();
                errorBox.empty();
                submit.prop('disabled', true).find('span').text(BUSY);
                $.ajax({
                    method: 'POST',
                    url: form.attr('action'),
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                }).done(function(result) {
                    if (result.success) {
                        toastr.success(result.msg);
                        form[0].reset();
                        modal.modal('hide');
                        if (typeof contact_table !== 'undefined') contact_table.ajax.reload();
                    } else {
                        errorBox.text(result.msg);
                    }
                }).fail(function() {
                    errorBox.text(FAILED);
                }).always(function() {
                    submit.prop('disabled', false).find('span').text(IDLE);
                });
            });
        });
    </script>
    @endif
    @if($can_view)
    <script type="text/javascript">
        $(document).ready(function() {
            var TYPE = @json($type);
            var STR = @json(__('contact_list'));
            var TAB_PARAMS = @json($tab_params);
            var CUSTOM_COLUMNS = @json($custom_columns);
            var IS_CUSTOMER = TYPE === 'customer';
            var colsKey = 'rr-contacts-' + TYPE + '-cols-{{ auth()->id() }}';
            var ICON = { chevron: @json($icon('chevron-down')), wallet: @json($icon('wallet')), close: @json($icon('close', 14)) };
            var state = { tab: '' };

            function esc(value) {
                return $('<div>').text(value == null ? '' : String(value)).html();
            }
            // الخلايا جاية من السيرفر HTML-escaped. DOMParser بيفك الترميز من غير ما يشغّل أي حاجة جوه النص
            function textOf(html) {
                var doc = new DOMParser().parseFromString(html == null ? '' : String(html), 'text/html');
                $(doc.body).find('small, .label').remove();
                return $.trim(doc.body.textContent.replace(/\s+/g, ' '));
            }
            function origValue(html) {
                var doc = new DOMParser().parseFromString(html == null ? '' : String(html), 'text/html');
                var node = doc.body.querySelector('[data-orig-value]');
                var value = node ? parseFloat(node.getAttribute('data-orig-value')) : NaN;
                return isNaN(value) ? 0 : value;
            }
            function money(value, withSymbol) {
                return __currency_trans_from_en(value, withSymbol);
            }
            function moneyCell(html, type, tone) {
                var value = origValue(html);
                if (type !== 'display') return value;
                return '<span class="rr-num' + (value == 0 ? ' rr-money--zero' : (tone ? ' rr-amount--' + tone : '')) + '">' + money(value, false) + '</span>';
            }
            function displayName(row) {
                return textOf(row.supplier_business_name) || textOf(row.name);
            }
            function plainCell(tone) {
                return function(data, type) {
                    return type === 'display' ? (tone ? '<span class="' + tone + '">' + esc(textOf(data)) + '</span>' : esc(textOf(data))) : textOf(data);
                };
            }

            // ─── الجدول ───
            // الحرف الأول من الاسم، واللون ثابت لنفس جهة الاتصال لأنه محسوب من الكود
            function avatar(label, code) {
                var letters = Array.from($.trim(label).replace(/^ال(?=.{2})/, ''));
                var hash = Array.from(code).reduce(function(sum, ch) { return sum + ch.charCodeAt(0); }, 0);
                return '<span class="rr-avatar rr-avatar--tone-' + (hash % 5) + '" aria-hidden="true">' + esc(letters[0] || '') + '</span>';
            }
            function contactCell(row) {
                var business = textOf(row.supplier_business_name), name = textOf(row.name), code = textOf(row.contact_id);
                var sub = (business && name ? '<span>' + esc(name) + '</span> · ' : '') + '<span class="rr-num">' + esc(code) + '</span>';
                var inactive = row.contact_status !== 'active'
                    ? ' <span class="rr-badge rr-badge--danger">' + esc(STR.inactive) + '</span>' : '';
                return '<div class="rr-contact-cell">' + avatar(business || name, code) +
                    '<div class="rr-cell-stack"><span class="rr-cell-stack__main">' + esc(business || name) + inactive + '</span>' +
                    '<span class="rr-cell-stack__sub">' + sub + '</span></div></div>';
            }

            function buildActions(row) {
                var source = $('<div>').html(row.action || '');
                var list = source.find('ul.dropdown-menu');
                if (!list.length) return row.action || '';
                var id = (String(source.find('a[href*="/contacts/"]').first().attr('href')).match(/\/contacts\/(\d+)/) || [])[1] || Math.random().toString(36).slice(2);
                var items = [], danger = null, lastSep = true, quick = '';

                var payLink = source.find('a.pay_purchase_due, a.pay_sale_due').first();
                if (payLink.length) {
                    quick = '<a href="' + esc(payLink.attr('href')) + '" class="' + esc(payLink.attr('class')) + ' rr-btn rr-btn--tint" title="' + esc(STR.pay) + '" aria-label="' + esc(STR.pay) + '">' + ICON.wallet + '<span class="rr-row-actions__label">' + esc(STR.pay) + '</span></a>';
                }

                list.children('li').each(function() {
                    var li = $(this);
                    if (li.hasClass('divider')) {
                        if (!lastSep) { items.push('<div class="rr-menu__sep" role="separator"></div>'); lastSep = true; }
                        return;
                    }
                    var link = li.children('a').first();
                    if (!link.length) return;
                    // تسجيل الدفعة ظاهر كزرار في الصف
                    if (payLink.length && link.attr('href') === payLink.attr('href')) return;
                    var label = $.trim(link.text().replace(/\s+/g, ' '));
                    link.empty().text(label).addClass('rr-menu__item').attr('role', 'menuitem');
                    if (link.hasClass('delete_contact_button')) {
                        danger = link.addClass('rr-menu__item--danger').prop('outerHTML');
                        return;
                    }
                    items.push(link.prop('outerHTML'));
                    lastSep = false;
                });
                if (items.length && lastSep) items.pop();
                if (danger) items.push('<div class="rr-menu__sep" role="separator"></div>', danger);

                return '<div class="rr-row-actions">' + quick +
                    '<button type="button" class="rr-btn rr-btn--secondary" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr_contact_menu_' + id + '" aria-label="' + esc(STR.more_actions.replace(':name', displayName(row))) + '"><span class="rr-row-actions__label">' + esc(STR.col_actions) + '</span>' + ICON.chevron + '</button>' +
                    '<div class="rr-menu" id="rr_contact_menu_' + id + '" role="menu">' + items.join('') + '</div></div>';
            }

            var columns = [
                { data: 'name', name: 'name', className: 'rr-td rr-m-title',
                    render: function(d, t, row) { return t === 'display' ? contactCell(row) : displayName(row); } },
                { key: 'mobile', data: 'mobile', name: 'mobile', orderable: false, className: 'rr-td rr-m-sub', render: plainCell('rr-num') },
                { key: 'tax', data: 'tax_number', name: 'tax_number', orderable: false, className: 'rr-td rr-td--muted', render: plainCell('rr-num') },
            ];
            if (IS_CUSTOMER) {
                columns.push(
                    { key: 'group', data: 'customer_group', name: 'cg.name', searchable: false, className: 'rr-td rr-td--muted', render: plainCell() },
                    { key: 'credit_limit', data: 'credit_limit', name: 'credit_limit', searchable: false, className: 'rr-td rr-td--num', visible: false,
                        render: function(d, t) {
                            if (t !== 'display') return textOf(d);
                            return /data-orig-value/.test(String(d)) ? moneyCell(d, t) : '<span class="rr-td--muted">' + esc(textOf(d)) + '</span>';
                        } }
                );
            }
            columns.push(
                { key: 'due', data: 'due', name: 'due', searchable: false, className: 'rr-td rr-td--num rr-m-amount', render: function(d, t) { return moneyCell(d, t, 'warning'); } },
                { key: 'advance', data: 'balance', name: 'balance', searchable: false, className: 'rr-td rr-td--num', render: function(d, t) { return moneyCell(d, t, 'success'); } },
                { key: 'return_due', data: 'return_due', searchable: false, orderable: false, className: 'rr-td rr-td--num', visible: false, render: function(d, t) { return moneyCell(d, t, 'danger'); } },
                { key: 'opening', data: 'opening_balance', name: 'opening_balance', searchable: false, className: 'rr-td rr-td--num', visible: false, render: function(d, t) { return moneyCell(d, t); } },
                { key: 'pay_term', data: 'pay_term', searchable: false, orderable: false, className: 'rr-td rr-td--muted', visible: false, render: plainCell() },
                { key: 'email', data: 'email', name: 'email', orderable: false, className: 'rr-td rr-td--muted', visible: false, render: plainCell() },
                { key: 'address', data: 'address', name: 'address', orderable: false, className: 'rr-td rr-td--muted rr-td--wrap', visible: false, render: plainCell() },
                { key: 'created_at', data: 'created_at', name: 'contacts.created_at', searchable: false, className: 'rr-td rr-td--muted', visible: false, render: plainCell('rr-num') }
            );
            if ($('[data-rr-col="total_rp"]').length) {
                columns.push({ key: 'total_rp', data: 'total_rp', name: 'total_rp', searchable: false, className: 'rr-td rr-td--num', visible: false, render: plainCell('rr-num') });
            }
            $.each(CUSTOM_COLUMNS, function(i, field) {
                columns.push({ key: field, data: field, name: field, orderable: false, className: 'rr-td rr-td--muted', visible: false, render: plainCell() });
            });
            columns.push(
                { data: 'action', orderable: false, searchable: false, className: 'rr-td rr-td--actions rr-m-status',
                    render: function(d, t, row) { return t === 'display' ? buildActions(row) : ''; } },
                // أعمدة مخفية: الترتيب الافتراضي بالكود، والبحث باسم النشاط
                { data: 'contact_id', name: 'contact_id', visible: false },
                { data: 'supplier_business_name', name: 'supplier_business_name', orderable: false, visible: false }
            );
            var codeColumn = columns.length - 2;

            // الأعمدة المحفوظة لليوزر ده
            var savedCols = {};
            try { savedCols = JSON.parse(localStorage.getItem(colsKey) || '{}') || {}; } catch (e) {}
            $.each(columns, function(i, col) {
                if (col.key && savedCols.hasOwnProperty(col.key)) col.visible = !!savedCols[col.key];
            });

            // ─── التصفية ───
            function filterParams() {
                var params = { type: TYPE };
                $('[data-rr-filter]').each(function() {
                    if ($(this).val()) params[$(this).data('rr-filter')] = $(this).val();
                });
                return params;
            }
            function tabParams() {
                var params = {};
                if (state.tab) params[TAB_PARAMS[state.tab]] = 1;
                return params;
            }

            var lastSummaryKey = null, lastPageKey = null, tabSwitch = false;
            // عام عشان app.js بيعمل له reload بعد الإضافة والتعديل والحذف وتغيير الحالة
            contact_table = $('#rr_contact_table').DataTable({
                processing: true,
                serverSide: true,
                fixedHeader: false,
                autoWidth: false,
                dom: 'r<"rr-table-scroll"t><"rr-pager"<"rr-pager__sum"il><"rr-pager__pages"p>>',
                order: [[codeColumn, 'desc']],
                language: $.extend({}, $.fn.dataTable.defaults.language, { emptyTable: STR.empty, zeroRecords: STR.empty }),
                ajax: {
                    url: '/contacts',
                    data: function(d) {
                        $.extend(d, filterParams(), tabParams());
                        d = __datatable_ajax_callback(d);
                        // الملخص بيتحدّث لما الفلاتر تتغير أو الجدول يتعمله reload، مش مع تقليب الصفحات أو التابات
                        var summaryKey = JSON.stringify([filterParams(), d.search.value]);
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
                    var href = $('<div>').html(data.action || '').find('a[href]').filter(function() { return /\/contacts\/\d+$/.test($(this).attr('href')) && !$(this).attr('class'); }).first().attr('href');
                    $(row).addClass('rr-tr').attr('tabindex', 0);
                    if (href) $(row).attr('data-href', href);
                },
                drawCallback: function() {
                    var info = this.api().page.info();
                    var left = info.recordsDisplay - info.end;
                    $('#rr_contacts_loadmore').prop('hidden', !(info.start === 0 && left > 0))
                        .find('.rr-loadmore__left').text(STR.load_more_left.replace(':count', left));
                    $('#rr_contacts_more_rows').prop('disabled', false);
                }
            });

            // ─── عرض المزيد (موبايل): بيكبّر طول الصفحة بدل التقليب ───
            var mobileQuery = window.matchMedia('(max-width: 767px)');
            var baseLength = contact_table.page.len();
            function resetMobileLength() {
                if (mobileQuery.matches && contact_table.page.len() !== baseLength) contact_table.page.len(baseLength);
            }
            $('#rr_contacts_more_rows').on('click', function() {
                $(this).prop('disabled', true);
                contact_table.page.len(contact_table.page.len() + 25).draw(false);
            });
            function reload() {
                resetMobileLength();
                contact_table.ajax.reload();
            }

            function renderChips() {
                var chips = [];
                $('[data-rr-filter]').each(function() {
                    if (!$(this).val()) return;
                    var label = $(this).data('rr-filter-label');
                    chips.push('<button type="button" class="rr-filter-chip" data-rr-clear="' + esc(this.id) + '" aria-label="' + esc(STR.remove_filter.replace(':name', label)) + '">' +
                        '<span class="rr-filter-chip__label">' + esc(label) + ':</span> ' + esc($(this).find('option:selected').text()) + ICON.close + '</button>');
                });
                var count = chips.length;
                if (count > 1) chips.push('<button type="button" class="rr-linkbtn" data-rr-clear-all>' + esc(STR.clear_filters) + '</button>');
                $('#rr_contacts_chips').html(chips.join('')).prop('hidden', !count);
                $('#rr_contacts_filters_count').text(count).prop('hidden', !count);
            }
            $(document).on('change', '[data-rr-filter]', function() {
                renderChips();
                reload();
            });
            $('#rr_contacts_chips').on('click', '[data-rr-clear]', function() {
                $('#' + $(this).data('rr-clear')).val('').trigger('change');
            });
            $('#rr_contacts_chips').on('click', '[data-rr-clear-all]', function() {
                $('[data-rr-filter]').val('');
                renderChips();
                reload();
            });
            $('#rr_contacts_filters_toggle').on('click', function() {
                var open = $(this).attr('aria-expanded') !== 'true';
                $(this).attr('aria-expanded', open ? 'true' : 'false');
                $(this).closest('.rr-table-wrap').toggleClass('is-filters-open', open);
            });

            var searchTimer = null;
            $('#rr_contacts_search').on('input', function() {
                var value = this.value;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() { resetMobileLength(); contact_table.search(value).draw(); }, 350);
            });

            // ─── التابات + مؤشر المستحق ───
            function setTab(tab) {
                state.tab = tab;
                $('[data-rr-tab]').each(function() {
                    if ($(this).data('rr-tab') === tab) { $(this).attr('aria-current', 'page'); } else { $(this).removeAttr('aria-current'); }
                });
                $('#rr_contacts_due_kpi').attr('aria-current', tab === 'due' ? 'true' : 'false');
                tabSwitch = true;
                reload();
            }
            $('[data-rr-tab]').on('click', function() { setTab($(this).data('rr-tab')); });
            $('#rr_contacts_due_kpi').on('click', function(event) {
                event.preventDefault();
                setTab(state.tab === 'due' ? '' : 'due');
            });

            // ─── الملخص ───
            var summaryRequest = null;
            function loadSummary() {
                if (summaryRequest) summaryRequest.abort();
                var params = filterParams();
                params.search = $.trim($('#rr_contacts_search').val());
                $('#rr_contacts_kpis').attr('aria-busy', 'true');
                summaryRequest = $.getJSON(@json(route('contacts.list_summary')), params).done(function(data) {
                    $('[data-rr-kpi="contacts"]').text(__number_f(data.contacts, false, false, 0));
                    $('[data-rr-kpi="active"]').text(STR.kpi_active.replace(':count', __number_f(data.active, false, false, 0)));
                    $.each(['due', 'return_due', 'advance', 'opening_left'], function(i, key) {
                        $('[data-rr-kpi="' + key + '"]').text(money(data[key], true));
                    });
                    $.each(data.counts, function(key, count) {
                        $('[data-rr-count="' + key + '"]').text(__number_f(count, false, false, 0)).toggleClass('is-zero', count == 0);
                    });
                }).fail(function(xhr, status) {
                    if (status !== 'abort') toastr.error(STR.kpi_failed);
                }).always(function() {
                    $('#rr_contacts_kpis').attr('aria-busy', 'false');
                });
            }

            // ─── اختيار الأعمدة ───
            $.each(columns, function(i, col) {
                if (col.key) $('[data-rr-col="' + col.key + '"]').prop('checked', contact_table.column(i).visible());
            });
            $('[data-rr-col]').on('change', function() {
                var key = $(this).data('rr-col');
                var index = columns.findIndex(function(col) { return col.key === key; });
                if (index < 0) return;
                contact_table.column(index).visible(this.checked);
                savedCols[key] = this.checked;
                try { localStorage.setItem(colsKey, JSON.stringify(savedCols)); } catch (e) {}
            });

            // ─── التقرير: من السيرفر لكل النتائج. تاب المستحقات بديله نطاق التقرير، فمش بيتكتب في الفلاتر ───
            function exportNote() {
                var parts = [];
                if (state.tab && state.tab !== 'due') parts.push(STR['tab_' + state.tab]);
                $('[data-rr-filter]').each(function() {
                    if ($(this).val()) parts.push($(this).data('rr-filter-label') + ': ' + $(this).find('option:selected').text());
                });
                var search = $.trim($('#rr_contacts_search').val());
                if (search) parts.push(STR.search + ': ' + search);
                return parts.join('، ');
            }

            // نطاق التقرير من النافذة، والأعمدة المختارة بتتحفظ لليوزر ده
            var reportKey = 'rr-contacts-' + TYPE + '-report-{{ auth()->id() }}';
            var reportCols = {};
            try { reportCols = JSON.parse(localStorage.getItem(reportKey) || '{}') || {}; } catch (e) {}
            $('[data-rr-report-col]').each(function() {
                var key = $(this).data('rr-report-col');
                if (reportCols.hasOwnProperty(key)) this.checked = !!reportCols[key];
            });
            function setReportScope(scope) {
                $('[data-rr-report-scope]').each(function() {
                    $(this).attr('aria-pressed', $(this).data('rr-report-scope') === scope ? 'true' : 'false');
                });
            }
            $('[data-rr-report-scope]').on('click', function() { setReportScope($(this).data('rr-report-scope')); });
            $('#rr_contacts_report_btn').on('click', function() {
                setReportScope(state.tab === 'due' ? 'due' : 'all');
                $('#rr_contacts_report_modal').modal('show');
            });
            $('[data-rr-report-go]').on('click', function() {
                var format = $(this).data('rr-report-go');
                var cols = [];
                $('[data-rr-report-col]').each(function() {
                    reportCols[$(this).data('rr-report-col')] = this.checked;
                    if (this.checked) cols.push($(this).data('rr-report-col'));
                });
                try { localStorage.setItem(reportKey, JSON.stringify(reportCols)); } catch (e) {}

                // فلتر "عليها مستحقات" في الجدول بيسيب اللي عليه رصيد افتتاحي بس، والتقرير بيحسبه بـ scope
                var params = $.extend(filterParams(), tabParams());
                delete params[TAB_PARAMS.due];
                var url = @json(route('contacts.list_export')) + '?' + $.param($.extend(params, {
                    format: format,
                    scope: $('[data-rr-report-scope][aria-pressed="true"]').data('rr-report-scope'),
                    cols: cols.join(','),
                    search: $.trim($('#rr_contacts_search').val()),
                    note: exportNote()
                }));
                if (format === 'print') { window.open(url, '_blank'); } else { window.location.href = url; }
                $('#rr_contacts_report_modal').modal('hide');
            });

            // ─── الضغط على الصف (أو Enter) بيفتح صفحة جهة الاتصال، إلا لو الضغط على زرار أو رابط جوه الصف ───
            function openContact(row) {
                var href = $(row).attr('data-href');
                if (href) window.location.href = href;
            }
            $('#rr_contact_table').on('click', 'tbody tr.rr-tr', function(event) {
                if ($(event.target).closest('a, button, input, label, .rr-menu').length) return;
                openContact(this);
            });
            $('#rr_contact_table').on('keydown', 'tbody tr.rr-tr', function(event) {
                if (event.key === 'Enter' && event.target === this) {
                    event.preventDefault();
                    openContact(this);
                }
            });
        });
    </script>
    @endif
@endsection

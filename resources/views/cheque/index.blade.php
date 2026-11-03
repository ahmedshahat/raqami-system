@extends('layouts.app')
@section('title', __('cheque.cheques_report'))

@php
    $stat_buckets = [
        ['overdue', 'fa-exclamation-circle', __('cheque.overdue'), 'danger'],
        ['today', 'fa-calendar-day', __('cheque.today'), 'warning'],
        ['this_week', 'fa-calendar-week', __('cheque.this_week'), 'info'],
        ['this_month', 'fa-calendar-alt', __('cheque.this_month'), 'success'],
        ['later', 'fa-hourglass-half', __('cheque.later'), 'neutral'],
    ];
@endphp

@section('css')
    {{-- نظام تصميم رقمي (نفس شاشات موديول المقاولات) --}}
    <link rel="stylesheet" href="{{ asset('css/raqmi.css?v=' . @filemtime(public_path('css/raqmi.css'))) }}">
@endsection

@section('content')
<main class="content rr-page rr-page--md" aria-labelledby="rr-cheques-title">

    <header class="rr-head">
        <img class="rr-head__logo" src="{{ asset('img/logo-small.png') }}" width="56" height="56" alt="">
        <div class="rr-head__titles">
            <h1 id="rr-cheques-title" class="rr-head__title">{{ __('cheque.cheques_report') }}</h1>
            <p class="rr-head__sub">{{ __('cheque.report_intro') }}</p>
        </div>
        <div class="rr-head__actions">
            <button type="button" class="rr-btn rr-btn--secondary" id="cheque_print_btn">
                <i class="fa fa-print"></i>{{ __('cheque.print') }}
            </button>
        </div>
    </header>

    <nav class="rr-tabs" aria-label="{{ __('cheque.cheques') }}">
        @foreach($types as $type)
            <a class="rr-tab" href="#tab_{{ $type }}" data-cheque-tab="{{ $type }}" @if($active_tab == $type) aria-current="page" @endif>
                <i class="fa {{ $type == 'receivable' ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                {{ __('cheque.' . $type) }}
                @if(!empty($summaries[$type]['overdue_count']))
                    <span class="rr-badge rr-badge--danger"><span class="rr-num">{{ (int) $summaries[$type]['overdue_count'] }}</span></span>
                @endif
            </a>
        @endforeach
        <a class="rr-tab" href="#tab_cash_flow" data-cheque-tab="cash_flow" @if($active_tab == 'cash_flow') aria-current="page" @endif>
            <i class="fa fa-exchange-alt"></i>{{ __('cheque.cash_flow') }}
        </a>
    </nav>

    @foreach($types as $type)
        @php $s = $summaries[$type]; @endphp
        <section class="cheque-pane rr-section" id="tab_{{ $type }}" @if($active_tab != $type) hidden @endif>

            <div class="rr-statgroup-wrap">
                <div class="rr-statgroup rr-statgroup--6" style="--rr-stats: 6">
                    @foreach($stat_buckets as [$bucket, $icon, $label, $tone])
                        @if($bucket == 'overdue')
                            <a href="#" class="rr-stat rr-stat--{{ $tone }} cheque-quick-filter" data-type="{{ $type }}" data-bucket="overdue">
                        @else
                            <div class="rr-stat rr-stat--{{ $tone }}">
                        @endif
                            <div class="rr-stat__label"><span class="rr-stat__icon"><i class="fa {{ $icon }}"></i></span>{{ $label }}</div>
                            <div class="rr-stat__value {{ $bucket == 'overdue' && $s['overdue'] > 0 ? 'rr-stat__value--danger' : '' }}"><span class="rr-num">{{ @num_format($s[$bucket]) }}</span></div>
                            <div class="rr-stat__hint">{{ __('cheque.cheques_count', ['count' => (int) $s[$bucket . '_count']]) }}</div>
                        @if($bucket == 'overdue')
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                    <div class="rr-stat rr-stat--brand">
                        <div class="rr-stat__label"><span class="rr-stat__icon"><i class="fa fa-layer-group"></i></span>{{ $type == 'payable' ? __('cheque.not_cleared') : __('cheque.status_pending') }}</div>
                        <div class="rr-stat__value"><span class="rr-num">{{ @num_format($s['total']) }}</span></div>
                        <div class="rr-stat__hint">{{ __('cheque.cheques_count', ['count' => (int) $s['total_count']]) }}</div>
                    </div>
                </div>
            </div>

            @php
                $status_options = [
                    'pending' => $type == 'payable' ? __('cheque.status_pending_payable') : __('cheque.status_pending'),
                    'collected' => $type == 'payable' ? __('cheque.status_cleared') : __('cheque.status_collected'),
                    'bounced' => __('cheque.status_bounced'),
                    'cancelled' => __('cheque.status_cancelled'),
                    'all' => __('cheque.all'),
                ];
            @endphp
            <form class="rr-filter cheque-filters" data-type="{{ $type }}" role="search" onsubmit="return false;">
                <div class="rr-filter__fields">
                    <div class="rr-field rr-filter__grow">
                        <label class="rr-field__label" for="contact_id_{{ $type }}">{{ $type == 'receivable' ? __('cheque.customer') : __('cheque.supplier') }}</label>
                        {!! Form::select('contact_id', $type == 'receivable' ? $customers : $suppliers, null, ['class' => 'form-control select2 cheque-filter', 'style' => 'width:100%', 'placeholder' => __('cheque.all'), 'id' => 'contact_id_' . $type]); !!}
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="status_{{ $type }}">{{ __('cheque.status') }}</label>
                        <div class="rr-control">
                            <select name="status" id="status_{{ $type }}" class="rr-control__input rr-control__select cheque-filter">
                                @foreach($status_options as $value => $label)
                                    <option value="{{ $value }}" @selected($value == 'pending')>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="rr-control__chev"><i class="fa fa-chevron-down"></i></span>
                        </div>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="location_id_{{ $type }}">{{ __('purchase.business_location') }}</label>
                        <div class="rr-control">
                            <select name="location_id" id="location_id_{{ $type }}" class="rr-control__input rr-control__select cheque-filter">
                                <option value="">{{ __('cheque.all') }}</option>
                                @foreach($business_locations as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <span class="rr-control__chev"><i class="fa fa-chevron-down"></i></span>
                        </div>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="bank_name_{{ $type }}">{{ __('cheque.bank_name') }}</label>
                        <div class="rr-control">
                            <input type="search" name="bank_name" id="bank_name_{{ $type }}" class="rr-control__input cheque-filter" placeholder="{{ __('cheque.bank_name') }}">
                        </div>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="start_date_{{ $type }}">{{ __('cheque.due_from') }}</label>
                        <div class="rr-control">
                            <input type="date" name="start_date" id="start_date_{{ $type }}" class="rr-control__input cheque-filter">
                        </div>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="end_date_{{ $type }}">{{ __('cheque.due_to') }}</label>
                        <div class="rr-control">
                            <input type="date" name="end_date" id="end_date_{{ $type }}" class="rr-control__input cheque-filter">
                        </div>
                    </div>
                    <input type="hidden" name="due_bucket" value="" class="cheque-filter">
                </div>
                <div class="rr-filter__actions">
                    <button type="button" class="rr-btn rr-btn--ghost cheque-reset">{{ __('cheque.reset_filter') }}</button>
                </div>
            </form>

            <section class="rr-table-wrap">
                <div class="rr-table-head">
                    <h2 class="rr-table-head__title">{{ __('cheque.' . $type) }}</h2>
                </div>
                <div class="rr-table-scroll">
                    <table class="rr-table rr-table--tinted cheques-table" id="cheques_table_{{ $type }}" data-type="{{ $type }}" data-url="{{ action([\App\Http\Controllers\ChequeController::class, 'data'], [$type]) }}">
                        <thead>
                            <tr>
                                <th class="rr-th"><span class="rr-th__in">{{ __('cheque.cheque_no') }}</span></th>
                                <th class="rr-th"><span class="rr-th__in">{{ $type == 'receivable' ? __('cheque.customer') : __('cheque.supplier') }}</span></th>
                                <th class="rr-th"><span class="rr-th__in">{{ __('cheque.bank_name') }}</span></th>
                                <th class="rr-th"><span class="rr-th__in">{{ __('cheque.invoice') }}</span></th>
                                <th class="rr-th rr-th--num"><span class="rr-th__in">{{ __('cheque.amount') }}</span></th>
                                <th class="rr-th"><span class="rr-th__in">{{ $type == 'receivable' ? __('cheque.received_on') : __('cheque.issued_on') }}</span></th>
                                <th class="rr-th"><span class="rr-th__in">{{ __('cheque.due_date') }}</span></th>
                                <th class="rr-th"><span class="rr-th__in">{{ __('cheque.days_left') }}</span></th>
                                <th class="rr-th"><span class="rr-th__in">{{ __('cheque.status') }}</span></th>
                                <th class="rr-th"><span class="rr-sr">{{ __('messages.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tfoot class="rr-tfoot">
                            <tr>
                                <td class="rr-td" colspan="4">{{ __('sale.total') }}</td>
                                <td class="rr-td rr-td--num"><span class="rr-num footer_cheque_total">0</span></td>
                                <td class="rr-td" colspan="5"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </section>
    @endforeach

    <section class="cheque-pane" id="tab_cash_flow" @if($active_tab != 'cash_flow') hidden @endif>
        <section class="rr-table-wrap">
            <div class="rr-table-head">
                <div class="rr-table-head__titles">
                    <h2 class="rr-table-head__title">{{ __('cheque.cash_flow') }}</h2>
                    <p class="rr-table-head__sub">{{ __('cheque.cash_flow_intro') }}</p>
                </div>
            </div>
            <div class="rr-table-scroll">
                <table class="rr-table rr-table--tinted">
                    <thead>
                        <tr>
                            <th class="rr-th">{{ __('cheque.week') }}</th>
                            @if(in_array('receivable', $types))<th class="rr-th rr-th--num">{{ __('cheque.incoming') }}</th>@endif
                            @if(in_array('payable', $types))<th class="rr-th rr-th--num">{{ __('cheque.outgoing') }}</th>@endif
                            <th class="rr-th rr-th--num">{{ __('cheque.net') }}</th>
                            <th class="rr-th rr-th--num">{{ __('cheque.cumulative') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cash_flow as $row)
                            <tr class="rr-tr {{ $row['key'] == 'overdue' && ($row['in'] || $row['out']) ? 'rr-tr--danger' : '' }}">
                                <td class="rr-td">
                                    @if($row['key'] == 'overdue')
                                        {{ __('cheque.overdue_row') }}
                                    @elseif($row['key'] == 'later')
                                        {{ __('cheque.later') }} <span class="rr-td__sub"><span class="rr-num">{{ @format_date($row['from']) }}</span></span>
                                    @else
                                        <span class="rr-num">{{ @format_date($row['from']) }}</span> – <span class="rr-num">{{ @format_date($row['to']) }}</span>
                                    @endif
                                </td>
                                @if(in_array('receivable', $types))<td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($row['in']) }}</span></td>@endif
                                @if(in_array('payable', $types))<td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($row['out']) }}</span></td>@endif
                                <td class="rr-td rr-td--num">
                                    @if($row['net'] < 0)
                                        <span class="rr-badge rr-badge--danger" title="{{ __('cheque.negative_week_hint') }}"><span class="rr-num">{{ @num_format($row['net']) }}</span></span>
                                    @else
                                        <span class="rr-num">{{ @num_format($row['net']) }}</span>
                                    @endif
                                </td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($row['cumulative']) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</main>

<div class="modal fade cheque_modal rr-scope" tabindex="-1" role="dialog"></div>
@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function () {
    var cheque_tables = {};
    var print_urls = {
        receivable: "{{ action([\App\Http\Controllers\ChequeController::class, 'printReport'], ['receivable']) }}",
        payable: "{{ action([\App\Http\Controllers\ChequeController::class, 'printReport'], ['payable']) }}",
        cash_flow: "{{ action([\App\Http\Controllers\ChequeController::class, 'printReport'], ['cash_flow']) }}"
    };

    function activeTab() {
        return $('.rr-tab[aria-current="page"]').data('cheque-tab');
    }

    function filterData(type, d) {
        $('.cheque-filters[data-type="' + type + '"] .cheque-filter').each(function () {
            d[$(this).attr('name')] = $(this).val();
        });
    }

    function initTable(type) {
        if (cheque_tables[type]) {
            return;
        }
        var $table = $('#cheques_table_' + type);
        cheque_tables[type] = $table.DataTable({
            processing: true,
            serverSide: true,
            searching: false,
            lengthChange: false,
            fixedHeader: false,
            scrollX: false,
            autoWidth: false,
            pageLength: 25,
            buttons: [],
            dom: 'rt<"rr-pager"<"rr-pager__sum"i><"rr-pager__pages"p>>',
            order: [[6, 'asc']],
            ajax: {
                url: $table.data('url'),
                data: function (d) { filterData(type, d); }
            },
            columnDefs: [
                { targets: '_all', className: 'rr-td' },
                { targets: [4], className: 'rr-td rr-td--num' },
                { targets: [9], className: 'rr-td rr-td--actions' }
            ],
            columns: [
                { data: 'cheque_number', name: 'cheques.cheque_number' },
                { data: 'contact_name', name: 'contact_name' },
                { data: 'bank_name', name: 'cheques.bank_name' },
                { data: 'invoice', name: 'invoice' },
                { data: 'amount', name: 'cheques.amount' },
                { data: 'received_on', name: 'cheques.received_on' },
                { data: 'due_date', name: 'cheques.due_date' },
                { data: 'days_left', name: 'days_left' },
                { data: 'status', name: 'cheques.status' },
                { data: 'action', name: 'action', orderable: false }
            ],
            fnDrawCallback: function () {
                var total = 0;
                $table.find('.cheque-amount').each(function () {
                    total += parseFloat($(this).data('orig-value')) || 0;
                });
                $table.find('.footer_cheque_total').text(__number_f(total));
            }
        });
    }

    function showTab(type) {
        $('.rr-tab').removeAttr('aria-current');
        $('.rr-tab[data-cheque-tab="' + type + '"]').attr('aria-current', 'page');
        $('.cheque-pane').attr('hidden', true);
        $('#tab_' + type).removeAttr('hidden');
        if (type !== 'cash_flow') {
            initTable(type);
            cheque_tables[type].columns.adjust();
        }
    }

    showTab(activeTab());

    $(document).on('click', '.rr-tab[data-cheque-tab]', function (e) {
        e.preventDefault();
        showTab($(this).data('cheque-tab'));
    });

    $(document).on('change', '.cheque-filter', function () {
        var $filters = $(this).closest('.cheque-filters');
        if ($(this).attr('name') !== 'due_bucket') {
            $filters.find('input[name="due_bucket"]').val('');
        }
        var type = $filters.data('type');
        if (cheque_tables[type]) {
            cheque_tables[type].ajax.reload();
        }
    });

    $(document).on('click', '.cheque-reset', function () {
        var $filters = $(this).closest('.cheque-filters');
        $filters.find('select[name="status"]').val('pending');
        $filters.find('input, select').not('[name="status"]').val('');
        $filters.find('select.select2').val('').trigger('change.select2');
        cheque_tables[$filters.data('type')].ajax.reload();
    });

    $(document).on('click', '.cheque-quick-filter', function (e) {
        e.preventDefault();
        var type = $(this).data('type');
        var $filters = $('.cheque-filters[data-type="' + type + '"]');
        $filters.find('select[name="status"]').val('pending');
        $filters.find('input[name="due_bucket"]').val($(this).data('bucket'));
        cheque_tables[type].ajax.reload();
    });

    $('#cheque_print_btn').on('click', function () {
        var type = activeTab();
        var params = {};
        if (type !== 'cash_flow') {
            filterData(type, params);
        }
        var query = $.param(params);
        window.open(print_urls[type] + (query ? '?' + query : ''), '_blank');
    });

    // Row actions menu (rr-menu): fixed position next to its toggle, closes on outside click / item click
    var active_menu = null, active_toggle = null;
    function closeMenu() {
        if (!active_menu) return;
        active_menu.style.display = 'none';
        active_toggle.setAttribute('aria-expanded', 'false');
        active_menu = null;
        active_toggle = null;
    }
    $(document).on('click', '[data-cheque-menu-toggle]', function (e) {
        e.stopPropagation();
        var menu = document.getElementById(this.getAttribute('aria-controls'));
        if (!menu) return;
        if (active_menu === menu) { closeMenu(); return; }
        closeMenu();
        menu.style.display = 'block';
        menu.style.visibility = 'hidden';
        var box = this.getBoundingClientRect(), width = menu.offsetWidth, height = menu.offsetHeight;
        var left = getComputedStyle(this).direction === 'rtl' ? box.left : box.right - width;
        menu.style.left = Math.max(8, Math.min(window.innerWidth - width - 8, left)) + 'px';
        menu.style.top = (box.bottom + height + 8 <= window.innerHeight || box.top < height + 8 ? box.bottom + 4 : box.top - height - 4) + 'px';
        menu.style.visibility = 'visible';
        this.setAttribute('aria-expanded', 'true');
        active_menu = menu;
        active_toggle = this;
    });
    $(document).on('click', function (e) {
        if (active_menu && (!$(e.target).closest('.rr-menu').length || $(e.target).closest('.rr-menu__item').length)) {
            closeMenu();
        }
    });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
    $(window).on('resize', closeMenu);
    window.addEventListener('scroll', closeMenu, true);

    $(document).on('submit', 'form#cheque_action_form', function (e) {
        e.preventDefault();
        var $form = $(this);
        $form.find('button[type="submit"]').attr('disabled', true);
        $.ajax({
            method: 'POST',
            url: $form.attr('action'),
            dataType: 'json',
            data: $form.serialize(),
            success: function (result) {
                if (result.success) {
                    toastr.success(result.msg);
                    window.location.href = window.location.pathname + '?tab=' + activeTab();
                } else {
                    toastr.error(result.msg);
                    $form.find('button[type="submit"]').attr('disabled', false);
                }
            },
            error: function () {
                $form.find('button[type="submit"]').attr('disabled', false);
            }
        });
    });
});
</script>
@endsection

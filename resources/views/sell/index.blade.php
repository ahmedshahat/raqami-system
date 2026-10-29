@extends('layouts.app')
@section('title', __('lang_v1.all_sales'))

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

    <!-- Content Header (Page header) -->
    <section class="content-header no-print">
        <h1  class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('sale.sells') <span id="sell_list_selected_range" class="tw-text-gray-600 tw-font-normal tw-text-base">{{ @format_date(\Carbon\Carbon::now()->startOfYear()) }} ~ {{ @format_date(\Carbon\Carbon::now()) }}</span>
        </h1>
    </section>

    <!-- Main content -->
    <section class="content no-print">
        @component('components.filters', ['title' => __('report.filters')])
            @include('sell.partials.sell_list_filters')
            @if ($payment_types)
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('payment_method', __('lang_v1.payment_method') . ':') !!}
                        {!! Form::select('payment_method', $payment_types, null, [
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'placeholder' => __('lang_v1.all'),
                        ]) !!}
                    </div>
                </div>
            @endif

            @if (!empty($sources))
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('sell_list_filter_source', __('lang_v1.sources') . ':') !!}

                        {!! Form::select('sell_list_filter_source', $sources, null, [
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'placeholder' => __('lang_v1.all'),
                        ]) !!}
                    </div>
                </div>
            @endif
        @endcomponent
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_sales')])
            @can('direct_sell.access')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{ action([\App\Http\Controllers\SellController::class, 'create']) }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg> @lang('messages.add')
                        </a>
                    </div>
                @endslot
            @endcan
            @if (auth()->user()->can('direct_sell.view') ||
                    auth()->user()->can('view_own_sell_only') ||
                    auth()->user()->can('view_commission_agent_sell'))
                @php
                    $custom_labels = json_decode(session('business.custom_labels'), true);
                @endphp
                @can('print_invoice')
                    <div class="clearfix tw-mb-3">
                        <button type="button" id="print_selected_invoices"
                            class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary pull-left"
                            disabled>
                            <i class="fas fa-print" aria-hidden="true"></i>
                            طباعة الفواتير المحددة (<span id="selected_invoices_count">0</span>)
                        </button>
                    </div>
                @endcan
                <table class="table table-bordered table-striped ajax_view" id="sell_table">
                    <thead>
                        <tr>
                            @can('print_invoice')
                                <th class="text-center">
                                    <input type="checkbox" id="select_all_invoices" title="تحديد فواتير الصفحة الحالية"
                                        aria-label="تحديد فواتير الصفحة الحالية">
                                </th>
                            @endcan
                            <th>@lang('messages.action')</th>
                            <th>@lang('messages.date')</th>
                            <th>@lang('sale.invoice_no')</th>
                            <th>@lang('sale.customer_name')</th>
                            <th>@lang('lang_v1.contact_no')</th>
                            <th>@lang('sale.location')</th>
                            <th>@lang('sale.payment_status')</th>
                            <th>@lang('lang_v1.payment_method')</th>
                            <th>@lang('sale.total_amount')</th>
                            <th>@lang('sale.total_paid')</th>
                            <th>@lang('lang_v1.sell_due')</th>
                            <th>@lang('lang_v1.sell_return_due')</th>
                            <th>@lang('lang_v1.shipping_status')</th>
                            <th>@lang('lang_v1.total_items')</th>
                            <th>@lang('lang_v1.types_of_service')</th>
                            <th>{{ $custom_labels['types_of_service']['custom_field_1'] ?? __('lang_v1.service_custom_field_1') }}
                            </th>
                            <th>{{ $custom_labels['sell']['custom_field_1'] ?? '' }}</th>
                            <th>{{ $custom_labels['sell']['custom_field_2'] ?? '' }}</th>
                            <th>{{ $custom_labels['sell']['custom_field_3'] ?? '' }}</th>
                            <th>{{ $custom_labels['sell']['custom_field_4'] ?? '' }}</th>
                            <th>@lang('lang_v1.added_by')</th>
                            <th>@lang('sale.sell_note')</th>
                            <th>@lang('sale.staff_note')</th>
                            <th>@lang('sale.shipping_details')</th>
                            <th>@lang('restaurant.table')</th>
                            <th>@lang('restaurant.service_staff')</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr class="bg-gray font-17 footer-total text-center">
                            <td colspan="{{ auth()->user()->can('print_invoice') ? 7 : 6 }}"><strong>@lang('sale.total'):</strong></td>
                            <td class="footer_payment_status_count"></td>
                            <td class="payment_method_count"></td>
                            <td class="footer_sale_total"></td>
                            <td class="footer_total_paid"></td>
                            <td class="footer_total_remaining"></td>
                            <td class="footer_total_sell_return_due"></td>
                            <td colspan="2"></td>
                            <td class="service_type_count"></td>
                            <td colspan="7"></td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        @endcomponent
    </section>
    <!-- /.content -->
    <div class="modal fade payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>

    <div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>

    <div class="modal fade" id="invoice_image_share_modal" tabindex="-1" role="dialog" aria-labelledby="invoiceImageShareTitle">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="invoiceImageShareTitle">مشاركة صورة الفاتورة</h4>
                </div>
                <div class="modal-body text-center">
                    <img id="invoice_image_preview" alt="صورة الفاتورة" style="max-width: 100%; height: auto; border: 1px solid #ddd;">
                    <p id="invoice_whatsapp_recipient" style="margin-top: 10px; font-weight: bold;"></p>
                    <p id="invoice_share_help" class="help-block" style="margin-top: 10px;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-info" id="download_invoice_image"><i class="fas fa-download"></i> تنزيل الصورة</button>
                    <button type="button" class="btn btn-success" id="open_invoice_whatsapp"><i class="fab fa-whatsapp"></i> تنزيل وفتح واتساب</button>
                    <button type="button" class="btn btn-success" id="share_invoice_image"><i class="fas fa-share-alt"></i> مشاركة</button>
                </div>
            </div>
        </div>
    </div>

    <!-- This will be printed -->
    <section class="invoice print_section" id="receipt_section">
        </section> 
    <section class="invoice print_section" id="bulk_receipt_section"></section>

@stop

@section('javascript')
    @include('sell.partials.invoice_share_print_js')
    <script type="text/javascript">
        var selectedInvoices = {};

        function updateSelectedInvoicesUi() {
            var selectedCount = Object.keys(selectedInvoices).length;
            $('#selected_invoices_count').text(selectedCount);
            $('#print_selected_invoices').prop('disabled', selectedCount === 0);

            var pageCheckboxes = $('#sell_table tbody .select-invoice');
            var checkedOnPage = pageCheckboxes.filter(':checked').length;
            $('#select_all_invoices')
                .prop('checked', pageCheckboxes.length > 0 && checkedOnPage === pageCheckboxes.length)
                .prop('indeterminate', checkedOnPage > 0 && checkedOnPage < pageCheckboxes.length);
        }

        $(document).ready(function() {
            //Date range as a button
            var currentYearStart = moment().startOf('year');
            var currentDate = moment();
            
            // Function to update heading with date range
            function updateDateRangeHeading(start, end) {
                if (start && end) {
                    var formattedStart = start.format(moment_date_format);
                    var formattedEnd = end.format(moment_date_format);
                    $('#sell_list_selected_range').text(formattedStart + ' ~ ' + formattedEnd);
                } else {
                    // Reset heading to the default current-year period.
                    var defaultStart = moment().startOf('year').format(moment_date_format);
                    var defaultEnd = moment().format(moment_date_format);
                    $('#sell_list_selected_range').text(defaultStart + ' ~ ' + defaultEnd);
                }
            }
            
            $('#sell_list_filter_date_range').daterangepicker(
                $.extend(true, {}, dateRangeSettings, { startDate: currentYearStart, endDate: currentDate }),
                function(start, end) {
                    updateDateRangeHeading(start, end);
                    sell_table.ajax.reload();
                }
            );
            $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
                $('#sell_list_filter_date_range').val('');
                updateDateRangeHeading(null, null);
                sell_table.ajax.reload();
            });

            sell_table = $('#sell_table').DataTable({
                processing: true,
                serverSide: true,
                fixedHeader:false,
                aaSorting: [
                    [{{ auth()->user()->can('print_invoice') ? 2 : 1 }}, 'desc']
                ],
                "ajax": {
                    "url": "/sells",
                    "data": function(d) {
                        if ($('#sell_list_filter_date_range').val()) {
                            var start = $('#sell_list_filter_date_range').data('daterangepicker')
                                .startDate.format('YYYY-MM-DD');
                            var end = $('#sell_list_filter_date_range').data('daterangepicker').endDate
                                .format('YYYY-MM-DD');
                            d.start_date = start;
                            d.end_date = end;
                        }
                        d.is_direct_sale = 1;

                        d.location_id = $('#sell_list_filter_location_id').val();
                        d.customer_id = $('#sell_list_filter_customer_id').val();
                        d.payment_status = $('#sell_list_filter_payment_status').val();
                        d.created_by = $('#created_by').val();
                        d.sales_cmsn_agnt = $('#sales_cmsn_agnt').val();
                        d.service_staffs = $('#service_staffs').val();

                        if ($('#shipping_status').length) {
                            d.shipping_status = $('#shipping_status').val();
                        }

                        if ($('#sell_list_filter_source').length) {
                            d.source = $('#sell_list_filter_source').val();
                        }

                        if ($('#only_subscriptions').is(':checked')) {
                            d.only_subscriptions = 1;
                        }

                        if ($('#payment_method').length) {
                            d.payment_method = $('#payment_method').val();
                        }

                        d = __datatable_ajax_callback(d);
                    }
                },
                scrollY: "75vh",
                scrollX: true,
                scrollCollapse: true,
                columns: [
                    @can('print_invoice')
                    {
                        data: 'transaction_id',
                        name: 'transactions.id',
                        defaultContent: '',
                        orderable: false,
                        searchable: false,
                        className: 'selectable_td text-center',
                        render: function(data, type, row) {
                            // Keep the table usable during rolling deployments where
                            // the updated view may briefly run with an older controller.
                            if (data === null || data === undefined || data === '') {
                                return '';
                            }

                            if (type !== 'display') {
                                return data;
                            }

                            var checked = selectedInvoices[data] ? ' checked' : '';
                            var invoiceNumber = $('<div>').html(row.invoice_no || data).text() || data;
                            var escapedInvoiceNumber = $('<div>').text(invoiceNumber).html();
                            var escapedPrintUrl = $('<div>').text(row.print_url || '').html();
                            return '<input type="checkbox" class="select-invoice" value="' + data +
                                '" data-invoice-no="' + escapedInvoiceNumber +
                                '" data-print-url="' + escapedPrintUrl + '"' + checked +
                                ' aria-label="تحديد الفاتورة ' + escapedInvoiceNumber + '">';
                        }
                    },
                    @endcan
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        "searchable": false
                    },
                    {
                        data: 'transaction_date',
                        name: 'transaction_date'
                    },
                    {
                        data: 'invoice_no',
                        name: 'invoice_no'
                    },
                    {
                        data: 'conatct_name',
                        name: 'conatct_name'
                    },
                    {
                        data: 'mobile',
                        name: 'contacts.mobile'
                    },
                    {
                        data: 'business_location',
                        name: 'bl.name'
                    },
                    {
                        data: 'payment_status',
                        name: 'payment_status'
                    },
                    {
                        data: 'payment_methods',
                        orderable: false,
                        "searchable": false
                    },
                    {
                        data: 'final_total',
                        name: 'final_total'
                    },
                    {
                        data: 'total_paid',
                        name: 'total_paid',
                        "searchable": false
                    },
                    {
                        data: 'total_remaining',
                        name: 'total_remaining'
                    },
                    {
                        data: 'return_due',
                        orderable: false,
                        "searchable": false
                    },
                    {
                        data: 'shipping_status',
                        name: 'shipping_status'
                    },
                    {
                        data: 'total_items',
                        name: 'total_items',
                        "searchable": false
                    },
                    {
                        data: 'types_of_service_name',
                        name: 'tos.name',
                        @if (empty($is_types_service_enabled))
                            visible: false
                        @endif
                    },
                    {
                        data: 'service_custom_field_1',
                        name: 'service_custom_field_1',
                        @if (empty($is_types_service_enabled))
                            visible: false
                        @endif
                    },
                    {
                        data: 'custom_field_1',
                        name: 'transactions.custom_field_1',
                        @if (empty($custom_labels['sell']['custom_field_1']))
                            visible: false
                        @endif
                    },
                    {
                        data: 'custom_field_2',
                        name: 'transactions.custom_field_2',
                        @if (empty($custom_labels['sell']['custom_field_2']))
                            visible: false
                        @endif
                    },
                    {
                        data: 'custom_field_3',
                        name: 'transactions.custom_field_3',
                        @if (empty($custom_labels['sell']['custom_field_3']))
                            visible: false
                        @endif
                    },
                    {
                        data: 'custom_field_4',
                        name: 'transactions.custom_field_4',
                        @if (empty($custom_labels['sell']['custom_field_4']))
                            visible: false
                        @endif
                    },
                    {
                        data: 'added_by',
                        name: 'u.first_name'
                    },
                    {
                        data: 'additional_notes',
                        name: 'additional_notes'
                    },
                    {
                        data: 'staff_note',
                        name: 'staff_note'
                    },
                    {
                        data: 'shipping_details',
                        name: 'shipping_details'
                    },
                    {
                        data: 'table_name',
                        name: 'tables.name',
                        @if (empty($is_tables_enabled))
                            visible: false
                        @endif
                    },
                    {
                        data: 'waiter',
                        name: 'ss.first_name',
                        @if (empty($is_service_staff_enabled))
                            visible: false
                        @endif
                    },
                ],
                "fnDrawCallback": function(oSettings) {
                    __currency_convert_recursively($('#sell_table'));
                    updateSelectedInvoicesUi();
                },
                "footerCallback": function(row, data, start, end, display) {
                    var footer_sale_total = 0;
                    var footer_total_paid = 0;
                    var footer_total_remaining = 0;
                    var footer_total_sell_return_due = 0;
                    for (var r in data) {
                        footer_sale_total += $(data[r].final_total).data('orig-value') ? parseFloat($(
                            data[r].final_total).data('orig-value')) : 0;
                        footer_total_paid += $(data[r].total_paid).data('orig-value') ? parseFloat($(
                            data[r].total_paid).data('orig-value')) : 0;
                        footer_total_remaining += $(data[r].total_remaining).data('orig-value') ?
                            parseFloat($(data[r].total_remaining).data('orig-value')) : 0;
                        footer_total_sell_return_due += $(data[r].return_due).find('.sell_return_due')
                            .data('orig-value') ? parseFloat($(data[r].return_due).find(
                                '.sell_return_due').data('orig-value')) : 0;
                    }

                    $('.footer_total_sell_return_due').html(__currency_trans_from_en(
                        footer_total_sell_return_due));
                    $('.footer_total_remaining').html(__currency_trans_from_en(footer_total_remaining));
                    $('.footer_total_paid').html(__currency_trans_from_en(footer_total_paid));
                    $('.footer_sale_total').html(__currency_trans_from_en(footer_sale_total));

                    $('.footer_payment_status_count').html(__count_status(data, 'payment_status'));
                    $('.service_type_count').html(__count_status(data, 'types_of_service_name'));
                    $('.payment_method_count').html(__count_status(data, 'payment_methods'));
                },
                createdRow: function(row, data, dataIndex) {
                    $(row).find('td:eq({{ auth()->user()->can('print_invoice') ? 7 : 6 }})').attr('class', 'clickable_td');
                }
            });

            $(document).on('change',
                '#sell_list_filter_location_id, #sell_list_filter_customer_id, #sell_list_filter_payment_status, #created_by, #sales_cmsn_agnt, #service_staffs, #shipping_status, #sell_list_filter_source, #payment_method',
                function() {
                    sell_table.ajax.reload();
                });

            $('#only_subscriptions').on('ifChanged', function(event) {
                sell_table.ajax.reload();
            });

            $('#sell_table').on('click', 'tbody td.selectable_td', function(event) {
                event.stopPropagation();

                if ($(event.target).is('.select-invoice')) {
                    return;
                }

                event.preventDefault();
                $(this).find('.select-invoice').trigger('click');
            });

            $(document).on('change', '.select-invoice', function() {
                var transactionId = String($(this).val());

                if (this.checked) {
                    selectedInvoices[transactionId] = {
                        id: transactionId,
                        number: String($(this).data('invoice-no') || transactionId),
                        url: String($(this).data('print-url') || '')
                    };
                } else {
                    delete selectedInvoices[transactionId];
                }

                updateSelectedInvoicesUi();
            });

            $('#select_all_invoices').on('change', function() {
                var shouldSelect = this.checked;
                $('#sell_table tbody .select-invoice').each(function() {
                    $(this).prop('checked', shouldSelect).trigger('change');
                });
            });

            $('#print_selected_invoices').on('click', async function() {
                var invoices = Object.keys(selectedInvoices).map(function(id) {
                    return selectedInvoices[id];
                });

                if (!invoices.length) {
                    toastr.warning('حدد فاتورة واحدة على الأقل للطباعة.');
                    return;
                }

                await printInvoicesBulk(invoices, $(this));
                updateSelectedInvoicesUi();
            });
        });

    </script>
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection

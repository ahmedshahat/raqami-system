<script type="text/javascript">
$(document).ready( function(){
    $('#ledger_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#ledger_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
        }
    );
    $('#ledger_date_range, #ledger_location').change( function(){
        get_contact_ledger();
    });
    get_contact_ledger();

    rp_log_table = $('#rp_log_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader:false,
        aaSorting: [[0, 'desc']],
        ajax: '/sells?customer_id={{ $contact->id }}&rewards_only=true',
        columns: [
            { data: 'transaction_date', name: 'transactions.transaction_date'  },
            { data: 'invoice_no', name: 'transactions.invoice_no'},
            { data: 'rp_earned', name: 'transactions.rp_earned'},
            { data: 'rp_redeemed', name: 'transactions.rp_redeemed'},
        ]
    });

    supplier_stock_report_table = $('#supplier_stock_report_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader:false,
        'ajax': {
            url: "{{action([\App\Http\Controllers\ContactController::class, 'getSupplierStockReport'], [$contact->id])}}",
            data: function (d) {
                d.location_id = $('#sr_location_id').val();
            }
        },
        columns: [
            { data: 'product_name', name: 'p.name'  },
            { data: 'sub_sku', name: 'v.sub_sku'  },
            { data: 'purchase_quantity', name: 'purchase_quantity', searchable: false},
            { data: 'total_quantity_sold', name: 'total_quantity_sold', searchable: false},
            { data: 'total_quantity_transfered', name: 'total_quantity_transfered', searchable: false},
            { data: 'total_quantity_returned', name: 'total_quantity_returned', searchable: false},
            { data: 'current_stock', name: 'current_stock', searchable: false},
            { data: 'stock_price', name: 'stock_price', searchable: false}
        ],
        fnDrawCallback: function(oSettings) {
            __currency_convert_recursively($('#supplier_stock_report_table'));
        },
    });

    $('#sr_location_id').change( function() {
        supplier_stock_report_table.ajax.reload();
    });

    $('#contact_id, #rr_contact_switch').change( function() {
        if ($(this).val()) {
            window.location = "{{url('/contacts')}}/" + $(this).val();
        }
    });

    $('a[href="#sales_tab"]').on('shown.bs.tab', function (e) {
        sell_table.ajax.reload();
    });

    //Date picker
    $('#discount_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });

    $(document).on('submit', 'form#add_discount_form, form#edit_discount_form', function(e) {
        e.preventDefault();
        var form = $(this);
        var data = form.serialize();

        $.ajax({
            method: 'POST',
            url: $(this).attr('action'),
            dataType: 'json',
            data: data,
            success: function(result) {
                if (result.success === true) {
                    $('div#add_discount_modal').modal('hide');
                    $('div#edit_ledger_discount_modal').modal('hide');
                    toastr.success(result.msg);
                    form[0].reset();
                    form.find('button[type="submit"]').removeAttr('disabled');
                    get_contact_ledger();
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });

    $(document).on('click', 'button.delete_ledger_discount', function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(willDelete => {
            if (willDelete) {
                var href = $(this).data('href');
                var data = $(this).serialize();

                $.ajax({
                    method: 'DELETE',
                    url: href,
                    dataType: 'json',
                    data: data,
                    success: function(result) {
                        if (result.success == true) {
                            toastr.success(result.msg);
                            get_contact_ledger();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            }
        });
    });
});

$(document).on('shown.bs.modal', '#edit_ledger_discount_modal', function(e){
    $('#edit_ledger_discount_modal').find('#edit_discount_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
    });
})

$("input.transaction_types, input#show_payments").on('ifChanged', function (e) {
    get_contact_ledger();
});

$(document).on('change', 'input[name="ledger_format"]', function(){
    get_contact_ledger();
})

$(document).one('shown.bs.tab', 'a[href="#payments_tab"]', function(){
    get_contact_payments();
})
// الصفحة مفتوحة من البداية على تاب المدفوعات: مفيش shown.bs.tab هنا
if ($('#payments_tab').hasClass('active')) {
    get_contact_payments();
}

$(document).on('click', '#contact_payments_pagination a', function(e){
    e.preventDefault();
    get_contact_payments($(this).attr('href'));
})

function get_contact_payments(url = null) {
    if (!url) {
        url = "{{action([\App\Http\Controllers\ContactController::class, 'getContactPayments'], [$contact->id])}}";
    }
    $.ajax({
        url: url,
        dataType: 'html',
        success: function(result) {
            $('#contact_payments_div').html(result);
            __currency_convert_recursively($('#contact_payments_div'));
        },
    });
}

function get_contact_ledger() {

    var start_date = '';
    var end_date = '';
    var transaction_types = $('input.transaction_types:checked').map(function(i, e) {return e.value}).toArray();
    var show_payments = $('input#show_payments').is(':checked');
    var location_id = $('#ledger_location').val();

    if($('#ledger_date_range').val()) {
        start_date = $('#ledger_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
        end_date = $('#ledger_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
    }

    var format = $('input[name="ledger_format"]:checked').val();
    var data = {
        start_date: start_date,
        transaction_types: transaction_types,
        show_payments: show_payments,
        end_date: end_date,
        format: format,
        location_id: location_id,
        ui: $('#contact_ledger_div').data('ui') || ''
    }
    $.ajax({
        url: '/contacts/ledger?contact_id={{$contact->id}}',
        data: data,
        dataType: 'html',
        success: function(result) {
            $('#contact_ledger_div')
                .html(result);
            __currency_convert_recursively($('#contact_ledger_div'));

            // جدول رقمي (contact.partials.ledger_screen) مش محتاج DataTable
            $('#ledger_table:not(.rr-cs__ledger-table)').DataTable({
                searching: false,
                ordering:false,
                paging:false,
                fixedHeader:false,
                dom: 't'
            });
        },
    });
}

$(document).on('click', '#send_ledger', function() {
    var start_date = $('#ledger_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
    var end_date = $('#ledger_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
    var format = $('input[name="ledger_format"]:checked').val();

    var location_id = $('#ledger_location').val();

    var url = "{{action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], [$contact->id, 'send_ledger'])}}" + '?start_date=' + start_date + '&end_date=' + end_date + '&format=' + format + '&location_id=' + location_id;

    $.ajax({
        url: url,
        dataType: 'html',
        success: function(result) {
            $('.view_modal')
                .html(result)
                .modal('show');
        },
    });
})

$(document).on('click', '#print_ledger_pdf, #print_ledger', function() {
    var start_date = $('#ledger_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
    var end_date = $('#ledger_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');

    var format = $('input[name="ledger_format"]:checked').val();

    var location_id = $('#ledger_location').val();

    var url = $(this).data('href') + '&start_date=' + start_date + '&end_date=' + end_date + '&format=' + format + '&location_id=' + location_id;
    window.open(url);
});

</script>
@include('sale_pos.partials.sale_table_javascript')
<script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@if(in_array($contact->type, ['both', 'supplier']))
    <script src="{{ asset('js/purchase.js?v=' . $asset_v) }}"></script>
@endif

<!-- document & note.js -->
@include('documents_and_notes.document_and_note_js')
@if(!empty($contact_view_tabs))
    @foreach($contact_view_tabs as $key => $tabs)
        @foreach ($tabs as $index => $value)
            @if(!empty($value['module_js_path']))
                @include($value['module_js_path'])
            @endif
        @endforeach
    @endforeach
@endif

<script type="text/javascript">
    $(document).ready( function(){
        $('#purchase_list_filter_date_range').daterangepicker(
            dateRangeSettings,
            function (start, end) {
                $('#purchase_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
               purchase_table.ajax.reload();
            }
        );
        $('#purchase_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $('#purchase_list_filter_date_range').val('');
            purchase_table.ajax.reload();
        });

        $('#purchases-link').on('click', function(e) {
            purchase_table.ajax.reload();
        });
    });
</script>
@include('sale_pos.partials.subscriptions_table_javascript', ['contact_id' => $contact->id])

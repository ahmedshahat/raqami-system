@extends('layouts.app')

@section('title', __('accounting::lang.receipt_and_payment_vouchers'))

@section('css')
<style>
    .voucher-floating-menu {
        min-width: 205px !important;
        z-index: 20000 !important;
        direction: rtl;
        text-align: right;
    }
    .voucher-floating-menu > li > a {
        display: flex !important;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 9px 14px !important;
        white-space: nowrap;
    }
    .voucher-floating-menu > li > a > i {
        width: 18px;
        flex: 0 0 18px;
        text-align: center;
    }
</style>
@endsection

@section('content')
@include('accounting::layouts.nav')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('accounting::lang.receipt_and_payment_vouchers')</h1>
</section>

<section class="content no-print">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('voucher_type_filter', __('accounting::lang.voucher_type').':') !!}
                        {!! Form::select('voucher_type_filter', [
                            'receipt_voucher' => __('accounting::lang.receipt_voucher'),
                            'payment_voucher' => __('accounting::lang.payment_voucher')
                        ], null, ['class' => 'form-control', 'placeholder' => __('lang_v1.all')]) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('voucher_date_filter', __('report.date_range').':') !!}
                        {!! Form::text('voucher_date_filter', null, ['class' => 'form-control', 'readonly', 'placeholder' => __('lang_v1.select_a_date_range')]) !!}
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    @component('components.widget', ['class' => 'box-solid'])
        @can('accounting.add_journal')
            @slot('tool')
                <div class="box-tools tw-flex tw-gap-2">
                    <a href="{{ action([\Modules\Accounting\Http\Controllers\VoucherController::class, 'create'], ['type' => 'receipt_voucher']) }}" class="tw-dw-btn tw-dw-btn-success tw-text-white">
                        <i class="fas fa-arrow-down"></i> @lang('accounting::lang.add_receipt_voucher')
                    </a>
                    <a href="{{ action([\Modules\Accounting\Http\Controllers\VoucherController::class, 'create'], ['type' => 'payment_voucher']) }}" class="tw-dw-btn tw-dw-btn-error tw-text-white">
                        <i class="fas fa-arrow-up"></i> @lang('accounting::lang.add_payment_voucher')
                    </a>
                </div>
            @endslot
        @endcan

        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="vouchers_table">
                <thead><tr>
                    <th>@lang('messages.action')</th>
                    <th>@lang('accounting::lang.voucher_type')</th>
                    <th>@lang('purchase.ref_no')</th>
                    <th>@lang('messages.date')</th>
                    <th>@lang('accounting::lang.party_name')</th>
                    <th>@lang('sale.amount')</th>
                    <th>@lang('accounting::lang.cash_or_bank_account')</th>
                    <th>@lang('accounting::lang.payment_method')</th>
                    <th>@lang('business.business_location')</th>
                    <th>@lang('accounting::lang.voucher_status')</th>
                    <th>@lang('lang_v1.added_by')</th>
                    <th>@lang('accounting::lang.statement')</th>
                </tr></thead>
            </table>
        </div>
    @endcomponent
</section>
@stop

@section('javascript')
<script>
$(function () {
    // Move the open action menu above the scrolling table so it is never clipped.
    $(document).on('shown.bs.dropdown', '.voucher-action-group', function () {
        var $group = $(this);
        var $button = $group.find('[data-toggle="dropdown"]');
        var $menu = $group.find('.dropdown-menu');
        var offset = $button.offset();
        var menuWidth = Math.max($menu.outerWidth(), 205);
        var left = offset.left + $button.outerWidth() - menuWidth;

        left = Math.max(8, Math.min(left, $(window).width() - menuWidth - 8));
        $menu.data('voucher-owner', $group)
            .appendTo('body')
            .addClass('voucher-floating-menu')
            .css({
                display: 'block',
                position: 'absolute',
                top: offset.top + $button.outerHeight(),
                left: left,
                right: 'auto',
                width: menuWidth
            });
    });

    $(document).on('hide.bs.dropdown', '.voucher-action-group', function () {
        var $group = $(this);
        $('body > .voucher-floating-menu').filter(function () {
            return $(this).data('voucher-owner') && $(this).data('voucher-owner')[0] === $group[0];
        }).removeClass('voucher-floating-menu').removeAttr('style').appendTo($group);
    });

    var vouchersTable = $('#vouchers_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[3, 'desc']],
        ajax: {
            url: "{{ action([\Modules\Accounting\Http\Controllers\VoucherController::class, 'index']) }}",
            data: function (d) {
                d.voucher_type = $('#voucher_type_filter').val();
                if ($('#voucher_date_filter').val()) {
                    var picker = $('#voucher_date_filter').data('daterangepicker');
                    d.start_date = picker.startDate.format('YYYY-MM-DD');
                    d.end_date = picker.endDate.format('YYYY-MM-DD');
                }
            }
        },
        columns: [
            {data: 'action', orderable: false, searchable: false},
            {data: 'type', name: 'accounting_acc_trans_mappings.type'},
            {data: 'ref_no', name: 'accounting_acc_trans_mappings.ref_no'},
            {data: 'operation_date', name: 'accounting_acc_trans_mappings.operation_date'},
            {data: 'party_name', name: 'accounting_acc_trans_mappings.party_name'},
            {data: 'amount', name: 'vat.amount'},
            {data: 'cash_account_name', name: 'cash_account.name'},
            {data: 'payment_method', name: 'accounting_acc_trans_mappings.payment_method'},
            {data: 'location_name', name: 'bl.name'},
            {data: 'status', name: 'accounting_acc_trans_mappings.status'},
            {data: 'added_by', name: 'added_by'},
            {data: 'note', name: 'accounting_acc_trans_mappings.note'}
        ]
    });

    $('#voucher_type_filter').on('change', function () { vouchersTable.ajax.reload(); });
    $('#voucher_date_filter').daterangepicker(dateRangeSettings, function (start, end) {
        $('#voucher_date_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
        vouchersTable.ajax.reload();
    });
    $('#voucher_date_filter').on('cancel.daterangepicker', function () {
        $(this).val('');
        vouchersTable.ajax.reload();
    });

    $(document).on('click', '.cancel-voucher', function (e) {
        e.preventDefault();
        var url = $(this).data('href');
        swal({
            title: "{{ __('accounting::lang.confirm_voucher_cancellation') }}",
            text: "{{ __('accounting::lang.cancellation_creates_reversal_entry') }}",
            icon: 'warning', buttons: true, dangerMode: true
        }).then(function (confirmed) {
            if (!confirmed) return;
            $.ajax({method: 'DELETE', url: url, dataType: 'json', success: function (result) {
                if (result.success) { toastr.success(result.msg); vouchersTable.ajax.reload(); }
                else { toastr.error(result.msg); }
            }});
        });
    });
});
</script>
@endsection

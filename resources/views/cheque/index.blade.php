@extends('layouts.app')
@section('title', __('cheque.cheques_report'))

@section('css')
<style>
    .cheque-summary .cheque-stat { border-radius: 8px; padding: 12px 14px; margin-bottom: 12px; background: #fff; border: 1px solid #e5e7eb; }
    .cheque-summary .cheque-stat .stat-label { color: #6b7280; font-size: 13px; }
    .cheque-summary .cheque-stat .stat-value { font-size: 20px; font-weight: 700; direction: ltr; unicode-bidi: isolate; }
    .cheque-summary .cheque-stat.is-overdue { border-color: #fca5a5; background: #fef2f2; }
    .cheque-summary .cheque-stat.is-overdue .stat-value { color: #b91c1c; }
    .cheque-summary .cheque-stat.is-today { border-color: #fdba74; background: #fff7ed; }
    .cheque-summary .cheque-stat.is-week { border-color: #fde68a; background: #fffbeb; }
    .cheque-summary .cheque-stat.is-total { border-color: #93c5fd; background: #eff6ff; }
    .cheque-summary .cheque-stat a { color: inherit; }
    .cash-flow-table td.negative { color: #b91c1c; font-weight: 700; }
    .cash-flow-table td.positive { color: #15803d; }
    .cash-flow-table .num { direction: ltr; unicode-bidi: isolate; text-align: right; }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('cheque.cheques_report') }}</h1>
</section>

<section class="content no-print">
    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
            @foreach($types as $type)
                <li class="@if($active_tab == $type) active @endif">
                    <a href="#tab_{{ $type }}" data-toggle="tab" data-tab="{{ $type }}">
                        <i class="fa {{ $type == 'receivable' ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                        {{ __('cheque.' . $type) }}
                        @if(!empty($summaries[$type]['overdue_count']))
                            <span class="label bg-red">{{ (int) $summaries[$type]['overdue_count'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
            <li class="@if($active_tab == 'cash_flow') active @endif">
                <a href="#tab_cash_flow" data-toggle="tab" data-tab="cash_flow"><i class="fa fa-exchange-alt"></i> {{ __('cheque.cash_flow') }}</a>
            </li>
        </ul>

        <div class="tab-content">
            @foreach($types as $type)
                @php $s = $summaries[$type]; @endphp
                <div class="tab-pane @if($active_tab == $type) active @endif" id="tab_{{ $type }}">
                    <div class="row cheque-summary">
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="cheque-stat is-overdue">
                                <a href="#" class="cheque-quick-filter" data-type="{{ $type }}" data-bucket="overdue">
                                    <div class="stat-label">{{ __('cheque.overdue') }}</div>
                                    <div class="stat-value">{{ @num_format($s['overdue']) }}</div>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="cheque-stat is-today">
                                <div class="stat-label">{{ __('cheque.today') }}</div>
                                <div class="stat-value">{{ @num_format($s['today']) }}</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="cheque-stat is-week">
                                <div class="stat-label">{{ __('cheque.this_week') }}</div>
                                <div class="stat-value">{{ @num_format($s['this_week']) }}</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="cheque-stat">
                                <div class="stat-label">{{ __('cheque.this_month') }}</div>
                                <div class="stat-value">{{ @num_format($s['this_month']) }}</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="cheque-stat">
                                <div class="stat-label">{{ __('cheque.later') }}</div>
                                <div class="stat-value">{{ @num_format($s['later']) }}</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="cheque-stat is-total">
                                <div class="stat-label">{{ $type == 'payable' ? __('cheque.total_pending_payable') : __('cheque.total_pending') }} ({{ (int) $s['total_count'] }})</div>
                                <div class="stat-value">{{ @num_format($s['total']) }}</div>
                            </div>
                        </div>
                    </div>

                    @component('components.filters', ['title' => __('report.filters')])
                        <div class="cheque-filters" data-type="{{ $type }}">
                            <div class="col-md-3">
                                <div class="form-group">
                                    {!! Form::label('contact_id_' . $type, ($type == 'receivable' ? __('cheque.customer') : __('cheque.supplier')) . ':') !!}
                                    {!! Form::select('contact_id', $type == 'receivable' ? $customers : $suppliers, null, ['class' => 'form-control select2 cheque-filter', 'style' => 'width:100%', 'placeholder' => __('messages.all'), 'id' => 'contact_id_' . $type]); !!}
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    {!! Form::label('status_' . $type, __('cheque.status') . ':') !!}
                                    @php
                                        $status_options = [
                                            'pending' => $type == 'payable' ? __('cheque.status_pending_payable') : __('cheque.status_pending'),
                                            'collected' => $type == 'payable' ? __('cheque.status_cleared') : __('cheque.status_collected'),
                                            'bounced' => __('cheque.status_bounced'),
                                            'cancelled' => __('cheque.status_cancelled'),
                                            'all' => __('cheque.all'),
                                        ];
                                    @endphp
                                    {!! Form::select('status', $status_options, 'pending', ['class' => 'form-control cheque-filter', 'id' => 'status_' . $type]); !!}
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    {!! Form::label('location_id_' . $type, __('purchase.business_location') . ':') !!}
                                    {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2 cheque-filter', 'style' => 'width:100%', 'placeholder' => __('messages.all'), 'id' => 'location_id_' . $type]); !!}
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    {!! Form::label('bank_name_' . $type, __('cheque.bank_name') . ':') !!}
                                    {!! Form::text('bank_name', null, ['class' => 'form-control cheque-filter', 'id' => 'bank_name_' . $type]); !!}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ __('cheque.due_date') }}:</label>
                                    <div class="input-group">
                                        <input type="date" name="start_date" class="form-control cheque-filter">
                                        <span class="input-group-addon">-</span>
                                        <input type="date" name="end_date" class="form-control cheque-filter">
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="due_bucket" value="" class="cheque-filter">
                        </div>
                    @endcomponent

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped cheques-table" id="cheques_table_{{ $type }}" data-type="{{ $type }}" data-url="{{ action([\App\Http\Controllers\ChequeController::class, 'data'], [$type]) }}" style="width:100%">
                            <thead>
                                <tr>
                                    <th>{{ __('cheque.cheque_no') }}</th>
                                    <th>{{ $type == 'receivable' ? __('cheque.customer') : __('cheque.supplier') }}</th>
                                    <th>{{ __('cheque.bank_name') }}</th>
                                    <th>{{ __('cheque.invoice') }}</th>
                                    <th>{{ __('cheque.amount') }}</th>
                                    <th>{{ $type == 'receivable' ? __('cheque.received_on') : __('cheque.issued_on') }}</th>
                                    <th>{{ __('cheque.due_date') }}</th>
                                    <th>{{ __('cheque.days_left') }}</th>
                                    <th>{{ __('cheque.status') }}</th>
                                    <th>{{ __('messages.action') }}</th>
                                </tr>
                            </thead>
                            <tfoot>
                                <tr class="bg-gray font-17 footer-total text-center">
                                    <td colspan="4"><strong>@lang('sale.total'):</strong></td>
                                    <td class="footer_cheque_total"></td>
                                    <td colspan="5"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="tab-pane @if($active_tab == 'cash_flow') active @endif" id="tab_cash_flow">
                <div class="table-responsive">
                    <table class="table table-bordered cash-flow-table">
                        <thead>
                            <tr>
                                <th>{{ __('cheque.week') }}</th>
                                @if(in_array('receivable', $types))<th>{{ __('cheque.incoming') }}</th>@endif
                                @if(in_array('payable', $types))<th>{{ __('cheque.outgoing') }}</th>@endif
                                <th>{{ __('cheque.net') }}</th>
                                <th>{{ __('cheque.cumulative') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cash_flow as $row)
                                <tr @if($row['key'] == 'overdue') class="danger" @endif>
                                    <td>
                                        @if($row['key'] == 'overdue')
                                            {{ __('cheque.overdue_row') }}
                                        @elseif($row['key'] == 'later')
                                            {{ __('cheque.later') }} ({{ @format_date($row['from']) }})
                                        @else
                                            {{ __('cheque.week_of', ['from' => @format_date($row['from']), 'to' => @format_date($row['to'])]) }}
                                        @endif
                                    </td>
                                    @if(in_array('receivable', $types))<td class="num">{{ @num_format($row['in']) }}</td>@endif
                                    @if(in_array('payable', $types))<td class="num">{{ @num_format($row['out']) }}</td>@endif
                                    <td class="num {{ $row['net'] < 0 ? 'negative' : ($row['net'] > 0 ? 'positive' : '') }}" @if($row['net'] < 0) title="{{ __('cheque.negative_week_hint') }}" @endif>
                                        {{ @num_format($row['net']) }}
                                        @if($row['net'] < 0) <i class="fa fa-exclamation-triangle"></i> @endif
                                    </td>
                                    <td class="num {{ $row['cumulative'] < 0 ? 'negative' : '' }}">{{ @num_format($row['cumulative']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade cheque_modal" tabindex="-1" role="dialog"></div>
@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function () {
    var cheque_tables = {};

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
            order: [[6, 'asc']],
            ajax: {
                url: $table.data('url'),
                data: function (d) { filterData(type, d); }
            },
            columns: [
                { data: 'cheque_number', name: 'cheques.cheque_number' },
                { data: 'contact_name', name: 'contact_name' },
                { data: 'bank_name', name: 'cheques.bank_name' },
                { data: 'invoice', name: 'invoice' },
                { data: 'amount', name: 'cheques.amount', searchable: false },
                { data: 'received_on', name: 'cheques.received_on', searchable: false },
                { data: 'due_date', name: 'cheques.due_date', searchable: false },
                { data: 'days_left', name: 'days_left', searchable: false },
                { data: 'status', name: 'cheques.status', searchable: false },
                { data: 'action', name: 'action', searchable: false, orderable: false }
            ],
            fnDrawCallback: function () {
                var total = 0;
                $table.find('.cheque-amount').each(function () {
                    total += parseFloat($(this).data('orig-value')) || 0;
                });
                $table.find('.footer_cheque_total').html(__currency_trans_from_en(total, true));
            }
        });
    }

    var active = $('.nav-tabs li.active a').data('tab');
    if (active && active !== 'cash_flow') {
        initTable(active);
    }

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var type = $(e.target).data('tab');
        if (type !== 'cash_flow') {
            initTable(type);
            cheque_tables[type].columns.adjust();
        }
    });

    $(document).on('change', '.cheque-filter', function () {
        var type = $(this).closest('.cheque-filters').data('type');
        $(this).closest('.cheque-filters').find('input[name="due_bucket"]').val('');
        if (cheque_tables[type]) {
            cheque_tables[type].ajax.reload();
        }
    });

    $(document).on('click', '.cheque-quick-filter', function (e) {
        e.preventDefault();
        var type = $(this).data('type');
        var $filters = $('.cheque-filters[data-type="' + type + '"]');
        $filters.find('select[name="status"]').val('pending');
        $filters.find('input[name="due_bucket"]').val($(this).data('bucket'));
        cheque_tables[type].ajax.reload();
    });

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
                    var tab = $('.nav-tabs li.active a').data('tab');
                    window.location.href = window.location.pathname + '?tab=' + tab;
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

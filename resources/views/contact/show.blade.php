@extends('layouts.app')
@section('title', __('contact.view_contact'))

@section('content')

<!-- Main content -->
<section class="content no-print">
    <div class="row no-print">
        <div class="col-md-4">
            <h3>@lang('contact.view_contact')</h3>
        </div>
        <div class="col-md-4 col-xs-12 mt-15 pull-right">
            {!! Form::select('contact_id', $contact_dropdown, $contact->id , ['class' => 'form-control select2', 'id' => 'contact_id']); !!}
        </div>
    </div>
    <div class="hide print_table_part">
        <style type="text/css">
            .info_col {
                width: 25%;
                float: left;
                padding-left: 10px;
                padding-right: 10px;
            }
        </style>
        <div style="width: 100%;">
            <div class="info_col">
                @include('contact.contact_basic_info')
            </div>
            <div class="info_col">
                @include('contact.contact_more_info')
            </div>
            @if( $contact->type != 'customer')
                <div class="info_col">
                    @include('contact.contact_tax_info')
                </div>
            @endif
            <div class="info_col">
                @include('contact.contact_payment_info')
            </div>
        </div>
    </div>
    <input type="hidden" id="sell_list_filter_customer_id" value="{{$contact->id}}">
    <input type="hidden" id="purchase_list_filter_supplier_id" value="{{$contact->id}}">
    <br>
    <div class="row">
        <div class="col-md-12">
            <div class="box box-solid">
                <div class="box-body">
                    @include('contact.partials.contact_info_tab')
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs nav-justified">
                    <li class="
                            @if(!empty($view_type) &&  $view_type == 'ledger')
                                active
                            @else
                                ''
                            @endif">
                        <a href="#ledger_tab" data-toggle="tab" aria-expanded="true"><i class="fas fa-scroll" aria-hidden="true"></i> @lang('lang_v1.ledger')</a>
                    </li>
                    @if(in_array($contact->type, ['both', 'supplier']))
                        <li class="
                            @if(!empty($view_type) &&  $view_type == 'purchase')
                                active
                            @else
                                ''
                            @endif">
                            <a href="#purchases_tab" data-toggle="tab" id="purchases-link" aria-expanded="true"><i class="fas fa-arrow-circle-down" aria-hidden="true"></i> @lang( 'purchase.purchases')</a>
                        </li>
                        <li class="
                            @if(!empty($view_type) &&  $view_type == 'stock_report')
                                active
                            @else
                                ''
                            @endif">
                            <a href="#stock_report_tab" data-toggle="tab" aria-expanded="true"><i class="fas fa-hourglass-half" aria-hidden="true"></i> @lang( 'report.stock_report')</a>
                        </li>
                    @endif
                    @if(in_array($contact->type, ['both', 'customer']))
                        <li class="
                            @if(!empty($view_type) &&  $view_type == 'sales')
                                active
                            @else
                                ''
                            @endif">
                            <a href="#sales_tab" data-toggle="tab" aria-expanded="true"><i class="fas fa-arrow-circle-up" aria-hidden="true"></i> @lang( 'sale.sells')</a>
                        </li>
                        @if(in_array('subscription', $enabled_modules))
                            <li class="
                                @if(!empty($view_type) &&  $view_type == 'subscriptions')
                                    active
                                @else
                                    ''
                                @endif">
                                <a href="#subscriptions_tab" data-toggle="tab" aria-expanded="true"><i class="fas fa-recycle" aria-hidden="true"></i> @lang( 'lang_v1.subscriptions')</a>
                            </li>
                        @endif
                    @endif
                    <li class="
                            @if(!empty($view_type) &&  $view_type == 'documents_and_notes')
                                active
                            @else
                                ''
                            @endif
                            ">
                        <a href="#documents_and_notes_tab" data-toggle="tab" aria-expanded="true"><i class="fas fa-paperclip" aria-hidden="true"></i> @lang('lang_v1.documents_and_notes')</a>
                    </li>
                    <li class="
                            @if(!empty($view_type) &&  $view_type == 'payments')
                                active
                            @else
                                ''
                            @endif">
                        <a href="#payments_tab" data-toggle="tab" aria-expanded="true"><i class="fas fa-money-bill-alt" aria-hidden="true"></i> @lang('sale.payments')</a>
                    </li>

                    @if( in_array($contact->type, ['customer', 'both']) && session('business.enable_rp'))
                        <li class="
                            @if(!empty($view_type) &&  $view_type == 'reward_point')
                                active
                            @else
                                ''
                            @endif">
                            <a href="#reward_point_tab" data-toggle="tab" aria-expanded="true"><i class="fas fa-gift" aria-hidden="true"></i> {{ session('business.rp_name') ?? __( 'lang_v1.reward_points')}}</a>
                        </li>
                    @endif

                    <li class="
                        @if(!empty($view_type) &&  $view_type == 'activities')
                            active
                        @else
                            ''
                        @endif">
                        <a href="#activities_tab" data-toggle="tab" aria-expanded="true"><i class="fas fa-pen-square" aria-hidden="true"></i> @lang('lang_v1.activities')</a>
                        </li>

                    @if(!empty($contact_view_tabs))
                        @foreach($contact_view_tabs as $key => $tabs)
                            @foreach ($tabs as $index => $value)
                                @if(!empty($value['tab_menu_path']))
                                    @php
                                        $tab_data = !empty($value['tab_data']) ? $value['tab_data'] : [];
                                    @endphp
                                    @include($value['tab_menu_path'], $tab_data)
                                @endif
                            @endforeach
                        @endforeach
                    @endif

                </ul>

                <div class="tab-content">
                    <div class="tab-pane
                                @if(!empty($view_type) &&  $view_type == 'ledger')
                                    active
                                @else
                                    ''
                                @endif"
                            id="ledger_tab">
                        @include('contact.partials.ledger_tab')
                    </div>
                    @if(in_array($contact->type, ['both', 'supplier']))
                        <div class="tab-pane
                            @if(!empty($view_type) &&  $view_type == 'purchase')
                                active
                            @else
                                ''
                            @endif"
                        id="purchases_tab">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        {!! Form::label('purchase_list_filter_date_range', __('report.date_range') . ':') !!}
                                        {!! Form::text('purchase_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    @include('purchase.partials.purchase_table')
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane 
                            @if(!empty($view_type) &&  $view_type == 'stock_report')
                                active
                            @else
                                ''
                            @endif" id="stock_report_tab">
                            @include('contact.partials.stock_report_tab')
                        </div>
                    @endif
                    @if(in_array($contact->type, ['both', 'customer']))
                        <div class="tab-pane 
                            @if(!empty($view_type) &&  $view_type == 'sales')
                                active
                            @else
                                ''
                            @endif"
                        id="sales_tab">
                            <div class="row">
                                <div class="col-md-12">
                                    @component('components.widget')
                                        @include('sell.partials.sell_list_filters', ['only' => ['sell_list_filter_payment_status', 'sell_list_filter_date_range', 'only_subscriptions']])
                                    @endcomponent
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    @include('sale_pos.partials.sales_table')
                                </div>
                            </div>
                        </div>
                        @if(in_array('subscription', $enabled_modules))
                            @include('contact.partials.subscriptions')
                        @endif
                    @endif
                    <div class="tab-pane
                            @if(!empty($view_type) &&  $view_type == 'documents_and_notes')
                                active
                            @else
                                ''
                            @endif"
                        id="documents_and_notes_tab">
                        @include('contact.partials.documents_and_notes_tab')
                    </div>
                    <div class="tab-pane 
                        @if(!empty($view_type) &&  $view_type == 'payments')
                            active
                        @else
                            ''
                        @endif" id="payments_tab">
                        <div id="contact_payments_div" style="height: 500px;overflow-y: scroll;"></div>
                    </div>
                    @if( in_array($contact->type, ['customer', 'both']) && session('business.enable_rp'))
                        <div class="tab-pane
                            @if(!empty($view_type) &&  $view_type == 'reward_point')
                                active
                            @else
                                ''
                            @endif"
                        id="reward_point_tab">
                        <br>
                            <div class="row">
                            @if($reward_enabled)
                                <div class="col-md-3">
                                    <div class="info-box bg-yellow">
                                        <span class="info-box-icon"><i class="fa fa-gift"></i></span>

                                        <div class="info-box-content">
                                          <span class="info-box-text">{{session('business.rp_name')}}</span>
                                          <span class="info-box-number">{{$contact->total_rp ?? 0}}</span>
                                        </div>
                                        <!-- /.info-box-content -->
                                    </div>
                                </div>
                            @endif
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" 
                                    id="rp_log_table" width="100%">
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
                        </div>
                        </div>
                    @endif

                    <div class="tab-pane"
                        id="activities_tab">
                        @include('activity_log.activities')
                    </div>

                    @if(!empty($contact_view_tabs))
                        @foreach($contact_view_tabs as $key => $tabs)
                            @foreach ($tabs as $index => $value)
                                @if(!empty($value['tab_content_path']))
                                    @php
                                        $tab_data = !empty($value['tab_data']) ? $value['tab_data'] : [];
                                    @endphp
                                    @include($value['tab_content_path'], $tab_data)
                                @endif
                            @endforeach
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
<!-- /.content -->
<div class="modal fade payment_modal" tabindex="-1" role="dialog" 
        aria-labelledby="gridSystemModalLabel">
</div>
<div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>
<div class="modal fade pay_contact_due_modal" tabindex="-1" role="dialog" 
        aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade rr-scope" id="edit_ledger_discount_modal" tabindex="-1" role="dialog" 
        aria-labelledby="gridSystemModalLabel">
</div>
@can('contact.ledger_discount')
    @include('ledger_discount.create')
@endcan

@stop
@section('javascript')
@include('contact.partials.show_js')
@endsection

@extends('layouts.app')
@section('title', __('superadmin::lang.superadmin') . ' | ' . __('superadmin::lang.subscription'))

@section('content')

    <!-- Main content -->
    <section class="content">

        @include('superadmin::layouts.partials.currency')

        <div class="subscription-wrapper">

            {{-- ============================================================ --}}
            {{-- الباقة النشطة + القادمة + قيد الانتظار                        --}}
            {{-- ============================================================ --}}
            @component('components.widget')
                <div class="box-header">
                    <h3 class="box-title">الباقة الحالية</h3>
                </div>

                <div class="box-body">

                    @if (!empty($active))
                        @php
                            $total_days = \Carbon::parse($active->start_date)->diffInDays(\Carbon::parse($active->end_date));
                            $remaining_days = \Carbon::today()->diffInDays(\Carbon::parse($active->end_date));
                            $progress_percent = $total_days > 0 ? min(100, round(($remaining_days / $total_days) * 100)) : 100;
                        @endphp
                        <div class="active-plan-card">
                            <div class="active-plan-head">
                                <div>
                                    <span class="status-pill status-pill-success">
                                        <i class="fa fa-check-circle"></i>
                                       نشط الان
                                    </span>
                                    <h2 class="active-plan-name">{{ $active->package_details['name'] }}</h2>
                                </div>
                                <div class="active-plan-remaining">
                                    <span class="active-plan-remaining-label">متبقي على الانتهاء</span>
                                    <span class="active-plan-remaining-value">{{ $remaining_days }} يوم</span>
                                </div>
                            </div>
                            <div class="active-plan-progress">
                                <div class="active-plan-progress-bar" style="width: {{ $progress_percent }}%;"></div>
                            </div>
                            <div class="active-plan-dates">
                                <span><i class="fa fa-calendar"></i> تاريخ البدء {{ @format_date($active->start_date) }}</span>
                                <span><i class="fa fa-calendar-times-o"></i> تاريخ الانتهاء {{ @format_date($active->end_date) }}</span>
                            </div>
                        </div>
                    @else
                        <div class="no-active-plan">
                            <i class="fa fa-exclamation-triangle"></i>
                            لا يوجد لديك باقة مفعّلة حاليًا
                        </div>
                    @endif

                    @if (!empty($waiting))
                        @foreach ($waiting as $row)
                            <div class="waiting-plan-banner">
                                <i class="fa fa-clock-o"></i>
                                <div>
                                    <p class="waiting-plan-title">{{ $row->package_details['name'] }}</p>
                                    <p class="waiting-plan-msg">
                                        @if ($row->paid_via == 'offline')
                                            طلب الدفع قيد المراجعة، في انتظار موافقة المسؤول
                                        @else
                                            في انتظار تأكيد عملية الدفع
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    @if (!empty($nexts))
                        <h4 class="upcoming-plans-title">باقات قادمة بعد انتهاء الباقة الحالية</h4>
                        <div class="upcoming-plans-grid">
                            @foreach ($nexts as $next)
                                <div class="upcoming-plan-card">
                                    <span class="status-pill status-pill-accent">قادم</span>
                                    <p class="upcoming-plan-name">{{ $next->package_details['name'] }}</p>
                                    <p class="upcoming-plan-dates">
                                        من {{ @format_date($next->start_date) }} إلى {{ @format_date($next->end_date) }}
                                    </p>
                                    <a href="{{ route('force-active', $next->id) }}"
                                        class="tw-dw-btn tw-dw-btn-success tw-text-white tw-dw-btn-sm tw-dw-btn-block force_activate_now">
                                       التبديل لهذه الباقة الان
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>
            @endcomponent

            {{-- ============================================================ --}}
            {{-- جميع الاشتراكات                                               --}}
            {{-- ============================================================ --}}
            @component('components.widget')
                <div class="box-header">
                    <h3 class="box-title">@lang('superadmin::lang.all_subscriptions')</h3>
                </div>

                <div class="box-body">
                    <div class="row">
                        <div class ="col-xs-12">
                            <div class="table-responsive subscriptions-table-wrapper">
                                <!-- location table-->
                                <table class="table table-bordered table-hover" id="all_subscriptions_table">
                                    <thead>
                                        <tr>
                                            <th>@lang('superadmin::lang.package_name')</th>
                                            <th>@lang('superadmin::lang.start_date')</th>
                                            <th>@lang('superadmin::lang.trial_end_date')</th>
                                            <th>@lang('superadmin::lang.end_date')</th>
                                            <th>@lang('superadmin::lang.price')</th>
                                            <th>@lang('superadmin::lang.paid_via')</th>
                                            <th>@lang('superadmin::lang.payment_transaction_id')</th>
                                            <th>@lang('sale.status')</th>
                                            <th>@lang('lang_v1.created_at')</th>
                                            <th>@lang('business.created_by')</th>
                                            <th>@lang('messages.action')</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endcomponent

            {{-- ============================================================ --}}
            {{-- الباقات                                                       --}}
            {{-- ============================================================ --}}
            @component('components.widget')
                <div class="box-header">
                    <h3 class="box-title">@lang('superadmin::lang.packages')</h3>
                </div>

                <div class="box-body">
                    @include('superadmin::subscription.partials.packages')
                </div>
            @endcomponent

        </div>

    </section>

    <style>
    .subscription-wrapper {
        direction: rtl;
        text-align: right;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 600;
        padding: 3px 12px;
        border-radius: 20px;
    }
    .status-pill-success {
        background: #eafaf1;
        color: #1d7a46;
    }
    .status-pill-accent {
        background: #e8f0fe;
        color: #1a56b0;
    }
    .status-pill-warning {
        background: #fdf3e3;
        color: #8a5a13;
    }

    .active-plan-card {
        background: #fff;
        border: 1px solid #ececec;
        border-radius: 10px;
        padding: 1.25rem;
        margin-bottom: 16px;
    }
    .active-plan-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 14px;
    }
    .active-plan-name {
        margin: 8px 0 0;
        font-size: 20px;
        font-weight: 700;
    }
    .active-plan-remaining {
        text-align: left;
    }
    .active-plan-remaining-label {
        display: block;
        font-size: 12px;
        color: #888;
    }
    .active-plan-remaining-value {
        display: block;
        font-size: 22px;
        font-weight: 700;
    }
    .active-plan-progress {
        height: 6px;
        background: #f0f0f0;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    .active-plan-progress-bar {
        height: 100%;
        background: #00a65a;
    }
    .active-plan-dates {
        display: flex;
        gap: 24px;
        font-size: 13px;
        color: #666;
        flex-wrap: wrap;
    }
    .active-plan-dates i {
        margin-left: 4px;
    }

    .no-active-plan {
        background: #fdecea;
        color: #a02a2a;
        border-radius: 8px;
        padding: 14px 16px;
        font-size: 14px;
        margin-bottom: 16px;
    }
    .no-active-plan i {
        margin-left: 8px;
    }

    .waiting-plan-banner {
        background: #fcf8e3;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .waiting-plan-banner i {
        font-size: 18px;
        color: #8a6d3b;
    }
    .waiting-plan-title {
        margin: 0;
        font-weight: 600;
        font-size: 14px;
        color: #8a6d3b;
    }
    .waiting-plan-msg {
        margin: 2px 0 0;
        font-size: 13px;
        color: #8a6d3b;
    }

    .upcoming-plans-title {
        font-size: 15px;
        font-weight: 700;
        margin: 8px 0 12px;
    }
    .upcoming-plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }
    .upcoming-plan-card {
        background: #fff;
        border: 1px solid #ececec;
        border-radius: 10px;
        padding: 1rem;
    }
    .upcoming-plan-name {
        margin: 10px 0 2px;
        font-weight: 600;
        font-size: 14px;
    }
    .upcoming-plan-dates {
        margin: 0 0 12px;
        font-size: 12px;
        color: #888;
    }

    .subscriptions-table-wrapper table {
        font-size: 13px;
    }
    .badge-status-approved {
        background: #eafaf1;
        color: #1d7a46;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 14px;
    }
    .badge-status-waiting {
        background: #fcf8e3;
        color: #8a6d3b;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 14px;
    }
    .badge-status-rejected {
        background: #fdecea;
        color: #a02a2a;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 14px;
    }
    </style>

@endsection

@section('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            $('#all_subscriptions_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'allSubscriptions']) }}',
                columns: [{
                        data: 'package_name',
                        name: 'P.name'
                    },
                    {
                        data: 'start_date',
                        name: 'start_date'
                    },
                    {
                        data: 'trial_end_date',
                        name: 'trial_end_date'
                    },
                    {
                        data: 'end_date',
                        name: 'end_date'
                    },
                    {
                        data: 'package_price',
                        name: 'package_price'
                    },
                    {
                        data: 'paid_via',
                        name: 'paid_via'
                    },
                    {
                        data: 'payment_transaction_id',
                        name: 'payment_transaction_id'
                    },
                    {
                        data: 'status',
                        name: 'status'
                    },
                    {
                        data: 'created_at',
                        name: 'created_at'
                    },
                    {
                        data: 'created_by',
                        name: 'created_by'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        searchable: false,
                        orderable: false
                    },
                ],
                columnDefs: [{
                    targets: 7,
                    render: function(data, type, row) {
                        if (type !== 'display' || !data) {
                            return data;
                        }
                        var cssClass = 'badge-status-waiting';
                        if (data == 'approved') {
                            cssClass = 'badge-status-approved';
                        } else if (data == 'rejected') {
                            cssClass = 'badge-status-rejected';
                        }
                        return '<span class="' + cssClass + '">' + data + '</span>';
                    }
                }],
                "fnDrawCallback": function(oSettings) {
                    __currency_convert_recursively($('#all_subscriptions_table'), true);
                }
            });
            $(document).on('click', '.force_activate_now', function(e) {

                e.preventDefault();
                swal({
                    title: 'This will End your current plan and activate this plan from today. Do you want to continue?',
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((willActive) => {
                    if (willActive) {
                        var href = $(this).attr('href');
                        $.ajax({
                            method: "GET",
                            url: href,
                            dataType: "json",
                            success: function(result) {
                                if (result.success == true) {
                                    toastr.success(result.msg);
                                    location.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
@endsection
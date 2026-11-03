```blade
<div class="modal-dialog" role="document">
    <div class="modal-content">

        <div class="modal-header no-print">
            <button
                type="button"
                class="close"
                data-dismiss="modal"
                aria-label="Close"
            >
                <span aria-hidden="true">&times;</span>
            </button>

            <h4 class="modal-title">
                تفاصيل الاشتراك
            </h4>
        </div>

        <div class="modal-body">

            <div class="row">

                <div class="col-xs-6">
                    <div class="well well-sm">

                        <strong>@lang('business.business_name'): </strong>
                        {{ $system['invoice_business_name'] ?? '' }}
                        <br>

                        <strong>@lang('business.email'): </strong>
                        {{ $system['email'] ?? '' }}
                        <br>

                        <strong>@lang('business.landmark'): </strong>
                        {{ $system['invoice_business_landmark'] ?? '' }}
                        <br>

                        <strong>@lang('business.city'): </strong>
                        {{ $system['invoice_business_city'] ?? '' }}

                        <strong>@lang('business.zip_code'): </strong>
                        {{ $system['invoice_business_zip'] ?? '' }}
                        <br>

                        <strong>@lang('business.state'): </strong>
                        {{ $system['invoice_business_state'] ?? '' }}

                        <strong>@lang('business.country'): </strong>
                        {{ $system['invoice_business_country'] ?? '' }}

                    </div>
                </div>

                <div class="col-xs-6">
                    <div class="well well-sm">

                        <strong>@lang('business.business_name'): </strong>
                        {{ optional($subscription->business)->name }}
                        <br>

                        @if(
                            !empty(optional($subscription->business)->tax_number_1) &&
                            !empty(optional($subscription->business)->tax_label_1)
                        )
                            <strong>
                                {{ $subscription->business->tax_label_1 }}:
                            </strong>

                            {{ $subscription->business->tax_number_1 }}
                            <br>
                        @endif

                        @if(
                            !empty(optional($subscription->business)->tax_number_2) &&
                            !empty(optional($subscription->business)->tax_label_2)
                        )
                            <strong>
                                {{ $subscription->business->tax_label_2 }}:
                            </strong>

                            {{ $subscription->business->tax_number_2 }}
                            <br>
                        @endif

                    </div>
                </div>

            </div>

            <div class="row">
                <div class="col-md-12">

                    <table class="table subscription-details">

                        <thead>
                            <tr>
                                <th>الباقة</th>
                                <th>الكمية</th>
                                <th>السعر</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>

                                <td>
                                    {{ optional($subscription->package)->name }}
                                </td>

                                <td>
                                    1
                                </td>

                                <td>
                                    @if(empty($subscription->coupon_code))

                                        <span
                                            class="display_currency"
                                            data-currency_symbol="true"
                                            data-use_page_currency="true"
                                        >
                                            {{ $subscription->package_price }}
                                        </span>

                                    @else

                                        <span
                                            class="display_currency"
                                            data-currency_symbol="true"
                                            data-use_page_currency="true"
                                        >
                                            {{ $subscription->original_price }}
                                        </span>

                                        <br>

                                        <span>
                                            الخصم:
                                        </span>

                                        <span
                                            class="display_currency"
                                            data-currency_symbol="true"
                                            data-use_page_currency="true"
                                        >
                                            {{ $subscription->original_price - $subscription->package_price }}
                                        </span>

                                        <small class="badge bg-info">
                                            {{ $subscription->coupon_code }}
                                        </small>

                                        <br>

                                        <strong>
                                            السعر بعد الخصم:
                                        </strong>

                                        <span
                                            class="display_currency"
                                            data-currency_symbol="true"
                                            data-use_page_currency="true"
                                        >
                                            {{ $subscription->package_price }}
                                        </span>

                                    @endif
                                </td>

                            </tr>
                        </tbody>

                    </table>

                </div>
            </div>

            <hr>

            <div class="row">
                <div class="col-xs-12">

                    <table class="table">

                        <tr>
                            <th>تاريخ إنشاء الطلب:</th>

                            <td>
                                {{ @format_date($subscription->created_at) }}
                            </td>

                            <th>رقم عملية الدفع:</th>

                            <td>
                                {{ $subscription->payment_transaction_id ?: 'غير متوفر' }}
                            </td>
                        </tr>

                        <tr>
                            <th>تم الإنشاء بواسطة:</th>

                            <td>
                                {{ optional($subscription->created_user)->user_full_name }}
                            </td>

                            <th>طريقة الدفع:</th>

                            <td>
                                @if($subscription->paid_via === 'offline')
                                    تحويل يدوي
                                @else
                                    {{ $subscription->paid_via }}
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>حالة الاشتراك:</th>

                            <td>
                                @if($subscription->status === 'approved')
                                    <span class="label label-success">
                                        تمت الموافقة
                                    </span>
                                @elseif($subscription->status === 'waiting')
                                    <span class="label label-warning">
                                        قيد المراجعة
                                    </span>
                                @elseif($subscription->status === 'declined')
                                    <span class="label label-danger">
                                        مرفوض
                                    </span>
                                @else
                                    {{ $subscription->status }}
                                @endif
                            </td>

                            <th>إيصال التحويل:</th>

                            <td>
                                @if(!empty($subscription->payment_receipt))

                                    <a
                                        href="{{ asset($subscription->payment_receipt) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn btn-success btn-sm no-print"
                                    >
                                        <i class="fa fa-eye"></i>
                                        عرض صورة التحويل
                                    </a>

                                @else

                                    <span class="text-muted">
                                        لا يوجد إيصال مرفق
                                    </span>

                                @endif
                            </td>
                        </tr>

                    </table>

                </div>
            </div>

            @if(!empty($subscription->payment_receipt))

                <hr>

                <div class="row">
                    <div class="col-md-12">

                        <div class="well well-sm">

                            <strong>
                                <i class="fa fa-file-image-o"></i>
                                صورة إيصال التحويل
                            </strong>

                            <div
                                style="
                                    margin-top: 15px;
                                    text-align: center;
                                "
                            >
                                <a
                                    href="{{ asset($subscription->payment_receipt) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <img
                                        src="{{ asset($subscription->payment_receipt) }}"
                                        alt="صورة إيصال التحويل"
                                        style="
                                            width: auto;
                                            max-width: 100%;
                                            max-height: 400px;
                                            object-fit: contain;
                                            border: 1px solid #ddd;
                                            border-radius: 8px;
                                            padding: 5px;
                                            background-color: #fff;
                                        "
                                    >
                                </a>
                            </div>

                        </div>

                    </div>
                </div>

            @endif

        </div>

        <div class="modal-footer no-print">

            <button
                type="button"
                class="tw-dw-btn tw-dw-btn-primary tw-text-white"
                aria-label="Print"
                onclick="$(this).closest('div.modal-content').printThis();"
            >
                <i class="fa fa-print"></i>
                @lang('messages.print')
            </button>

            <button
                type="button"
                class="tw-dw-btn tw-dw-btn-neutral tw-text-white"
                data-dismiss="modal"
            >
                @lang('messages.close')
            </button>

        </div>

    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        __currency_convert_recursively(
            $('.subscription-details')
        );
    });
</script>
```

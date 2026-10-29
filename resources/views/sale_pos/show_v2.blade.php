{{--
    نافذة فاتورة البيع بتصميم رقمي (SellController@show?v2=1). نفس بيانات sale_pos.show.
    بتتفتح من شاشة المبيعات الجديدة بس لحد ما تتعمم؛ النافذة القديمة لسه مستخدمة في باقي الشاشات.
--}}
@php
    $custom_labels = json_decode(session('business.custom_labels'), true);
    $is_order = $sell->type == 'sales_order';
    $is_construction = ($sell->source ?? null) === 'construction_certificate';
    $contact = $sell->contact;

    $total_paid = 0;
    foreach ($sell->payment_lines as $payment_line) {
        $total_paid += $payment_line->is_return ? -1 * $payment_line->amount : $payment_line->amount;
    }
    $remaining = $sell->final_total - (string) $total_paid;
    $payment_status = \App\Transaction::getPaymentStatus($sell);
    $status_tone = ['paid' => 'success', 'partial' => 'info', 'due' => 'warning', 'overdue' => 'danger', 'partial-overdue' => 'danger'][$payment_status] ?? '';
    $status_label = $sell->status == 'draft' && $sell->is_quotation == 1 ? __('lang_v1.quotation') : ($statuses[$sell->status] ?? __('sale.' . $sell->status));

    // نفس شروط قايمة الإجراءات في جدول المبيعات
    $can_edit = false;
    $edit_url = null;
    if (! $is_construction) {
        if ($sell->is_direct_sale == 0) {
            $can_edit = auth()->user()->can('sell.update');
            $edit_url = action([\App\Http\Controllers\SellPosController::class, 'edit'], [$sell->id]);
        } else {
            $can_edit = $is_order ? auth()->user()->can('so.update') : auth()->user()->can('direct_sell.update');
            $edit_url = action([\App\Http\Controllers\SellController::class, 'edit'], [$sell->id]);
        }
    }
    $can_pay = ! $is_order && (auth()->user()->can('sell.payments') || auth()->user()->can('edit_sell_payment') || auth()->user()->can('delete_sell_payment'));
    $can_print = ! $is_order && auth()->user()->can('print_invoice');

    // سطور الحساب: اللي قيمته صفر ما بيظهرش
    $summary = [];
    $summary[] = ['label' => __('sale.total'), 'value' => $sell->total_before_tax];
    if ($sell->discount_amount > 0) {
        $summary[] = ['label' => __('sale.discount') . ($sell->discount_type == 'percentage' ? ' (' . (float) $sell->discount_amount . '%)' : ''), 'sign' => '-',
            'value' => $sell->discount_type == 'percentage' ? $sell->total_before_tax * $sell->discount_amount / 100 : $sell->discount_amount];
    }
    if (in_array('types_of_service', $enabled_modules) && $sell->packing_charge > 0) {
        $summary[] = ['label' => __('lang_v1.packing_charge'), 'sign' => '+',
            'value' => $sell->packing_charge_type == 'percent' ? $sell->total_before_tax * $sell->packing_charge / 100 : $sell->packing_charge];
    }
    if (session('business.enable_rp') == 1 && ! empty($sell->rp_redeemed)) {
        $summary[] = ['label' => session('business.rp_name'), 'sign' => '-', 'value' => $sell->rp_redeemed_amount];
    }
    foreach ($order_taxes as $name => $value) {
        if ($value != 0) {
            $summary[] = ['label' => __('sale.order_tax') . ' (' . $name . ')', 'sign' => '+', 'value' => $value];
        }
    }
    // ضريبة الأصناف داخلة أصلًا في أسعارها (المجموع شامل الضريبة)، فبتظهر كسطر توضيحي تحت المجموع مش كإضافة
    foreach ($line_taxes as $name => $value) {
        if ($value != 0) {
            array_splice($summary, 1, 0, [['label' => __('sell_list.inv_tax_included', ['name' => $name]), 'value' => $value, 'note' => true]]);
        }
    }
    if ($sell->shipping_charges > 0) {
        $summary[] = ['label' => __('sale.shipping') . ($sell->shipping_details ? ' (' . $sell->shipping_details . ')' : ''), 'sign' => '+', 'value' => $sell->shipping_charges];
    }
    for ($i = 1; $i <= 4; $i++) {
        if (! empty($sell->{'additional_expense_value_' . $i}) && ! empty($sell->{'additional_expense_key_' . $i})) {
            $summary[] = ['label' => $sell->{'additional_expense_key_' . $i}, 'sign' => '+', 'value' => $sell->{'additional_expense_value_' . $i}];
        }
    }
    if ($sell->round_off_amount != 0) {
        $summary[] = ['label' => __('lang_v1.round_off'), 'value' => $sell->round_off_amount];
    }

    // بيانات إضافية: بتظهر بس لو ليها قيمة
    $extra = [];
    if (in_array('tables', $enabled_modules) && ! empty($sell->table)) {
        $extra[__('restaurant.table')] = $sell->table->name;
    }
    if (in_array('service_staff', $enabled_modules) && ! empty($sell->service_staff)) {
        $extra[__('restaurant.service_staff')] = $sell->service_staff->user_full_name;
    }
    if (in_array('types_of_service', $enabled_modules) && ! empty($sell->types_of_service)) {
        $extra[__('lang_v1.types_of_service')] = $sell->types_of_service->name;
        if (! empty($sell->types_of_service->enable_custom_fields)) {
            for ($i = 1; $i <= 6; $i++) {
                $default = $i <= 4 ? __('lang_v1.service_custom_field_' . $i) : __('lang_v1.custom_field', ['number' => $i]);
                if (! empty($sell->{'service_custom_field_' . $i})) {
                    $extra[$custom_labels['types_of_service']['custom_field_' . $i] ?? $default] = $sell->{'service_custom_field_' . $i};
                }
            }
        }
    }
    for ($i = 1; $i <= 4; $i++) {
        if (! empty($custom_labels['sell']['custom_field_' . $i]) && ! empty($sell->{'custom_field_' . $i})) {
            $extra[$custom_labels['sell']['custom_field_' . $i]] = $sell->{'custom_field_' . $i};
        }
    }
    if (! empty($sell->is_export) && ! empty($sell->export_custom_fields_info)) {
        foreach ($sell->export_custom_fields_info as $key => $value) {
            if (! empty($value)) {
                $extra[__('lang_v1.export_custom_field' . substr($key, -1))] = $value;
            }
        }
    }

    $has_shipping = ! empty($sell->shipping_status) || ! empty($sell->shipping_address()) || ! empty($sell->shipping_address) || ! empty($sell->delivered_to) || ! empty($sell->delivery_person_user);
    $shipping_extra = [];
    for ($i = 1; $i <= 5; $i++) {
        if (! empty($sell->{'shipping_custom_field_' . $i})) {
            $shipping_extra[$custom_labels['shipping']['custom_field_' . $i] ?? __('lang_v1.custom_field', ['number' => $i])] = $sell->{'shipping_custom_field_' . $i};
        }
    }
    $shipping_media = $sell->media->where('model_media_type', 'shipping_document')->all();
    $shipping_tone = ['ordered' => 'warning', 'packed' => 'info', 'shipped' => 'info', 'delivered' => 'success', 'cancelled' => 'danger'][$sell->shipping_status] ?? '';

    $lot_enabled = session()->get('business.enable_lot_number') == 1;
    $staff_column = ! empty($pos_settings['inline_service_staff']);
@endphp

<div class="modal-dialog rr-modal-dialog rr-modal-dialog--xl no-print" role="document" id="rr_invoice_{{ $sell->id }}">
    <div class="modal-content rr-modal rr-invoice">
        <header class="rr-modal__head">
            <div class="rr-modal__titles">
                <p class="rr-eyebrow">{{ $is_order ? __('restaurant.order_no') : __('sell_list.inv_eyebrow') }}</p>
                <h2 class="rr-modal__title rr-invoice__title" id="modalTitle">
                    <span class="rr-num">{{ $sell->invoice_no }}</span>
                    <span class="rr-badge">{{ $status_label }}</span>
                    @if(! $is_order && ! empty($payment_status))
                        <span class="rr-badge{{ $status_tone ? ' rr-badge--' . $status_tone : '' }}"><span class="rr-badge__dot"></span>{{ __('lang_v1.' . $payment_status) }}</span>
                    @endif
                </h2>
                <p class="rr-invoice__meta">
                    <span class="rr-invoice__meta-item">@include('partials.rr.icon', ['name' => 'calendar', 'size' => 16])<span class="rr-num">{{ @format_datetime($sell->transaction_date) }}</span></span>
                    @if($sell->location)<span class="rr-invoice__meta-item">@include('partials.rr.icon', ['name' => 'map-pin', 'size' => 16]){{ $sell->location->name }}</span>@endif
                    @if($sell->sales_person)<span class="rr-invoice__meta-item">@include('partials.rr.icon', ['name' => 'user', 'size' => 16]){{ $sell->sales_person->user_full_name }}</span>@endif
                </p>
            </div>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('sell_list.close')">@include('partials.rr.icon', ['name' => 'close'])</button>
        </header>

        <div class="rr-modal__body rr-invoice__body">
            @if($constructionCertificate && $constructionCertificate->project)
                <div class="rr-callout rr-callout--info">
                    <span class="rr-callout__icon">@include('partials.rr.icon', ['name' => 'building'])</span>
                    <dl class="rr-invoice__facts">
                        <div><dt>@lang('construction::lang.project_name')</dt><dd><a class="rr-code-link" href="{{ route('construction.projects.show', $constructionCertificate->project_id) }}">{{ $constructionCertificate->project->name }}</a></dd></div>
                        <div><dt>@lang('construction::lang.project_code')</dt><dd class="rr-num">{{ $constructionCertificate->project->code }}</dd></div>
                        @if($constructionCertificate->contract)
                            <div><dt>@lang('construction::lang.contract_number')</dt><dd><a class="rr-code-link rr-num" href="{{ route('construction.projects.contracts.show', [$constructionCertificate->project_id, $constructionCertificate->contract_id]) }}">{{ $constructionCertificate->contract->contract_number }}</a></dd></div>
                        @endif
                        <div><dt>@lang('construction::lang.certificate_number')</dt><dd><a class="rr-code-link rr-num" href="{{ route('construction.projects.certificates.show', [$constructionCertificate->project_id, $constructionCertificate->id]) }}">{{ $constructionCertificate->certificate_number }}</a></dd></div>
                        @if($constructionCertificate->measurement)
                            <div><dt>@lang('construction::lang.measurement_number')</dt><dd><a class="rr-code-link rr-num" href="{{ route('construction.projects.measurements.show', [$constructionCertificate->project_id, $constructionCertificate->measurement_id]) }}">{{ $constructionCertificate->measurement->measurement_number }}</a></dd></div>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- ═══ المبالغ ═══ --}}
            <div class="rr-statgroup-wrap">
                <div class="rr-statgroup rr-statgroup--{{ ! $is_order && $total_returns > 0 ? 4 : ($is_order ? 1 : 3) }}" style="--rr-stats: {{ ! $is_order && $total_returns > 0 ? 4 : ($is_order ? 1 : 3) }}">
                    <div class="rr-stat rr-stat--brand">
                        <span class="rr-stat__label">@lang('sale.total_payable')</span>
                        <span class="rr-stat__value"><span class="rr-money">@include('partials.rr.money', ['value' => $sell->final_total])</span></span>
                    </div>
                    @if(! $is_order)
                        <div class="rr-stat rr-stat--success">
                            <span class="rr-stat__label">@lang('sale.total_paid')</span>
                            <span class="rr-stat__value"><span class="rr-money">@include('partials.rr.money', ['value' => $total_paid])</span></span>
                        </div>
                        <div class="rr-stat {{ $remaining > 0 ? 'rr-stat--warning' : 'rr-stat--neutral' }}">
                            <span class="rr-stat__label">@lang('sale.total_remaining')</span>
                            <span class="rr-stat__value{{ $remaining > 0 ? ' rr-stat__value--warning' : '' }}"><span class="rr-money{{ $remaining == 0 ? ' rr-money--zero' : '' }}">@include('partials.rr.money', ['value' => $remaining])</span></span>
                        </div>
                        @if($total_returns > 0)
                            <div class="rr-stat rr-stat--danger">
                                <span class="rr-stat__label">@lang('sell_list.kpi_returns')</span>
                                <span class="rr-stat__value"><span class="rr-money">@include('partials.rr.money', ['value' => $total_returns])</span></span>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <div class="rr-invoice__grid">
                {{-- ═══ العمود الرئيسي: الأصناف + الحساب + المدفوعات ═══ --}}
                <div class="rr-invoice__main">
                    <section class="rr-table-wrap">
                        <div class="rr-table-head"><h3 class="rr-table-head__title">@lang('sell_list.inv_items') <span class="rr-tab__count rr-num">{{ $sell->sell_lines->count() }}</span></h3></div>
                        <div class="rr-table-scroll">
                            <table class="rr-table rr-invoice__lines">
                                <thead>
                                    <tr>
                                        <th class="rr-th">@lang('sale.product')</th>
                                        @if($lot_enabled)<th class="rr-th">@lang('lang_v1.lot_n_expiry')</th>@endif
                                        @if($is_order)<th class="rr-th rr-th--num">@lang('lang_v1.quantity_remaining')</th>@endif
                                        <th class="rr-th rr-th--num">@lang('sale.qty')</th>
                                        @if($staff_column)<th class="rr-th">@lang('restaurant.service_staff')</th>@endif
                                        <th class="rr-th rr-th--num">@lang('sale.unit_price')</th>
                                        <th class="rr-th rr-th--num">@lang('sale.discount')</th>
                                        <th class="rr-th rr-th--num">@lang('sale.tax')</th>
                                        <th class="rr-th rr-th--num">@lang('sale.subtotal')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sell->sell_lines as $line)
                                        @php
                                            $is_ipc = $is_construction && ($line->product->sku ?? null) === 'CONST-IPC-SERVICE';
                                            $unit = ! empty($line->sub_unit) ? $line->sub_unit->short_name : ($line->product->unit->short_name ?? '');
                                            $details = [];
                                            if ($line->product->type == 'variable') {
                                                $details[] = trim(($line->variations->product_variation->name ?? '') . ' - ' . ($line->variations->name ?? ''), ' -');
                                            }
                                            if (! $is_ipc && ! empty($line->variations->sub_sku)) {
                                                $details[] = $line->variations->sub_sku;
                                            }
                                            if (! empty($line->product->brand->name)) {
                                                $details[] = $line->product->brand->name;
                                            }
                                            $warranty = $is_warranty_enabled ? $line->warranties->first() : null;
                                        @endphp
                                        <tr class="rr-tr">
                                            <td class="rr-td rr-td--wrap">
                                                <div class="rr-cell-stack">
                                                    <span class="rr-cell-stack__main">{{ $is_ipc && $line->sell_line_note ? $line->sell_line_note : $line->product->name }}</span>
                                                    @if($details)<span class="rr-cell-stack__sub">{{ implode(' · ', $details) }}</span>@endif
                                                    @if(! $is_ipc && ! empty($line->sell_line_note))<span class="rr-cell-stack__sub">{{ $line->sell_line_note }}</span>@endif
                                                    @if($warranty)
                                                        <span class="rr-cell-stack__sub">{{ $warranty->display_name }} - <span class="rr-num">{{ @format_date($warranty->getEndDate($sell->transaction_date)) }}</span>@if($warranty->description) · {{ $warranty->description }}@endif</span>
                                                    @endif
                                                    @if(in_array('kitchen', $enabled_modules) && $line->res_line_order_status)
                                                        <span><span class="rr-badge{{ $line->res_line_order_status == 'served' ? ' rr-badge--success' : ($line->res_line_order_status == 'cooked' ? ' rr-badge--danger' : ' rr-badge--info') }}">@lang('restaurant.order_statuses.' . $line->res_line_order_status)</span></span>
                                                    @endif
                                                </div>
                                            </td>
                                            @if($lot_enabled)
                                                <td class="rr-td rr-td--muted">{{ $line->lot_details->lot_number ?? '—' }}@if(session()->get('business.enable_product_expiry') == 1 && ! empty($line->lot_details->exp_date)) <span class="rr-num">({{ @format_date($line->lot_details->exp_date) }})</span>@endif</td>
                                            @endif
                                            @if($is_order)
                                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity($line->quantity - $line->so_quantity_invoiced) }}</span> {{ $unit }}</td>
                                            @endif
                                            <td class="rr-td rr-td--num">
                                                <span class="rr-num">{{ @format_quantity($line->quantity) }}</span> {{ $unit }}
                                                @if(! empty($line->product->second_unit) && $line->secondary_unit_quantity != 0)
                                                    <span class="rr-cell-stack__sub"><span class="rr-num">{{ @format_quantity($line->secondary_unit_quantity) }}</span> {{ $line->product->second_unit->short_name }}</span>
                                                @endif
                                            </td>
                                            @if($staff_column)<td class="rr-td rr-td--muted">{{ $line->service_staff->user_full_name ?? '' }}</td>@endif
                                            <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($line->unit_price_before_discount) }}</span></td>
                                            <td class="rr-td rr-td--num">
                                                <span class="rr-num{{ $line->get_discount_amount() == 0 ? ' rr-money--zero' : '' }}">{{ @num_format($line->get_discount_amount()) }}</span>
                                                @if($line->line_discount_type == 'percentage' && $line->line_discount_amount > 0)<span class="rr-cell-stack__sub rr-num">{{ @num_format($line->line_discount_amount) }}%</span>@endif
                                            </td>
                                            <td class="rr-td rr-td--num">
                                                <span class="rr-num{{ $line->item_tax == 0 ? ' rr-money--zero' : '' }}">{{ @num_format($line->item_tax) }}</span>
                                                @if(! empty($taxes[$line->tax_id]))<span class="rr-cell-stack__sub">{{ $taxes[$line->tax_id] }}</span>@endif
                                            </td>
                                            <td class="rr-td rr-td--num"><span class="rr-num rr-invoice__subtotal">{{ @num_format($line->quantity * $line->unit_price_inc_tax) }}</span></td>
                                        </tr>
                                        @foreach($line->modifiers ?? [] as $modifier)
                                            <tr class="rr-tr rr-invoice__modifier">
                                                <td class="rr-td"><span class="rr-cell-stack__sub">+ {{ $modifier->product->name }}@if(! empty($modifier->variations->name)) - {{ $modifier->variations->name }}@endif</span></td>
                                                @if($lot_enabled)<td class="rr-td"></td>@endif
                                                @if($is_order)<td class="rr-td"></td>@endif
                                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity($modifier->quantity) }}</span></td>
                                                @if($staff_column)<td class="rr-td"></td>@endif
                                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($modifier->unit_price) }}</span></td>
                                                <td class="rr-td"></td>
                                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($modifier->item_tax) }}</span></td>
                                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($modifier->quantity * $modifier->unit_price_inc_tax) }}</span></td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <dl class="rr-deflist rr-invoice__summary">
                            @foreach($summary as $row)
                                <div class="rr-deflist__item{{ ! empty($row['note']) ? ' rr-invoice__summary-note' : '' }}">
                                    <dt>{{ $row['label'] }}</dt>
                                    <dd><span class="rr-money rr-money--sm">@if(! empty($row['sign']))<span class="rr-invoice__sign">{{ $row['sign'] }}</span>@endif @include('partials.rr.money', ['value' => $row['value']])</span></dd>
                                </div>
                            @endforeach
                            <div class="rr-deflist__item rr-deflist__item--total">
                                <dt>@lang('sale.total_payable')</dt>
                                <dd><span class="rr-money">@include('partials.rr.money', ['value' => $sell->final_total])</span></dd>
                            </div>
                        </dl>
                    </section>

                    @if(! $is_order)
                        <section class="rr-table-wrap">
                            <div class="rr-table-head">
                                <h3 class="rr-table-head__title">@lang('sell_list.inv_payments') <span class="rr-tab__count rr-num">{{ $sell->payment_lines->count() }}</span></h3>
                                @if($can_pay && $sell->payment_status != 'paid')
                                    <a href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$sell->id]) }}" class="add_payment_modal rr-btn rr-btn--secondary rr-btn--sm" data-dismiss="modal">@include('partials.rr.icon', ['name' => 'plus', 'size' => 16])@lang('sell_list.inv_add_payment')</a>
                                @endif
                            </div>
                            @if($sell->payment_lines->isEmpty())
                                <p class="rr-invoice__empty">@lang('sell_list.inv_no_payments')</p>
                            @else
                                <div class="rr-table-scroll">
                                    <table class="rr-table rr-invoice__payments">
                                        <thead>
                                            <tr>
                                                <th class="rr-th">@lang('messages.date')</th>
                                                <th class="rr-th">@lang('sale.payment_mode')</th>
                                                <th class="rr-th">@lang('purchase.ref_no')</th>
                                                <th class="rr-th">@lang('sale.payment_note')</th>
                                                <th class="rr-th rr-th--num">@lang('sale.amount')</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($sell->payment_lines as $payment_line)
                                                <tr class="rr-tr">
                                                    <td class="rr-td"><span class="rr-num">{{ @format_date($payment_line->paid_on) }}</span></td>
                                                    <td class="rr-td">{{ $payment_types[$payment_line->method] ?? $payment_line->method }}@if($payment_line->is_return) <span class="rr-badge rr-badge--danger">@lang('lang_v1.change_return')</span>@endif</td>
                                                    <td class="rr-td rr-td--muted"><span class="rr-num">{{ $payment_line->payment_ref_no ?: '—' }}</span></td>
                                                    <td class="rr-td rr-td--muted rr-td--wrap">{{ $payment_line->note ?: '—' }}</td>
                                                    <td class="rr-td rr-td--num"><span class="rr-num">{{ $payment_line->is_return ? '-' : '' }}{{ @num_format($payment_line->amount) }}</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </section>
                    @endif
                </div>

                {{-- ═══ العمود الجانبي: العميل + الشحن + بيانات إضافية + ملاحظات ═══ --}}
                <aside class="rr-invoice__side">
                    <section class="rr-card">
                        <div class="rr-card__head"><span class="rr-chip rr-chip--sm">@include('partials.rr.icon', ['name' => 'user', 'size' => 16])</span><h3 class="rr-card__title">@lang('sell_list.col_customer')</h3></div>
                        <div class="rr-invoice__contact">
                            <p class="rr-invoice__contact-name">{{ $contact->name ?? '—' }}</p>
                            @if(! empty($contact->supplier_business_name))<p class="rr-invoice__contact-line">{{ $contact->supplier_business_name }}</p>@endif
                            @if(! empty($sell->billing_address()))
                                <p class="rr-invoice__contact-line">{{ $sell->billing_address() }}</p>
                            @elseif(! empty($contact->contact_address))
                                <p class="rr-invoice__contact-line">{!! $contact->contact_address !!}</p>
                            @endif
                            @foreach(['mobile' => 'phone', 'alternate_number' => 'phone', 'landline' => 'phone'] as $field => $icon)
                                @if(! empty($contact->$field))
                                    <a class="rr-invoice__contact-link" href="tel:{{ $contact->$field }}">@include('partials.rr.icon', ['name' => $icon, 'size' => 16])<span class="rr-num">{{ $contact->$field }}</span></a>
                                @endif
                            @endforeach
                            @if(! empty($contact->email))
                                <a class="rr-invoice__contact-link" href="mailto:{{ $contact->email }}">@include('partials.rr.icon', ['name' => 'mail', 'size' => 16])<span class="rr-num">{{ $contact->email }}</span></a>
                            @endif
                        </div>
                    </section>

                    @if($has_shipping || $shipping_extra || count($shipping_media))
                        <section class="rr-card">
                            <div class="rr-card__head"><span class="rr-chip rr-chip--sm rr-chip--info">@include('partials.rr.icon', ['name' => 'truck', 'size' => 16])</span><h3 class="rr-card__title">@lang('sale.shipping')</h3>
                                @if(! empty($sell->shipping_status))<span class="rr-badge{{ $shipping_tone ? ' rr-badge--' . $shipping_tone : '' }}">{{ $shipping_statuses[$sell->shipping_status] ?? $sell->shipping_status }}</span>@endif
                            </div>
                            <dl class="rr-deflist">
                                @if(! empty($sell->shipping_address()) || ! empty($sell->shipping_address))
                                    <div class="rr-deflist__item"><dt>@lang('business.address')</dt><dd>{{ $sell->shipping_address() ?: $sell->shipping_address }}</dd></div>
                                @endif
                                @if(! empty($sell->delivered_to))
                                    <div class="rr-deflist__item"><dt>@lang('lang_v1.delivered_to')</dt><dd>{{ $sell->delivered_to }}</dd></div>
                                @endif
                                @if(! empty($sell->delivery_person_user->first_name))
                                    <div class="rr-deflist__item"><dt>@lang('lang_v1.delivery_person')</dt><dd>{{ trim($sell->delivery_person_user->surname . ' ' . $sell->delivery_person_user->first_name . ' ' . $sell->delivery_person_user->last_name) }}</dd></div>
                                @endif
                                @foreach($shipping_extra as $label => $value)
                                    <div class="rr-deflist__item"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @endforeach
                            </dl>
                            @if(count($shipping_media))
                                <div class="rr-invoice__media">@include('sell.partials.media_table', ['medias' => $shipping_media])</div>
                            @endif
                        </section>
                    @endif

                    @if($extra || ! empty($sales_orders) || $sell->document_path)
                        <section class="rr-card">
                            <div class="rr-card__head"><span class="rr-chip rr-chip--sm rr-chip--neutral">@include('partials.rr.icon', ['name' => 'info', 'size' => 16])</span><h3 class="rr-card__title">@lang('sell_list.inv_extra')</h3></div>
                            <dl class="rr-deflist">
                                @foreach($extra as $label => $value)
                                    <div class="rr-deflist__item"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @endforeach
                                @if(! empty($sales_orders))
                                    @foreach($sales_orders as $so)
                                        <div class="rr-deflist__item"><dt>@lang('lang_v1.sales_order')</dt><dd><span class="rr-num">{{ $so->invoice_no }}</span> · <span class="rr-num">{{ @format_date($so->transaction_date) }}</span></dd></div>
                                    @endforeach
                                @endif
                            </dl>
                            @if($sell->document_path)
                                <div class="rr-card__body rr-invoice__doc">
                                    <a href="{{ $sell->document_path }}" download="{{ $sell->document_name }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('partials.rr.icon', ['name' => 'download', 'size' => 16])@lang('purchase.download_document')</a>
                                </div>
                            @endif
                        </section>
                    @endif

                    @if($sell->additional_notes || $sell->staff_note)
                        <section class="rr-card">
                            <div class="rr-card__head"><span class="rr-chip rr-chip--sm rr-chip--warning">@include('partials.rr.icon', ['name' => 'file', 'size' => 16])</span><h3 class="rr-card__title">@lang('sell_list.inv_notes')</h3></div>
                            @if($sell->additional_notes)
                                <div class="rr-invoice__note"><p class="rr-invoice__note-label">@lang('sale.sell_note')</p><p class="rr-invoice__note-text">{!! nl2br(e($sell->additional_notes)) !!}</p></div>
                            @endif
                            @if($sell->staff_note)
                                <div class="rr-invoice__note"><p class="rr-invoice__note-label">@lang('sale.staff_note')</p><p class="rr-invoice__note-text">{!! nl2br(e($sell->staff_note)) !!}</p></div>
                            @endif
                        </section>
                    @endif
                </aside>
            </div>

            @if(count($activities))
                <details class="rr-card rr-collapse rr-invoice__activities">
                    <summary class="rr-card__head">
                        <span class="rr-chip rr-chip--sm rr-chip--neutral">@include('partials.rr.icon', ['name' => 'clock', 'size' => 16])</span>
                        <span class="rr-card__titles"><span class="rr-card__title">@lang('sell_list.inv_activities')</span></span>
                        <span class="rr-tab__count rr-num">{{ count($activities) }}</span>
                        <span class="rr-collapse__chev">@include('partials.rr.icon', ['name' => 'chevron-down', 'size' => 16])</span>
                    </summary>
                    <div class="rr-card__body rr-invoice__activity-log">
                        @includeIf('activity_log.activities', ['activity_type' => 'sell'])
                    </div>
                </details>
            @endif
        </div>

        <footer class="rr-modal__foot">
            @if($can_print)
                <a href="#" class="print-invoice rr-btn rr-btn--primary" data-href="{{ route('sell.printInvoice', [$sell->id]) }}">@include('partials.rr.icon', ['name' => 'print'])@lang('lang_v1.print_invoice')</a>
                <button type="button" class="share-invoice-image rr-btn rr-btn--secondary" data-href="{{ route('sell.printInvoice', [$sell->id]) }}" data-invoice-no="{{ $sell->invoice_no }}">@include('partials.rr.icon', ['name' => 'share'])@lang('sell_list.share_btn')</button>
            @endif
            @if(! $is_order)
                <a href="#" class="print-invoice rr-btn rr-btn--secondary" data-href="{{ route('sell.printInvoice', [$sell->id]) }}?package_slip=true">@include('partials.rr.icon', ['name' => 'box'])@lang('lang_v1.packing_slip')</a>
            @endif
            @if($can_edit && $edit_url)
                <a href="{{ $edit_url }}" target="_blank" class="rr-btn rr-btn--ghost">@include('partials.rr.icon', ['name' => 'edit'])@lang('messages.edit')</a>
            @endif
            <button type="button" class="rr-btn rr-btn--ghost rr-invoice__close" data-dismiss="modal">@lang('messages.close')</button>
        </footer>
    </div>
</div>

<script type="text/javascript">
    $(function() {
        // متغيرات وتصميم رقمي للنافذة (.view_modal مش جوه rr-page)، وبتتشال لما تتقفل عشان النوافذ القديمة
        var modal = $('#rr_invoice_{{ $sell->id }}').closest('.modal');
        modal.addClass('rr-scope rr-invoice-modal').one('hidden.bs.modal', function() {
            $(this).removeClass('rr-scope rr-invoice-modal');
        });
        __currency_convert_recursively($('#rr_invoice_{{ $sell->id }} .rr-invoice__activity-log'));
    });
</script>

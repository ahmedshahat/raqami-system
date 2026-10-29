{{--
    تصميم "رقمي" لفاتورة المبيعات (A4 / طابعة عادية). بيتختار من إعدادات تصميم الفاتورة (design = raqmi).
    مستقل عن classic: كل الأنماط جوه .rqi وبـ !important للخط لأن app.css بيفرض الخط والمقاس على كل عنصر.
    بيتطبع جوه #receipt_section وبيتصوّر بـ html2canvas (مشاركة الصورة)، فمفيش اعتماد على خطوط أو ملفات خارجية.
--}}
@php
    $rd = $receipt_details;
    $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'ur', 'he']);
    $lines = $rd->lines ?? [];
    $show_price = empty($rd->hide_price);

    // حالة الدفع (للفواتير النهائية بس، اللي فيها total_due_label)
    $pay_state = null;
    if (! empty($rd->total_due_label)) {
        $pay_state = empty($rd->total_due) ? 'paid' : (empty($rd->total_paid) ? 'due' : 'partial');
    }

    $total_quantity = $rd->total_quantity ?? rtrim(rtrim(number_format(collect($lines)->sum(fn ($l) => (float) ($l['quantity_uf'] ?? 0)), 2, '.', ','), '0'), '.');

    // تفاصيل الفاتورة الجانبية: [عنوان, قيمة, html?]
    $facts = [];
    $add = function ($label, $value, $html = false) use (&$facts) {
        if ($value !== null && $value !== '' && $value !== []) {
            $facts[] = [trim(strip_tags((string) $label), " :\t\n"), $value, $html];
        }
    };
    $add($rd->sales_person_label ?? '', $rd->sales_person ?? '');
    $add($rd->commission_agent_label ?? '', $rd->commission_agent ?? '');
    $add($rd->types_of_service_label ?? '', $rd->types_of_service ?? '');
    foreach (($rd->types_of_service_custom_fields ?? []) as $k => $v) {
        $add($k, $v);
    }
    $add($rd->table_label ?? '', $rd->table ?? '');
    $add($rd->service_staff_label ?? '', $rd->service_staff ?? '');
    foreach ([1, 2, 3, 4, 5] as $i) {
        $add($rd->{'shipping_custom_field_'.$i.'_label'} ?? '', $rd->{'shipping_custom_field_'.$i.'_value'} ?? '', true);
    }
    $add(__('restaurant.order_no'), $rd->sale_orders_invoice_no ?? '', true);
    $add(__('lang_v1.order_dates'), $rd->sale_orders_invoice_date ?? '', true);
    foreach ([1, 2, 3, 4] as $i) {
        $add($rd->{'sell_custom_field_'.$i.'_label'} ?? '', $rd->{'sell_custom_field_'.$i.'_value'} ?? '', true);
    }
    foreach (['brand' => 'repair_brand', 'device' => 'repair_device', 'model_no' => 'repair_model_no', 'serial_no' => 'repair_serial_no', 'repair_status' => 'repair_status', 'repair_warranty' => 'repair_warranty'] as $label => $value) {
        $add($rd->{$label.'_label'} ?? '', $rd->{$value} ?? '');
    }

    // ملخص الإجماليات: [عنوان, قيمة, إشارة]
    $sums = [];
    $sums[] = [$rd->subtotal_label ?? '', $rd->subtotal ?? '', ''];
    if (! empty($rd->total_exempt_uf)) $sums[] = [__('lang_v1.exempt'), $rd->total_exempt, ''];
    if (! empty($rd->shipping_charges)) $sums[] = [$rd->shipping_charges_label, $rd->shipping_charges, '+'];
    if (! empty($rd->packing_charge)) $sums[] = [$rd->packing_charge_label, $rd->packing_charge, '+'];
    if (! empty($rd->discount)) $sums[] = [$rd->discount_label, $rd->discount, '−'];
    if (! empty($rd->total_line_discount)) $sums[] = [$rd->line_discount_label, $rd->total_line_discount, '−'];
    foreach (($rd->additional_expenses ?? []) as $k => $v) $sums[] = [$k, $v, '+'];
    if (! empty($rd->reward_point_label)) $sums[] = [$rd->reward_point_label, $rd->reward_point_amount, '−'];
    if (! empty($rd->tax)) $sums[] = [$rd->tax_label, $rd->tax, '+'];
    if (($rd->round_off_amount ?? 0) > 0) $sums[] = [$rd->round_off_label, $rd->round_off, ''];

    $extra_cols = (! empty($rd->discounted_unit_price_label) ? 1 : 0) + (! empty($rd->item_discount_label) ? 1 : 0);
@endphp

<div class="rqi" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<style>
    .rqi { --rqi-brand: #22177A; --rqi-brand-2: #3425B8; --rqi-tint: #ECEAFB; --rqi-ink: #1B1F2A; --rqi-muted: #5B6475; --rqi-line: #DDE1E8; --rqi-soft: #F6F7FA;
        color: var(--rqi-ink); background: #fff; font-family: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, sans-serif !important; font-size: 12.5px !important; line-height: 1.55;
        max-width: 210mm; margin: 0 auto; padding: 0; text-align: start; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    /* الثيم بيفرض مقاس خط على كل عنصر، فكل حاجة جوه الفاتورة بتورث المقاس إلا اللي ليه قاعدة هنا */
    .rqi * { box-sizing: border-box; font-family: inherit !important; font-size: inherit !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .rqi p { margin: 0; }
    .rqi-num { direction: ltr; unicode-bidi: isolate; font-variant-numeric: tabular-nums; }
    .rqi-bar { height: 6px; background: linear-gradient(90deg, var(--rqi-brand), var(--rqi-brand-2)); border-radius: 3px; margin-bottom: 18px; }

    /* الرأس */
    .rqi-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--rqi-line); }
    .rqi-brand { display: flex; align-items: flex-start; gap: 14px; min-width: 0; }
    .rqi-logo { max-height: 72px; max-width: 150px; width: auto; object-fit: contain; }
    .rqi-biz h1 { margin: 0 0 4px; font-size: 20px !important; line-height: 1.3; font-weight: 700; color: var(--rqi-brand); }
    .rqi-biz p, .rqi-biz div { color: var(--rqi-muted); font-size: 11px !important; line-height: 1.6; }
    .rqi-biz .rqi-taxid { color: var(--rqi-ink); font-weight: 600; }
    .rqi-doc { text-align: end; flex: 0 0 auto; }
    .rqi-doc__title { margin: 0 0 6px; font-size: 22px !important; line-height: 1.2; font-weight: 700; color: var(--rqi-ink); }
    .rqi-doc__title * { font-size: inherit !important; margin: 0; }
    .rqi-doc__no { display: inline-block; padding: 4px 12px; border-radius: 999px; background: var(--rqi-tint); color: var(--rqi-brand); font-weight: 700; font-size: 13px !important; }
    .rqi-doc dl { margin: 10px 0 0; display: grid; grid-template-columns: auto auto; justify-content: end; gap: 2px 12px; font-size: 11.5px !important; }
    .rqi-doc dt { color: var(--rqi-muted); font-weight: 400; }
    .rqi-doc dd { margin: 0; font-weight: 600; }
    .rqi-letterhead { width: 100%; display: block; margin-bottom: 12px; }
    .rqi-badge { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; padding: 3px 10px; border-radius: 999px; font-size: 11px !important; font-weight: 700; border: 1px solid; }
    .rqi-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .rqi-badge--paid { color: #0A6252; background: #E0F3EF; border-color: #8CCABD; }
    .rqi-badge--partial { color: #0A5A82; background: #E1F0F8; border-color: #8DC1DD; }
    .rqi-badge--due { color: #7C4A00; background: #FFF1D3; border-color: #E3B75F; }

    /* بطاقات العميل والتفاصيل */
    .rqi-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 16px 0; }
    .rqi-card { border: 1px solid var(--rqi-line); border-radius: 10px; padding: 10px 14px; background: var(--rqi-soft); min-width: 0; }
    .rqi-card__label { margin: 0 0 4px; color: var(--rqi-brand); font-size: 10.5px !important; font-weight: 700; letter-spacing: .02em; }
    .rqi-card__body { font-size: 12px !important; line-height: 1.6; overflow-wrap: anywhere; }
    .rqi-card__body b, .rqi-card__body strong { font-weight: 600; }
    .rqi-facts { display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; margin: 0; font-size: 11.5px !important; }
    .rqi-facts dt { color: var(--rqi-muted); font-weight: 400; }
    .rqi-facts dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }

    /* جدول الأصناف */
    .rqi-items { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--rqi-line); border-radius: 10px; overflow: hidden; margin: 0; }
    .rqi-items th { background: var(--rqi-brand); color: #fff; font-weight: 600; font-size: 11px !important; padding: 8px 10px; text-align: start !important; white-space: nowrap; border: 0; }
    .rqi-items td { padding: 8px 10px; border-top: 1px solid var(--rqi-line); vertical-align: top; font-size: 12px !important; }
    .rqi-items tbody tr:first-child td { border-top: 0; }
    .rqi-items tbody tr:nth-child(even) td { background: var(--rqi-soft); }
    .rqi-items .rqi-c { text-align: center !important; width: 34px; color: var(--rqi-muted); }
    .rqi-items .rqi-n { text-align: end !important; white-space: nowrap; }
    .rqi-items .rqi-strong { font-weight: 700; }
    .rqi-item__name { font-weight: 600; }
    .rqi-item__meta { display: block; color: var(--rqi-muted); font-size: 10.5px !important; line-height: 1.5; margin-top: 2px; }
    .rqi-item__meta * { font-size: inherit !important; }
    .rqi-item__img { width: 36px; height: 36px; object-fit: cover; border-radius: 6px; float: inline-start; margin-inline-end: 8px; }
    .rqi-mod td { background: #fff !important; color: var(--rqi-muted); font-size: 11px !important; padding-top: 4px; padding-bottom: 4px; border-top: 0; }
    .rqi-mod .rqi-item__name { font-weight: 400; padding-inline-start: 12px; }
    .rqi-mod .rqi-item__name::before { content: '+ '; }

    /* الدفعات والإجماليات */
    .rqi-foot { display: grid; grid-template-columns: 1fr 300px; gap: 16px; margin-top: 16px; align-items: start; }
    .rqi-pay { min-width: 0; }
    .rqi-pay table { width: 100%; border-collapse: collapse; font-size: 11.5px !important; }
    .rqi-pay td { padding: 5px 0; border-bottom: 1px dashed var(--rqi-line); }
    .rqi-pay td + td { text-align: end; padding-inline-start: 12px; }
    .rqi-pay .rqi-muted { color: var(--rqi-muted); }
    .rqi-pay__totals { margin-top: 10px; display: grid; grid-template-columns: 1fr auto; gap: 4px 12px; font-size: 12px !important; }
    .rqi-pay__totals dt { color: var(--rqi-muted); font-weight: 400; }
    .rqi-pay__totals dd { margin: 0; font-weight: 700; text-align: end; }
    .rqi-pay__totals .rqi-due { color: #AE231B; }
    .rqi-totals { border: 1px solid var(--rqi-line); border-radius: 10px; overflow: hidden; }
    .rqi-totals dl { margin: 0; padding: 8px 14px; display: grid; grid-template-columns: 1fr auto; gap: 4px 12px; font-size: 12px !important; }
    .rqi-totals dt { color: var(--rqi-muted); font-weight: 400; }
    .rqi-totals dd { margin: 0; text-align: end; font-weight: 600; }
    .rqi-sign { color: var(--rqi-muted); font-weight: 400; margin-inline-end: 2px; }
    .rqi-grand { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 14px; background: var(--rqi-brand); color: #fff; }
    .rqi-grand span { font-size: 13px !important; font-weight: 600; }
    .rqi-grand strong { font-size: 19px !important; font-weight: 700; }
    .rqi-words { padding: 6px 14px 8px; background: var(--rqi-tint); color: var(--rqi-brand); font-size: 10.5px !important; }

    .rqi-taxes { margin-top: 14px; width: 100%; border-collapse: collapse; font-size: 11px !important; }
    .rqi-taxes caption { caption-side: top; text-align: start; color: var(--rqi-brand); font-weight: 700; font-size: 10.5px !important; padding-bottom: 4px; }
    .rqi-taxes td { border: 1px solid var(--rqi-line); padding: 4px 10px; }
    .rqi-taxes td + td { text-align: end; }
    .rqi-note { margin-top: 14px; padding: 10px 14px; border-inline-start: 3px solid var(--rqi-brand); background: var(--rqi-soft); font-size: 11.5px !important; border-radius: 4px; }
    .rqi-note b { display: block; color: var(--rqi-brand); font-size: 10.5px !important; margin-bottom: 2px; }

    /* التذييل */
    .rqi-end { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-top: 22px; padding-top: 12px; border-top: 1px solid var(--rqi-line); }
    .rqi-end__text { color: var(--rqi-muted); font-size: 11px !important; min-width: 0; }
    .rqi-end__text * { font-size: inherit !important; }
    .rqi-codes { display: flex; align-items: flex-end; gap: 12px; flex: 0 0 auto; }
    .rqi-codes img { display: block; }
    .rqi-qr { width: 92px; height: 92px; }
    .rqi-thanks { margin-top: 10px; text-align: center; color: var(--rqi-brand); font-weight: 600; font-size: 11px !important; }

    @media print {
        @page { size: A4 portrait; margin: 10mm; }
        .rqi { max-width: none; }
        .rqi-items tr, .rqi-foot, .rqi-end { break-inside: avoid; page-break-inside: avoid; }
        .rqi-items thead { display: table-header-group; }
    }
</style>

    @if(! empty($rd->letter_head))
        <img class="rqi-letterhead" src="{{ $rd->letter_head }}" alt="">
    @else
        <div class="rqi-bar"></div>
    @endif

    {{-- ─── الرأس: النشاط + بيانات المستند ─── --}}
    <header class="rqi-head">
        <div class="rqi-brand">
            @if(empty($rd->letter_head))
                @if(! empty($rd->logo))
                    <img class="rqi-logo" src="{{ $rd->logo }}" alt="">
                @endif
                <div class="rqi-biz">
                    @if(! empty($rd->display_name))<h1>{{ $rd->display_name }}</h1>@endif
                    @if(! empty($rd->header_text))<div>{!! $rd->header_text !!}</div>@endif
                    @if(! empty($rd->address))<p>{!! $rd->address !!}</p>@endif
                    @if(! empty($rd->contact) || ! empty($rd->website))
                        <p>{!! $rd->contact ?? '' !!}@if(! empty($rd->contact) && ! empty($rd->website)) · @endif{{ $rd->website ?? '' }}</p>
                    @endif
                    @if(! empty($rd->location_custom_fields))<p>{{ $rd->location_custom_fields }}</p>@endif
                    @foreach([1, 2, 3, 4, 5] as $i)
                        @if(! empty($rd->{'sub_heading_line'.$i}))<p>{{ $rd->{'sub_heading_line'.$i} }}</p>@endif
                    @endforeach
                    @if(! empty($rd->tax_info1))<p class="rqi-taxid">{{ $rd->tax_label1 }} <span class="rqi-num">{{ $rd->tax_info1 }}</span></p>@endif
                    @if(! empty($rd->tax_info2))<p class="rqi-taxid">{{ $rd->tax_label2 }} <span class="rqi-num">{{ $rd->tax_info2 }}</span></p>@endif
                </div>
            @endif
        </div>

        <div class="rqi-doc">
            @if(! empty($rd->invoice_heading))<div class="rqi-doc__title">{!! $rd->invoice_heading !!}</div>@endif
            <span class="rqi-doc__no">@if(! empty($rd->invoice_no_prefix))<bdi>{{ trim(strip_tags($rd->invoice_no_prefix)) }}</bdi> @endif<span class="rqi-num">{{ $rd->invoice_no }}</span></span>
            <dl>
                <dt>{{ trim(strip_tags($rd->date_label ?? ''), ' :') }}</dt><dd class="rqi-num">{{ $rd->invoice_date }}</dd>
                @if(! empty($rd->due_date_label) && ! empty($rd->due_date))
                    <dt>{{ trim(strip_tags($rd->due_date_label), ' :') }}</dt><dd class="rqi-num">{{ $rd->due_date }}</dd>
                @endif
            </dl>
            @if($pay_state && $show_price)
                <span class="rqi-badge rqi-badge--{{ $pay_state }}">{{ __('lang_v1.'.$pay_state) }}</span>
            @endif
        </div>
    </header>

    {{-- ─── العميل + التفاصيل ─── --}}
    @if(! empty($rd->customer_info) || ! empty($facts))
        <section class="rqi-parties">
            <div class="rqi-card">
                @if(! empty($rd->customer_info))
                    <p class="rqi-card__label">{{ trim(strip_tags($rd->customer_label ?? ''), ' :') }}</p>
                    <div class="rqi-card__body">
                        {!! $rd->customer_info !!}
                        @if(! empty($rd->client_id_label))<br><b>{{ $rd->client_id_label }}</b> <span class="rqi-num">{{ $rd->client_id }}</span>@endif
                        @if(! empty($rd->customer_tax_label))<br><b>{{ $rd->customer_tax_label }}</b> <span class="rqi-num">{{ $rd->customer_tax_number }}</span>@endif
                        @if(! empty($rd->customer_custom_fields))<br>{!! $rd->customer_custom_fields !!}@endif
                        @if(! empty($rd->customer_rp_label))<br><b>{{ $rd->customer_rp_label }}</b> <span class="rqi-num">{{ $rd->customer_total_rp }}</span>@endif
                    </div>
                @endif
            </div>
            @if(! empty($facts))
                <div class="rqi-card">
                    <p class="rqi-card__label">@lang('sell_list.inv_extra')</p>
                    <dl class="rqi-facts">
                        @foreach($facts as $fact)
                            <dt>{{ $fact[0] }}</dt><dd>@if($fact[2]){!! $fact[1] !!}@else{{ $fact[1] }}@endif</dd>
                        @endforeach
                    </dl>
                </div>
            @endif
        </section>
    @else
        <div style="height: 16px"></div>
    @endif

    @includeIf('sale_pos.receipts.partial.common_repair_invoice')

    {{-- ─── الأصناف ─── --}}
    <table class="rqi-items">
        <thead>
            <tr>
                <th class="rqi-c">#</th>
                <th>{{ $rd->table_product_label }}</th>
                <th class="rqi-n">{{ $rd->table_qty_label }}</th>
                @if($show_price)
                    <th class="rqi-n">{{ $rd->table_unit_price_label }}</th>
                    @if(! empty($rd->discounted_unit_price_label))<th class="rqi-n">{{ $rd->discounted_unit_price_label }}</th>@endif
                    @if(! empty($rd->item_discount_label))<th class="rqi-n">{{ $rd->item_discount_label }}</th>@endif
                    <th class="rqi-n">{{ $rd->table_subtotal_label }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $line)
                <tr>
                    <td class="rqi-c rqi-num">{{ $loop->iteration }}</td>
                    <td>
                        @if(! empty($line['image']))<img class="rqi-item__img" src="{{ $line['image'] }}" alt="">@endif
                        <span class="rqi-item__name">{{ $line['name'] }} {{ $line['product_variation'] ?? '' }} {{ $line['variation'] ?? '' }}</span>
                        @php
                            $meta = array_filter([
                                $line['sub_sku'] ?? null, $line['brand'] ?? null, $line['cat_code'] ?? null, $line['product_custom_fields'] ?? null,
                                ! empty($line['lot_number']) ? ($line['lot_number_label'] ?? '').': '.$line['lot_number'] : null,
                                ! empty($line['product_expiry']) ? ($line['product_expiry_label'] ?? '').': '.$line['product_expiry'] : null,
                            ]);
                        @endphp
                        @if(! empty($meta))<span class="rqi-item__meta">{{ implode(' · ', $meta) }}</span>@endif
                        @if(! empty($line['product_description']))<span class="rqi-item__meta">{!! $line['product_description'] !!}</span>@endif
                        @if(! empty($line['sell_line_note']))<span class="rqi-item__meta">{!! $line['sell_line_note'] !!}</span>@endif
                        @if(! empty($line['warranty_name']))
                            <span class="rqi-item__meta">{{ $line['warranty_name'] }}@if(! empty($line['warranty_exp_date'])) - {{ @format_date($line['warranty_exp_date']) }}@endif {{ $line['warranty_description'] ?? '' }}</span>
                        @endif
                        @if($rd->show_base_unit_details && $line['quantity'] && $line['base_unit_multiplier'] !== 1)
                            <span class="rqi-item__meta rqi-num">1 {{ $line['units'] }} = {{ $line['base_unit_multiplier'] }} {{ $line['base_unit_name'] }} · {{ $line['base_unit_price'] }} × {{ $line['orig_quantity'] }}</span>
                        @endif
                    </td>
                    <td class="rqi-n"><span class="rqi-num">{{ $line['quantity'] }}</span> {{ $line['units'] }}</td>
                    @if($show_price)
                        <td class="rqi-n rqi-num">{{ $line['unit_price_before_discount'] }}</td>
                        @if(! empty($rd->discounted_unit_price_label))<td class="rqi-n rqi-num">{{ $line['unit_price_inc_tax'] }}</td>@endif
                        @if(! empty($rd->item_discount_label))
                            <td class="rqi-n"><span class="rqi-num">{{ $line['total_line_discount'] ?? '0.00' }}</span>@if(! empty($line['line_discount_percent'])) <span class="rqi-num">({{ $line['line_discount_percent'] }}%)</span>@endif</td>
                        @endif
                        <td class="rqi-n rqi-num rqi-strong">{{ $line['line_total'] }}</td>
                    @endif
                </tr>
                @foreach(($line['modifiers'] ?? []) as $modifier)
                    <tr class="rqi-mod">
                        <td></td>
                        <td><span class="rqi-item__name">{{ $modifier['name'] }} {{ $modifier['variation'] ?? '' }}</span>@if(! empty($modifier['sell_line_note'])) ({!! $modifier['sell_line_note'] !!})@endif</td>
                        <td class="rqi-n"><span class="rqi-num">{{ $modifier['quantity'] }}</span> {{ $modifier['units'] }}</td>
                        @if($show_price)
                            <td class="rqi-n rqi-num">{{ $modifier['unit_price_inc_tax'] }}</td>
                            @if(! empty($rd->discounted_unit_price_label))<td class="rqi-n rqi-num">{{ $modifier['unit_price_exc_tax'] }}</td>@endif
                            @if(! empty($rd->item_discount_label))<td></td>@endif
                            <td class="rqi-n rqi-num">{{ $modifier['line_total'] }}</td>
                        @endif
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="{{ $show_price ? 5 + $extra_cols : 3 }}">&nbsp;</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ─── الدفعات + الإجماليات ─── --}}
    @if($show_price)
        <section class="rqi-foot">
            <div class="rqi-pay">
                @if(! empty($rd->payments))
                    <p class="rqi-card__label">@lang('sell_list.inv_payments')</p>
                    <table>
                        @foreach($rd->payments as $payment)
                            <tr>
                                <td>{{ $payment['method'] }} <span class="rqi-muted rqi-num">{{ $payment['date'] }}</span></td>
                                <td class="rqi-num">{{ $payment['amount'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
                <dl class="rqi-pay__totals">
                    <dt>{{ __('sell_list.rqi_total_qty') }}</dt><dd class="rqi-num">{{ $total_quantity }}</dd>
                    @if(! empty($rd->total_items_label))<dt>{{ trim(strip_tags($rd->total_items_label), ' :') }}</dt><dd class="rqi-num">{{ $rd->total_items }}</dd>@endif
                    @if(! empty($rd->total_paid))<dt>{{ trim(strip_tags($rd->total_paid_label), ' :') }}</dt><dd class="rqi-num">{{ $rd->total_paid }}</dd>@endif
                    @if(! empty($rd->total_due) && ! empty($rd->total_due_label))<dt>{{ trim(strip_tags($rd->total_due_label), ' :') }}</dt><dd class="rqi-num rqi-due">{{ $rd->total_due }}</dd>@endif
                    @if(! empty($rd->total_previous_due))<dt>{{ trim(strip_tags($rd->total_previous_due_label), ' :') }}</dt><dd class="rqi-num">{{ $rd->total_previous_due }}</dd>@endif
                    @if(! empty($rd->all_due))<dt>{{ trim(strip_tags($rd->all_bal_label), ' :') }}</dt><dd class="rqi-num rqi-due">{{ $rd->all_due }}</dd>@endif
                </dl>
            </div>

            <div class="rqi-totals">
                <dl>
                    @foreach($sums as $sum)
                        <dt>{{ trim(strip_tags($sum[0]), ' :') }}</dt>
                        <dd>@if($sum[2])<span class="rqi-sign">{{ $sum[2] }}</span>@endif<span class="rqi-num">{{ $sum[1] }}</span></dd>
                    @endforeach
                </dl>
                <div class="rqi-grand"><span>{{ trim(strip_tags($rd->total_label), ' :') }}</span><strong class="rqi-num">{{ $rd->total }}</strong></div>
                @if(! empty($rd->total_in_words))<div class="rqi-words">{{ $rd->total_in_words }}</div>@endif
            </div>
        </section>

        @if(! empty($rd->tax_summary_label) && ! empty($rd->taxes))
            <table class="rqi-taxes">
                <caption>{{ $rd->tax_summary_label }}</caption>
                @foreach($rd->taxes as $key => $val)
                    <tr><td>{{ $key }}</td><td class="rqi-num">{{ $val }}</td></tr>
                @endforeach
            </table>
        @endif
    @endif

    @if(! empty($rd->additional_notes))
        <div class="rqi-note"><b>@lang('sell_list.inv_notes')</b>{!! nl2br(e(strip_tags($rd->additional_notes))) !!}</div>
    @endif

    {{-- ─── التذييل: نص الفاتورة + الباركود / QR ─── --}}
    @if(! empty($rd->footer_text) || $rd->show_barcode || ($rd->show_qr_code && ! empty($rd->qr_code_text)))
        <footer class="rqi-end">
            <div class="rqi-end__text">{!! $rd->footer_text ?? '' !!}</div>
            <div class="rqi-codes">
                @if($rd->show_barcode)
                    <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($rd->invoice_no, 'C128', 1.6, 34, [27, 31, 42], true) }}" alt="">
                @endif
                @if($rd->show_qr_code && ! empty($rd->qr_code_text))
                    <img class="rqi-qr" src="data:image/png;base64,{{ DNS2D::getBarcodePNG($rd->qr_code_text, 'QRCODE', 3, 3, [27, 31, 42]) }}" alt="">
                @endif
            </div>
        </footer>
    @endif
    @if(empty($rd->footer_text))<p class="rqi-thanks">@lang('sell_list.rqi_thanks')</p>@endif
</div>

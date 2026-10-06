@php
    $customerName = $quote->customer?->supplier_business_name ?: $quote->customer?->name;
    $companyAddress = collect([$businessLocation?->landmark, $businessLocation?->city, $businessLocation?->state, $businessLocation?->country])->filter()->implode('، ');
    $logoSource = null;
    if ($business->logo) {
        $logoFile = public_path('uploads/business_logos/'.$business->logo);
        if (is_file($logoFile)) {
            $extension = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
            $mime = match ($extension) {'jpg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'webp' => 'image/webp', default => 'image/png'};
            $logoSource = $pdfMode ? 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoFile)) : asset('uploads/business_logos/'.$business->logo);
        }
    }
@endphp
<!doctype html><html lang="{{ app()->getLocale() }}" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $quote->number }}</title><style>
@if(empty($pdfMode))@page{size:A4 portrait;margin:8mm}@endif
body{direction:rtl;margin:0;color:#26364d}.accent{height:5px;background:#1769d2;margin-bottom:5mm}.header,.details,.items,.signatures,.footer{width:100%;border-collapse:collapse;direction:rtl}
.header{border-bottom:1px solid #dce5f0;margin-bottom:5mm}.header td{padding:4px;vertical-align:middle}.logo{width:20mm}.logo img{max-width:18mm;max-height:18mm}.company{color:#123d91}.company-data{text-align:left;color:#66758b}
.title{text-align:center;margin:5mm 0}.title h1{margin:0;color:#172f55}.title p{margin:2mm 0;color:#1769d2}.title .status{color:#60748e}
.details{border:1px solid #dfe7f0;margin-bottom:5mm;table-layout:fixed}.details td{width:50%;padding:8px 10px;border-bottom:1px solid #e8eef5;vertical-align:top}.details .print-label{display:block;color:#7d8da2}.details .print-value{display:block;color:#23436d}
.items{table-layout:fixed}.items th,.items td{border:1px solid #d7e0eb}.items th{background:#1769d2;color:#fff;text-align:center}.items td{text-align:center}.items .description{text-align:right}.items .code{width:12%}.items .description{width:37%}.items .unit{width:12%}.items .quantity{width:12%}.items .price{width:13%}.items .amount{width:14%}.items tfoot td{background:#eafaf4;color:#087453}
.notes{margin-top:6mm;padding:10px;border:1px solid #dfe7f0;color:#4b6079;white-space:pre-line}.notes h2{margin:0 0 5px;color:#173b70}.signatures{margin-top:8mm}.signatures td{width:50%;padding:9px;text-align:center;vertical-align:top;border:1px solid #e4ebf3}.signatures .line{height:13mm;border-bottom:1px solid #bfcbd9;margin:3mm 8mm}
.footer{margin-top:5mm;border-top:1px solid #e3eaf2;color:#8a97a8}.footer td:last-child{text-align:left;direction:ltr}
.toolbar{display:flex;justify-content:center;padding:12px;background:#eef4fc}.toolbar button{padding:9px 18px;border:0;border-radius:9px;background:#1769d2;color:#fff;cursor:pointer}
@media print{.toolbar{display:none}}
@include('construction::partials.print_typography')
</style></head><body>
@if(!$pdfMode)<div class="toolbar"><button type="button" onclick="window.print()">@lang('construction::lang.print')</button></div>@endif
<div class="accent"></div><table class="header"><tr>@if($logoSource)<td class="logo"><img src="{{ $logoSource }}" alt=""></td>@endif<td><div class="company print-company-name">{{ $business->name }}</div>@if($businessLocation?->name)<div>{{ $businessLocation->name }}</div>@endif</td><td class="company-data print-company-contact">@if($companyAddress)<div>{{ $companyAddress }}</div>@endif @if($businessLocation?->mobile)<div>{{ $businessLocation->mobile }}</div>@endif @if($business->tax_number_1)<div>@lang('construction::lang.tax_registration_number'): {{ $business->tax_number_1 }}</div>@endif</td></tr></table>
<div class="title"><h1 class="print-document-title">@lang('construction::lang.quote_print_title')</h1><p class="print-document-reference">{{ $quote->number }}</p><div class="status print-value">@lang('construction::lang.quote_status_'.($quote->project_id ? 'converted' : $quote->status))</div></div>
<table class="details print-keep-together"><tr><td><div class="print-label">@lang('construction::lang.customer')</div><div class="print-value">{{ $customerName }}</div></td><td><div class="print-label">@lang('construction::lang.quote_date')</div><div class="print-value">{{ @format_date($quote->quote_date) }}</div></td></tr><tr><td><div class="print-label">@lang('construction::lang.quote_title')</div><div class="print-value">{{ $quote->title }}</div></td><td><div class="print-label">@lang('construction::lang.quote_validity_days')</div><div class="print-value">{{ $quote->validity_days }} @lang('construction::lang.days')</div></td></tr></table>
<table class="items print-table"><thead><tr><th class="code">@lang('construction::lang.boq_code')</th><th class="description">@lang('construction::lang.boq_description')</th><th class="unit">@lang('construction::lang.unit')</th><th class="quantity">@lang('construction::lang.quantity')</th><th class="price">@lang('construction::lang.unit_price')</th><th class="amount">@lang('construction::lang.total')</th></tr></thead><tbody>@foreach($quote->items as $item)<tr><td>{{ $item->code }}</td><td class="description">{{ $item->description }}</td><td>{{ $item->unit }}</td><td class="print-number">{{ @format_quantity((float)$item->quantity) }}</td><td class="print-number">@include('construction::partials.money', ['value' => $item->unit_price])</td><td class="print-number">@include('construction::partials.money', ['value' => $item->total])</td></tr>@endforeach</tbody><tfoot><tr class="print-total"><td colspan="5">@lang('construction::lang.quote_total')</td><td class="print-number">@include('construction::partials.money', ['value' => $quote->total])</td></tr></tfoot></table>
@if($quote->notes)<section class="notes print-keep-together"><h2 class="print-section-title">@lang('construction::lang.quote_notes')</h2><div class="print-body-text">{{ $quote->notes }}</div></section>@endif
<table class="signatures print-signature print-keep-together"><tr><td>@lang('construction::lang.contractor_approval')<div class="line"></div>@lang('construction::lang.name_and_signature')</td><td>@lang('construction::lang.customer_approval')<div class="line"></div>@lang('construction::lang.name_and_signature')</td></tr></table>
<table class="footer print-footer"><tr><td>{{ $business->name }}</td><td>{{ $quote->number }}</td></tr></table>
@if($autoPrint)<script>window.addEventListener('load',function(){document.fonts.ready.then(function(){window.print()})})</script>@endif
</body></html>

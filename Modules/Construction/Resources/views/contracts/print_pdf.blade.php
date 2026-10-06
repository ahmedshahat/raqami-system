@php
    $customerName = $project->customer->supplier_business_name ?: $project->customer->name;
    $companyAddress = collect([$businessLocation?->landmark, $businessLocation?->city, $businessLocation?->state, $businessLocation?->zip_code, $businessLocation?->country])->filter(fn ($value) => filled($value))->implode('، ');
    $companyPhone = $businessLocation?->mobile ?: $businessLocation?->alternate_number;
    $companyEmail = $businessLocation?->email;
    $taxNumber = $business?->tax_number_1;
    $logoSource = null;
    if (!empty($business?->logo)) {
        $logoFile = public_path('uploads/business_logos/'.$business->logo);
        if (is_file($logoFile)) {
            $extension = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml',
                'webp' => 'image/webp', default => 'image/png',
            };
            $logoSource = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoFile));
        }
    }
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>
@if(empty($pdfMode))@page{size:A4 portrait;margin:8mm}@endif
body{direction:rtl;margin:0;color:#26364d}
.print-toolbar{display:flex;justify-content:center;padding:12px;background:#eef4fc}.print-toolbar button{padding:9px 18px;border:0;border-radius:9px;background:#1769d2;color:#fff;cursor:pointer}
@media print{.print-toolbar{display:none}}
table{direction:rtl;width:100%;border-collapse:collapse}
.accent{height:5px;margin-bottom:6mm;background:#1769d2}
.header{border-bottom:1px solid #dce5f0;margin-bottom:4mm}.header td{padding:0 0 8px;vertical-align:middle}
.logo{width:18mm;padding-left:5mm!important}.logo img{max-width:17mm;max-height:17mm}
.company-name{color:#123d91}.branch{color:#708096;font-size:13px}
.company-data{width:48%;color:#57677e;text-align:left}.company-data b{color:#243b5c}
.title{text-align:center;margin:4mm 0 5mm}.reference{color:#1769d2}.title h1{margin:1mm 0;color:#172f55}
.project-line{color:#8290a4;font-size:13px}.document-badge--draft{color:#b42318}.document-badge--official{color:#06765a}
.section{margin-bottom:3mm;border:1px solid #dfe7f0}.section-title{padding:7px 10px;border-bottom:1px solid #dfe7f0;background:#f3f7fc;color:#173b70}
.info{table-layout:fixed}.info td{padding:5px 9px;border-bottom:1px solid #e9eef5;vertical-align:top}.info tr:last-child td{border-bottom:0}
.label{width:18%;background:#fafcff;color:#7a899d}.value{width:32%;color:#263b5b}.value--primary{color:#0b66cf}
.content-card{padding:8px 11px;color:#46566d;white-space:pre-line}.content-card--subject{color:#203a60}
.signatures{margin-top:1mm}.signatures td{width:50%;padding:3px 8mm;text-align:center;vertical-align:top}
.party{height:8mm;color:#1f3a61}.signature-line{padding-top:3px;color:#6f7e92}.signature-hints{color:#8a97a8}
.footer{margin-top:1mm;padding-top:1mm;border-top:1px solid #e3eaf2;color:#8a97a8}.footer td:last-child{text-align:left;direction:ltr}
.annex{page-break-before:always}.annex-head{margin-bottom:5mm;padding-bottom:4mm;border-bottom:2px solid #1769d2}.annex-subtitle,.annex-reference{color:#79889c}
.boq{table-layout:fixed}.boq th,.boq td{border:1px solid #d7e0eb}.boq th{background:#1769d2;color:#fff;text-align:center}
.boq .code{width:11%}.boq .description{width:29%}.boq .unit{width:10%}.boq .quantity{width:15%}.boq .price{width:17%}.boq .amount{width:18%}
.section-row td{background:#eaf3ff;color:#17477f}.total td{border-color:#b9dfd2;background:#eafaf4;color:#076a50}
@include('construction::partials.print_typography')
</style></head>
<body>
@if(empty($pdfMode))<div class="print-toolbar"><button type="button" onclick="window.print()">@lang('construction::lang.print')</button></div>@endif
<div class="accent"></div>
<table class="header"><tr>
    @if($logoSource)<td class="logo"><img src="{{ $logoSource }}" alt=""></td>@endif
    <td><div class="company-name print-company-name">{{ $business?->name ?: __('construction::lang.company') }}</div>@if(!empty($businessLocation?->name))<div class="branch">{{ $businessLocation->name }}</div>@endif</td>
    @if($companyAddress || $companyPhone || $companyEmail || $taxNumber)
    <td class="company-data print-company-contact">
        @if($companyAddress)<div><b>@lang('construction::lang.company_address'):</b> {{ $companyAddress }}</div>@endif
        @if($companyPhone)<div><b>@lang('construction::lang.company_phone'):</b> {{ $companyPhone }}</div>@endif
        @if($companyEmail)<div><b>@lang('construction::lang.company_email'):</b> {{ $companyEmail }}</div>@endif
        @if($taxNumber)<div><b>@lang('construction::lang.tax_registration_number'):</b> {{ $taxNumber }}</div>@endif
    </td>
    @endif
</tr></table>
<div class="title">
    <div class="reference print-document-reference">@lang('construction::lang.contract_reference') {{ $contract->contract_number }}</div>
    <h1 class="print-document-title">@lang('construction::lang.construction_contract')</h1>
    <div class="project-line">{{ $project->name }} — {{ $project->code }}</div>
    @if($project->quote)<div class="project-line print-document-reference">@lang('construction::lang.quote_number'): {{ $project->quote->number }}</div>@endif
    @if($contract->status === 'draft')<div class="document-badge--draft">@lang('construction::lang.draft_not_for_signature')</div>@else<div class="document-badge--official">@lang('construction::lang.official_contract_copy')</div>@endif
</div>
<div class="section print-keep-together"><div class="section-title print-section-title">@lang('construction::lang.contract_information')</div><table class="info">
    <tr><td class="label print-label">@lang('construction::lang.contract_number')</td><td class="value print-value">{{ $contract->contract_number }}</td>@if($contract->signed_at)<td class="label print-label">@lang('construction::lang.signed_at')</td><td class="value print-value">{{ @format_date($contract->signed_at) }}</td>@endif</tr>
    <tr><td class="label print-label">@lang('construction::lang.contract_type')</td><td class="value print-value">@lang('construction::lang.'.$contract->contract_type)</td><td class="label print-label">@lang('construction::lang.linked_boq')</td><td class="value print-value">{{ $contract->boqVersion?->name ?: '—' }}</td></tr>
    <tr><td class="label print-label">@lang('construction::lang.original_value')</td><td class="value print-value value--primary print-number">@include('construction::partials.money', ['value' => $contract->original_value])</td><td class="label print-label">@lang('construction::lang.status')</td><td class="value print-value">@lang('construction::lang.'.$contract->status)</td></tr>
</table></div>
<div class="section print-keep-together"><div class="section-title print-section-title">@lang('construction::lang.project_information')</div><table class="info">
    <tr><td class="label print-label">@lang('construction::lang.project')</td><td class="value print-value">{{ $project->name }}</td><td class="label print-label">@lang('construction::lang.project_code')</td><td class="value print-value">{{ $project->code }}</td></tr>
    <tr><td class="label print-label">@lang('construction::lang.customer')</td><td class="value print-value">{{ $customerName }}</td><td class="label print-label">@lang('construction::lang.location')</td><td class="value print-value">{{ $project->location ?: '—' }}</td></tr>
</table></div>
<div class="section print-keep-together"><div class="section-title print-section-title">@lang('construction::lang.financial_terms')</div><table class="info">
    <tr><td class="label print-label">@lang('construction::lang.advance_payment_value')</td><td class="value print-value print-number">@include('construction::partials.money', ['value' => $contract->advance_payment_value])</td><td class="label print-label">@lang('construction::lang.retention_percent')</td><td class="value print-value print-number">{{ @num_format((float)$contract->retention_percent) }}%</td></tr>
    <tr><td class="label print-label">@lang('construction::lang.performance_bond_value')</td><td class="value print-value print-number">@if((float)$contract->performance_bond_value > 0)@include('construction::partials.money', ['value' => $contract->performance_bond_value])@else—@endif</td><td class="label print-label">@lang('construction::lang.warranty_months')</td><td class="value print-value">{{ $contract->warranty_months ?: '—' }}</td></tr>
    <tr><td class="label print-label">@lang('construction::lang.payment_terms_days')</td><td class="value print-value">{{ $contract->payment_terms_days ?: '—' }}</td><td class="label print-label">@lang('construction::lang.total_contract_value')</td><td class="value print-value value--primary print-number">@include('construction::partials.money', ['value' => $contract->original_value])</td></tr>
</table></div>
<div class="section print-keep-together"><div class="section-title print-section-title">@lang('construction::lang.contract_subject')</div><div class="content-card content-card--subject print-body-text">{{ $contract->title ?: __('construction::lang.construction_contract').' - '.$project->name }}</div></div>
<div class="section"><div class="section-title print-section-title">@lang('construction::lang.contract_terms')</div><div class="content-card print-body-text">{!! nl2br(e($contract->notes ?: __('construction::lang.no_additional_terms'))) !!}</div></div>
<table class="signatures print-signature print-keep-together"><tr>
    <td><div class="party">@lang('construction::lang.first_party_contractor')<br>{{ $business?->name }}</div><div>____________________________</div><div class="signature-line">@lang('construction::lang.name_and_signature')</div><div class="signature-hints">@lang('construction::lang.signed_at') &nbsp;&nbsp;&nbsp; @lang('construction::lang.company_stamp')</div></td>
    <td><div class="party">@lang('construction::lang.second_party_customer')<br>{{ $customerName }}</div><div>____________________________</div><div class="signature-line">@lang('construction::lang.name_and_signature')</div><div class="signature-hints">@lang('construction::lang.signed_at') &nbsp;&nbsp;&nbsp; @lang('construction::lang.company_stamp')</div></td>
</tr></table>
<table class="footer print-footer"><tr><td>{{ $business?->name }}</td><td>{{ $contract->contract_number }}</td></tr></table>
@if($contract->boqVersion)
<div class="annex">
    <div class="accent"></div>
    <div class="annex-head"><div class="print-document-title">@lang('construction::lang.boq_contract_annex')</div><div class="annex-subtitle">@lang('construction::lang.boq_annex_description')</div><div class="annex-reference">{{ $project->name }} — {{ $contract->contract_number }} — {{ $contract->boqVersion->name }}</div></div>
    <table class="boq print-table"><thead><tr><th class="code">@lang('construction::lang.boq_code')</th><th class="description">@lang('construction::lang.boq_description')</th><th class="unit">@lang('construction::lang.unit')</th><th class="quantity">@lang('construction::lang.contract_quantity')</th><th class="price">@lang('construction::lang.sales_unit_price')</th><th class="amount">@lang('construction::lang.sales_total')</th></tr></thead><tbody>
    @foreach($contract->boqVersion->items as $item)
        <tr class="{{ $item->row_type === 'section' ? 'section-row' : '' }}"><td>{{ $item->code }}</td><td>{{ $item->description }}</td><td>{{ $item->unit ?: '—' }}</td><td class="print-number">{{ $item->row_type === 'item' ? @format_quantity((float)$item->contract_quantity) : '—' }}</td><td class="print-number">@if($item->row_type === 'item')@include('construction::partials.money', ['value' => $item->sales_unit_price])@else—@endif</td><td class="print-number">@if($item->row_type === 'item')@include('construction::partials.money', ['value' => $item->sales_total])@else—@endif</td></tr>
    @endforeach
    <tr class="total print-total"><td colspan="5">@lang('construction::lang.total_contract_value')</td><td class="print-number">@include('construction::partials.money', ['value' => $contract->original_value])</td></tr>
    </tbody></table>
    <table class="footer print-footer"><tr><td>{{ $business?->name }}</td><td>{{ $contract->contract_number }}</td></tr></table>
</div>
@endif
@if(!empty($autoPrint))<script>window.addEventListener('load',function(){document.fonts.ready.then(function(){window.print()})})</script>@endif
</body></html>

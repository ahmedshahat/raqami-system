@php
    $companyAddress = collect([$businessLocation?->landmark, $businessLocation?->city, $businessLocation?->state, $businessLocation?->country])->filter()->implode('، ');
    $logoSource = null;
    if ($business->logo) {
        $logoFile = public_path('uploads/business_logos/'.$business->logo);
        if (is_file($logoFile)) $logoSource = asset('uploads/business_logos/'.$business->logo);
    }
    $personName = function ($user) {
        return $user ? trim(collect([$user->surname, $user->first_name, $user->last_name])->filter()->implode(' ')) : '';
    };
    $isIssue = $document->type === 'issue';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $document->number }} — @lang('construction::lang.material_'.$document->type)</title>
<style>
@page{size:A4 portrait;margin:9mm}
*{box-sizing:border-box}body{direction:rtl;margin:0;color:#273b55;background:#eef3f8}.page{position:relative;width:192mm;min-height:277mm;margin:12px auto;padding:0 8mm 8mm;background:#fff;box-shadow:0 8px 30px rgba(22,45,78,.12)}
.accent{height:5px;margin:0 -8mm 5mm;background:linear-gradient(90deg,#0f8f75,#1769d2)}
.header,.meta,.items,.signatures,.footer{width:100%;border-collapse:collapse}.header td{padding:2mm 1mm;vertical-align:middle;border-bottom:1px solid #dce5ef}.logo{width:23mm}.logo img{max-width:21mm;max-height:18mm}.company strong{display:block;color:#143f78;font-size:18px}.company span,.contact{color:#697b91;font-size:11px}.contact{text-align:left;line-height:1.7}
.document-title{text-align:center;margin:5mm 0 4mm}.document-title h1{margin:0 0 1.5mm;color:#173d70;font-size:23px}.reference{display:inline-block;padding:1.5mm 5mm;border-radius:20px;background:#eaf3ff;color:#1769d2;font-weight:800;direction:ltr}.state{margin-right:2mm;padding:1mm 3mm;border-radius:14px;color:#fff;font-size:10px}.state.approved{background:#13916f}.state.draft{background:#d78a16}.watermark{position:absolute;top:119mm;right:35mm;z-index:0;transform:rotate(-27deg);color:rgba(188,67,67,.07);font-size:54px;font-weight:900;pointer-events:none}
.meta{position:relative;z-index:1;margin-bottom:5mm;border:1px solid #dce5ef;table-layout:fixed}.meta td{width:33.33%;padding:2.3mm 3mm;border:1px solid #e2e9f1;vertical-align:top}.label{display:block;margin-bottom:1mm;color:#7b8da3;font-size:10px}.value{display:block;color:#263e60;font-weight:700}.items{position:relative;z-index:1;table-layout:fixed}.items th,.items td{padding:2.2mm 1.5mm;border:1px solid #d5dfeb;text-align:center;vertical-align:middle}.items th{background:#1769d2;color:#fff;font-size:10px}.items td{font-size:10px}.items tbody tr:nth-child(even) td{background:#f7faff}.items .description{text-align:right}.items .num{direction:ltr}.items tfoot td{background:#edf5ff;font-weight:800}.notes{position:relative;z-index:1;min-height:16mm;margin-top:4mm;padding:3mm;border:1px solid #dde6f0;border-radius:4px}.notes strong{display:block;margin-bottom:1.5mm;color:#173d70}.notes div{white-space:pre-line;color:#53687f}
.signatures{position:relative;z-index:1;margin-top:8mm;table-layout:fixed}.signatures td{width:25%;height:31mm;padding:3mm 2mm;border:1px solid #dce5ef;text-align:center;vertical-align:top}.signature-title{display:block;color:#173d70;font-weight:800}.signature-name{display:block;min-height:6mm;margin-top:2mm;color:#60758d}.signature-line{display:block;margin:9mm 5mm 0;border-top:1px solid #9eafc2;padding-top:1mm;color:#8797aa;font-size:9px}.footer{position:absolute;right:8mm;bottom:5mm;width:176mm;border-top:1px solid #dce5ef;color:#8190a2;font-size:9px}.footer td{padding-top:2mm}.footer td:last-child{text-align:left;direction:ltr}
.toolbar{display:flex;justify-content:center;gap:8px;padding:12px;background:#e8eff8}.toolbar button{padding:9px 18px;border:0;border-radius:8px;background:#1769d2;color:#fff;cursor:pointer}.toolbar button.secondary{background:#617389}
@media print{body{background:#fff}.toolbar{display:none}.page{width:auto;min-height:auto;margin:0;padding:0;box-shadow:none}.accent{margin-top:0}.footer{position:fixed}.watermark{position:fixed}}
@include('construction::partials.print_typography')
</style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">@lang('construction::lang.print_document')</button><button type="button" class="secondary" onclick="window.close()">@lang('construction::lang.close_preview')</button></div>
<main class="page">
    <div class="accent"></div>
    @if($document->status !== 'approved')<div class="watermark">@lang('construction::lang.draft_unapproved')</div>@endif
    <table class="header"><tr>@if($logoSource)<td class="logo"><img src="{{ $logoSource }}" alt=""></td>@endif<td class="company"><strong>{{ $business->name }}</strong><span>{{ $businessLocation->name }}</span></td><td class="contact">@if($companyAddress)<div>{{ $companyAddress }}</div>@endif @if($businessLocation->mobile || $businessLocation->alternate_number)<div>{{ $businessLocation->mobile ?: $businessLocation->alternate_number }}</div>@endif @if($business->tax_number_1)<div>@lang('construction::lang.tax_registration_number'): {{ $business->tax_number_1 }}</div>@endif</td></tr></table>
    <div class="document-title"><h1>@lang('construction::lang.material_'.$document->type.'_print_title')</h1><span class="reference">{{ $document->number }}</span><span class="state {{ $document->status }}">@lang('construction::lang.'.$document->status)</span></div>
    <table class="meta"><tr><td><span class="label">@lang('construction::lang.project_name')</span><span class="value">{{ $document->project->name }}</span></td><td><span class="label">@lang('construction::lang.project_code')</span><span class="value">{{ $document->project->code }}</span></td><td><span class="label">@lang('construction::lang.document_date')</span><span class="value">{{ @format_date($document->document_date) }}</span></td></tr><tr><td><span class="label">@lang('construction::lang.source_warehouse')</span><span class="value">{{ $document->location->name }}</span></td><td><span class="label">@lang('construction::lang.customer')</span><span class="value">{{ $document->project->customer?->supplier_business_name ?: $document->project->customer?->name ?: '—' }}</span></td><td><span class="label">{{ $isIssue ? __('construction::lang.issued_by') : __('construction::lang.return_for_issue') }}</span><span class="value">{{ $isIssue ? ($personName($document->createdBy) ?: '—') : ($document->parentIssue?->number ?: '—') }}</span></td></tr></table>
    <table class="items"><thead><tr><th style="width:6%">#</th><th style="width:27%">@lang('construction::lang.material_product')</th><th style="width:23%">@lang('construction::lang.project_item')</th><th style="width:9%">@lang('construction::lang.unit')</th><th style="width:10%">@lang('construction::lang.quantity')</th><th style="width:12%">@lang('construction::lang.unit_cost')</th><th style="width:13%">@lang('construction::lang.total_cost')</th></tr></thead><tbody>@foreach($document->lines as $line)<tr><td>{{ $loop->iteration }}</td><td class="description"><strong>{{ $line->variation->full_name }}</strong>@if($line->notes)<br><small>{{ $line->notes }}</small>@endif</td><td class="description">{{ $line->boqItem ? $line->boqItem->code.' — '.$line->boqItem->description : __('construction::lang.general_project_material') }}</td><td>{{ $line->product->unit?->short_name ?: '—' }}</td><td class="num">{{ @format_quantity((float)$line->quantity) }}</td><td class="num">@include('construction::partials.money', ['value' => $line->unit_cost])</td><td class="num">@include('construction::partials.money', ['value' => $line->total_cost])</td></tr>@endforeach</tbody><tfoot><tr><td colspan="6">@lang('construction::lang.document_total')</td><td class="num">@include('construction::partials.money', ['value' => $document->lines->sum('total_cost')])</td></tr></tfoot></table>
    <section class="notes"><strong>@lang('construction::lang.notes')</strong><div>{{ $document->notes ?: __('construction::lang.no_notes') }}</div></section>
    <table class="signatures"><tr>
        <td><span class="signature-title">@lang('construction::lang.document_preparer')</span><span class="signature-name">{{ $personName($document->createdBy) }}</span><span class="signature-line">@lang('construction::lang.name_and_signature')</span></td>
        <td><span class="signature-title">{{ $isIssue ? __('construction::lang.storekeeper_delivery') : __('construction::lang.storekeeper_receipt') }}</span><span class="signature-name">&nbsp;</span><span class="signature-line">@lang('construction::lang.name_and_signature')</span></td>
        <td><span class="signature-title">{{ $isIssue ? __('construction::lang.site_recipient') : __('construction::lang.return_delivered_by') }}</span><span class="signature-name">&nbsp;</span><span class="signature-line">@lang('construction::lang.name_and_signature')</span></td>
        <td><span class="signature-title">@lang('construction::lang.project_manager_approval')</span><span class="signature-name">{{ $personName($document->project->manager) }}</span><span class="signature-line">@lang('construction::lang.signature_and_date')</span></td>
    </tr></table>
    <table class="footer"><tr><td>{{ $business->name }} · @lang('construction::lang.printed_at') {{ now()->format('Y-m-d H:i') }}</td><td>{{ $document->number }}</td></tr></table>
</main>
@if($autoPrint)<script>window.addEventListener('load',function(){document.fonts.ready.then(function(){window.print()})})</script>@endif
</body></html>

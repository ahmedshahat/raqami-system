@php
    $companyAddress = collect([$businessLocation?->landmark, $businessLocation?->city, $businessLocation?->state, $businessLocation?->country])->filter()->implode('، ');
    $logoSource = $business->logo && is_file(public_path('uploads/business_logos/'.$business->logo)) ? asset('uploads/business_logos/'.$business->logo) : null;
    $personName = fn ($user) => $user ? trim(collect([$user->surname, $user->first_name, $user->last_name])->filter()->implode(' ')) : '';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $sheet->number }} — @lang('construction::lang.labor_sheet_print_title')</title>
    <style>
        @page{size:A4 portrait;margin:9mm}*{box-sizing:border-box}body{direction:rtl;margin:0;color:#273b55;background:#eef3f8}.page{position:relative;width:192mm;min-height:277mm;margin:12px auto;padding:0 8mm 8mm;background:#fff;box-shadow:0 8px 30px rgba(22,45,78,.12)}.accent{height:5px;margin:0 -8mm 5mm;background:linear-gradient(90deg,#e59a18,#1769d2)}.header,.meta,.items,.signatures,.footer{width:100%;border-collapse:collapse}.header td{padding:2mm 1mm;vertical-align:middle;border-bottom:1px solid #dce5ef}.logo{width:23mm}.logo img{max-width:21mm;max-height:18mm}.company strong{display:block;color:#143f78;font-size:18px}.company span,.contact{color:#697b91;font-size:11px}.contact{text-align:left;line-height:1.7}.document-title{text-align:center;margin:5mm 0 4mm}.document-title h1{margin:0 0 1.5mm;color:#173d70;font-size:23px}.reference{display:inline-block;padding:1.5mm 5mm;border-radius:20px;background:#fff2d8;color:#9b6500;font-weight:800;direction:ltr}.state{margin-right:2mm;padding:1mm 3mm;border-radius:14px;color:#fff;font-size:10px}.state.approved{background:#13916f}.state.draft{background:#d78a16}.state.cancelled{background:#ba3535}.watermark{position:absolute;top:119mm;right:35mm;transform:rotate(-27deg);color:rgba(188,67,67,.09);font-size:54px;font-weight:900}.meta{position:relative;margin-bottom:5mm;table-layout:fixed}.meta td{width:33.33%;padding:2.3mm 3mm;border:1px solid #e2e9f1}.label{display:block;margin-bottom:1mm;color:#7b8da3;font-size:10px}.value{display:block;color:#263e60;font-weight:700}.items{position:relative;table-layout:fixed}.items th,.items td{padding:2.1mm 1.3mm;border:1px solid #d5dfeb;text-align:center}.items th{background:#1769d2;color:#fff;font-size:9px}.items td{font-size:9px}.items tbody tr:nth-child(even) td{background:#f7faff}.items .description{text-align:right}.items tfoot td{background:#edf5ff;font-weight:800}.notes,.cancellation{min-height:14mm;margin-top:4mm;padding:3mm;border:1px solid #dde6f0}.notes strong,.cancellation strong{display:block;color:#173d70}.cancellation{border-color:#e8bcbc;background:#fff5f5;color:#7d3333}.cancellation strong{color:#aa3030}.cancellation small{display:block;margin-top:2mm;color:#956060}.signatures{margin-top:8mm;table-layout:fixed}.signatures td{width:25%;height:31mm;padding:3mm 2mm;border:1px solid #dce5ef;text-align:center;vertical-align:top}.signature-title{display:block;color:#173d70;font-weight:800}.signature-line{display:block;margin:14mm 5mm 0;border-top:1px solid #9eafc2;padding-top:1mm;color:#8797aa;font-size:9px}.footer{position:absolute;right:8mm;bottom:5mm;width:176mm;border-top:1px solid #dce5ef;color:#8190a2;font-size:9px}.footer td{padding-top:2mm}.footer td:last-child{text-align:left}.toolbar{display:flex;justify-content:center;gap:8px;padding:12px}.toolbar button{padding:9px 18px;border:0;border-radius:8px;background:#1769d2;color:#fff}.toolbar .secondary{background:#617389}@media print{body{background:#fff}.toolbar{display:none}.page{width:auto;min-height:auto;margin:0;padding:0;box-shadow:none}.footer{position:fixed}}
        .quantity-unit{white-space:nowrap;font-weight:700}.quantity-unit span{color:#60758f;font-size:8px}
        @include('construction::partials.print_typography')
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">@lang('construction::lang.print_document')</button><button class="secondary" onclick="window.close()">@lang('construction::lang.close_preview')</button></div>
    <main class="page">
        <div class="accent"></div>
        @if($sheet->status === 'cancelled')
            <div class="watermark">@lang('construction::lang.cancelled')</div>
        @elseif($sheet->status !== 'approved')
            <div class="watermark">@lang('construction::lang.draft_unapproved')</div>
        @endif
        <table class="header"><tr>@if($logoSource)<td class="logo"><img src="{{ $logoSource }}"></td>@endif<td class="company"><strong>{{ $business->name }}</strong><span>{{ $businessLocation?->name }}</span></td><td class="contact">{{ $companyAddress }}</td></tr></table>
        <div class="document-title"><h1>@lang('construction::lang.labor_sheet_print_title')</h1><span class="reference">{{ $sheet->number }}</span><span class="state {{ $sheet->status }}">@lang('construction::lang.'.$sheet->status)</span></div>
        <table class="meta">
            <tr><td><span class="label">@lang('construction::lang.project_name')</span><span class="value">{{ $sheet->project->name }}</span></td><td><span class="label">@lang('construction::lang.project_code')</span><span class="value">{{ $sheet->project->code }}</span></td><td><span class="label">@lang('construction::lang.period')</span><span class="value">{{ @format_date($sheet->period_start) }} — {{ @format_date($sheet->period_end) }}</span></td></tr>
            <tr><td><span class="label">@lang('construction::lang.work_site')</span><span class="value">{{ $sheet->work_site ?: '—' }}</span></td><td><span class="label">@lang('construction::lang.customer')</span><span class="value">{{ $sheet->project->customer?->supplier_business_name ?: $sheet->project->customer?->name ?: '—' }}</span></td><td><span class="label">@lang('construction::lang.document_preparer')</span><span class="value">{{ $personName($sheet->createdBy) }}</span></td></tr>
        </table>
        <table class="items">
            <thead><tr><th>#</th><th>@lang('construction::lang.worker_or_team')</th><th>@lang('construction::lang.role_name')</th><th>@lang('construction::lang.project_item')</th><th>@lang('construction::lang.calculation_type')</th><th>@lang('construction::lang.quantity')</th><th>@lang('construction::lang.unit_cost')</th><th>@lang('construction::lang.total_cost')</th></tr></thead>
            <tbody>
                @foreach($sheet->lines as $line)
                    <tr><td>{{ $loop->iteration }}</td><td class="description">{{ $line->worker_name }}</td><td>{{ $line->role_name ?: '—' }}</td><td class="description">{{ $line->boqItem ? $line->boqItem->code.' — '.$line->boqItem->description : __('construction::lang.general_project_labor') }}</td><td>@lang('construction::lang.calc_'.$line->calculation_type)</td><td class="quantity-unit">{{ @format_quantity((float)$line->quantity) }} <span>@lang('construction::lang.calc_'.$line->calculation_type)</span></td><td>@include('construction::partials.money', ['value' => $line->unit_cost])</td><td>@include('construction::partials.money', ['value' => $line->total_cost])</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="7">@lang('construction::lang.sheet_total')</td><td>@include('construction::partials.money', ['value' => $sheet->lines->sum('total_cost')])</td></tr></tfoot>
        </table>
        <section class="notes"><strong>@lang('construction::lang.notes')</strong><div>{{ $sheet->notes ?: __('construction::lang.no_notes') }}</div></section>
        @if($sheet->status === 'cancelled')
            <section class="cancellation">
                <strong>@lang('construction::lang.labor_sheet_cancellation_record')</strong>
                <div>{{ $sheet->cancellation_reason }}</div>
                <small>@lang('construction::lang.cancelled_by'): {{ $personName($sheet->cancelledBy) ?: '—' }} · @lang('construction::lang.cancelled_at'): {{ $sheet->cancelled_at ? @format_datetime($sheet->cancelled_at) : '—' }}</small>
            </section>
        @endif
        <table class="signatures"><tr><td><span class="signature-title">@lang('construction::lang.document_preparer')</span><span class="signature-line">@lang('construction::lang.name_and_signature')</span></td><td><span class="signature-title">@lang('construction::lang.site_supervisor')</span><span class="signature-line">@lang('construction::lang.name_and_signature')</span></td><td><span class="signature-title">@lang('construction::lang.project_manager_approval')</span><span class="signature-line">@lang('construction::lang.signature_and_date')</span></td><td><span class="signature-title">@lang('construction::lang.accounts_approval')</span><span class="signature-line">@lang('construction::lang.signature_and_date')</span></td></tr></table>
        <table class="footer"><tr><td>{{ $business->name }} · @lang('construction::lang.printed_at') {{ now()->format('Y-m-d H:i') }}</td><td>{{ $sheet->number }}</td></tr></table>
    </main>
    @if($autoPrint)<script>window.addEventListener('load',function(){document.fonts.ready.then(function(){window.print()})})</script>@endif
</body>
</html>

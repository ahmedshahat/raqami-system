<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ __('accounting::lang.'.$voucher->type) }} - {{ $voucher->ref_no }}</title>
    <style>
        @font-face{font-family:'IBM Plex Sans Arabic';src:url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.woff2') }}') format('woff2');font-weight:400;font-style:normal;font-display:swap}
        @font-face{font-family:'IBM Plex Sans Arabic';src:url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Medium.woff2') }}') format('woff2');font-weight:500;font-style:normal;font-display:swap}
        @font-face{font-family:'IBM Plex Sans Arabic';src:url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-SemiBold.woff2') }}') format('woff2');font-weight:600;font-style:normal;font-display:swap}
        @font-face{font-family:'IBM Plex Sans Arabic';src:url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.woff2') }}') format('woff2');font-weight:700;font-style:normal;font-display:swap}}') format('opentype');font-weight:normal;font-style:normal;font-display:swap}
        *{box-sizing:border-box} body{margin:0;background:#eef1f5;color:#172033;font-family:'IBM Plex Sans Arabic',sans-serif;font-size:16px}
        .toolbar{max-width:900px;margin:18px auto 0;text-align:left}.print-btn{border:0;border-radius:7px;background:#2457d6;color:#fff;padding:10px 22px;font-weight:bold;cursor:pointer}
        .sheet{position:relative;max-width:900px;min-height:620px;margin:12px auto 30px;background:#fff;padding:34px 44px;border:1px solid #d8dee8;box-shadow:0 5px 24px rgba(31,45,61,.12);overflow:hidden}
        .cancelled-watermark{position:absolute;top:250px;left:130px;right:130px;transform:rotate(-22deg);font-size:74px;font-weight:bold;color:rgba(190,25,25,.13);text-align:center;border:8px solid rgba(190,25,25,.13);z-index:0}
        .content{position:relative;z-index:1}.header{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #153b71;padding-bottom:15px}
        .brand{display:flex;align-items:center;gap:14px}.logo{max-height:68px;max-width:125px}.business-name{font-size:22px;font-weight:bold;color:#153b71}.location{margin-top:7px;color:#556070}
        .voucher-title{text-align:center}.voucher-title h1{margin:0;padding:9px 25px;border:2px solid #153b71;color:#153b71;border-radius:6px;font-size:25px}.voucher-title .number{margin-top:9px;font-weight:bold}
        .meta{display:grid;grid-template-columns:1fr 1fr;margin:25px 0;border:1px solid #bdc7d6}.meta div{padding:11px 14px;border-bottom:1px solid #dfe4eb}.meta div:nth-child(odd){border-left:1px solid #dfe4eb}.meta div:nth-last-child(-n+2){border-bottom:0}.label{color:#526070;font-weight:bold}
        .amount-box{display:grid;grid-template-columns:180px 1fr;margin:20px 0;border:2px solid #153b71;border-radius:5px;overflow:hidden}.amount-number{background:#153b71;color:#fff;text-align:center;padding:15px 8px;font-size:21px;font-weight:bold}.amount-words{padding:15px;font-weight:bold}
        .statement{min-height:92px;border:1px solid #bdc7d6;border-radius:5px;padding:15px;line-height:1.9}.accounts{margin-top:18px;width:100%;border-collapse:collapse}.accounts th,.accounts td{border:1px solid #bdc7d6;padding:10px;text-align:center}.accounts th{background:#edf3fa;color:#153b71}
        .signatures{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:54px;text-align:center}.signature{border-top:1px solid #667085;padding-top:9px;font-weight:bold}.audit{margin-top:28px;color:#7b8492;font-size:11px;text-align:center}
        @media print{body{background:#fff}.toolbar{display:none}.sheet{max-width:none;min-height:0;margin:0;border:0;box-shadow:none;padding:20px 25px}@page{size:A4 portrait;margin:10mm}}
    </style>
</head>
<body>
<div class="toolbar"><button class="print-btn" onclick="window.print()">🖨 {{ __('accounting::lang.print_voucher') }}</button></div>
<main class="sheet">
    @if($voucher->status === 'cancelled')<div class="cancelled-watermark">@lang('accounting::lang.cancelled')</div>@endif
    <div class="content">
        <header class="header">
            <div class="brand">
                @if(!empty($business->logo))<img class="logo" src="{{ asset('uploads/business_logos/'.$business->logo) }}" alt="">@endif
                <div><div class="business-name">{{ $business->name }}</div><div class="location">{{ optional($location)->name }}</div></div>
            </div>
            <div class="voucher-title">
                <h1>@lang('accounting::lang.'.$voucher->type)</h1>
                <div class="number">@lang('accounting::lang.voucher_number'): {{ $voucher->ref_no }}</div>
            </div>
        </header>

        <section class="meta">
            <div><span class="label">@lang('messages.date'):</span> {{ @format_datetime($voucher->operation_date) }}</div>
            <div><span class="label">{{ $voucher->type === 'receipt_voucher' ? __('accounting::lang.received_from') : __('accounting::lang.paid_to') }}:</span> {{ $voucher->party_name }}</div>
            <div><span class="label">@lang('accounting::lang.payment_method'):</span> @lang('accounting::lang.payment_method_'.$voucher->payment_method)</div>
            <div><span class="label">@lang('accounting::lang.payment_reference'):</span> {{ $voucher->payment_reference ?: __('accounting::lang.not_available') }}</div>
        </section>

        <section class="amount-box">
            <div class="amount-number">{{ @num_format($cashLine->amount) }} {{ session('currency')['symbol'] ?? '' }}</div>
            <div class="amount-words"><span class="label">@lang('accounting::lang.amount_in_words'):</span> {{ $amountInWords ?: @num_format($cashLine->amount) }} @lang('accounting::lang.only_no_more')</div>
        </section>

        <section class="statement"><span class="label">@lang('accounting::lang.for'):</span> {{ $voucher->note }}</section>

        <table class="accounts">
            <thead><tr><th>@lang('accounting::lang.cash_or_bank_account')</th><th>@lang('accounting::lang.offset_account')</th><th>@lang('accounting::lang.debit')</th><th>@lang('accounting::lang.credit')</th></tr></thead>
            <tbody><tr>
                <td>{{ optional($cashLine->account)->name }}</td><td>{{ optional($offsetLine->account)->name }}</td>
                <td>{{ $voucher->type === 'receipt_voucher' ? @num_format($cashLine->amount) : @num_format($offsetLine->amount) }}</td>
                <td>{{ $voucher->type === 'receipt_voucher' ? @num_format($offsetLine->amount) : @num_format($cashLine->amount) }}</td>
            </tr></tbody>
        </table>

        <section class="signatures">
            <div class="signature">@lang('accounting::lang.prepared_by')<br><small>{{ optional($createdBy)->first_name }} {{ optional($createdBy)->last_name }}</small></div>
            <div class="signature">{{ $voucher->type === 'receipt_voucher' ? __('accounting::lang.payer_signature') : __('accounting::lang.recipient_signature') }}</div>
            <div class="signature">@lang('accounting::lang.accountant_signature')</div>
            <div class="signature">@lang('accounting::lang.manager_approval')</div>
        </section>

        <div class="audit">
            @lang('accounting::lang.system_generated_voucher')
            @if($voucher->status === 'cancelled') — @lang('accounting::lang.cancelled_at') {{ @format_datetime($voucher->cancelled_at) }} {{ optional($cancelledBy)->first_name }}@endif
        </div>
    </div>
</main>
</body>
</html>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>@lang('accounting::lang.ledger') - {{ $account->name }}</title>
    <style>
        @font-face{font-family:'IBM Plex Sans Arabic';src:url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.woff2') }}') format('woff2');font-weight:400;font-style:normal;font-display:swap}
        @font-face{font-family:'IBM Plex Sans Arabic';src:url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Medium.woff2') }}') format('woff2');font-weight:500;font-style:normal;font-display:swap}
        @font-face{font-family:'IBM Plex Sans Arabic';src:url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-SemiBold.woff2') }}') format('woff2');font-weight:600;font-style:normal;font-display:swap}
        @font-face{font-family:'IBM Plex Sans Arabic';src:url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.woff2') }}') format('woff2');font-weight:700;font-style:normal;font-display:swap}}') format('opentype');font-weight:normal;font-style:normal;font-display:swap}
        *{box-sizing:border-box}body{margin:0;background:#edf1f5;color:#172033;font-family:'IBM Plex Sans Arabic',sans-serif;font-size:13px}
        .toolbar{max-width:1120px;margin:16px auto 0;text-align:left}.print-btn{border:0;border-radius:7px;background:#2457d6;color:white;padding:10px 22px;font-family:inherit;font-size:14px;cursor:pointer}
        .sheet{max-width:1120px;margin:12px auto 28px;background:white;padding:25px 30px;border:1px solid #d6dee9;box-shadow:0 5px 22px rgba(28,45,69,.12)}
        .header{display:flex;justify-content:space-between;align-items:center;padding-bottom:14px;border-bottom:3px solid #153b71}.brand{display:flex;align-items:center;gap:12px}.logo{max-height:62px;max-width:120px}.business{font-size:21px;font-weight:bold;color:#153b71}.report-title{text-align:left}.report-title h1{margin:0;color:#153b71;font-size:24px}.issued{margin-top:5px;color:#687488;font-size:11px}
        .account-card{display:grid;grid-template-columns:2fr 1fr 1fr 1.5fr;gap:0;margin:18px 0;border:1px solid #c8d1de;border-radius:5px;overflow:hidden}.account-card>div{padding:10px 12px;border-left:1px solid #dbe1e8}.account-card>div:last-child{border-left:0}.label{display:block;color:#687488;font-size:10px;margin-bottom:3px}.value{font-weight:bold;color:#22344e}
        .summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}.summary>div{padding:10px 12px;border-radius:5px;background:#f4f7fb;border:1px solid #dce4ee}.summary strong{display:block;margin-top:4px;font-size:15px;color:#153b71}.summary .closing{background:#eaf2ff;border-color:#abc5ec}.negative{color:#b42318!important}
        table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}tr{page-break-inside:avoid}th,td{border:1px solid #cbd4df;padding:7px 6px;vertical-align:top}th{background:#153b71;color:white;text-align:center;font-weight:bold}td.money{text-align:left;direction:ltr;white-space:nowrap;font-variant-numeric:tabular-nums}td.center{text-align:center}.desc{line-height:1.5}.note{word-wrap:break-word}.opening-row td{background:#eef4fb;font-weight:bold}.totals td{background:#e8edf4;font-weight:bold}.closing-row td{background:#dceaff;font-weight:bold;color:#153b71}
        .signatures{display:grid;grid-template-columns:repeat(4,1fr);gap:30px;margin-top:40px;text-align:center}.signature{padding-top:8px;border-top:1px solid #667085;font-weight:bold}.footer{margin-top:22px;text-align:center;color:#7c8796;font-size:10px}
        @media print{body{background:white}.toolbar{display:none}.sheet{max-width:none;margin:0;padding:8px 10px;border:0;box-shadow:none}@page{size:A4 landscape;margin:8mm}}
    </style>
</head>
<body>
@php
    $normalSide = in_array($account->account_primary_type, ['asset', 'expenses'], true) ? 'debit' : 'credit';
    $oppositeSide = $normalSide === 'debit' ? 'credit' : 'debit';
@endphp
<div class="toolbar"><button class="print-btn" onclick="window.print()">🖨 @lang('accounting::lang.print_ledger_a4')</button></div>
<main class="sheet">
    <header class="header">
        <div class="brand">
            @if(!empty($business->logo))<img class="logo" src="{{ asset('uploads/business_logos/'.$business->logo) }}" alt="">@endif
            <div class="business">{{ $business->name }}</div>
        </div>
        <div class="report-title">
            <h1>@lang('accounting::lang.general_ledger_statement')</h1>
            <div class="issued">@lang('accounting::lang.printed_at'): {{ @format_datetime(now()) }}</div>
        </div>
    </header>

    <section class="account-card">
        <div><span class="label">@lang('accounting::lang.account_name')</span><span class="value">{{ $account->name }}</span></div>
        <div><span class="label">@lang('accounting::lang.gl_code')</span><span class="value">{{ $account->gl_code ?: __('accounting::lang.not_available') }}</span></div>
        <div><span class="label">@lang('accounting::lang.account_type')</span><span class="value">@lang('accounting::lang.'.$account->account_primary_type)</span></div>
        <div><span class="label">@lang('accounting::lang.report_period')</span><span class="value">{{ $start_date ? @format_date($start_date) : __('accounting::lang.from_beginning') }} — {{ @format_date($end_date) }}</span></div>
    </section>

    <section class="summary">
        <div><span class="label">@lang('accounting::lang.opening_balance')</span><strong class="{{ $opening_balance < 0 ? 'negative' : '' }}">{{ @num_format(abs($opening_balance)) }} {{ $opening_balance == 0 ? '' : __('accounting::lang.'.($opening_balance > 0 ? $normalSide : $oppositeSide)) }}</strong></div>
        <div><span class="label">@lang('accounting::lang.total_debit')</span><strong>{{ @num_format($total_debit) }}</strong></div>
        <div><span class="label">@lang('accounting::lang.total_credit')</span><strong>{{ @num_format($total_credit) }}</strong></div>
        <div class="closing"><span class="label">@lang('accounting::lang.closing_balance')</span><strong class="{{ $closing_balance < 0 ? 'negative' : '' }}">{{ @num_format(abs($closing_balance)) }} {{ $closing_balance == 0 ? '' : __('accounting::lang.'.($closing_balance > 0 ? $normalSide : $oppositeSide)) }}</strong></div>
    </section>

    <table>
        <colgroup><col style="width:12%"><col style="width:19%"><col style="width:25%"><col style="width:12%"><col style="width:10%"><col style="width:10%"><col style="width:12%"></colgroup>
        <thead><tr>
            <th>@lang('messages.date')</th><th>@lang('lang_v1.description')</th><th>@lang('brand.note')</th><th>@lang('lang_v1.added_by')</th><th>@lang('accounting::lang.debit')</th><th>@lang('accounting::lang.credit')</th><th>@lang('accounting::lang.running_balance')</th>
        </tr></thead>
        <tbody>
            <tr class="opening-row"><td colspan="6">@lang('accounting::lang.opening_balance')</td><td class="money">{{ @num_format(abs($opening_balance)) }} {{ $opening_balance == 0 ? '' : __('accounting::lang.'.($opening_balance > 0 ? $normalSide : $oppositeSide)) }}</td></tr>
            @forelse($transactions as $transaction)
                @php
                    $rowSide = $transaction->running_balance >= 0 ? $normalSide : $oppositeSide;
                @endphp
                <tr>
                    <td class="center">{{ @format_datetime($transaction->operation_date) }}</td>
                    <td class="desc">{!! $transaction->description_html !!}</td>
                    <td class="note">{{ $transaction->note ?: $transaction->aat_note }}</td>
                    <td class="center">{{ trim($transaction->added_by) }}</td>
                    <td class="money">{{ $transaction->type === 'debit' ? @num_format($transaction->amount) : '' }}</td>
                    <td class="money">{{ $transaction->type === 'credit' ? @num_format($transaction->amount) : '' }}</td>
                    <td class="money {{ $transaction->running_balance < 0 ? 'negative' : '' }}">{{ @num_format(abs($transaction->running_balance)) }} {{ $transaction->running_balance == 0 ? '' : __('accounting::lang.'.$rowSide) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">@lang('accounting::lang.no_transactions_in_period')</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="totals"><td colspan="4">@lang('accounting::lang.period_totals')</td><td class="money">{{ @num_format($total_debit) }}</td><td class="money">{{ @num_format($total_credit) }}</td><td></td></tr>
            <tr class="closing-row"><td colspan="6">@lang('accounting::lang.closing_balance')</td><td class="money">{{ @num_format(abs($closing_balance)) }} {{ $closing_balance == 0 ? '' : __('accounting::lang.'.($closing_balance > 0 ? $normalSide : $oppositeSide)) }}</td></tr>
        </tfoot>
    </table>

    <section class="signatures">
        <div class="signature">@lang('accounting::lang.prepared_by')</div><div class="signature">@lang('accounting::lang.reviewed_by')</div><div class="signature">@lang('accounting::lang.accountant_signature')</div><div class="signature">@lang('accounting::lang.manager_approval')</div>
    </section>
    <div class="footer">@lang('accounting::lang.system_generated_ledger')</div>
</main>
</body>
</html>

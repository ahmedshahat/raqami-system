@php
    $is_rtl = in_array(app()->getLocale(), config('constants.langs_rtl', ['ar', 'he']));
    $title = $type == 'cash_flow' ? __('cheque.cash_flow') : __('cheque.' . $type);
    $total = $cheques->sum('amount');
    $today = \Carbon::today();
    $tones = ['pending' => 'info', 'collected' => 'success', 'bounced' => 'danger', 'cancelled' => 'neutral'];
    $landscape = $type != 'cash_flow';
    $buckets = [
        ['overdue', __('cheque.overdue'), 'danger'],
        ['today', __('cheque.today'), 'warning'],
        ['this_week', __('cheque.this_week'), 'info'],
        ['this_month', __('cheque.this_month'), 'success'],
        ['later', __('cheque.later'), 'neutral'],
        ['total', $type == 'payable' ? __('cheque.not_cleared') : __('cheque.status_pending'), 'brand'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $is_rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} - {{ $business->name }}</title>
<style>
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 400; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.woff2') }}') format('woff2'); }
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 600; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-SemiBold.woff2') }}') format('woff2'); }
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 700; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.woff2') }}') format('woff2'); }

    /* نفس ألوان نظام تصميم رقمي */
    :root {
        --ink: #11141B; --muted: #454E60; --subtle: #586174;
        --line: #DADFE8; --sunken: #EDEFF4; --brand: #22177A;
        --danger: #AE231B; --danger-tint: #FDE8E6; --warning: #7C4A00; --warning-tint: #FFF1D3;
        --success: #0A6252; --success-tint: #E0F3EF; --info: #0A5A82; --info-tint: #E1F0F8; --neutral-tint: #EAEDF2;
    }
    @page { size: A4 {{ $landscape ? 'landscape' : 'portrait' }}; margin: 10mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; }
    body { background: #F3F4F8; color: var(--ink); font-family: 'IBM Plex Sans Arabic', Tahoma, Arial, sans-serif; font-size: 11px; line-height: 1.5; }

    .toolbar { display: flex; justify-content: center; gap: 8px; padding: 12px; }
    .toolbar button { font: inherit; font-size: 13px; font-weight: 600; border-radius: 6px; padding: 7px 18px; cursor: pointer; border: 1px solid #778093; background: #fff; color: var(--ink); }
    .toolbar .primary { background: var(--brand); border-color: var(--brand); color: #fff; }

    .sheet { width: {{ $landscape ? '277mm' : '190mm' }}; margin: 0 auto 24px; padding: 10mm; background: #fff; border: 1px solid var(--line); border-radius: 8px; }

    .head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-bottom: 10px; border-bottom: 1px solid var(--line); }
    .brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .brand img { max-height: 42px; max-width: 110px; object-fit: contain; }
    .brand .name { font-size: 15px; font-weight: 700; }
    .doc { text-align: end; }
    .doc .title { font-size: 16px; font-weight: 700; color: var(--brand); }
    .doc .meta { color: var(--subtle); font-size: 10px; }

    .filters { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 8px 0 0; color: var(--muted); font-size: 10.5px; }
    .filters b { color: var(--ink); font-weight: 600; }

    .stats { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); margin: 10px 0; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
    .stat { padding: 7px 10px; border-inline-start: 1px solid var(--line); }
    .stat:first-child { border-inline-start: 0; }
    .stat .label { color: var(--muted); font-size: 10px; }
    .stat .value { font-size: 14px; font-weight: 700; }
    .stat .count { color: var(--subtle); font-size: 9.5px; }
    .stat.danger .value { color: var(--danger); }
    .stat { box-shadow: inset 0 3px 0 var(--tone, var(--line)); }
    .tone-danger { --tone: var(--danger); } .tone-warning { --tone: #E3B75F; } .tone-info { --tone: var(--info); }
    .tone-success { --tone: var(--success); } .tone-neutral { --tone: #C5CBD6; }
    .tone-brand { --tone: var(--brand); background: #ECEAFB; } .tone-brand .value { color: var(--brand); }
    th { border-top: 2px solid var(--brand); }

    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th { background: var(--sunken); color: var(--muted); font-size: 10px; font-weight: 600; text-align: start; padding: 6px 6px; border-bottom: 1px solid var(--line); }
    td { padding: 6px 6px; border-bottom: 1px solid var(--line); vertical-align: middle; overflow-wrap: anywhere; }
    tr.danger td { background: var(--danger-tint); }
    tr.warning td { background: var(--warning-tint); }
    tfoot td { background: var(--sunken); font-weight: 700; border-top: 1px solid #778093; border-bottom: 0; }
    .num { direction: ltr; unicode-bidi: isolate; font-variant-numeric: tabular-nums; white-space: nowrap; }
    th.end, td.end { text-align: end; }
    .muted { color: var(--subtle); }
    .badge { display: inline-block; padding: 0 6px; border-radius: 4px; font-size: 9.5px; font-weight: 600; white-space: nowrap; }
    .badge.info { background: var(--info-tint); color: var(--info); }
    .badge.success { background: var(--success-tint); color: var(--success); }
    .badge.danger { background: var(--danger-tint); color: var(--danger); }
    .badge.warning { background: var(--warning-tint); color: var(--warning); }
    .badge.neutral { background: var(--neutral-tint); color: var(--muted); }
    .neg { color: var(--danger); font-weight: 700; }
    .empty { text-align: center; color: var(--subtle); padding: 18px; }

    .col-idx { width: 4%; } .col-no { width: 10%; } .col-contact { width: 22%; } .col-bank { width: 10%; }
    .col-inv { width: 9%; } .col-date { width: 9%; } .col-days { width: 9%; } .col-status { width: 9%; } .col-amount { width: 9%; }

    .signs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20mm; margin-top: 14mm; text-align: center; color: var(--muted); font-size: 10px; }
    .signs div { border-top: 1px solid #778093; padding-top: 5px; }

    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .sheet { width: auto; margin: 0; padding: 0; border: 0; border-radius: 0; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <button type="button" class="primary" onclick="window.print()">{{ __('cheque.print') }}</button>
    <button type="button" onclick="window.close()">{{ __('messages.close') }}</button>
</div>

<div class="sheet">
    <div class="head">
        <div class="brand">
            @if(!empty($logo))<img src="{{ $logo }}" alt="">@endif
            <div class="name">{{ $business->name }}</div>
        </div>
        <div class="doc">
            <div class="title">{{ $title }}</div>
            <div class="meta">{{ __('cheque.printed_on') }}: <span class="num">{{ @format_datetime('now') }}</span></div>
        </div>
    </div>

    @if(!empty($filters))
        <div class="filters">
            @foreach($filters as $label => $value)
                <span>{{ $label }}: <b>{{ $value }}</b></span>
            @endforeach
        </div>
    @endif

    @if($type != 'cash_flow')
        <div class="stats">
            @foreach($buckets as [$key, $label, $tone])
                <div class="stat tone-{{ $tone }} {{ $key == 'overdue' && $summary['overdue'] > 0 ? 'danger' : '' }}">
                    <div class="label">{{ $label }}</div>
                    <div class="value num">{{ @num_format($summary[$key]) }}</div>
                    <div class="count">{{ __('cheque.cheques_count', ['count' => (int) $summary[$key . '_count']]) }}</div>
                </div>
            @endforeach
        </div>

        <table>
            <colgroup>
                <col class="col-idx"><col class="col-no"><col class="col-contact"><col class="col-bank"><col class="col-inv">
                <col class="col-date"><col class="col-date"><col class="col-days"><col class="col-status"><col class="col-amount">
            </colgroup>
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('cheque.cheque_no') }}</th>
                    <th>{{ $type == 'receivable' ? __('cheque.customer') : __('cheque.supplier') }}</th>
                    <th>{{ __('cheque.bank_name') }}</th>
                    <th>{{ __('cheque.invoice') }}</th>
                    <th>{{ $type == 'receivable' ? __('cheque.received_on') : __('cheque.issued_on') }}</th>
                    <th>{{ __('cheque.due_date') }}</th>
                    <th>{{ __('cheque.days_left') }}</th>
                    <th>{{ __('cheque.status') }}</th>
                    <th class="end">{{ __('cheque.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cheques as $cheque)
                    @php
                        $days = $cheque->status == 'pending' ? $today->diffInDays(\Carbon::parse($cheque->due_date)->startOfDay(), false) : null;
                        $row_class = is_null($days) ? '' : ($days < 0 ? 'danger' : ($days <= 7 ? 'warning' : ''));
                    @endphp
                    <tr class="{{ $row_class }}">
                        <td class="muted num">{{ $loop->iteration }}</td>
                        <td class="num">{{ $cheque->cheque_number }}</td>
                        <td>{{ $cheque->contact_name }}@if(!empty($cheque->supplier_business_name))<div class="muted">{{ $cheque->supplier_business_name }}</div>@endif</td>
                        <td>{{ $cheque->bank_name ?: '—' }}</td>
                        <td class="num">
                            @if(!empty($cheque->transaction_id))
                                {{ $type == 'receivable' ? $cheque->invoice_no : $cheque->ref_no }}
                            @else
                                <span class="muted">{{ __('cheque.on_account') }}</span>
                            @endif
                        </td>
                        <td class="num">{{ !empty($cheque->received_on) ? @format_date($cheque->received_on) : '' }}</td>
                        <td class="num"><b>{{ @format_date($cheque->due_date) }}</b></td>
                        <td>
                            @if(is_null($days))
                                <span class="muted">—</span>
                            @elseif($days < 0)
                                <span class="badge danger">{{ __('cheque.days_overdue', ['days' => abs($days)]) }}</span>
                            @elseif($days == 0)
                                <span class="badge warning">{{ __('cheque.due_today') }}</span>
                            @else
                                <span class="{{ $days <= 7 ? 'badge warning' : 'muted' }}">{{ __('cheque.days_remaining', ['days' => $days]) }}</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $tones[$cheque->status] ?? 'neutral' }}">{{ $statuses[$cheque->status] ?? $cheque->status }}</span></td>
                        <td class="end num"><b>{{ @num_format($cheque->amount) }}</b></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="empty">{{ __('cheque.no_cheques') }}</td></tr>
                @endforelse
            </tbody>
            @if($cheques->count())
                <tfoot>
                    <tr>
                        <td colspan="9">{{ __('sale.total') }} · {{ __('cheque.cheques_count', ['count' => $cheques->count()]) }}</td>
                        <td class="end num">{{ @num_format($total) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @else
        <table style="margin-top: 10px;">
            <thead>
                <tr>
                    <th>{{ __('cheque.week') }}</th>
                    @if(in_array('receivable', $types))<th class="end">{{ __('cheque.incoming') }}</th>@endif
                    @if(in_array('payable', $types))<th class="end">{{ __('cheque.outgoing') }}</th>@endif
                    <th class="end">{{ __('cheque.net') }}</th>
                    <th class="end">{{ __('cheque.cumulative') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cash_flow as $row)
                    <tr class="{{ $row['key'] == 'overdue' && ($row['in'] || $row['out']) ? 'danger' : '' }}">
                        <td>
                            @if($row['key'] == 'overdue')
                                {{ __('cheque.overdue_row') }}
                            @elseif($row['key'] == 'later')
                                {{ __('cheque.later') }} <span class="muted num">{{ @format_date($row['from']) }}</span>
                            @else
                                <span class="num">{{ @format_date($row['from']) }}</span> – <span class="num">{{ @format_date($row['to']) }}</span>
                            @endif
                        </td>
                        @if(in_array('receivable', $types))<td class="end num">{{ @num_format($row['in']) }}</td>@endif
                        @if(in_array('payable', $types))<td class="end num">{{ @num_format($row['out']) }}</td>@endif
                        <td class="end num {{ $row['net'] < 0 ? 'neg' : '' }}">{{ @num_format($row['net']) }}</td>
                        <td class="end num {{ $row['cumulative'] < 0 ? 'neg' : '' }}">{{ @num_format($row['cumulative']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>{{ __('sale.total') }}</td>
                    @if(in_array('receivable', $types))<td class="end num">{{ @num_format(collect($cash_flow)->sum('in')) }}</td>@endif
                    @if(in_array('payable', $types))<td class="end num">{{ @num_format(collect($cash_flow)->sum('out')) }}</td>@endif
                    <td class="end num">{{ @num_format(collect($cash_flow)->sum('net')) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="signs">
        <div>{{ __('cheque.sign_accountant') }}</div>
        <div>{{ __('cheque.sign_finance_manager') }}</div>
        <div>{{ __('cheque.sign_general_manager') }}</div>
    </div>
</div>

@if(!request()->has('preview'))
<script>
    window.addEventListener('load', function () {
        var go = function () { setTimeout(function () { window.print(); }, 200); };
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(go); } else { go(); }
    });
</script>
@endif
</body>
</html>

{{--
    طباعة قائمة المبيعات أو المشتريات لكل الفترة والفلاتر (/sells/v2/export و/purchases/export بـ format=print).
    صفحة مستقلة من غير layout التطبيق، بتفتح في تاب جديد وبتطبع نفسها. البيانات نفسها اللي في Excel (SalesListExport وPurchasesListExport).
--}}
@php
    $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'ur', 'he']);
    $fmt = fn ($n) => number_format((float) $n, $precision);
    $sum = fn ($key) => array_sum(array_map(fn ($r) => $r['values'][$key], $records));
    $count = count($records);
    $total = $sum('total');
    $tones = ['paid' => 'paid', 'partial' => 'partial', 'due' => 'due', 'overdue' => 'overdue'];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $export->phrase('xl_title') }} · {{ $export->periodText() }}</title>
<style>
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 400; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.woff2') }}') format('woff2'); }
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 600; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-SemiBold.woff2') }}') format('woff2'); }
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 700; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.woff2') }}') format('woff2'); }
    :root { --brand: #22177A; --brand-2: #3425B8; --tint: #ECEAFB; --ink: #1B1F2A; --muted: #5B6475; --line: #DDE1E8; --soft: #F6F7FA; }
    * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { margin: 0; background: #E9EBF0; color: var(--ink); font: 400 11px/1.5 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, sans-serif; }
    .page { max-width: 297mm; margin: 16px auto; padding: 10mm; background: #fff; box-shadow: 0 4px 20px rgba(0, 0, 0, .12); }
    .num { direction: ltr; unicode-bidi: isolate; font-variant-numeric: tabular-nums; }
    .bar { height: 5px; border-radius: 3px; background: linear-gradient(90deg, var(--brand), var(--brand-2)); margin-bottom: 14px; }
    .head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; padding-bottom: 10px; border-bottom: 1px solid var(--line); }
    .head h1 { margin: 0; font-size: 18px; color: var(--brand); }
    .head h2 { margin: 2px 0 0; font-size: 13px; font-weight: 600; }
    .head p { margin: 2px 0 0; color: var(--muted); font-size: 10px; }
    .head .meta { text-align: end; }
    .kpis { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin: 12px 0; }
    .kpi { border: 1px solid var(--line); border-top: 3px solid var(--c, var(--brand)); border-radius: 8px; padding: 6px 10px; background: var(--soft); }
    .kpi span { display: block; color: var(--muted); font-size: 10px; }
    .kpi strong { display: block; font-size: 14px; color: var(--c, var(--brand)); }
    table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
    th { background: var(--brand); color: #fff; font-weight: 600; text-align: start; padding: 6px 8px; white-space: nowrap; font-size: 10px; }
    td { padding: 5px 8px; border-top: 1px solid var(--line); vertical-align: top; }
    tbody tr:nth-child(even) td { background: var(--soft); }
    .n { text-align: end; white-space: nowrap; }
    .c { text-align: center; color: var(--muted); }
    .strong { font-weight: 700; }
    .sub { display: block; color: var(--muted); font-size: 9.5px; }
    tfoot td { background: var(--tint); color: var(--brand); font-weight: 700; border-top: 2px solid var(--brand); }
    .badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 9.5px; font-weight: 700; border: 1px solid; white-space: nowrap; }
    .badge--paid { color: #0A6252; background: #E0F3EF; border-color: #8CCABD; }
    .badge--partial { color: #0A5A82; background: #E1F0F8; border-color: #8DC1DD; }
    .badge--due { color: #7C4A00; background: #FFF1D3; border-color: #E3B75F; }
    .badge--overdue { color: #AE231B; background: #FDE8E6; border-color: #EDA29B; }
    .empty { padding: 24px; text-align: center; color: var(--muted); }
    .toolbar { max-width: 297mm; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
    .toolbar button { font: inherit; font-size: 13px; font-weight: 600; padding: 8px 18px; border-radius: 8px; border: 1px solid var(--brand); background: var(--brand); color: #fff; cursor: pointer; }
    .toolbar button + button { background: #fff; color: var(--brand); }
    @media print {
        @page { size: A4 landscape; margin: 8mm; }
        body { background: #fff; }
        .page { margin: 0; padding: 0; box-shadow: none; max-width: none; }
        .toolbar { display: none; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">{{ $export->phrase('print') }}</button>
    <button type="button" onclick="window.close()">{{ $export->phrase('close') }}</button>
</div>
<main class="page">
    <div class="bar"></div>
    <header class="head">
        <div>
            <h1>{{ $business_name }}</h1>
            <h2>{{ $export->phrase('xl_title') }} · {{ $export->periodText() }}</h2>
            @if($note !== '')<p>{{ $export->phrase('active_filters') }}: {{ $note }}</p>@endif
        </div>
        <div class="meta">
            <p>{{ $export->phrase('xl_generated', ['date' => now()->format('Y-m-d H:i'), 'user' => trim(auth()->user()->first_name.' '.auth()->user()->last_name)]) }}</p>
        </div>
    </header>

    <section class="kpis">
        <div class="kpi"><span>{{ $export->phrase('kpi_total') }}</span><strong class="num">{{ $fmt($total) }}</strong></div>
        <div class="kpi" style="--c: #0A6252"><span>{{ $export->phrase('kpi_paid') }}</span><strong class="num">{{ $fmt($sum('paid')) }}</strong></div>
        <div class="kpi" style="--c: #7C4A00"><span>{{ $export->phrase('kpi_due') }}</span><strong class="num">{{ $fmt($sum('due')) }}</strong></div>
        <div class="kpi" style="--c: #AE231B"><span>{{ $export->phrase('kpi_returns') }}</span><strong class="num">{{ $fmt($sum('returns')) }}</strong></div>
        <div class="kpi" style="--c: #0A5A82"><span>{{ $export->phrase('kpi_invoices') }}</span><strong class="num">{{ number_format($count) }}</strong></div>
        <div class="kpi" style="--c: #5B6475"><span>{{ $export->phrase('kpi_average') }}</span><strong class="num">{{ $fmt($count ? $total / $count : 0) }}</strong></div>
    </section>

    <table>
        <thead>
            <tr>
                <th class="c">#</th>
                <th>{{ $export->phrase('col_invoice') }}</th>
                <th>{{ $export->phrase('col_customer') }}</th>
                <th>{{ $export->phrase('col_location') }}</th>
                <th class="n">{{ $export->phrase('col_total') }}</th>
                <th class="n">{{ $export->phrase('col_paid') }}</th>
                <th class="n">{{ $export->phrase('col_due') }}</th>
                <th class="n">{{ $export->phrase('kpi_returns') }}</th>
                <th>{{ $export->phrase('col_status') }}</th>
                <th>{{ $export->phrase('col_method') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                @php $v = $record['values']; @endphp
                <tr>
                    <td class="c num">{{ $v['n'] }}</td>
                    <td><span class="strong num">{{ $v['invoice'] }}</span><span class="sub num">{{ \Carbon\Carbon::parse($v['date'])->format('Y-m-d H:i') }}</span></td>
                    <td>{{ $v['customer'] }}@if($v['mobile'] !== '')<span class="sub num">{{ $v['mobile'] }}</span>@endif</td>
                    <td>{{ $v['location'] }}</td>
                    <td class="n num strong">{{ $fmt($v['total']) }}</td>
                    <td class="n num">{{ $fmt($v['paid']) }}</td>
                    <td class="n num">{{ $fmt($v['due']) }}</td>
                    <td class="n num">{{ $v['returns'] ? $fmt($v['returns']) : '' }}</td>
                    <td>@if($v['status'] !== '')<span class="badge badge--{{ $tones[$record['tone']] ?? 'due' }}">{{ $v['status'] }}</span>@endif @if(! empty($v['receive']))<span class="sub">{{ $v['receive'] }}</span>@endif</td>
                    <td>{{ $v['method'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">{{ $export->phrase('empty') }}</td></tr>
            @endforelse
        </tbody>
        @if($count)
            <tfoot>
                <tr>
                    <td colspan="4">{{ $export->phrase('xl_total_row') }} <span class="num">({{ number_format($count) }})</span></td>
                    <td class="n num">{{ $fmt($total) }}</td>
                    <td class="n num">{{ $fmt($sum('paid')) }}</td>
                    <td class="n num">{{ $fmt($sum('due')) }}</td>
                    <td class="n num">{{ $fmt($sum('returns')) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</main>
<script>
    // الطباعة بتبدأ لوحدها بعد ما الخط يحمّل
    window.addEventListener('load', function () {
        (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(function () { setTimeout(function () { window.print(); }, 150); });
    });
</script>
</body>
</html>

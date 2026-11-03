{{--
    تقرير طباعة الموردين أو العملاء بكل الفلاتر (contacts/list-export?format=print).
    صفحة مستقلة من غير layout التطبيق، بتفتح في تاب جديد وبتطبع نفسها. التصميم من .rr-report في raqmi.css، ودايما بالوضع الفاتح.
--}}
@php
    $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'ur', 'he']);
    $currency = session('currency', []);
    $fmt = fn ($n) => number_format((float) $n, max(0, $precision), (string) ($currency['decimal_separator'] ?? '.'), (string) ($currency['thousand_separator'] ?? ','));
    $sum = fn ($key) => array_sum(array_map(fn ($r) => $r['values'][$key], $records));
    $count = count($records);
    $owing = count(array_filter($records, fn ($r) => $r['values']['total_due'] > 0));
    $title = __('contact_list.report_title_' . ($only_due ? 'due_' : '') . $type);
    $user = trim(auth()->user()->first_name . ' ' . auth()->user()->last_name);
    // الأعمدة الثابتة: # وجهة الاتصال وأعمدة المستحق التلاتة
    $colspan = 2 + count(array_filter($columns, fn ($column) => ! $column[2]));
    $money_columns = array_filter($columns, fn ($column) => $column[2]);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<link rel="stylesheet" href="{{ asset('css/saudi-riyal-symbol.css') }}">
<link rel="stylesheet" href="{{ asset('css/raqmi.css?v=' . ($asset_v ?? '')) }}">
<style>
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 400; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.woff2') }}') format('woff2'); }
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 600; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-SemiBold.woff2') }}') format('woff2'); }
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 700; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.woff2') }}') format('woff2'); }
    @page { size: A4 {{ count($columns) > 2 ? 'landscape' : 'portrait' }}; margin: 10mm; }
</style>
</head>
<body class="rr-page rr-report-page">
<div class="rr-report-page__bar">
    <button type="button" class="rr-btn rr-btn--secondary" onclick="window.close()">@lang('contact_list.close')</button>
    <button type="button" class="rr-btn rr-btn--primary" onclick="window.print()">@include('partials.rr.icon', ['name' => 'print', 'size' => 18])@lang('contact_list.print')</button>
</div>
<main class="rr-report{{ count($columns) > 2 ? ' rr-report--wide' : '' }}">
    <header class="rr-report__head">
        <div class="rr-report__brand">
            @if(! empty($business_logo))
                <img class="rr-report__logo" src="{{ asset('uploads/business_logos/' . $business_logo) }}" alt="">
            @endif
            <div>
                <p class="rr-report__business">{{ $business_name }}</p>
                <h1 class="rr-report__title">{{ $title }}</h1>
            </div>
        </div>
        <dl class="rr-report__meta">
            <div><dt>@lang('contact_list.report_date')</dt><dd class="rr-num">{{ now()->format('Y-m-d H:i') }}</dd></div>
            <div><dt>@lang('contact_list.report_by')</dt><dd>{{ $user }}</dd></div>
            <div><dt>@lang('contact_list.report_count_' . $type)</dt><dd class="rr-num">{{ number_format($count) }}</dd></div>
        </dl>
    </header>
    @if($note !== '')
        <p class="rr-report__filters"><strong>@lang('contact_list.active_filters'):</strong> {{ $note }}</p>
    @endif

    <table class="rr-report__table">
        <thead>
            <tr>
                <th class="rr-report__n">#</th>
                <th>@lang('contact_list.col_contact')</th>
                @foreach($columns as $column)
                    @unless($column[2])<th>{{ $column[0] }}</th>@endunless
                @endforeach
                <th class="rr-report__amount">@lang('contact_list.report_col_invoice_due')</th>
                <th class="rr-report__amount">@lang('contact_list.report_col_opening_left')</th>
                @foreach($money_columns as $column)
                    <th class="rr-report__amount">{{ $column[0] }}</th>
                @endforeach
                <th class="rr-report__amount rr-report__total-col">@lang('contact_list.report_col_total')</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                @php $v = $record['values']; @endphp
                <tr>
                    <td class="rr-report__n"><span class="rr-num">{{ $v['n'] }}</span></td>
                    <td>
                        <span class="rr-report__name">{{ $v['business_name'] !== '' ? $v['business_name'] : $v['name'] }}</span>
                        <span class="rr-report__sub">{{ $v['business_name'] !== '' ? $v['name'] . ' · ' : '' }}<span class="rr-num">{{ $v['contact_id'] }}</span>@if($record['inactive']) · {{ $v['status'] }}@endif</span>
                    </td>
                    @foreach($columns as $key => $column)
                        @unless($column[2])
                            <td><span @class(['rr-num' => in_array($key, ['mobile', 'tax_number', 'credit_limit', 'created_at'])])>{{ $key === 'credit_limit' && is_numeric($v[$key]) ? $fmt($v[$key]) : $v[$key] }}</span></td>
                        @endunless
                    @endforeach
                    <td class="rr-report__amount{{ $v['invoice_due'] == 0 ? ' rr-report__zero' : '' }}"><span class="rr-num">{{ $fmt($v['invoice_due']) }}</span></td>
                    <td class="rr-report__amount{{ $v['opening_left'] == 0 ? ' rr-report__zero' : '' }}"><span class="rr-num">{{ $fmt($v['opening_left']) }}</span></td>
                    @foreach($money_columns as $key => $column)
                        <td class="rr-report__amount{{ $v[$key] == 0 ? ' rr-report__zero' : '' }}"><span class="rr-num">{{ $fmt($v[$key]) }}</span></td>
                    @endforeach
                    <td class="rr-report__amount rr-report__total-col{{ $v['total_due'] == 0 ? ' rr-report__zero' : '' }}"><span class="rr-num">{{ $fmt($v['total_due']) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="{{ $colspan + 3 + count($money_columns) }}" class="rr-report__empty">@lang('contact_list.empty')</td></tr>
            @endforelse
        </tbody>
        @if($count)
            <tfoot>
                <tr>
                    <td colspan="{{ $colspan }}">@lang('contact_list.xl_total_row')</td>
                    <td class="rr-report__amount"><span class="rr-num">{{ $fmt($sum('invoice_due')) }}</span></td>
                    <td class="rr-report__amount"><span class="rr-num">{{ $fmt($sum('opening_left')) }}</span></td>
                    @foreach($money_columns as $key => $column)
                        <td class="rr-report__amount"><span class="rr-num">{{ $fmt($sum($key)) }}</span></td>
                    @endforeach
                    <td class="rr-report__amount"><span class="rr-num">{{ $fmt($sum('total_due')) }}</span></td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if($count)
        <section class="rr-report__summary">
            <dl class="rr-report__breakdown">
                <div><dt>@lang('contact_list.report_count_owing_' . $type)</dt><dd class="rr-num">{{ number_format($owing) }}</dd></div>
                <div><dt>@lang('contact_list.report_col_invoice_due')</dt><dd><span class="rr-money">@include('partials.rr.money', ['value' => $sum('invoice_due')])</span></dd></div>
                <div><dt>@lang('contact_list.report_col_opening_left')</dt><dd><span class="rr-money">@include('partials.rr.money', ['value' => $sum('opening_left')])</span></dd></div>
            </dl>
            <div class="rr-report__grand">
                <span class="rr-report__grand-label">@lang('contact_list.report_total_due_' . $type)</span>
                <span class="rr-report__grand-value rr-money">@include('partials.rr.money', ['value' => $sum('total_due')])</span>
            </div>
        </section>
    @endif
</main>
<script>
    // الطباعة بتبدأ لوحدها بعد ما الخط يحمّل
    window.addEventListener('load', function () {
        (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(function () { setTimeout(function () { window.print(); }, 150); });
    });
</script>
</body>
</html>

{{--
    كشف الحساب للطباعة والـ PDF والإرسال بالبريد. نفس القالب بيتفتح في المتصفح (action=print) وفي mPDF (action=pdf)،
    فالتنسيق كله جداول وألوان صريحة من غير متغيرات CSS ولا flex/grid لأن mPDF مش بيدعمهم. الألوان منسوخة من توكنز raqmi.css.
    الأرقام والتواريخ جوه <bdo dir="ltr"> عشان تطلع صح في الاتجاهين.
--}}
@php
    $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'ur', 'he']);
    $for_pdf = ! empty($for_pdf);
    $format = in_array($format ?? null, ['format_1', 'format_2', 'format_3', 'format_4']) ? $format : 'format_1';
    $landscape = in_array($format, ['format_3', 'format_4']);
    $currency = session('currency', []);
    $precision = (int) session('business.currency_precision', 2);
    $fmt = fn ($n) => number_format((float) $n, max(0, $precision), (string) ($currency['decimal_separator'] ?? '.'), (string) ($currency['thousand_separator'] ?? ','));
    // preg بـ u مش trim: trim بتشتغل على البايتات فبتكسر أول حرف عربي
    $plain = fn ($html) => preg_replace('/^[\s,،\x{00A0}]+|[\s,،\x{00A0}]+$/u', '', strip_tags(preg_replace('/\s*(<br\s*\/?>\s*)+/i', '، ', html_entity_decode((string) $html))));

    $is_customer = in_array($contact->type, ['customer', 'both']);
    $is_supplier = in_array($contact->type, ['supplier', 'both']);
    $business = $contact->business;
    $logo = ! empty($business->logo) ? $business->logo : null;
    $logo_src = $logo ? ($for_pdf ? public_path('uploads/business_logos/' . $logo) : asset('uploads/business_logos/' . $logo)) : null;
    if ($logo && $for_pdf && ! file_exists($logo_src)) {
        $logo_src = null;
    }
    $address = $plain(! empty($location) ? $location->location_address : $business->business_address);
    // العنوان بس، من غير الاسم اللي الـ accessor بيحطه في أوله
    $contact_address = $plain(implode('<br>', array_filter([$contact->address_line_1, $contact->address_line_2, $contact->city, $contact->state, $contact->country, $contact->zip_code])));
    $user = auth()->user() ? trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) : '';
    $cheques = $pending_cheques ?? collect();
    $period_due = $ledger_details['balance_due'] - $ledger_details['ledger_discount'];
    $overall_due = $ledger_details['all_balance_due'] ?? $period_due;
    $with_aging = in_array($format, ['format_2', 'format_4']);
    $kinds = ['sell', 'purchase', 'sell_return', 'purchase_return', 'ledger_discount'];
    $status_tone = ['paid' => 'ok', 'partial' => 'info', 'due' => 'warn'];
    $status_keys = [__('lang_v1.paid') => 'paid', __('lang_v1.partial') => 'partial', __('lang_v1.due') => 'due'];
    $balance_label = fn ($text) => str_replace([' ' . __('lang_v1.dr'), ' ' . __('lang_v1.cr')], [' ' . __('contact_statement.dr'), ' ' . __('contact_statement.cr')], (string) $text);

    $rows = [];
    $total_debit = 0;
    $total_credit = 0;
    $total_amount = 0;
    $total_due = 0;
    $closing_balance = '';
    $aging = ['current' => 0, 'days_30' => 0, 'days_60' => 0, 'days_90' => 0, 'over_90' => 0, 'total' => 0];

    foreach ($ledger_details['ledger'] as $data) {
        $kind = $data['transaction_type'] ?? null;
        $is_payment = array_key_exists('payment_method_key', $data);
        $is_opening = $data['type'] == __('lang_v1.opening_balance');
        $has_due = ! empty($data['total_due']);

        // نفس حساب الأعمار في النماذج القديمة
        if ($with_aging && $has_due && ($data['payment_status'] ?? '') != 'paid' && ! empty($data['due_date'])) {
            $row_due = $kind == 'ledger_discount' ? -1 * $data['total_due'] : $data['total_due'];
            $data['total_due'] = $row_due;
            $aging['total'] += $row_due;
            $days = $data['due_date']->diffInDays();
            $bucket = $days == 0 ? 'current' : ($days <= 30 ? 'days_30' : ($days <= 60 ? 'days_60' : ($days <= 90 ? 'days_90' : 'over_90')));
            $aging[$bucket] += $row_due;
        }

        if ($format == 'format_2' && ! $has_due) {
            continue;
        }
        if ($format != 'format_2' && $is_opening && empty((float) $data['debit']) && empty((float) $data['credit'])) {
            continue;
        }

        // الدفع من الرصيد المقدم مش بيدخل في الرصيد (getLedgerDetails)، فمش بيدخل في المجموع برضه
        $counted = ($data['payment_method_key'] ?? '') != 'advance';
        if ($counted) {
            $total_debit += (float) ($data['debit'] ?: 0);
            $total_credit += (float) ($data['credit'] ?: 0);
        }
        $total_amount += (float) ($data['final_total'] ?? 0);
        $total_due += (float) ($data['total_due'] ?? 0);
        $closing_balance = $data['balance'] ?? $closing_balance;

        $lines = [];
        if ($format == 'format_3') {
            foreach ($data['sell_lines'] ?? [] as $line) {
                $lines[] = [$line->product->name ?? '', $line->quantity, $line->sub_unit->short_name ?? ($line->product->unit->short_name ?? ''), $line->unit_price_inc_tax, $line->quantity * $line->unit_price_inc_tax];
            }
            foreach ($data['purchase_lines'] ?? [] as $line) {
                $lines[] = [$line->product->name ?? '', $line->quantity, $line->sub_unit->short_name ?? ($line->product->unit->short_name ?? ''), $line->purchase_price_inc_tax, $line->quantity * $line->purchase_price_inc_tax];
            }
        }

        $status_key = $status_keys[$data['payment_status'] ?? ''] ?? ($data['payment_status'] ?? '');
        $rows[] = [
            'date' => $data['date'],
            'label' => $is_payment ? __('contact_show.kind_payment') : (in_array($kind, $kinds) ? __('contact_show.kind_' . $kind) : $data['type']),
            'tone' => $is_payment ? 'ok' : (in_array($kind, ['sell', 'purchase']) ? 'brand' : (in_array($kind, ['sell_return', 'purchase_return']) ? 'warn' : ($kind == 'ledger_discount' ? 'info' : 'muted'))),
            'ref' => $data['ref_no'] ?? '',
            'location' => $data['location'] ?? '',
            'status' => isset($status_tone[$status_key]) ? __('lang_v1.' . $status_key) : ($data['payment_status'] ?? ''),
            'status_tone' => $status_tone[$status_key] ?? 'muted',
            'debit' => $data['debit'] ?? '',
            'credit' => $data['credit'] ?? '',
            'balance' => $data['balance'] ?? '',
            'method' => $data['payment_method'] ?? '',
            'note' => trim(strip_tags(str_replace('<small>', ' ', (string) ($data['others'] ?? '')))),
            'amount' => $data['final_total'] ?? 0,
            'due' => $data['total_due'] ?? 0,
            'due_date' => $data['due_date'] ?? null,
            'lines' => $lines,
        ];
    }

    $has_location = in_array($format, ['format_1', 'format_3']) && collect($rows)->pluck('location')->filter()->unique()->count() > 1;
    $show_status = in_array($format, ['format_1', 'format_3']);
    $show_balance = in_array($format, ['format_3', 'format_4']);
    // عمود الملاحظة بياخد مساحة كبيرة، فبيظهر بس لو فيه ملاحظات
    $show_note = $format != 'format_2' && collect($rows)->pluck('note')->filter()->isNotEmpty();
    // عدد الأعمدة قبل المبالغ، عشان colspan بتاع سطر الإجمالي
    $lead_cols = 3 + ($has_location ? 1 : 0) + ($show_status ? 1 : 0) + ($format == 'format_2' ? 1 : 0) + ($format == 'format_4' ? 1 : 0);
    $total_cols = $lead_cols + 2 + ($show_balance ? 1 : 0) + ($format == 'format_1' || $format == 'format_3' ? 1 : 0) + ($show_note ? 1 : 0);
    $title = __('contact_statement.title');
    $party_label = $is_customer && $is_supplier ? __('contact_statement.party_both') : ($is_supplier ? __('contact_statement.party_supplier') : __('contact_statement.party_customer'));
    $due_label = $is_customer && $is_supplier ? __('contact_show.due_both') : ($is_supplier ? __('contact_show.due_supplier') : __('contact_show.due_customer'));
    if ($overall_due < 0) {
        $due_label = $is_customer && $is_supplier ? __('contact_statement.credit_both') : ($is_supplier ? __('contact_statement.credit_supplier') : __('contact_statement.credit_customer'));
    }
    $code = $currency['code'] ?? ($currency['symbol'] ?? '');
    $stat_count = 3 + ($is_customer ? 1 : 0) + ($is_supplier ? 1 : 0) + ($ledger_details['ledger_discount'] > 0 ? 1 : 0);
    $stat_width = 'width: ' . floor(94 / $stat_count) . '%;';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} - {{ $contact->name }}</title>
<style>
    @if(! $for_pdf)
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 400; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.woff2') }}') format('woff2'); }
    @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 700; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.woff2') }}') format('woff2'); }
    @page { size: A4 {{ $landscape ? 'landscape' : 'portrait' }}; margin: 10mm; }
    html { background: #EDEFF4; }
    body { margin: 0 auto; padding: 16px; max-width: {{ $landscape ? '297mm' : '210mm' }}; box-sizing: border-box; }
    .sheet { background: #FFFFFF; padding: 10mm; }
    .bar { margin-bottom: 12px; text-align: {{ $rtl ? 'left' : 'right' }}; }
    .bar button { height: 40px; margin-inline-start: 6px; padding: 0 18px; border: 1px solid #7E879A; border-radius: 8px; background: #FFFFFF; color: #11141B; font: inherit; font-weight: 700; cursor: pointer; }
    .bar button.main { background: #22177A; border-color: #22177A; color: #FFFFFF; }
    @media (max-width: 640px) {
        body { padding: 8px; }
        .sheet { padding: 12px; overflow-x: auto; }
    }
    @media print {
        html { background: none; }
        body { padding: 0; max-width: none; }
        .sheet { padding: 0; }
        .bar { display: none; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    @endif
    body { font-family: ibmplex, 'IBM Plex Sans Arabic', Tahoma, sans-serif; font-size: 9.5pt; color: #11141B; }
    table { border-collapse: collapse; }
    .full { width: 100%; }
    .top td { vertical-align: top; padding: 0; }
    .rule { height: 3px; background: #22177A; }
    .logo { max-height: 52px; max-width: 110px; }
    .biz { font-size: 13pt; font-weight: bold; color: #11141B; }
    .sub { font-size: 8.5pt; color: #454E60; }
    .doc-title { font-size: 17pt; font-weight: bold; color: #22177A; }
    .gap { height: 10px; }
    .box { border: 1px solid #DADFE8; padding: 8px 10px; vertical-align: top; width: 50%; }
    .box-gap { width: 10px; border: 0; }
    .box-title { font-size: 8pt; color: #454E60; }
    .box-name { font-size: 11pt; font-weight: bold; }
    .kv td { padding: 1px 0; font-size: 8.5pt; }
    .kv .k { color: #454E60; width: 80pt; }
    .stat { padding: 7px 9px; vertical-align: top; }
    .stat-gap { width: 6px; }
    .stat-l { font-size: 8pt; color: #454E60; }
    .stat-v { font-size: 11.5pt; font-weight: bold; }
    .s-plain { background: #EDEFF4; }
    .s-brand { background: #ECEAFB; }
    .s-ok { background: #E0F3EF; }
    .s-info { background: #E1F0F8; }
    .s-warn { background: #FFF1D3; }
    .ink-ok { color: #0A6252; }
    .ink-info { color: #0A5A82; }
    .ink-warn { color: #7C4A00; }
    .ink-brand { color: #22177A; }
    .ink-muted { color: #454E60; }
    .sec { font-size: 10pt; font-weight: bold; color: #22177A; padding: 10px 0 4px; }
    .grid { width: 100%; }
    .grid th { background: #22177A; color: #FFFFFF; font-size: 8pt; font-weight: bold; padding: 6px 7px; text-align: {{ $rtl ? 'right' : 'left' }}; }
    .grid td { padding: 5px 7px; border-bottom: 1px solid #DADFE8; font-size: 8.5pt; vertical-align: middle; }
    .grid .num { text-align: {{ $rtl ? 'left' : 'right' }}; white-space: nowrap; }
    .grid .odd td { background: #F6F7FA; }
    .grid .b { font-weight: bold; }
    .grid .lines td { background: #F6F7FA; padding: 3px 7px 6px; font-size: 7.5pt; color: #454E60; }
    .grid .sum td { background: #ECEAFB; border-bottom: 0; font-weight: bold; color: #22177A; }
    .empty { padding: 26px 0; text-align: center; color: #454E60; border: 1px solid #DADFE8; }
    .grand { background: #FFF1D3; padding: 9px 12px; width: 42%; }
    .grand-ok { background: #E0F3EF; }
    .grand-l { font-size: 8.5pt; font-weight: bold; }
    .grand-v { font-size: 15pt; font-weight: bold; }
    .note { font-size: 8pt; color: #454E60; }
    .age th { background: #EDEFF4; color: #454E60; font-size: 7.5pt; font-weight: bold; padding: 5px 7px; text-align: center; border: 1px solid #DADFE8; }
    .age td { padding: 5px 7px; font-size: 8.5pt; text-align: center; border: 1px solid #DADFE8; }
    tr { page-break-inside: avoid; }
    thead { display: table-header-group; }
</style>
</head>
<body>
@unless($for_pdf)
    <div class="bar">
        <button type="button" onclick="window.close()">@lang('contact_statement.close')</button>
        <button type="button" class="main" onclick="window.print()">@lang('contact_statement.print')</button>
    </div>
@endunless
<div class="sheet">
    <div class="rule"></div>
    <table class="full top" style="margin-top: 10px;">
        <tr>
            <td style="width: 60%;">
                <table class="top">
                    <tr>
                        @if($logo_src)
                            <td style="padding-{{ $rtl ? 'left' : 'right' }}: 10px;"><img class="logo" src="{{ $logo_src }}" alt=""></td>
                        @endif
                        <td>
                            <div class="biz">{{ $business->name }}</div>
                            @if($address !== '')<div class="sub">{{ $address }}</div>@endif
                            @if(! empty($business->tax_number_1))<div class="sub">{{ $business->tax_label_1 ?: __('contact_show.tax_number') }}: <bdo dir="ltr">{{ $business->tax_number_1 }}</bdo></div>@endif
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 40%; text-align: {{ $rtl ? 'left' : 'right' }};">
                <div class="doc-title">{{ $title }}</div>
                <div class="sub"><bdo dir="ltr">{{ $ledger_details['start_date'] }}</bdo> @lang('contact_statement.to') <bdo dir="ltr">{{ $ledger_details['end_date'] }}</bdo></div>
            </td>
        </tr>
    </table>
    <div class="gap"></div>

    <table class="full">
        <tr>
            <td class="box">
                <div class="box-title">{{ $party_label }}</div>
                <div class="box-name">{{ $contact->supplier_business_name ?: $contact->name }}</div>
                <table class="full kv">
                    @if(! empty($contact->supplier_business_name) && ! empty(trim($contact->name)))
                        <tr><td class="k">@lang('contact_statement.contact_person')</td><td>{{ $contact->name }}</td></tr>
                    @endif
                    <tr><td class="k">@lang('contact_statement.code')</td><td><bdo dir="ltr">{{ $contact->contact_id }}</bdo></td></tr>
                    @if(! empty($contact->mobile))<tr><td class="k">@lang('contact_show.mobile')</td><td><bdo dir="ltr">{{ $contact->mobile }}</bdo></td></tr>@endif
                    @if(! empty($contact->tax_number))<tr><td class="k">@lang('contact_show.tax_number')</td><td><bdo dir="ltr">{{ $contact->tax_number }}</bdo></td></tr>@endif
                    @if($contact_address !== '')<tr><td class="k">@lang('contact_show.address')</td><td>{{ $contact_address }}</td></tr>@endif
                </table>
            </td>
            <td class="box-gap"></td>
            <td class="box">
                <div class="box-title">@lang('contact_statement.details')</div>
                <table class="full kv">
                    <tr><td class="k">@lang('contact_statement.issue_date')</td><td><bdo dir="ltr">{{ now()->format(session('business.date_format', 'Y-m-d') . ' H:i') }}</bdo></td></tr>
                    <tr><td class="k">@lang('contact_statement.currency')</td><td><bdo dir="ltr">{{ $code }}</bdo></td></tr>
                    @if(! empty($location))<tr><td class="k">@lang('contact_show.location')</td><td>{{ $location->name }}</td></tr>@endif
                    @if($user !== '')<tr><td class="k">@lang('contact_statement.issued_by')</td><td>{{ $user }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>
    <div class="gap"></div>

    <table class="full">
        <tr>
            <td class="stat s-plain" style="{{ $stat_width }}"><div class="stat-l">@lang('contact_show.opening_balance')</div><div class="stat-v"><bdo dir="ltr">{{ $fmt($ledger_details['beginning_balance']) }}</bdo></div></td>
            <td class="stat-gap"></td>
            @if($is_customer)
                <td class="stat s-brand" style="{{ $stat_width }}"><div class="stat-l">@lang('contact_show.invoices')</div><div class="stat-v ink-brand"><bdo dir="ltr">{{ $fmt($ledger_details['total_invoice']) }}</bdo></div></td>
                <td class="stat-gap"></td>
            @endif
            @if($is_supplier)
                <td class="stat s-brand" style="{{ $stat_width }}"><div class="stat-l">@lang('contact_show.purchases')</div><div class="stat-v ink-brand"><bdo dir="ltr">{{ $fmt($ledger_details['total_purchase']) }}</bdo></div></td>
                <td class="stat-gap"></td>
            @endif
            <td class="stat s-ok" style="{{ $stat_width }}"><div class="stat-l">@lang('contact_show.payments')</div><div class="stat-v ink-ok"><bdo dir="ltr">{{ $fmt($ledger_details['total_paid']) }}</bdo></div></td>
            <td class="stat-gap"></td>
            @if($ledger_details['ledger_discount'] > 0)
                <td class="stat s-info" style="{{ $stat_width }}"><div class="stat-l">@lang('contact_show.discounts')</div><div class="stat-v ink-info"><bdo dir="ltr">{{ $fmt($ledger_details['ledger_discount']) }}</bdo></div></td>
                <td class="stat-gap"></td>
            @endif
            <td class="stat s-warn" style="{{ $stat_width }}"><div class="stat-l">@lang('contact_show.period_due')</div><div class="stat-v ink-warn"><bdo dir="ltr">{{ $fmt($period_due) }}</bdo></div></td>
        </tr>
    </table>

    @if(count($cheques) > 0)
        @php
            $cheque_types = $cheques->pluck('type')->unique();
            $show_cheque_type = $cheque_types->count() > 1;
        @endphp
        <div class="sec">@lang('cheque.pending_cheques_on_contact')</div>
        <div class="note">@lang('cheque.pending_cheques_note')</div>
        <table class="grid" style="margin-top: 4px;">
            <thead>
                <tr>
                    <th>@lang('cheque.cheque_no')</th>
                    <th>@lang('cheque.bank_name')</th>
                    @if($show_cheque_type)<th>@lang('lang_v1.type')</th>@endif
                    <th>@lang('cheque.invoice')</th>
                    <th>@lang('cheque.due_date')</th>
                    <th class="num">@lang('cheque.amount')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cheques as $cheque)
                    <tr>
                        <td><bdo dir="ltr">{{ $cheque->cheque_number }}</bdo></td>
                        <td>{{ $cheque->bank_name }}</td>
                        @if($show_cheque_type)<td>@lang('cheque.' . $cheque->type . '_short')</td>@endif
                        <td>
                            @if(! empty($cheque->transaction))
                                <bdo dir="ltr">{{ $cheque->type == 'receivable' ? $cheque->transaction->invoice_no : $cheque->transaction->ref_no }}</bdo>
                            @else
                                @lang('cheque.on_account')
                            @endif
                        </td>
                        <td><bdo dir="ltr">{{ @format_date($cheque->due_date) }}</bdo></td>
                        <td class="num"><bdo dir="ltr">{{ $fmt($cheque->amount) }}</bdo></td>
                    </tr>
                @endforeach
                @unless($show_cheque_type)
                    <tr class="sum">
                        <td colspan="4">@lang('cheque.uncovered_due')</td>
                        <td class="num"><bdo dir="ltr">{{ $fmt($overall_due - $cheques->sum('amount')) }}</bdo></td>
                    </tr>
                @endunless
            </tbody>
        </table>
    @endif

    <div class="sec">@lang('contact_statement.transactions')</div>
    @if(empty($rows))
        <div class="empty">@lang('contact_show.no_moves')</div>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 120pt;">@lang('contact_show.col_date')</th>
                    <th>@lang('contact_show.col_type')</th>
                    <th>@lang('contact_show.col_ref')</th>
                    @if($has_location)<th>@lang('contact_show.location')</th>@endif
                    @if($show_status)<th>@lang('contact_show.col_status')</th>@endif
                    @if($format == 'format_4')<th>@lang('contact_show.col_method')</th>@endif
                    @if($format == 'format_2')
                        <th>@lang('contact_statement.due_date')</th>
                        <th class="num">@lang('contact_statement.amount')</th>
                        <th class="num">@lang('contact_statement.remaining')</th>
                    @else
                        <th class="num">@lang('contact_show.col_debit')</th>
                        <th class="num">@lang('contact_show.col_credit')</th>
                    @endif
                    @if($show_balance)<th class="num">@lang('contact_show.col_balance')</th>@endif
                    @if(in_array($format, ['format_1', 'format_3']))<th>@lang('contact_show.col_method')</th>@endif
                    @if($show_note)<th>@lang('contact_show.col_note')</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr class="{{ $loop->even ? 'odd' : '' }}">
                        <td style="white-space: nowrap;"><bdo dir="ltr">{{ @format_datetime($row['date']) }}</bdo></td>
                        <td class="b ink-{{ $row['tone'] }}">{{ $row['label'] }}</td>
                        <td><bdo dir="ltr">{{ $row['ref'] }}</bdo></td>
                        @if($has_location)<td>{{ $row['location'] }}</td>@endif
                        @if($show_status)<td class="ink-{{ $row['status_tone'] }}">{{ $row['status'] }}</td>@endif
                        @if($format == 'format_4')<td>{{ $row['method'] }}</td>@endif
                        @if($format == 'format_2')
                            <td>@if(! empty($row['due_date']))<bdo dir="ltr">{{ @format_date($row['due_date']) }}</bdo>@endif</td>
                            <td class="num"><bdo dir="ltr">{{ $fmt($row['amount']) }}</bdo></td>
                            <td class="num b"><bdo dir="ltr">{{ $fmt($row['due']) }}</bdo></td>
                        @else
                            <td class="num">@if($row['debit'] != '')<bdo dir="ltr">{{ $fmt($row['debit']) }}</bdo>@endif</td>
                            <td class="num">@if($row['credit'] != '')<bdo dir="ltr">{{ $fmt($row['credit']) }}</bdo>@endif</td>
                        @endif
                        @if($show_balance)<td class="num b">{{ $balance_label($row['balance']) }}</td>@endif
                        @if(in_array($format, ['format_1', 'format_3']))<td>{{ $row['method'] }}</td>@endif
                        @if($show_note)<td>{{ $row['note'] }}</td>@endif
                    </tr>
                    @if(! empty($row['lines']))
                        <tr class="lines">
                            <td colspan="{{ $total_cols }}">
                                @foreach($row['lines'] as $line)
                                    <div>{{ $line[0] }} : <bdo dir="ltr">{{ @format_quantity($line[1]) }}</bdo> {{ $line[2] }} × <bdo dir="ltr">{{ $fmt($line[3]) }}</bdo> = <bdo dir="ltr">{{ $fmt($line[4]) }}</bdo></div>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                @endforeach
                <tr class="sum">
                    <td colspan="{{ $lead_cols }}">@lang('contact_statement.total_row', ['count' => count($rows)])</td>
                    @if($format == 'format_2')
                        <td class="num"><bdo dir="ltr">{{ $fmt($total_amount) }}</bdo></td>
                        <td class="num"><bdo dir="ltr">{{ $fmt($total_due) }}</bdo></td>
                    @else
                        <td class="num"><bdo dir="ltr">{{ $fmt($total_debit) }}</bdo></td>
                        <td class="num"><bdo dir="ltr">{{ $fmt($total_credit) }}</bdo></td>
                    @endif
                    @if($show_balance)<td class="num">{{ $balance_label($closing_balance) }}</td>@endif
                    @if(in_array($format, ['format_1', 'format_3']))<td></td>@endif
                    @if($show_note)<td></td>@endif
                </tr>
            </tbody>
        </table>
    @endif

    @if($with_aging)
        <div class="sec">@lang('contact_statement.aging')</div>
        <table class="full age">
            <tr>
                <th>@lang('contact_statement.aging_current')</th>
                <th>@lang('contact_statement.aging_30')</th>
                <th>@lang('contact_statement.aging_60')</th>
                <th>@lang('contact_statement.aging_90')</th>
                <th>@lang('contact_statement.aging_over')</th>
                <th>@lang('contact_statement.aging_total')</th>
            </tr>
            <tr>
                <td><bdo dir="ltr">{{ $fmt($aging['current']) }}</bdo></td>
                <td><bdo dir="ltr">{{ $fmt($aging['days_30']) }}</bdo></td>
                <td><bdo dir="ltr">{{ $fmt($aging['days_60']) }}</bdo></td>
                <td><bdo dir="ltr">{{ $fmt($aging['days_90']) }}</bdo></td>
                <td><bdo dir="ltr">{{ $fmt($aging['over_90']) }}</bdo></td>
                <td style="font-weight: bold;"><bdo dir="ltr">{{ $fmt($aging['total']) }}</bdo></td>
            </tr>
        </table>
    @endif

    <div class="gap"></div>
    <table class="full">
        <tr>
            <td style="vertical-align: bottom;"><span class="note">@lang('contact_statement.footer_note')</span></td>
            <td class="grand {{ $overall_due > 0 ? '' : 'grand-ok' }}">
                <div class="grand-l {{ $overall_due > 0 ? 'ink-warn' : 'ink-ok' }}">{{ $due_label }}</div>
                <div class="grand-v {{ $overall_due > 0 ? 'ink-warn' : 'ink-ok' }}"><bdo dir="ltr">{{ $fmt(abs($overall_due)) }} {{ $code }}</bdo></div>
                <div class="note">@lang('contact_statement.overall_hint')</div>
            </td>
        </tr>
    </table>
</div>
@unless($for_pdf)
    <script>
        // الطباعة بتبدأ لوحدها بعد ما الخط يحمّل
        window.addEventListener('load', function () {
            (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(function () { setTimeout(function () { window.print(); }, 150); });
        });
    </script>
@endunless
</body>
</html>

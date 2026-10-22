{{--
    مبلغ بعملة البيزنس (رقم + رمز، والريال السعودي بأيقونته):
    <span class="rr-money">@include('partials.rr.money', ['value' => $x])</span>
--}}
@php
    $rrCurrency = session('currency', []);
    $rrIsSar = strtoupper((string) ($rrCurrency['code'] ?? '')) === 'SAR';
    $rrSymbol = (string) ($rrCurrency['symbol'] ?? '');
    $rrBefore = session('business.currency_symbol_placement') === 'before';
    $rrAmount = number_format(
        (float) $value,
        max(0, min(6, (int) session('business.currency_precision', 2))),
        (string) ($rrCurrency['decimal_separator'] ?? '.'),
        (string) ($rrCurrency['thousand_separator'] ?? ',')
    );
@endphp
@if($rrBefore)@if($rrIsSar)<img class="sar-currency-icon" src="{{ asset('img/saudi-riyal-new.svg') }}" alt="ريال سعودي" width="10" height="11">@elseif($rrSymbol !== '')<span class="print-money-symbol">{{ $rrSymbol }}</span>@endif @endif<span class="rr-num">{{ $rrAmount }}</span>@if(! $rrBefore) @if($rrIsSar)<img class="sar-currency-icon" src="{{ asset('img/saudi-riyal-new.svg') }}" alt="ريال سعودي" width="10" height="11">@elseif($rrSymbol !== '')<span class="print-money-symbol">{{ $rrSymbol }}</span>@endif @endif

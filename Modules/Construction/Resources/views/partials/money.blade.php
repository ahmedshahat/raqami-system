@php
    $money = \Modules\Construction\Support\CurrencyFormatter::parts($value);
    $isSaudiRiyal = $money['code'] === 'SAR';
    $symbolFile = public_path('img/saudi-riyal-new.svg');
    $symbolSource = $isSaudiRiyal
        ? (!empty($pdfMode)
            ? 'data:image/svg+xml;base64,'.base64_encode(file_get_contents($symbolFile))
            : asset('img/saudi-riyal-new.svg'))
        : null;
@endphp
<span class="print-money print-money--{{ $money['position'] }}">@if($money['position'] === 'before')@include('construction::partials.money_symbol', ['money' => $money, 'isSaudiRiyal' => $isSaudiRiyal, 'symbolSource' => $symbolSource])@endif<span>{{ $money['amount'] }}</span>@if($money['position'] === 'after')@include('construction::partials.money_symbol', ['money' => $money, 'isSaudiRiyal' => $isSaudiRiyal, 'symbolSource' => $symbolSource])@endif</span>

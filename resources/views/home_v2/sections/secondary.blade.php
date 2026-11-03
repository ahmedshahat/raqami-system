@if(! empty($secondary))
    {{-- مؤشرات إضافية: مجموعة واحدة متقسمة بخطوط، والمستحق على العملاء بخلفية تحذير خفيفة --}}
    <section class="rr-statgroup-wrap" aria-label="@lang('home_v2.secondary')">
        <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: {{ count($secondary) }}">
            @foreach($secondary as $key => $stat)
                <div class="rr-stat{{ $key == 'receivable' ? ' rr-stat--tint-warning' : '' }}">
                    <div class="rr-stat__label">@lang('home_v2.stat_' . $key)</div>
                    <div class="rr-stat__value rr-money{{ (float) $stat['value'] == 0 ? ' rr-money--zero' : '' }}">@include('partials.rr.money', ['value' => $stat['value']])</div>
                    @if(($stat['opening'] ?? 0) > 0)
                        <div class="rr-stat__hint">@lang('home_v2.stat_opening_part') @include('partials.rr.money', ['value' => $stat['opening']])</div>
                    @endif
                    @if(($stat['credit'] ?? 0) > 0)
                        <div class="rr-stat__hint">@lang('home_v2.stat_credit_' . $key) @include('partials.rr.money', ['value' => $stat['credit']])</div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif

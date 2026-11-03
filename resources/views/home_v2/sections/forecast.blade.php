@if(! is_null($forecast))
    <section class="rr-card" aria-labelledby="rr-forecast-title">
        <header class="rr-card__head">
            <div class="rr-card__titles">
                <h2 id="rr-forecast-title" class="rr-card__title">@lang('home_v2.forecast')</h2>
                <p class="rr-card__desc">@lang('home_v2.forecast_desc')</p>
            </div>
        </header>
        @if($forecast->isEmpty())
            <div class="rr-list__row rr-attn rr-attn--clear">
                <span class="rr-attn__icon" aria-hidden="true">@include('partials.rr.icon', ['name' => 'check', 'size' => 18])</span>
                <span class="rr-list__title">@lang('home_v2.forecast_empty')</span>
            </div>
        @else
            <ul class="rr-list">
                @foreach($forecast as $item)
                    <li class="rr-list__row">
                        <div class="rr-list__main">
                            <span class="rr-list__title"><bdi>{{ $item->label }}</bdi></span>
                            <span class="rr-list__meta">
                                @lang('home_v2.forecast_meta', ['stock' => (float) $item->stock + 0, 'unit' => $item->unit, 'daily' => $item->daily + 0])
                            </span>
                        </div>
                        <div class="rr-list__end">
                            @if($item->stock <= 0)
                                <span class="rr-badge rr-badge--danger"><span class="rr-badge__dot"></span>@lang('home_v2.forecast_out')</span>
                            @else
                                <span class="rr-badge rr-badge--{{ $item->days_left <= 7 ? 'danger' : 'warning' }}">@lang('home_v2.forecast_days', ['days' => $item->days_left])</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif

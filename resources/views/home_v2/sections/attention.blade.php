{{-- محتاج انتباهك: البنود اللي فيها حاجة الأول، وصفها بلون حالتها الخفيف --}}
<section class="rr-card rr-home__fill" aria-labelledby="rr-attention-title">
    <header class="rr-card__head">
        <div class="rr-card__titles">
            <h2 id="rr-attention-title" class="rr-card__title">@lang('home_v2.attention')</h2>
            <p class="rr-card__desc">@lang('home_v2.attention_desc')</p>
        </div>
    </header>
    @if(empty($attention))
        <div class="rr-empty"><p class="rr-empty__text">@lang('home_v2.no_data')</p></div>
    @else
        <ul class="rr-list">
            @foreach(collect($attention)->sortBy(fn ($item) => $item['count'] > 0 ? 0 : 1) as $item)
                @php $needs = $item['count'] > 0; @endphp
                <li>
                    <a class="rr-list__row rr-attn rr-attn--{{ $needs ? $item['tone'] : 'clear' }}" href="{{ $item['url'] }}">
                        <span class="rr-attn__icon" aria-hidden="true">@include('partials.rr.icon', ['name' => $needs ? 'info' : 'check', 'size' => 18])</span>
                        <div class="rr-list__main">
                            <span class="rr-list__title">@lang('home_v2.attention_' . $item['key'])</span>
                            <span class="rr-list__meta">
                                @if(! $needs)
                                    @lang('home_v2.all_clear')
                                @elseif(! is_null($item['amount']))
                                    <span class="rr-money">@include('partials.rr.money', ['value' => $item['amount']])</span>
                                @else
                                    @lang('home_v2.attention_' . $item['key'] . '_hint')
                                @endif
                            </span>
                        </div>
                        <span class="rr-attn__count rr-num">{{ $item['count'] }}</span>
                        <span class="rr-list__go">@include('partials.rr.icon', ['name' => 'chevron-forward', 'size' => 16])</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>

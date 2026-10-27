{{-- ═══ كروت الموديولات (DataController::home_dashboard_widgets) ═══ --}}
@if(! empty($module_widgets))
    <section class="rr-section" aria-labelledby="rr-modules-title">
        <h2 id="rr-modules-title" class="rr-section-title">@lang('home_v2.modules')</h2>
        <div class="rr-home__modules">
            @foreach($module_widgets as $widget)
                <article class="rr-card">
                    <header class="rr-card__head">
                        <div class="rr-card__titles"><h3 class="rr-card__title">{{ $widget['title'] }}</h3></div>
                        @if($widget['url'])
                            <a class="rr-btn rr-btn--ghost rr-btn--sm" href="{{ $widget['url'] }}">@lang('home_v2.open')@include('partials.rr.icon', ['name' => 'chevron-forward', 'size' => 16])</a>
                        @endif
                    </header>
                    <div class="rr-minis">
                        @foreach($widget['items'] as $item)
                            @php $alert = in_array($item['tone'] ?? null, ['warning', 'danger']) && $item['value'] > 0; @endphp
                            <div class="rr-mini">
                                <span class="rr-mini__label">{{ $item['label'] }}</span>
                                <span class="rr-mini__value{{ (float) $item['value'] == 0 ? ' rr-money--zero' : '' }}">
                                    @if(! empty($item['money']))
                                        <span class="rr-money">@include('partials.rr.money', ['value' => $item['value']])</span>
                                    @else
                                        <span class="rr-num">{{ number_format((float) $item['value']) }}</span>
                                    @endif
                                    @if($alert)
                                        <span class="rr-badge rr-badge--{{ $item['tone'] }}">@include('partials.rr.icon', ['name' => 'info', 'size' => 14])@lang('home_v2.needs_follow_up')</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

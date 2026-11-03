{{--
    رأس تقرير: $eyebrow, $title, $description, $icon, $printUrl (اختياري), $printLabel, $meta (اختياري: [[icon, نص], ...])
--}}
<header class="rr-head">
    <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => $icon, 'size' => 24])</span>
    <div class="rr-head__titles">
        <div class="rr-eyebrow">{{ $eyebrow }}</div>
        <h1 id="rr-report-title" class="rr-head__title">{{ $title }}</h1>
        <p class="rr-head__sub">{{ $description }}</p>
        @if(! empty($meta))
            <div class="rr-head__meta">
                @foreach($meta as [$metaIcon, $metaText])
                    <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => $metaIcon, 'size' => 18]){{ $metaText }}</span>
                @endforeach
            </div>
        @endif
    </div>
    @if(! empty($printUrl))
        <div class="rr-head__actions">
            <a class="rr-btn rr-btn--secondary" target="_blank" rel="noopener" href="{{ $printUrl }}">@include('construction::partials.rr_icon', ['name' => 'print'])<span>{{ $printLabel }}</span></a>
        </div>
    @endif
</header>

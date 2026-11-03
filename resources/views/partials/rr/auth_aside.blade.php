{{-- الجانب التعريفي في صفحات الدخول والتسجيل
     $pill (اختياري)، $title، $em، $desc، $benefits = [['icon' => ..., 'key' => ...]]، $image (اختياري، وإلا رسم توضيحي) --}}
<aside class="rr-auth__aside">
    @if (!empty($pill))
        <span class="rr-badge rr-badge--brand">{{ $pill }}</span>
    @endif
    <h2 class="rr-auth__aside-title">{{ $title }}<em>{{ $em }}</em></h2>
    <p class="rr-auth__aside-desc">{{ $desc }}</p>
    <ul class="rr-auth__benefits">
        @foreach ($benefits as $benefit)
            <li>
                <span class="rr-landing__feature-icon">@include('partials.rr.icon', ['name' => $benefit['icon'], 'size' => 20])</span>
                <span><strong>@lang('guest.benefit_' . $benefit['key'])</strong><small>@lang('guest.benefit_' . $benefit['key'] . '_desc')</small></span>
            </li>
        @endforeach
    </ul>
    @if (!empty($image))
        <img class="rr-auth__aside-img" src="{{ $image }}" alt="@lang('guest.app_preview_alt')">
    @else
        @include('partials.rr.app_art')
    @endif
</aside>

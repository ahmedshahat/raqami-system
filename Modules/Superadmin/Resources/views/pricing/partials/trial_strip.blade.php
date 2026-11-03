{{-- الباقة المجانية كشريط فوق الباقات المدفوعة --}}
@php
    $duration = $package->interval == 'days'
        ? trans_choice('guest.days_count', $package->interval_count)
        : $package->interval_count . ' ' . __('lang_v1.' . $package->interval);
@endphp
<section class="rr-card rr-card--tint rr-pricing__trial">
    <span class="rr-landing__feature-icon">@include('partials.rr.icon', ['name' => 'gift', 'size' => 22])</span>
    <div class="rr-pricing__trial-text">
        <strong>{{ $package_name($package->name) }} — @lang('guest.pricing_free_for') {{ $duration }}</strong>
        <span>{{ $package->description }}</span>
    </div>
    @if ($package->enable_custom_link == 1)
        <a href="{{ $package->custom_link }}" class="rr-btn rr-btn--primary rr-btn--lg">{{ $package->custom_link_text }}</a>
    @else
        <a href="{{ route('business.getRegister') }}?package={{ $package->id }}" class="rr-btn rr-btn--primary rr-btn--lg">
            @lang('superadmin::lang.register_free')
            @include('partials.rr.icon', ['name' => 'arrow-forward', 'size' => 18])
        </a>
    @endif
</section>

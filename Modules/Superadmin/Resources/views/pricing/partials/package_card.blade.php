@php
    $icons = ['cart', 'box', 'building', 'users'];
    $card_icon = $icons[($card_index - 1) % count($icons)];
    $interval_type = !empty($intervals[$package->interval])
        ? $intervals[$package->interval]
        : __('lang_v1.' . $package->interval);
    $limit = function ($count) {
        return $count == 0 ? __('superadmin::lang.unlimited') : $count;
    };
    $features = [
        $limit($package->location_count) . ' ' . __('business.business_locations'),
        $limit($package->user_count) . ' ' . __('superadmin::lang.users'),
        $limit($package->product_count) . ' ' . __('superadmin::lang.products'),
        $limit($package->invoice_count) . ' ' . __('superadmin::lang.invoices'),
    ];
    foreach ((array) $package->custom_permissions as $permission => $value) {
        if (!empty($value) && isset($permission_formatted[$permission])) {
            $features[] = $permission_formatted[$permission];
        }
    }
    if ($package->trial_days != 0) {
        $features[] = __('guest.pricing_guarantee', ['days' => trans_choice('guest.days_count', $package->trial_days)]);
    }

    // سعر الشهر الواحد للمقارنة بين الربع سنوي والسنوي
    $months = ['months' => 1, 'years' => 12][$package->interval] ?? 0;
    $monthly_price = $months && $package->interval_count ? $package->price / ($months * $package->interval_count) : null;
@endphp

<article class="rr-card rr-package {{ $package->interval }} {{ $package->mark_package_as_popular ? 'rr-package--popular' : '' }}">
    @if ($package->mark_package_as_popular)
        <span class="rr-badge rr-badge--brand rr-package__flag">@lang('guest.pricing_popular')</span>
    @endif

    <header class="rr-package__head">
        <span class="rr-landing__feature-icon">@include('partials.rr.icon', ['name' => $card_icon, 'size' => 22])</span>
        <h2 class="rr-card__title">{{ $package_name($package->name) }}</h2>
        <p class="rr-package__desc">{!! nl2br(e($package->description)) !!}</p>
    </header>

    <div class="rr-package__price">
        <strong class="rr-num">{{ number_format((float) $package->price, 0, '.', ',') }}</strong>
        <span class="rr-package__currency">{{ $currency_label }}</span>
        <span class="rr-package__period">/ {{ \Lang::has('guest.' . $package->interval . '_count') ? trans_choice('guest.' . $package->interval . '_count', $package->interval_count) : $package->interval_count . ' ' . $interval_type }}</span>
        @if ($monthly_price && $months * $package->interval_count > 1)
            <span class="rr-package__monthly">@lang('guest.pricing_per_month', ['price' => number_format($monthly_price, 0, '.', ','), 'currency' => $currency_label])</span>
        @endif
    </div>

    @if ($package->enable_custom_link == 1)
        <a href="{{ $package->custom_link }}" class="rr-btn rr-btn--lg {{ $package->mark_package_as_popular ? 'rr-btn--primary' : 'rr-btn--secondary' }}">{{ $package->custom_link_text }}</a>
    @else
        <a href="{{ route('business.getRegister') }}?package={{ $package->id }}" class="rr-btn rr-btn--lg {{ $package->mark_package_as_popular ? 'rr-btn--primary' : 'rr-btn--secondary' }}">@lang('superadmin::lang.register_subscribe')</a>
    @endif

    <ul class="rr-package__features">
        @foreach ($features as $feature)
            <li>@include('partials.rr.icon', ['name' => 'check', 'size' => 16])<span>{{ $feature }}</span></li>
        @endforeach
    </ul>
</article>

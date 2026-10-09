@php
    $icons = ['fa-store', 'fa-briefcase', 'fa-award', 'fa-building'];
    $card_icon = $icons[($card_index - 1) % count($icons)];
    $interval_type = !empty($intervals[$package->interval])
        ? $intervals[$package->interval]
        : __('lang_v1.' . $package->interval);
@endphp

<article class="pricing-package {{ $package->interval }} {{ $package->mark_package_as_popular ? 'is-popular' : '' }}">
    @if($package->mark_package_as_popular)
        <span class="popular-badge">الأكثر استخدامًا</span>
    @endif

    <div class="package-icon"><i class="fa {{ $card_icon }}"></i></div>
    <h2>{{ $package->name }}</h2>
    <div class="package-description">{!! nl2br(e($package->description)) !!}</div>

    <div class="package-price">
        @if($package->price != 0)
            <strong>
                <bdi>{{ number_format((float) $package->price, 0, '.', ',') }}</bdi>
                <bdi>{{ $__system_currency->symbol ?? $__system_currency->code ?? '' }}</bdi>
            </strong>
            <span>/ {{ $package->interval_count }} {{ $interval_type }}</span>
        @else
            <strong>
                مجانًا لمدة
                <bdi>{{ $package->interval_count }} {{ $interval_type }}</bdi>
            </strong>
        @endif
    </div>

    @if($package->enable_custom_link == 1)
        <a href="{{ $package->custom_link }}" class="package-action">{{ $package->custom_link_text }}</a>
    @else
        <a href="{{ route('business.getRegister') }}?package={{ $package->id }}" class="package-action">
            {{ $package->price != 0 ? __('superadmin::lang.register_subscribe') : __('superadmin::lang.register_free') }}
        </a>
    @endif

    <ul class="package-features">
        <li><i class="fa fa-check"></i><span>{{ $package->location_count == 0 ? __('superadmin::lang.unlimited') : $package->location_count }} @lang('business.business_locations')</span></li>
        <li><i class="fa fa-check"></i><span>{{ $package->user_count == 0 ? __('superadmin::lang.unlimited') : $package->user_count }} @lang('superadmin::lang.users')</span></li>
        <li><i class="fa fa-check"></i><span>{{ $package->product_count == 0 ? __('superadmin::lang.unlimited') : $package->product_count }} @lang('superadmin::lang.products')</span></li>
        <li><i class="fa fa-check"></i><span>{{ $package->invoice_count == 0 ? __('superadmin::lang.unlimited') : $package->invoice_count }} @lang('superadmin::lang.invoices')</span></li>

        @if(!empty($package->custom_permissions))
            @foreach($package->custom_permissions as $permission => $value)
                @if(!empty($value) && isset($permission_formatted[$permission]))
                    <li><i class="fa fa-check"></i><span>{{ $permission_formatted[$permission] }}</span></li>
                @endif
            @endforeach
        @endif

        @if($package->trial_days != 0)
            <li><i class="fa fa-check"></i><span>{{ $package->trial_days }} @lang('superadmin::lang.trial_days')</span></li>
        @endif
    </ul>
</article>

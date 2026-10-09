<div class="package-card-col {{ $package->interval }} tw-relative">
    <div class="package-card-box {{ $package->mark_package_as_popular == 1 ? 'package-card-popular' : '' }}">

        @if ($package->mark_package_as_popular == 1)
            <span class="package-card-badge">@lang('superadmin::lang.popular')</span>
        @endif

        <h2 class="package-card-name">{{ $package->name }}</h2>

        @if (!empty($package->description))
            <p class="package-card-desc">{{ $package->description }}</p>
        @endif

        @php
            $interval_type = !empty($intervals[$package->interval])
                ? $intervals[$package->interval]
                : __('lang_v1.' . $package->interval);
        @endphp

        <div class="package-card-price-row">
            @if ($package->price != 0)
                <span class="package-card-price display_currency" data-currency_symbol="true">{{ $package->price }}</span>
            @else
                <span class="package-card-price package-card-price-free">@lang('superadmin::lang.free_for_duration', ['duration' => $package->interval_count . ' ' . $interval_type])</span>
            @endif
        </div>
        @if ($package->price != 0)
            <p class="package-card-price-caption">/ {{ $package->interval_count }} {{ $interval_type }}</p>
        @endif

        @if ($package->enable_custom_link == 1)
            <a href="{{ $package->custom_link }}" class="package-card-cta">{{ $package->custom_link_text }}</a>
        @else
            @if (isset($action_type) && $action_type == 'register')
                <a href="{{ route('business.getRegister') }}?package={{ $package->id }}" class="package-card-cta">
                    @if ($package->price != 0)
                        @lang('superadmin::lang.register_subscribe')
                    @else
                        @lang('superadmin::lang.register_free')
                    @endif
                </a>
            @else
                <a href="{{ action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay'], [$package->id]) }}" class="package-card-cta">
                    @if ($package->price != 0)
                        @lang('superadmin::lang.pay_and_subscribe')
                    @else
                        @lang('superadmin::lang.subscribe')
                    @endif
                </a>
            @endif
        @endif

        <ul class="package-card-features">
            <li>
                <i class="fa fa-check-circle-o"></i>
                <span>
                    @if ($package->location_count == 0)
                        @lang('superadmin::lang.unlimited')
                    @else
                        {{ $package->location_count }}
                    @endif
                    @lang('business.business_locations')
                </span>
            </li>
            <li>
                <i class="fa fa-check-circle-o"></i>
                <span>
                    @if ($package->user_count == 0)
                        @lang('superadmin::lang.unlimited')
                    @else
                        {{ $package->user_count }}
                    @endif
                    @lang('superadmin::lang.users')
                </span>
            </li>
            <li>
                <i class="fa fa-check-circle-o"></i>
                <span>
                    @if ($package->product_count == 0)
                        @lang('superadmin::lang.unlimited')
                    @else
                        {{ $package->product_count }}
                    @endif
                    @lang('superadmin::lang.products')
                </span>
            </li>
            <li>
                <i class="fa fa-check-circle-o"></i>
                <span>
                    @if ($package->invoice_count == 0)
                        @lang('superadmin::lang.unlimited')
                    @else
                        {{ $package->invoice_count }}
                    @endif
                    @lang('superadmin::lang.invoices')
                </span>
            </li>

            @if (!empty($package->custom_permissions))
                @foreach ($package->custom_permissions as $permission => $value)
                    @isset($permission_formatted[$permission])
                        <li>
                            <i class="fa fa-check-circle-o"></i>
                            <span>{{ $permission_formatted[$permission] }}</span>
                        </li>
                    @endisset
                @endforeach
            @endif

            @if ($package->trial_days != 0)
                <li>
                    <i class="fa fa-check-circle-o"></i>
                    <span>{{ $package->trial_days }} @lang('superadmin::lang.trial_days')</span>
                </li>
            @endif
        </ul>

    </div>
</div>
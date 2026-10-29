@extends('layouts.guest_rr')
@section('title', __('superadmin::lang.pricing'))
@section('with_scripts', true)

@section('content')
    @include('superadmin::layouts.partials.currency')
    @php
        $currency = \App\System::getCurrency();
        $currency_label = $currency && \Lang::has('guest.currency_' . $currency->code)
            ? __('guest.currency_' . $currency->code)
            : ($currency->symbol ?? $currency->code ?? '');
        // المدة ظاهرة في مفتاح ربع سنوي/سنوي، فبنشيلها من اسم الباقة لو مكتوبة جواه بين قوسين
        $package_name = function ($name) {
            return trim(preg_replace('/\s*\(\s*(ربع\s*سنو[يى]|سنو[يى]|شهر[يى]|quarterly|yearly|annual|monthly)\s*\)\s*/iu', ' ', $name));
        };
        $visible_packages = $packages->filter(function ($package) {
            return is_null($package->businesses);
        });
    @endphp

    <div class="rr-landing__wrap rr-pricing">
        <header class="rr-pricing__intro">
            <h1 class="rr-landing__title">@lang('guest.pricing_title')</h1>
            <p class="rr-landing__lead">@lang('guest.pricing_lead')</p>

            <div class="rr-segmented" role="group" aria-label="@lang('guest.pricing_duration')" id="rr_pricing_duration">
                <button type="button" class="rr-segmented__item" data-duration="months" aria-pressed="true">@lang('guest.pricing_quarterly')</button>
                <button type="button" class="rr-segmented__item" data-duration="years" aria-pressed="false">@lang('guest.pricing_yearly')</button>
            </div>
            <span class="rr-badge rr-badge--success" id="rr_pricing_saving">@lang('guest.pricing_saving')</span>
        </header>

        @foreach ($visible_packages->where('price', 0) as $package)
            @include('superadmin::pricing.partials.trial_strip')
        @endforeach

        <section class="rr-pricing__grid">
            @php($pricing_index = 0)
            @foreach ($visible_packages->where('price', '!=', 0) as $package)
                @php($pricing_index++)
                @include('superadmin::pricing.partials.package_card', ['card_index' => $pricing_index])
            @endforeach
        </section>

        <section class="rr-landing__features rr-pricing__assurances">
            @foreach ([['shield', 'no_card'], ['users', 'support'], ['calendar', 'trial'], ['gift', 'updates']] as [$icon, $key])
                <div class="rr-pricing__assurance">
                    <span class="rr-landing__feature-icon">@include('partials.rr.icon', ['name' => $icon, 'size' => 20])</span>
                    <span><strong>@lang('guest.assurance_' . $key)</strong><small>@lang('guest.assurance_' . $key . '_desc')</small></span>
                </div>
            @endforeach
        </section>

        <p class="rr-pricing__note">@include('partials.rr.icon', ['name' => 'info', 'size' => 16]) @lang('guest.pricing_tax_note')</p>
    </div>
@stop

@section('javascript')
    <script>
        $(function() {
            var $packages = $('.rr-package');
            var hasMonths = $packages.filter('.months').length > 0;
            var hasYears = $packages.filter('.years').length > 0;

            function showDuration(duration) {
                $('#rr_pricing_duration .rr-segmented__item').each(function() {
                    $(this).attr('aria-pressed', $(this).data('duration') === duration ? 'true' : 'false');
                });
                $packages.filter('.months, .years').prop('hidden', true);
                $packages.filter('.' + duration).prop('hidden', false);
            }

            $('#rr_pricing_duration').on('click', '.rr-segmented__item', function() {
                showDuration($(this).data('duration'));
            });

            if (hasMonths && hasYears) {
                showDuration('months');
            } else {
                $('#rr_pricing_duration, #rr_pricing_saving').prop('hidden', true);
            }
        });
    </script>
@endsection

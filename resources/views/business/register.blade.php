@extends('layouts.guest_rr')
@section('title', __('lang_v1.register'))
@section('with_scripts', true)

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.10.6/build/css/intlTelInput.css">
@endsection

@section('content')
    <div class="rr-landing__wrap rr-auth">
        <section class="rr-card rr-auth__panel">
            <header class="rr-auth__head">
                <h1 class="rr-auth__title">@lang('guest.register_title')</h1>
                <p class="rr-auth__sub">@lang('guest.register_sub')</p>
            </header>
            {!! Form::open([
                'url' => route('business.postRegister'),
                'method' => 'post',
                'id' => 'simple_business_register_form',
                'class' => 'rr-auth__form rr-form-wrap',
                'files' => true,
            ]) !!}
            @include('business.partials.register_simple_form')
            {!! Form::hidden('package_id', $package_id) !!}
            {!! Form::close() !!}
        </section>

        @include('partials.rr.auth_aside', [
            'title' => __('guest.register_showcase_title'),
            'em' => __('guest.register_showcase_em'),
            'desc' => __('guest.register_showcase_desc'),
            'benefits' => [['icon' => 'shield', 'key' => 'secure'], ['icon' => 'cloud', 'key' => 'access'], ['icon' => 'clock', 'key' => 'fast']],
        ])
    </div>
@stop
@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.10.6/build/js/intlTelInput.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            // رسائل التحقق تظهر تحت الحقل كله، مش جوه .rr-control.
            // السكربت ده بيشتغل قبل login.js، فبنستنى لحد ما login.js يعمل validate بقواعده وبعدين نغيّر مكان الرسالة
            setTimeout(function() {
                $('#simple_business_register_form').validate().settings.errorPlacement = function(error, element) {
                    error.addClass('rr-field__error').insertAfter(element.closest('.rr-control'));
                };
            });

            const mobileInput = document.querySelector('#mobile');
            const mobileCountry = document.querySelector('#mobile_country');

            if (mobileInput && mobileCountry && window.intlTelInput) {
                const countries = ['eg', 'sa', 'kw', 'ly', 'ye', 'sy', 'jo', 'om', 'qa', 'bh', 'ae', 'sd', 'dz', 'tn'];
                const selectedCountry = (mobileCountry.value || 'EG').toLowerCase();
                const phoneInput = window.intlTelInput(mobileInput, {
                    initialCountry: countries.includes(selectedCountry) ? selectedCountry : 'eg',
                    onlyCountries: countries,
                    countryOrder: countries,
                    countrySearch: false,
                    countrySelectorMode: 'DROPDOWN',
                    separateDialCode: true,
                    showFlags: true,
                    dropdownParent: document.body,
                    countryNameOverrides: {
                        eg: 'مصر',
                        sa: 'السعودية',
                        kw: 'الكويت',
                        ly: 'ليبيا',
                        ye: 'اليمن',
                        sy: 'سوريا',
                        jo: 'الأردن',
                        om: 'عُمان',
                        qa: 'قطر',
                        bh: 'البحرين',
                        ae: 'الإمارات',
                        sd: 'السودان',
                        dz: 'الجزائر',
                        tn: 'تونس'
                    }
                });

                let previousDialCode = phoneInput.getSelectedCountryData().dialCode || '';

                const removeOldStandaloneDialCode = function() {
                    const value = mobileInput.value.replace(/[\s()-]/g, '');
                    if (value === previousDialCode || value === '+' + previousDialCode) {
                        mobileInput.value = '';
                    }
                };

                const syncMobileCountry = function() {
                    const country = phoneInput.getSelectedCountryData();
                    if (country && country.iso2) {
                        mobileCountry.value = country.iso2.toUpperCase();
                        previousDialCode = country.dialCode || '';
                    }
                };

                removeOldStandaloneDialCode();
                mobileInput.addEventListener('countrychange', function() {
                    removeOldStandaloneDialCode();
                    syncMobileCountry();
                });
                $('#simple_business_register_form').on('submit', syncMobileCountry);
                syncMobileCountry();
            }
        })
    </script>
@endsection

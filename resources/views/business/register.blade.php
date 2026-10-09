@extends('layouts.auth2')
@section('title', __('lang_v1.register'))

@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.10.6/build/css/intlTelInput.css">
    <link rel="stylesheet" href="{{ asset('css/register.css?v=' . $asset_v) }}">
@endsection

@section('content')
    <div class="register-page" dir="rtl">
        <div class="register-shell">
            <section class="register-showcase">
                <div class="brand-lockup">
                    <img src="{{ asset('img/logo-small.png') }}" alt="{{ config('app.name') }}">
                    <div><strong>{{ config('app.name', 'UltimatePOS') }}</strong><span>النظام المحاسبي السحابي في مصر</span></div>
                </div>
                <div class="showcase-copy">
                    <h2>برنامج محاسبة سحابي<br><em>أسهل، أسرع، أدق</em></h2>
                    <p>أدر أعمالك من أي مكان وفي أي وقت،<br>الفواتير، المخزون، الحسابات وأكثر من ذلك بكثير.</p>
                </div>
                <div class="showcase-photo" aria-hidden="true"></div>
                <div class="showcase-benefits">
                    <div><i class="fa fa-shield-alt"></i><strong>أمان عالي</strong><span>حماية كاملة لبياناتك</span></div>
                    <div><i class="fa fa-cloud"></i><strong>تجربة سحابية</strong><span>الوصول من أي مكان</span></div>
                    <div><i class="fa fa-rocket"></i><strong>تشغيل سريع</strong><span>ابدأ خلال دقائق</span></div>
                </div>
            </section>

            <section class="register-card">
                <div class="register-heading">
                    <h1>إنشاء حساب جديد</h1>
                    <p>ابدأ تجربتك المجانية الآن، بدون بطاقة ائتمان</p>
                    <span></span>
                </div>
            {!! Form::open([
                'url' => route('business.postRegister'),
                'method' => 'post',
                'id' => 'simple_business_register_form',
                'files' => true,
            ]) !!}
            @include('business.partials.register_simple_form')
            {!! Form::hidden('package_id', $package_id) !!}
            {!! Form::close() !!}
            </section>
        </div>
    </div>
@stop
@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.10.6/build/js/intlTelInput.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
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

            $('.change_lang').click(function() {
                const packageId = @json($package_id);
                const params = new URLSearchParams({lang: $(this).attr('value')});
                if (packageId) params.set('package', packageId);
                window.location = "{{ route('business.getRegister') }}?" + params.toString();
            });

            $('.password-toggle').on('click', function() {
                const input = $($(this).data('target'));
                input.attr('type', input.attr('type') === 'password' ? 'text' : 'password');
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });
        })
    </script>
@endsection

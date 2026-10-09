@php($no_header = true)
@extends('layouts.auth')
@section('title', __('superadmin::lang.pricing'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/pricing.css?v=' . $asset_v) }}">
@endsection

@section('content')
    @include('superadmin::layouts.partials.currency')

    <div class="pricing-page" dir="rtl">
        <header class="pricing-header">
            <a href="{{ url('/') }}" class="pricing-brand">
                <img src="{{ asset('img/logo-small.png') }}" alt="{{ config('app.name') }}">
                <span>{{ config('app.name', 'UltimatePOS') }}</span>
            </a>

            <nav class="pricing-nav">
                <a href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'login']) }}{{ request()->lang ? '?lang=' . request()->lang : '' }}">تسجيل الدخول</a>
                @if(config('constants.allow_registration'))
                    <a href="{{ route('business.getRegister', array_filter(['lang' => request()->lang])) }}" class="pricing-nav-register">تسجيل</a>
                @endif
                <button type="button" class="pricing-language change_lang" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
                    <i class="fa fa-globe"></i>
                    {{ app()->getLocale() === 'ar' ? 'العربية' : 'English' }}
                    <i class="fa fa-chevron-down"></i>
                </button>
            </nav>
        </header>

        <main class="pricing-main">
            <section class="pricing-intro">
                <h1>باقات التسعير</h1>
                <p>اختر الباقة التي تناسب احتياجاتك وميزانيتك</p>
                <span class="pricing-title-line"></span>

                <div class="pricing-duration" role="group" aria-label="مدة الاشتراك">
                    <button type="button" class="duration-option active" data-duration="months">ربع سنوى</button>
                    <button type="button" class="duration-option" data-duration="years">سنوي</button>
                </div>
                <div class="pricing-saving">وفر 15% عند اختيار الدفع سنويًا</div>
            </section>

            <section class="pricing-grid">
                @php($pricing_index = 0)
                @foreach($packages as $package)
                    @if(!is_null($package->businesses))
                        @continue
                    @endif
                    @php($pricing_index++)
                    @include('superadmin::pricing.partials.package_card', ['card_index' => $pricing_index])
                @endforeach
            </section>

            <section class="pricing-assurances">
                <div><i class="fa fa-shield-alt"></i><p><strong>بدون بطاقة ائتمان</strong><span>لا نطلب بيانات البطاقة</span></p></div>
                <div><i class="fa fa-headset"></i><p><strong>دعم فني مميز</strong><span>فريق دعم جاهز لمساعدتك</span></p></div>
                <div><i class="fa fa-calendar-check"></i><p><strong>تجربة مجانية</strong><span>جرّب النظام قبل الاشتراك</span></p></div>
                <div><i class="fa fa-gift"></i><p><strong>تحديثات مستمرة</strong><span>مزايا جديدة باستمرار</span></p></div>
            </section>

            <p class="pricing-tax-note"><i class="fa fa-shield-alt"></i> جميع الأسعار شاملة ضريبة القيمة المضافة</p>
        </main>
    </div>
@stop

@section('javascript')
    <script>
        $(function() {
            $('.change_lang').on('click', function() {
                window.location = "{{ route('pricing') }}?lang=" + $(this).val();
            });

            $('.duration-option').on('click', function() {
                const duration = $(this).data('duration');
                $('.duration-option').removeClass('active');
                $(this).addClass('active');
                $('.pricing-package.months, .pricing-package.years').hide();
                $('.pricing-package.' + duration).fadeIn(180);
            });

            const initialDuration = $('.pricing-package.months').length ? 'months' : 'years';
            $('.duration-option[data-duration="' + initialDuration + '"]').trigger('click');
            if (!$('.pricing-package.years').length || !$('.pricing-package.months').length) {
                $('.pricing-duration, .pricing-saving').hide();
            }
        });
    </script>
@endsection

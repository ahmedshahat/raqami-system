@extends('layouts.auth2')
@section('title', __('lang_v1.login'))
@inject('request', 'Illuminate\Http\Request')

@section('content')
@php
    $username = old('username');
    $password = null;
    if (config('app.env') == 'demo') {
        $username = 'admin';
        $password = '123456';
    }
@endphp

<main class="raqmy-login" dir="rtl">
    <header class="raqmy-nav">
        <a class="raqmy-brand" href="{{ url('/') }}" aria-label="{{ config('app.name') }}">
            <img src="https://raqmycloud.com/wp-content/uploads/2026/06/Navy-Blue-Simple-Corporate-Letter-R-Logo-e1782043096380.png" alt="رقمي سيستم">
        </a>
        <nav class="raqmy-nav-actions">
            @include('layouts.partials.language_btn')
        </nav>
    </header>

    <section class="raqmy-shell">
        <div class="raqmy-login-card">
            <div class="raqmy-card-brand"><img class="raqmy-card-logo" src="https://raqmycloud.com/wp-content/uploads/2026/06/هوية-رقمى-سيستم.png" alt="رقمي سيستم"></div>
            <h1>مرحبًا بعودتك</h1>
            <p>سجّل دخولك للوصول إلى حسابك</p>

            <form method="POST" action="{{ route('login') }}" id="login-form" novalidate>
                {{ csrf_field() }}
                <div class="raqmy-field {{ $errors->has('username') ? 'has-error' : '' }}">
                    <label for="username">@lang('lang_v1.username')</label>
                    <div class="raqmy-input-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"/></svg>
                        <input id="username" type="text" name="username" value="{{ $username }}" required autofocus autocomplete="username" placeholder="@lang('lang_v1.username')">
                    </div>
                    @if ($errors->has('username'))<span class="raqmy-error">{{ $errors->first('username') }}</span>@endif
                </div>

                <div class="raqmy-field {{ $errors->has('password') ? 'has-error' : '' }}">
                    <label for="password">@lang('lang_v1.password')</label>
                    <div class="raqmy-input-wrap">
                        <button type="button" id="show_hide_icon" class="raqmy-eye" aria-label="إظهار كلمة المرور">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                        </button>
                        <input id="password" type="password" name="password" value="{{ $password }}" required autocomplete="current-password" placeholder="@lang('lang_v1.password')">
                    </div>
                    @if ($errors->has('password'))<span class="raqmy-error">{{ $errors->first('password') }}</span>@endif
                </div>

                <div class="raqmy-form-row">
                    <label class="raqmy-remember"><input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}><span></span>@lang('lang_v1.remember_me')</label>
                    @if (config('app.env') != 'demo')
                        <a href="{{ route('password.request') }}">@lang('lang_v1.forgot_your_password')</a>
                    @endif
                </div>

                @if(config('constants.enable_recaptcha'))
                    <div class="raqmy-captcha"><div class="g-recaptcha" data-sitekey="{{ config('constants.google_recaptcha_key') }}"></div></div>
                    @if ($errors->has('g-recaptcha-response'))<span class="raqmy-error">{{ $errors->first('g-recaptcha-response') }}</span>@endif
                @endif

                <button type="submit" class="raqmy-submit"><span>@lang('lang_v1.login')</span><span class="raqmy-spinner"></span></button>
            </form>

            @if (config('constants.allow_registration'))
                <div class="raqmy-divider"><span>أو</span></div>
                <div class="raqmy-offers">
                    <a class="raqmy-trial" href="https://v612.raqmycloud.com/business/register?package=3">
                        <span class="raqmy-trial-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3v3M17 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="m9 15 2 2 4-5"/></svg></span><span><strong>ابدأ نسخة تجريبية الآن</strong><small>جرّب جميع المميزات مجانًا لمدة 14 يوم</small></span>
                    </a>
                    <a class="raqmy-packages" href="{{ url('/pricing') }}" aria-label="الباقات">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h16v12H4ZM3 5h18v3H3ZM12 5v15M9 5c-2.5 0-3.5-4 0-3 1.5.4 3 3 3 3s1.5-2.6 3-3c3.5-1 2.5 3 0 3Z"/></svg>
                        <span>الباقات</span>
                    </a>
                </div>
                <p class="raqmy-new">{{ __('business.not_yet_registered') }} <a href="{{ route('business.getRegister') }}">{{ __('business.register_now') }}</a></p>
            @endif
            <div class="raqmy-zatca raqmy-zatca-card"><img src="https://raqmycloud.com/wp-content/uploads/2026/07/zatca-approved.png" alt="معتمد من هيئة الزكاة والضريبة والجمارك"></div>
        </div>

        <aside class="raqmy-showcase">
            <span class="raqmy-pill">نظام محاسبي سحابي متكامل</span>
            <h2>أدر أعمالك بذكاء<br><em>من أي مكان وفي أي وقت</em></h2>
            <p>برنامج محاسبي سحابي يساعدك على إدارة الفواتير،<br>المخزون، العملاء والتقارير بكفاءة وسهولة.</p>
            <div class="raqmy-features">
                <div><span><svg viewBox="0 0 24 24"><path d="M5 16a4 4 0 0 1 1-7.87A6 6 0 0 1 17.5 7 4.5 4.5 0 0 1 18 16Z"/></svg></span><p><strong>الوصول من أي مكان</strong><small>بياناتك متاحة أمامك في أي وقت</small></p></div>
                <div><span><svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6Z"/></svg></span><p><strong>أمان عالٍ</strong><small>حماية متقدمة لبياناتك وحساباتك</small></p></div>
                <div><span><svg viewBox="0 0 24 24"><path d="M5 20V10M12 20V4M19 20v-7"/></svg></span><p><strong>تقارير ذكية</strong><small>تقارير تفصيلية تساعدك على اتخاذ القرار</small></p></div>
            </div>
            <div class="raqmy-device">
                <img src="https://raqmycloud.com/wp-content/uploads/2026/07/برنامج-حسابات-مصانع-.png" alt="واجهة برنامج رقمي سيستم">
            </div>
        </aside>
    </section>
</main>
@stop

@section('javascript')
<script>
$(function () {
    $('.change_lang').on('click', function () { window.location = "{{ route('login') }}?lang=" + $(this).attr('value'); });
    $('#show_hide_icon').on('click', function () {
        const input = $('#password');
        const visible = input.attr('type') === 'text';
        input.attr('type', visible ? 'password' : 'text');
        $(this).toggleClass('is-visible', !visible).attr('aria-label', visible ? 'إظهار كلمة المرور' : 'إخفاء كلمة المرور');
    });
    $('#login-form').on('submit', function () { $(this).find('.raqmy-submit').addClass('is-loading').prop('disabled', true); });
});
</script>
@endsection

@section('auth_styles')
<style>
:root{--rq-blue:#0961ed;--rq-dark:#101d33;--rq-muted:#71809a;--rq-line:#d7e1f0}
html,body{background:#f7faff!important;min-height:100%;font-family:"Tajawal","Segoe UI",Tahoma,sans-serif}
.right-col{padding:0!important}.right-col>.row:first-child{display:none}.container-fluid{padding:0}.raqmy-login{min-height:100vh;color:var(--rq-dark);background:radial-gradient(circle at 25% 35%,#eaf3ff 0,transparent 36%),#fbfdff}
.raqmy-nav{height:88px;padding:0 3.5%;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid #e7edf6;direction:ltr}.raqmy-brand,.raqmy-card-brand{display:flex;align-items:center;gap:13px;color:var(--rq-dark);text-decoration:none!important;direction:ltr}.raqmy-mark{width:48px;height:48px;border-radius:9px;background:linear-gradient(145deg,#0869fa,#064cc8);color:white;display:grid;place-items:center;font-size:31px;font-weight:900;font-style:italic;box-shadow:0 8px 20px #1267ed30}.raqmy-brand-name,.raqmy-card-brand strong{font-size:21px;line-height:.8;text-align:right}.raqmy-brand-name small,.raqmy-card-brand small{font-size:17px}.raqmy-nav-actions{display:flex;align-items:center;gap:24px;direction:rtl}.raqmy-nav-actions details{margin:0!important;border:1px solid #d9e5f5;border-radius:30px;padding:3px 14px}.raqmy-nav-actions summary{color:var(--rq-blue)!important}.raqmy-register{background:var(--rq-blue);color:white!important;padding:14px 29px;border-radius:28px;font-weight:700}.raqmy-current{color:var(--rq-blue)!important;font-weight:700}
.raqmy-shell{width:calc(100% - 56px);max-width:1440px;min-height:calc(100vh - 116px);margin:12px auto 16px;display:grid;grid-template-columns:1fr 1fr;border:1px solid #e1e9f5;border-radius:18px;overflow:hidden;box-shadow:0 12px 40px #1c4c8e10;direction:ltr}.raqmy-login-card{direction:rtl;background:#fff;margin:14px;border-radius:18px;padding:42px clamp(36px,5vw,75px);display:flex;flex-direction:column;justify-content:center}.raqmy-card-brand{justify-content:center;margin-bottom:25px}.raqmy-card-brand .raqmy-mark{width:54px;height:54px}.raqmy-card-brand strong{font-size:24px}.raqmy-login-card h1{text-align:center;color:var(--rq-dark);font-size:34px;font-weight:800;margin:0 0 8px}.raqmy-login-card>p{text-align:center;color:var(--rq-muted);font-size:17px;margin-bottom:34px}.raqmy-field{margin-bottom:22px}.raqmy-field label{display:block;font-weight:700;margin-bottom:9px}.raqmy-input-wrap{position:relative}.raqmy-input-wrap input{width:100%;height:58px;border:1.5px solid var(--rq-line);border-radius:12px;padding:0 50px 0 50px;background:#fff;color:var(--rq-dark);font-size:16px;outline:none;transition:.2s}.raqmy-input-wrap input:focus{border-color:var(--rq-blue);box-shadow:0 0 0 4px #0961ed12}.raqmy-input-wrap>svg,.raqmy-eye{position:absolute;z-index:2;left:17px;top:50%;transform:translateY(-50%);width:24px;height:24px}.raqmy-input-wrap>svg{fill:none;stroke:#8290a8;stroke-width:2}.raqmy-eye{border:0;background:transparent;padding:0;cursor:pointer}.raqmy-eye svg{fill:none;stroke:#8290a8;stroke-width:2;width:24px}.raqmy-eye.is-visible svg{opacity:.55}.has-error input{border-color:#e84c4c}.raqmy-error{display:block;color:#d93636;font-size:13px;margin-top:5px}.raqmy-form-row{display:flex;justify-content:space-between;align-items:center;margin:2px 0 28px}.raqmy-form-row a,.raqmy-new a{color:var(--rq-blue);font-weight:700}.raqmy-remember{display:flex;gap:9px;align-items:center;margin:0;font-weight:600;cursor:pointer}.raqmy-remember input{position:absolute;opacity:0}.raqmy-remember span{width:20px;height:20px;border:1.5px solid #b9c7dc;border-radius:5px}.raqmy-remember input:checked+span{background:var(--rq-blue);border-color:var(--rq-blue);box-shadow:inset 0 0 0 4px white}.raqmy-submit{height:59px;width:100%;border:0;border-radius:12px;background:linear-gradient(90deg,#0b6bf4,#0658e3);color:white;font-size:19px;font-weight:800;box-shadow:0 9px 22px #0961ed25;position:relative}.raqmy-spinner{display:none;width:20px;height:20px;border:2px solid #ffffff66;border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}.raqmy-submit.is-loading span:first-child{display:none}.raqmy-submit.is-loading .raqmy-spinner{display:inline-block}@keyframes spin{to{transform:rotate(360deg)}}.raqmy-divider{display:flex;align-items:center;gap:20px;color:#77849a;margin:25px 0}.raqmy-divider:before,.raqmy-divider:after{content:"";height:1px;background:#e2e8f2;flex:1}.raqmy-trial{border:1.5px solid #a9c8fb;border-radius:12px;min-height:80px;display:flex;align-items:center;justify-content:center;gap:20px;color:var(--rq-dark)!important;text-decoration:none!important}.raqmy-trial span:last-child{display:flex;flex-direction:column;gap:5px}.raqmy-trial strong{font-size:17px}.raqmy-trial small{color:var(--rq-muted);font-size:14px}.raqmy-trial-icon{color:var(--rq-blue);background:#eaf2ff;border-radius:12px;padding:12px;font-size:22px}.raqmy-new{text-align:center!important;margin:25px 0 0!important;font-size:14px!important}
.raqmy-showcase{direction:rtl;padding:8% 10% 4%;text-align:center;position:relative;overflow:hidden;background:linear-gradient(140deg,#f7fbff,#eaf3ff)}.raqmy-showcase:after{content:"";position:absolute;width:180px;height:180px;right:-70px;top:-70px;border-radius:50%;background:#dceaff80}.raqmy-pill{display:inline-block;background:#e1edff;color:var(--rq-blue);font-weight:700;border-radius:20px;padding:8px 16px}.raqmy-showcase h2{font-size:36px;line-height:1.45;margin:18px 0 12px}.raqmy-showcase h2 em{color:var(--rq-blue);font-style:normal}.raqmy-showcase>p{color:var(--rq-muted);font-size:17px;font-weight:600;line-height:1.8}.raqmy-showcase ul{display:inline-block;text-align:right;list-style:none;margin:15px 0 12px;padding:0;color:#53627a;font-weight:700;line-height:2}.raqmy-showcase li:before{content:"✓";display:inline-grid;place-items:center;width:19px;height:19px;background:var(--rq-blue);color:white;border-radius:50%;font-size:11px;margin-left:10px}.raqmy-device{position:relative;width:74%;height:245px;margin:12px auto 18px;perspective:800px}.raqmy-screen{position:absolute;width:92%;height:210px;right:0;bottom:10px;background:#18243a;border:9px solid #1a2432;border-radius:12px;display:flex;overflow:hidden;box-shadow:0 22px 30px #1b395b38;transform:rotateX(2deg)}.screen-sidebar{width:18%;background:#0e2039;display:flex;flex-direction:column;align-items:center;gap:14px;padding:13px}.screen-sidebar b{color:white;font-size:22px}.screen-sidebar i{height:5px;width:70%;border-radius:4px;background:#65748a}.screen-main{flex:1;background:#f5f8fc;text-align:right;padding:14px;color:#2e3a4d}.stat-row{display:flex;gap:8px;margin:14px 0}.stat-row b{flex:1;background:white;padding:16px 5px;text-align:center;border-radius:5px;font-size:12px;box-shadow:0 2px 9px #55709912}.chart{height:85px;background:white;border-radius:5px;position:relative;overflow:hidden}.chart:before,.chart:after,.chart i{content:"";position:absolute;left:5%;right:5%;height:2px;background:#e5ebf4}.chart:before{top:28%}.chart:after{top:62%}.chart i{background:linear-gradient(120deg,transparent 15%,#2b77ef 16% 18%,transparent 19% 35%,#2b77ef 36% 39%,transparent 40% 57%,#2b77ef 58% 61%,transparent 62%);height:55px;top:15px}.raqmy-phone{position:absolute;left:-5px;bottom:0;width:75px;height:150px;border:7px solid #182331;border-radius:15px;background:#f4f8ff;z-index:3;display:flex;flex-direction:column;gap:9px;padding:22px 7px 7px;color:#1266ea;font-size:11px}.raqmy-phone i{display:block;height:35px;border-radius:5px;background:linear-gradient(145deg,#d9e9ff,#fff)}.raqmy-zatca{display:inline-flex;align-items:center;gap:12px;background:white;border-radius:14px;padding:12px 18px;text-align:right;box-shadow:0 9px 25px #3a5e8a12}.zatca-fingerprint{font-size:34px;color:#1ebba3}.raqmy-zatca strong{font-size:13px}.raqmy-zatca small{display:block;font-size:9px;color:#53627a}.raqmy-zatca .zatca-ok{color:#11aa84;font-size:10px;margin-top:4px}.raqmy-captcha{overflow:auto;margin-bottom:15px}
.raqmy-login-card{grid-column:2;grid-row:1}.raqmy-showcase{grid-column:1;grid-row:1}
.raqmy-trial-icon{width:48px;height:48px;padding:0;display:grid;place-items:center;flex:0 0 48px}.raqmy-trial-icon svg{width:25px;height:25px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.raqmy-offers{display:flex;gap:10px}.raqmy-offers .raqmy-trial{flex:1}.raqmy-packages{width:92px;flex:0 0 92px;border:1.5px solid #a9c8fb;border-radius:12px;color:var(--rq-blue)!important;text-decoration:none!important;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;font-weight:700}.raqmy-packages:hover{background:#f2f7ff}.raqmy-packages svg{width:25px;height:25px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.raqmy-packages span{font-size:13px}
.raqmy-brand img{display:block;width:210px;height:60px;object-fit:contain}.raqmy-card-brand img{display:block;width:min(270px,75%);height:auto;margin:auto}.raqmy-device{width:92%;height:auto;margin:12px auto 10px}.raqmy-device img{display:block;width:100%;height:auto;filter:drop-shadow(0 20px 18px #14345a20)}.raqmy-zatca{padding:0;background:transparent;box-shadow:none}.raqmy-zatca img{display:block;width:250px;max-width:100%;height:auto}
.raqmy-nav-actions details{position:relative;z-index:1000}.raqmy-nav-actions details>summary{display:flex;align-items:center;gap:7px;cursor:pointer;list-style:none;white-space:nowrap}.raqmy-nav-actions details>summary::-webkit-details-marker{display:none}.raqmy-nav-actions details>summary:before{content:"◉";font-size:11px;color:var(--rq-blue)}.raqmy-nav-actions details>ul{position:absolute!important;top:calc(100% + 10px)!important;right:0!important;left:auto!important;width:210px!important;max-height:300px;overflow-y:auto;margin:0!important;padding:8px!important;background:#fff!important;border:1px solid #dfe8f5;border-radius:14px!important;box-shadow:0 14px 35px #173f7726!important;direction:rtl;text-align:right;z-index:9999!important}.raqmy-nav-actions details>ul li{display:block;width:100%;margin:0}.raqmy-nav-actions details>ul li a{display:block;width:100%;padding:10px 12px!important;border-radius:9px;color:var(--rq-dark)!important;font-size:14px;white-space:nowrap;cursor:pointer;text-decoration:none!important}.raqmy-nav-actions details>ul li a:hover{color:var(--rq-blue)!important;background:#edf4ff}
.raqmy-card-brand .raqmy-mark{width:54px;height:54px;font-size:33px}.raqmy-features{width:100%;max-width:430px;display:grid;gap:8px;margin:9px auto}.raqmy-features>div{display:flex;align-items:center;gap:13px;text-align:right}.raqmy-features>div>span{width:44px;height:44px;flex:0 0 44px;border-radius:12px;background:#e9f2ff;color:var(--rq-blue);display:grid;place-items:center}.raqmy-features svg{width:23px;height:23px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.raqmy-features p{margin:0;color:var(--rq-dark);line-height:1.25}.raqmy-features strong,.raqmy-features small{display:block}.raqmy-features strong{font-size:14px;margin-bottom:3px}.raqmy-features small{font-size:11px;color:var(--rq-muted)}.raqmy-zatca-card{width:100%;margin:14px auto 0;padding:8px!important;background:#f7f9fc!important;border-radius:12px;display:flex;justify-content:center}.raqmy-zatca-card img{width:275px}.raqmy-login-card .raqmy-new{display:none}
.raqmy-card-logo{display:block;width:150px!important;height:68px!important;object-fit:contain}
.raqmy-nav-actions details>ul{max-width:calc(100vw - 24px)!important;box-sizing:border-box}
@media(min-width:992px){
    html,body{height:100%;overflow:hidden}
    .raqmy-login{height:100vh;min-height:0;overflow:hidden}
    .raqmy-nav{height:72px}
    .raqmy-brand img{width:190px;height:52px}
    .raqmy-shell{height:calc(100vh - 88px);min-height:0;margin:8px auto;width:calc(100% - 48px)}
    .raqmy-login-card{margin:10px;padding:clamp(18px,2.7vh,34px) clamp(38px,4vw,68px);overflow:hidden}
    .raqmy-card-brand{margin-bottom:clamp(9px,1.5vh,18px)}
    .raqmy-card-brand img{width:min(235px,66%);max-height:70px;object-fit:contain}
    .raqmy-login-card h1{font-size:clamp(25px,2.4vh,32px);margin-bottom:4px}
    .raqmy-login-card>p{font-size:15px;margin-bottom:clamp(14px,2.2vh,26px)}
    .raqmy-field{margin-bottom:clamp(10px,1.7vh,18px)}
    .raqmy-field label{margin-bottom:6px}
    .raqmy-input-wrap input{height:clamp(48px,5.5vh,56px)}
    .raqmy-form-row{margin:0 0 clamp(12px,1.8vh,22px)}
    .raqmy-submit{height:clamp(48px,5.5vh,56px)}
    .raqmy-divider{margin:clamp(11px,1.6vh,20px) 0}
    .raqmy-trial{min-height:clamp(62px,7.5vh,76px)}
    .raqmy-new{margin-top:clamp(10px,1.5vh,18px)!important}
    .raqmy-showcase{padding:clamp(20px,4vh,45px) 8% 18px;display:flex;flex-direction:column;align-items:center}
    .raqmy-pill{padding:6px 14px}
    .raqmy-showcase h2{font-size:clamp(25px,3vh,34px);line-height:1.3;margin:clamp(8px,1.3vh,15px) 0 6px}
    .raqmy-showcase>p{font-size:clamp(14px,1.7vh,17px);line-height:1.55;margin:4px 0}
    .raqmy-features{gap:clamp(5px,.8vh,8px);margin:clamp(5px,1vh,10px) auto}.raqmy-features>div>span{width:clamp(36px,4.6vh,44px);height:clamp(36px,4.6vh,44px);flex-basis:clamp(36px,4.6vh,44px)}
    .raqmy-device{width:min(92%,640px);min-height:0;margin:clamp(8px,1.5vh,18px) auto 4px}
    .raqmy-device img{max-height:34vh;width:100%;object-fit:contain}
    .raqmy-zatca img{width:clamp(220px,21vw,280px);max-height:10vh;object-fit:contain}
    .raqmy-zatca-card{margin-top:clamp(8px,1.2vh,13px)}
}
@media(min-width:992px) and (max-height:760px){
    .raqmy-nav{height:62px}.raqmy-shell{height:calc(100vh - 72px);margin:5px auto}
    .raqmy-login-card{padding:18px clamp(42px,5vw,78px)}
    .raqmy-card-brand{display:flex;margin-bottom:9px}.raqmy-card-logo{width:150px!important;height:60px!important}
    .raqmy-login-card h1{font-size:28px}.raqmy-login-card>p{font-size:15px;margin-bottom:15px}
    .raqmy-field{margin-bottom:11px}.raqmy-field label{font-size:14px;margin-bottom:5px}.raqmy-input-wrap input{height:50px;font-size:15px}
    .raqmy-form-row{margin-bottom:12px;font-size:14px}.raqmy-submit{height:50px;font-size:18px}
    .raqmy-divider{margin:10px 0}.raqmy-trial{min-height:62px}.raqmy-trial-icon{width:44px;height:44px;flex-basis:44px}.raqmy-trial-icon svg{width:23px}
    .raqmy-packages{width:78px;flex-basis:78px}
    .raqmy-trial strong{font-size:16px}.raqmy-trial small{font-size:12px}.raqmy-new{margin-top:8px!important;font-size:12px!important}
    .raqmy-showcase{padding:18px 8% 12px;justify-content:center}.raqmy-pill{font-size:14px;padding:6px 14px}
    .raqmy-showcase h2{font-size:30px;margin:9px 0 6px}.raqmy-showcase>p{display:block;font-size:14px;line-height:1.5}
    .raqmy-features{gap:6px;margin:8px auto}.raqmy-features>div>span{width:42px;height:42px;flex-basis:42px}.raqmy-features svg{width:22px}.raqmy-features strong{font-size:14px}.raqmy-features small{font-size:11px}
    .raqmy-device{width:min(94%,620px);margin:9px auto 2px}.raqmy-device img{max-height:35vh}
    .raqmy-zatca img{width:245px;max-height:10vh}.raqmy-zatca-card{margin-top:8px;padding:6px!important}
}
@media(max-width:991px){.raqmy-shell{grid-template-columns:1fr;max-width:680px}.raqmy-showcase{display:none}.raqmy-login-card{grid-column:1;min-height:calc(100vh - 140px)}.raqmy-nav{padding:0 20px}.raqmy-brand-name{display:none}}
@media(max-width:575px){.raqmy-nav{height:70px}.raqmy-brand img{width:145px;height:48px}.raqmy-nav-actions{gap:10px}.raqmy-nav-actions details{padding:0 7px}.raqmy-register{padding:10px 15px}.raqmy-current{display:none}.raqmy-mark{width:42px;height:42px;font-size:26px}.raqmy-shell{width:100%;margin:0;border:0;border-radius:0;min-height:calc(100vh - 70px)}.raqmy-login-card{margin:0;border-radius:0;padding:30px 22px;justify-content:flex-start}.raqmy-login-card h1{font-size:28px}.raqmy-card-brand{margin-top:5px}.raqmy-form-row{font-size:13px}.raqmy-trial{padding:10px}.raqmy-trial strong{font-size:15px}}
</style>
@endsection

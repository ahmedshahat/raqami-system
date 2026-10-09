{!! Form::hidden('language', request()->lang) !!}

@if (session('status') && empty(session('status.success')))
    <div class="alert alert-danger register-errors">
        {{ session('status.msg') }}
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger register-errors">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="register-field">
    {!! Form::label('name', 'اسم النشاط') !!}
    <div class="register-input"><i class="fa fa-building"></i>{!! Form::text('name', null, ['placeholder' => 'أدخل اسم النشاط', 'required', 'autofocus']) !!}</div>
</div>

<div class="register-field">
    {!! Form::label('first_name', 'اسم الشخص') !!}
    <div class="register-input"><i class="fa fa-user"></i>{!! Form::text('first_name', null, ['placeholder' => 'أدخل اسمك الكامل', 'required']) !!}</div>
</div>

<div class="register-field">
    {!! Form::label('mobile', 'رقم الهاتف') !!}
    <div class="register-input phone-input">
        <i class="fa fa-phone"></i>
        {!! Form::hidden('mobile_country', old('mobile_country', 'EG'), ['id' => 'mobile_country']) !!}
        {!! Form::text('mobile', null, ['id' => 'mobile', 'placeholder' => 'رقم الهاتف', 'required', 'dir' => 'ltr', 'inputmode' => 'tel', 'autocomplete' => 'tel-national']) !!}
    </div>
</div>

<div class="register-field">
    {!! Form::label('email', 'البريد الإلكتروني') !!}
    <div class="register-input"><i class="fa fa-envelope"></i>{!! Form::email('email', null, ['placeholder' => 'أدخل البريد الإلكتروني', 'required', 'dir' => 'rtl']) !!}</div>
</div>

<div class="register-field">
    {!! Form::label('username', 'اسم المستخدم') !!}
    <div class="register-input"><i class="fa fa-user-circle"></i>{!! Form::text('username', null, ['placeholder' => 'أدخل اسم المستخدم', 'required', 'autocomplete' => 'username']) !!}</div>
</div>

<div class="register-field">
    {!! Form::label('password', 'كلمة المرور') !!}
    <div class="register-input"><i class="fa fa-lock"></i>{!! Form::password('password', ['id' => 'password', 'placeholder' => 'أدخل كلمة المرور', 'required', 'autocomplete' => 'new-password']) !!}<button type="button" class="password-toggle" data-target="#password" aria-label="إظهار كلمة المرور"><i class="fa fa-eye-slash"></i></button></div>
</div>

<div class="register-field">
    {!! Form::label('confirm_password', 'تأكيد كلمة المرور') !!}
    <div class="register-input"><i class="fa fa-lock"></i>{!! Form::password('confirm_password', ['id' => 'confirm_password', 'placeholder' => 'أعد إدخال كلمة المرور', 'required', 'autocomplete' => 'new-password']) !!}<button type="button" class="password-toggle" data-target="#confirm_password" aria-label="إظهار كلمة المرور"><i class="fa fa-eye-slash"></i></button></div>
</div>

@if(config('constants.enable_recaptcha'))
    <div id="recaptcha-container" class="register-recaptcha"></div>
    <script>window.RECAPTCHA_SITE_KEY = "{{ config('constants.google_recaptcha_key') }}";</script>
@endif

<button type="submit" class="register-submit">ابدأ تجربتك المجانية الآن</button>
<p class="register-login">لديك حساب بالفعل؟ <a href="{{ url('/login') }}">تسجيل الدخول</a></p>

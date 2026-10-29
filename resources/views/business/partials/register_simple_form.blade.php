{!! Form::hidden('language', request()->lang) !!}

@if (session('status') && empty(session('status.success')))
    <div class="rr-alert rr-alert--danger" role="alert">{{ session('status.msg') }}</div>
@endif

@if ($errors->any())
    <div class="rr-alert rr-alert--danger" role="alert">{{ implode("\n", $errors->all()) }}</div>
@endif

<div class="rr-field">
    {!! Form::label('name', __('guest.business_name'), ['class' => 'rr-field__label']) !!}
    <div class="rr-control rr-control--lg">
        <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'building', 'size' => 18])</span>
        {!! Form::text('name', null, ['class' => 'rr-control__input', 'placeholder' => __('guest.business_name_ph'), 'required', 'autofocus']) !!}
    </div>
</div>

<div class="rr-field">
    {!! Form::label('first_name', __('guest.person_name'), ['class' => 'rr-field__label']) !!}
    <div class="rr-control rr-control--lg">
        <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'user', 'size' => 18])</span>
        {!! Form::text('first_name', null, ['class' => 'rr-control__input', 'placeholder' => __('guest.person_name_ph'), 'required']) !!}
    </div>
</div>

<div class="rr-field">
    {!! Form::label('mobile', __('guest.mobile'), ['class' => 'rr-field__label']) !!}
    <div class="rr-control rr-control--lg rr-control--phone">
        {!! Form::hidden('mobile_country', old('mobile_country', 'EG'), ['id' => 'mobile_country']) !!}
        {!! Form::text('mobile', null, ['id' => 'mobile', 'class' => 'rr-control__input', 'placeholder' => __('guest.mobile'), 'required', 'dir' => 'ltr', 'inputmode' => 'tel', 'autocomplete' => 'tel-national']) !!}
    </div>
</div>

<div class="rr-field">
    {!! Form::label('email', __('guest.email'), ['class' => 'rr-field__label']) !!}
    <div class="rr-control rr-control--lg">
        <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'mail', 'size' => 18])</span>
        {!! Form::email('email', null, ['class' => 'rr-control__input', 'placeholder' => __('guest.email_ph'), 'required', 'autocomplete' => 'email']) !!}
    </div>
</div>

<div class="rr-field">
    {!! Form::label('username', __('guest.username'), ['class' => 'rr-field__label']) !!}
    <div class="rr-control rr-control--lg">
        <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'user', 'size' => 18])</span>
        {!! Form::text('username', null, ['class' => 'rr-control__input', 'placeholder' => __('guest.username_ph'), 'required', 'autocomplete' => 'username']) !!}
    </div>
</div>

<div class="rr-form-grid">
    <div class="rr-field">
        {!! Form::label('password', __('guest.password'), ['class' => 'rr-field__label']) !!}
        <div class="rr-control rr-control--lg">
            <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'lock', 'size' => 18])</span>
            {!! Form::password('password', ['id' => 'password', 'class' => 'rr-control__input', 'placeholder' => __('guest.password_ph'), 'required', 'autocomplete' => 'new-password']) !!}
            @include('partials.rr.password_toggle', ['target' => '#password'])
        </div>
    </div>

    <div class="rr-field">
        {!! Form::label('confirm_password', __('guest.confirm_password'), ['class' => 'rr-field__label']) !!}
        <div class="rr-control rr-control--lg">
            <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'lock', 'size' => 18])</span>
            {!! Form::password('confirm_password', ['id' => 'confirm_password', 'class' => 'rr-control__input', 'placeholder' => __('guest.confirm_password_ph'), 'required', 'autocomplete' => 'new-password']) !!}
            @include('partials.rr.password_toggle', ['target' => '#confirm_password'])
        </div>
    </div>
</div>

@if(config('constants.enable_recaptcha'))
    <div id="recaptcha-container" class="rr-auth__captcha"></div>
    <script>window.RECAPTCHA_SITE_KEY = "{{ config('constants.google_recaptcha_key') }}";</script>
@endif

<button type="submit" class="rr-btn rr-btn--primary rr-btn--lg rr-auth__submit">@lang('guest.register_submit')</button>
<p class="rr-auth__foot">@lang('guest.have_account') <a class="rr-auth__link" href="{{ url('/login') }}">{{ __('business.sign_in') }}</a></p>

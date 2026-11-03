@if($errors->any())
    <div class="rr-alert rr-alert--danger" id="ct-labor-errors" role="alert" tabindex="-1">
        <div>
            <strong>@lang('construction::lang.fix_errors_before_saving')</strong>
            <ul class="rr-error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    </div>
@endif

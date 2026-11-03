<div class="rr-alert">@lang('construction::lang.quote_convert_explanation')</div>
<div class="rr-form-wrap">
    <div class="rr-form-grid">
        <div class="rr-field rr-field--full">
            <label class="rr-field__label" for="ct-convert-project-name">@lang('construction::lang.project_name')<span class="rr-field__req" aria-hidden="true">*</span></label>
            <div class="rr-control"><input id="ct-convert-project-name" class="rr-control__input" name="project_name" value="{{ $projectName ?? '' }}" maxlength="190" required></div>
        </div>
        <div class="rr-field">
            <label class="rr-field__label" for="ct-convert-manager">@lang('construction::lang.project_manager')</label>
            <div class="rr-control">
                <select id="ct-convert-manager" class="rr-control__input rr-control__select" name="manager_id">
                    <option value="">@lang('construction::lang.choose')</option>
                    @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->user_full_name ?: $user->username }}</option>@endforeach
                </select>
                <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
            </div>
        </div>
        <div class="rr-field">
            <label class="rr-field__label" for="ct-convert-location">@lang('construction::lang.location')</label>
            <div class="rr-control"><input id="ct-convert-location" class="rr-control__input" name="location" maxlength="255"></div>
        </div>
        <div class="rr-field">
            <label class="rr-field__label" for="ct-convert-start">@lang('construction::lang.start_date')</label>
            <div class="rr-control"><input id="ct-convert-start" class="rr-control__input" type="date" name="start_date" value="{{ now()->toDateString() }}"></div>
        </div>
        <div class="rr-field">
            <label class="rr-field__label" for="ct-convert-end">@lang('construction::lang.end_date')</label>
            <div class="rr-control"><input id="ct-convert-end" class="rr-control__input" type="date" name="end_date"></div>
        </div>
        <div class="rr-field rr-field--full">
            <label class="rr-field__label" for="ct-convert-consultant">@lang('construction::lang.consultant')</label>
            <div class="rr-control">
                <select id="ct-convert-consultant" class="rr-control__input rr-control__select" name="consultant_contact_id">
                    <option value="">@lang('construction::lang.no_consultant')</option>
                    @foreach($consultants as $consultant)<option value="{{ $consultant->id }}">{{ $consultant->supplier_business_name ?: $consultant->name }}</option>@endforeach
                </select>
                <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
            </div>
        </div>
    </div>
</div>

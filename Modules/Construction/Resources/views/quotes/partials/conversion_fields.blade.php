<p class="text-muted">@lang('construction::lang.quote_convert_explanation')</p>
<div class="form-group"><label>@lang('construction::lang.project_name') *</label><input name="project_name" class="form-control" value="{{ $projectName ?? '' }}" maxlength="190" required></div>
<div class="row">
    <div class="col-sm-6 form-group"><label>@lang('construction::lang.project_manager')</label><select name="manager_id" class="form-control"><option value="">@lang('construction::lang.choose')</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->user_full_name ?: $user->username }}</option>@endforeach</select></div>
    <div class="col-sm-6 form-group"><label>@lang('construction::lang.location')</label><input name="location" class="form-control" maxlength="255"></div>
    <div class="col-sm-6 form-group"><label>@lang('construction::lang.start_date')</label><input type="date" name="start_date" class="form-control" value="{{ now()->toDateString() }}"></div>
    <div class="col-sm-6 form-group"><label>@lang('construction::lang.end_date')</label><input type="date" name="end_date" class="form-control"></div>
</div>
<div class="form-group"><label>@lang('construction::lang.consultant')</label><select name="consultant_contact_id" class="form-control"><option value="">@lang('construction::lang.no_consultant')</option>@foreach($consultants as $consultant)<option value="{{ $consultant->id }}">{{ $consultant->supplier_business_name ?: $consultant->name }}</option>@endforeach</select></div>

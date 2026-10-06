@php
    $editing = isset($project);
    $selectedMembers = old('member_ids', $editing ? $project->members->pluck('id')->all() : []);
    $consultantSelection = old('consultant_selection', $editing ? ($project->consultant_contact_id ?: ($project->consultant_name ? 'manual' : 'none')) : 'none');
@endphp
@if($errors->any())<div class="alert alert-danger"><ul class="tw-mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">@lang('construction::lang.project_details')</h3></div>
    <div class="box-body">
        <div class="row">
            <div class="col-md-3 form-group"><label>@lang('construction::lang.project_code') *</label><input name="code" class="form-control" maxlength="40" required value="{{ old('code',$project->code ?? $suggestedCode ?? '') }}"><small class="text-muted">@lang('construction::lang.project_code_hint')</small></div>
            <div class="col-md-5 form-group"><label>@lang('construction::lang.project_name') *</label><input name="name" class="form-control" maxlength="190" required value="{{ old('name',$project->name ?? '') }}"></div>
            <div class="col-md-4 form-group"><label>@lang('construction::lang.status') *</label>@if($editing)<select name="status" class="form-control" required>@foreach(\Modules\Construction\Entities\ConstructionProject::STATUSES as $value)<option value="{{ $value }}" @selected(old('status',$project->status ?? 'draft') === $value)>@lang('construction::lang.'.$value)</option>@endforeach</select>@else<input type="hidden" name="status" value="draft"><input class="form-control" value="@lang('construction::lang.draft')" readonly><small class="text-muted">@lang('construction::lang.project_activates_with_contract')</small>@endif</div>
        </div>
        <div class="row">
            <div class="col-md-4 form-group"><label>@lang('construction::lang.customer') *</label><select name="customer_id" class="form-control select2" required><option value="">@lang('construction::lang.choose')</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}" @selected((string)old('customer_id',$project->customer_id ?? '') === (string)$contact->id)>{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach</select></div>
            <div class="col-md-4 form-group"><label>@lang('construction::lang.consultant')</label><select name="consultant_selection" id="consultant-selection" class="form-control select2" required><option value="none" @selected((string)$consultantSelection === 'none')>@lang('construction::lang.no_consultant')</option>@foreach($consultants as $contact)<option value="{{ $contact->id }}" @selected((string)$consultantSelection === (string)$contact->id)>{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach<option value="manual" @selected((string)$consultantSelection === 'manual')>@lang('construction::lang.manual_consultant_option')</option></select><small class="text-muted">@lang('construction::lang.consultant_choice_hint')</small></div>
            <div class="col-md-4 form-group" id="manual-consultant-wrap" style="{{ (string)$consultantSelection === 'manual' ? '' : 'display:none' }}"><label>@lang('construction::lang.manual_consultant_name') *</label><input name="consultant_name" id="manual-consultant-name" class="form-control" maxlength="190" value="{{ old('consultant_name',$project->consultant_name ?? '') }}"></div>
        </div>
        <div class="row">
            <div class="col-md-4 form-group"><label>@lang('construction::lang.project_manager')</label><select name="manager_id" class="form-control select2"><option value="">@lang('construction::lang.choose')</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)old('manager_id',$project->manager_id ?? '') === (string)$user->id)>{{ $user->user_full_name ?: $user->username }}</option>@endforeach</select></div>
            <div class="col-md-8 form-group"><label>@lang('construction::lang.project_members')</label><select name="member_ids[]" class="form-control select2" multiple>@foreach($users as $user)<option value="{{ $user->id }}" @selected(in_array($user->id,array_map('intval',$selectedMembers),true))>{{ $user->user_full_name ?: $user->username }}</option>@endforeach</select></div>
        </div>
        <div class="row">
            <div class="col-md-6 form-group"><label>@lang('construction::lang.location')</label><input name="location" class="form-control" maxlength="255" value="{{ old('location',$project->location ?? '') }}"></div>
            <div class="col-md-3 form-group"><label>@lang('construction::lang.start_date')</label><input type="text" name="start_date" class="form-control ct-date-picker" readonly value="{{ old('start_date',isset($project) && $project->start_date ? @format_date($project->start_date) : '') }}"></div>
            <div class="col-md-3 form-group"><label>@lang('construction::lang.end_date')</label><input type="text" name="end_date" class="form-control ct-date-picker" readonly value="{{ old('end_date',isset($project) && $project->end_date ? @format_date($project->end_date) : '') }}"></div>
        </div>
        <div class="form-group"><label>@lang('construction::lang.description')</label><textarea name="description" class="form-control" rows="3">{{ old('description',$project->description ?? '') }}</textarea></div>
    </div>
</div>
<div class="text-center" style="margin-bottom:20px"><a href="{{ route('construction.projects.index') }}" class="btn btn-default">@lang('messages.cancel')</a> <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('construction::lang.save_project')</button></div>

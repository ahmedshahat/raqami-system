@php
    $editing = isset($project);
    $selectedMembers = old('member_ids', $editing ? $project->members->pluck('id')->all() : []);
    $consultantSelection = old('consultant_selection', $editing ? ($project->consultant_contact_id ?: ($project->consultant_name ? 'manual' : 'none')) : 'none');
@endphp

<div class="ct-project-form-shell">
    <aside class="ct-project-form-aside">
        <a href="{{ route('construction.projects.index') }}" class="ct-project-form-back"><i class="fa fa-arrow-right" aria-hidden="true"></i> @lang('construction::lang.back_to_projects')</a>
        <span class="ct-project-form-icon" aria-hidden="true"><i class="fa fa-{{ $editing ? 'pen' : 'building' }}"></i></span>
        <h1>@lang('construction::lang.'.($editing ? 'edit_project' : 'new_project'))</h1>
        <p>@lang('construction::lang.project_form_intro')</p>
        <div class="ct-project-form-steps" aria-label="@lang('construction::lang.project_details')">
            <div><span>1</span><p><strong>@lang('construction::lang.project_identity')</strong><small>@lang('construction::lang.project_identity_hint')</small></p></div>
            <div><span>2</span><p><strong>@lang('construction::lang.project_parties')</strong><small>@lang('construction::lang.project_parties_hint')</small></p></div>
            <div><span>3</span><p><strong>@lang('construction::lang.project_schedule')</strong><small>@lang('construction::lang.project_schedule_hint')</small></p></div>
        </div>
    </aside>

    <main class="ct-project-form-main">
        @if($errors->any())<div class="alert alert-danger ct-project-form-errors" role="alert"><strong>@lang('construction::lang.form_has_errors')</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="ct-project-form-section">
            <header><span aria-hidden="true"><i class="fa fa-fingerprint"></i></span><div><h2>@lang('construction::lang.project_identity')</h2><p>@lang('construction::lang.project_identity_hint')</p></div></header>
            <div class="ct-project-fields ct-project-fields--identity">
                <div class="form-group"><label for="project-code">@lang('construction::lang.project_code') <em>*</em></label><input id="project-code" name="code" class="form-control" maxlength="40" required value="{{ old('code',$project->code ?? $suggestedCode ?? '') }}"><small>@lang('construction::lang.project_code_hint')</small></div>
                <div class="form-group ct-field-wide"><label for="project-name">@lang('construction::lang.project_name') <em>*</em></label><input id="project-name" name="name" class="form-control" maxlength="190" required value="{{ old('name',$project->name ?? '') }}"></div>
                <div class="form-group"><label for="project-status">@lang('construction::lang.status') <em>*</em></label>@if($editing)<select id="project-status" name="status" class="form-control" required>@foreach(\Modules\Construction\Entities\ConstructionProject::STATUSES as $value)<option value="{{ $value }}" @selected(old('status',$project->status ?? 'draft') === $value)>@lang('construction::lang.'.$value)</option>@endforeach</select>@else<input type="hidden" name="status" value="draft"><input id="project-status" class="form-control" value="@lang('construction::lang.draft')" readonly><small>@lang('construction::lang.project_activates_with_contract')</small>@endif</div>
            </div>
        </section>

        <section class="ct-project-form-section">
            <header><span aria-hidden="true"><i class="fa fa-users"></i></span><div><h2>@lang('construction::lang.project_parties')</h2><p>@lang('construction::lang.project_parties_hint')</p></div></header>
            <div class="ct-project-fields">
                <div class="form-group"><label for="project-customer">@lang('construction::lang.customer') <em>*</em></label><select id="project-customer" name="customer_id" class="form-control select2" required><option value="">@lang('construction::lang.choose')</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}" @selected((string)old('customer_id',$project->customer_id ?? '') === (string)$contact->id)>{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach</select></div>
                <div class="form-group"><label for="consultant-selection">@lang('construction::lang.consultant')</label><select name="consultant_selection" id="consultant-selection" class="form-control select2" required><option value="none" @selected((string)$consultantSelection === 'none')>@lang('construction::lang.no_consultant')</option>@foreach($consultants as $contact)<option value="{{ $contact->id }}" @selected((string)$consultantSelection === (string)$contact->id)>{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach<option value="manual" @selected((string)$consultantSelection === 'manual')>@lang('construction::lang.manual_consultant_option')</option></select><small>@lang('construction::lang.consultant_choice_hint')</small></div>
                <div class="form-group" id="manual-consultant-wrap" style="{{ (string)$consultantSelection === 'manual' ? '' : 'display:none' }}"><label for="manual-consultant-name">@lang('construction::lang.manual_consultant_name') <em>*</em></label><input name="consultant_name" id="manual-consultant-name" class="form-control" maxlength="190" value="{{ old('consultant_name',$project->consultant_name ?? '') }}"></div>
                <div class="form-group"><label for="project-manager">@lang('construction::lang.project_manager')</label><select id="project-manager" name="manager_id" class="form-control select2"><option value="">@lang('construction::lang.choose')</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)old('manager_id',$project->manager_id ?? '') === (string)$user->id)>{{ $user->user_full_name ?: $user->username }}</option>@endforeach</select></div>
                <div class="form-group ct-field-wide"><label for="project-members">@lang('construction::lang.project_members')</label><select id="project-members" name="member_ids[]" class="form-control select2" multiple>@foreach($users as $user)<option value="{{ $user->id }}" @selected(in_array($user->id,array_map('intval',$selectedMembers),true))>{{ $user->user_full_name ?: $user->username }}</option>@endforeach</select></div>
            </div>
        </section>

        <section class="ct-project-form-section">
            <header><span aria-hidden="true"><i class="fa fa-calendar-alt"></i></span><div><h2>@lang('construction::lang.project_schedule')</h2><p>@lang('construction::lang.project_schedule_hint')</p></div></header>
            <div class="ct-project-fields">
                <div class="form-group ct-field-wide"><label for="project-location">@lang('construction::lang.location')</label><input id="project-location" name="location" class="form-control" maxlength="255" value="{{ old('location',$project->location ?? '') }}"></div>
                <div class="form-group"><label for="project-start">@lang('construction::lang.start_date')</label><input id="project-start" type="text" name="start_date" class="form-control ct-date-picker" readonly value="{{ old('start_date',isset($project) && $project->start_date ? @format_date($project->start_date) : '') }}"></div>
                <div class="form-group"><label for="project-end">@lang('construction::lang.end_date')</label><input id="project-end" type="text" name="end_date" class="form-control ct-date-picker" readonly value="{{ old('end_date',isset($project) && $project->end_date ? @format_date($project->end_date) : '') }}"></div>
                <div class="form-group ct-field-full"><label for="project-description">@lang('construction::lang.description')</label><textarea id="project-description" name="description" class="form-control" rows="4">{{ old('description',$project->description ?? '') }}</textarea></div>
            </div>
        </section>

        <footer class="ct-project-form-footer"><span><i class="fa fa-info-circle" aria-hidden="true"></i> @lang('construction::lang.required_fields_hint')</span><div><a href="{{ route('construction.projects.index') }}" class="ct-list-btn ct-list-btn--secondary">@lang('messages.cancel')</a><button type="submit" class="ct-list-btn ct-list-btn--primary"><i class="fa fa-save" aria-hidden="true"></i> @lang('construction::lang.save_project')</button></div></footer>
    </main>
</div>

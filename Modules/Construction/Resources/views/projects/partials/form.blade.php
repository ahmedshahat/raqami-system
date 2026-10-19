@php
    $editing = isset($project);
    $selectedMembers = old('member_ids', $editing ? $project->members->pluck('id')->all() : []);
    $consultantSelection = old('consultant_selection', $editing ? ($project->consultant_contact_id ?: ($project->consultant_name ? 'manual' : 'none')) : 'none');
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
@endphp

@if($errors->any())
    <div class="rr-alert rr-alert--danger" role="alert">
        <div>
            <strong>@lang('construction::lang.form_has_errors')</strong>
            <ul class="rr-error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    </div>
@endif

{{-- 1. هوية المشروع --}}
<section class="rr-card rr-form-section" aria-labelledby="rr-form-identity">
    <header class="rr-card__head">
        <span class="rr-step-num" aria-hidden="true">1</span>
        <div class="rr-card__titles">
            <h2 id="rr-form-identity" class="rr-card__title">@lang('construction::lang.project_identity')</h2>
            <p class="rr-card__desc">@lang('construction::lang.project_identity_hint')</p>
        </div>
    </header>
    <div class="rr-card__body">
        <div class="rr-form-wrap">
            <div class="rr-form-grid">
                <div class="rr-field">
                    <label class="rr-field__label" for="project-code">@lang('construction::lang.project_code'){!! $req !!}</label>
                    <div class="rr-control"><input id="project-code" class="rr-control__input rr-control__input--num" name="code" maxlength="40" required value="{{ old('code', $project->code ?? $suggestedCode ?? '') }}" aria-describedby="project-code-hint"></div>
                    <div class="rr-field__hint" id="project-code-hint">@lang('construction::lang.project_code_hint')</div>
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="project-status">@lang('construction::lang.status'){!! $req !!}</label>
                    @if($editing)
                        <div class="rr-control">
                            <select id="project-status" class="rr-control__input rr-control__select" name="status" required>
                                @foreach(\Modules\Construction\Entities\ConstructionProject::STATUSES as $value)
                                    <option value="{{ $value }}" @selected(old('status', $project->status ?? 'draft') === $value)>@lang('construction::lang.' . $value)</option>
                                @endforeach
                            </select>
                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                        </div>
                    @else
                        <input type="hidden" name="status" value="draft">
                        <div class="rr-locked" id="project-status" aria-describedby="project-status-hint">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 16])@lang('construction::lang.draft')</div>
                        <div class="rr-field__hint" id="project-status-hint">@lang('construction::lang.project_activates_with_contract')</div>
                    @endif
                </div>
                <div class="rr-field rr-field--full">
                    <label class="rr-field__label" for="project-name">@lang('construction::lang.project_name'){!! $req !!}</label>
                    <div class="rr-control"><input id="project-name" class="rr-control__input" name="name" maxlength="190" required value="{{ old('name', $project->name ?? '') }}"></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 2. الأطراف وفريق العمل --}}
<section class="rr-card rr-form-section" aria-labelledby="rr-form-parties">
    <header class="rr-card__head">
        <span class="rr-step-num" aria-hidden="true">2</span>
        <div class="rr-card__titles">
            <h2 id="rr-form-parties" class="rr-card__title">@lang('construction::lang.project_parties')</h2>
            <p class="rr-card__desc">@lang('construction::lang.project_parties_hint')</p>
        </div>
    </header>
    <div class="rr-card__body">
        <div class="rr-form-wrap">
            <div class="rr-form-grid">
                <div class="rr-field rr-field--full">
                    <label class="rr-field__label" for="project-customer">@lang('construction::lang.customer'){!! $req !!}</label>
                    <select id="project-customer" name="customer_id" class="form-control select2" required>
                        <option value="">@lang('construction::lang.choose')</option>
                        @foreach($contacts as $contact)<option value="{{ $contact->id }}" @selected((string) old('customer_id', $project->customer_id ?? '') === (string) $contact->id)>{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach
                    </select>
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="consultant-selection">@lang('construction::lang.consultant')</label>
                    <select name="consultant_selection" id="consultant-selection" class="form-control select2" required aria-describedby="consultant-selection-hint">
                        <option value="none" @selected((string) $consultantSelection === 'none')>@lang('construction::lang.no_consultant')</option>
                        @foreach($consultants as $contact)<option value="{{ $contact->id }}" @selected((string) $consultantSelection === (string) $contact->id)>{{ $contact->supplier_business_name ?: $contact->name }}</option>@endforeach
                        <option value="manual" @selected((string) $consultantSelection === 'manual')>@lang('construction::lang.manual_consultant_option')</option>
                    </select>
                    <div class="rr-field__hint" id="consultant-selection-hint">@lang('construction::lang.consultant_choice_hint')</div>
                </div>
                <div class="rr-field" id="manual-consultant-wrap" style="{{ (string) $consultantSelection === 'manual' ? '' : 'display:none' }}">
                    <label class="rr-field__label" for="manual-consultant-name">@lang('construction::lang.manual_consultant_name'){!! $req !!}</label>
                    <div class="rr-control"><input name="consultant_name" id="manual-consultant-name" class="rr-control__input" maxlength="190" value="{{ old('consultant_name', $project->consultant_name ?? '') }}"></div>
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="project-manager">@lang('construction::lang.project_manager')</label>
                    <select id="project-manager" name="manager_id" class="form-control select2">
                        <option value="">@lang('construction::lang.choose')</option>
                        @foreach($users as $user)<option value="{{ $user->id }}" @selected((string) old('manager_id', $project->manager_id ?? '') === (string) $user->id)>{{ $user->user_full_name ?: $user->username }}</option>@endforeach
                    </select>
                </div>
                <div class="rr-field rr-field--full">
                    <label class="rr-field__label" for="project-members">@lang('construction::lang.project_members') <span class="rr-field__opt">(@lang('construction::lang.optional'))</span></label>
                    <select id="project-members" name="member_ids[]" class="form-control select2" multiple>
                        @foreach($users as $user)<option value="{{ $user->id }}" @selected(in_array($user->id, array_map('intval', $selectedMembers), true))>{{ $user->user_full_name ?: $user->username }}</option>@endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 3. الموقع والجدول الزمني --}}
<section class="rr-card rr-form-section" aria-labelledby="rr-form-schedule">
    <header class="rr-card__head">
        <span class="rr-step-num" aria-hidden="true">3</span>
        <div class="rr-card__titles">
            <h2 id="rr-form-schedule" class="rr-card__title">@lang('construction::lang.project_schedule')</h2>
            <p class="rr-card__desc">@lang('construction::lang.project_schedule_hint')</p>
        </div>
    </header>
    <div class="rr-card__body">
        <div class="rr-form-wrap">
            <div class="rr-form-grid">
                <div class="rr-field rr-field--full">
                    <label class="rr-field__label" for="project-location">@lang('construction::lang.location')</label>
                    <div class="rr-control"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'map-pin', 'size' => 18])</span><input id="project-location" class="rr-control__input" name="location" maxlength="255" value="{{ old('location', $project->location ?? '') }}"></div>
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="project-start">@lang('construction::lang.start_date')</label>
                    <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="project-start" type="text" name="start_date" class="rr-control__input ct-date-picker" readonly value="{{ old('start_date', isset($project) && $project->start_date ? @format_date($project->start_date) : '') }}"></div>
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="project-end">@lang('construction::lang.end_date')</label>
                    <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="project-end" type="text" name="end_date" class="rr-control__input ct-date-picker" readonly value="{{ old('end_date', isset($project) && $project->end_date ? @format_date($project->end_date) : '') }}"></div>
                </div>
                <div class="rr-field rr-field--full">
                    <label class="rr-field__label" for="project-description">@lang('construction::lang.description') <span class="rr-field__opt">(@lang('construction::lang.optional'))</span></label>
                    <div class="rr-control rr-control--area"><textarea id="project-description" class="rr-control__input" name="description" rows="3">{{ old('description', $project->description ?? '') }}</textarea></div>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="rr-form-actions">
    <button type="submit" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_project')</button>
    <a href="{{ $editing ? route('construction.projects.show', $project->id) : route('construction.projects.index') }}" class="rr-btn rr-btn--secondary">@lang('messages.cancel')</a>
    <span class="rr-form-actions__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.required_fields_hint')</span>
</footer>

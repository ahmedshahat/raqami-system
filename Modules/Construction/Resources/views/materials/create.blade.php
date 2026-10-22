@extends('construction::layouts.module')
@section('title', __('construction::lang.new_material_issue'))

@php
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
    $fieldError = fn ($field) => $errors->has($field) ? 'rr-control--invalid' : '';
@endphp

@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-material-form-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.materials.index') }}">@lang('construction::lang.material_documents')</a></li>
                    <li><span aria-current="page">@lang('construction::lang.new_material_issue')</span></li>
                </ol>
            </nav>
            <h1 id="rr-material-form-title" class="rr-head__title">@lang('construction::lang.new_material_issue')</h1>
            <p class="rr-head__sub">@lang('construction::lang.material_issue_draft_hint')</p>
        </div>
    </header>

    @include('construction::materials.partials.steps', ['current' => 1])

    <form method="POST" class="rr-form" action="{{ route('construction.materials.store') }}">
        @csrf
        @include('construction::labor.partials.form_errors')

        <section class="rr-card" aria-labelledby="rr-material-identity">
            <header class="rr-card__head">
                <div class="rr-card__titles">
                    <h2 id="rr-material-identity" class="rr-card__title">@lang('construction::lang.document_identity')</h2>
                    <p class="rr-card__desc">@lang('construction::lang.required_fields_hint')</p>
                </div>
            </header>
            <div class="rr-card__body">
                <div class="rr-form-wrap">
                    <div class="rr-form-grid">
                        <div class="rr-field">
                            <label class="rr-field__label" for="material-project">@lang('construction::lang.project'){!! $req !!}</label>
                            <select id="material-project" name="project_id" class="form-control select2" required>
                                <option value="">@lang('construction::lang.select_project')</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected((int) old('project_id', request('project_id')) === $project->id)>{{ $project->code }} — {{ $project->name }}</option>
                                @endforeach
                            </select>
                            @error('project_id')<p class="rr-field__error">{{ $message }}</p>@enderror
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="material-location">@lang('construction::lang.source_warehouse'){!! $req !!}</label>
                            <select id="material-location" name="location_id" class="form-control select2" required>
                                <option value="">@lang('construction::lang.select_warehouse')</option>
                                @foreach($locations as $id => $name)
                                    <option value="{{ $id }}" @selected((int) old('location_id', count($locations) === 1 ? $id : null) === (int) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('location_id')<p class="rr-field__error">{{ $message }}</p>@enderror
                        </div>
                        @include('construction::materials.partials.identity_fields', ['document' => null, 'defaultNumber' => $nextNumber])
                    </div>
                </div>
            </div>
        </section>

        <footer class="rr-form-actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'arrow-forward'])<span>@lang('construction::lang.create_draft_and_add_materials')</span></button>
            <a href="{{ route('construction.materials.index') }}" class="rr-btn rr-btn--secondary">@lang('messages.cancel')</a>
            <span class="rr-form-actions__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.stock_changes_after_approval')</span>
        </footer>
    </form>
</main>
@endsection

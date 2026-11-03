@extends('construction::layouts.module')
@section('title', __('construction::lang.new_labor_sheet'))

@php
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
    $fieldError = fn ($field) => $errors->has($field) ? 'rr-control--invalid' : '';
@endphp

@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-labor-form-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'users', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.labor.index') }}">@lang('construction::lang.labor_sheets')</a></li>
                    <li><span aria-current="page">@lang('construction::lang.new_labor_sheet')</span></li>
                </ol>
            </nav>
            <h1 id="rr-labor-form-title" class="rr-head__title">@lang('construction::lang.new_labor_sheet')</h1>
            <p class="rr-head__sub">@lang('construction::lang.labor_sheet_draft_hint')</p>
        </div>
    </header>

    <form method="POST" class="rr-form" action="{{ route('construction.labor.store') }}">
        @csrf
        @include('construction::labor.partials.form_errors')

        <section class="rr-card" aria-labelledby="rr-labor-identity">
            <header class="rr-card__head">
                <span class="rr-step-num" aria-hidden="true">1</span>
                <div class="rr-card__titles">
                    <h2 id="rr-labor-identity" class="rr-card__title">@lang('construction::lang.sheet_identity')</h2>
                    <p class="rr-card__desc">@lang('construction::lang.labor_required_fields_hint')</p>
                </div>
            </header>
            <div class="rr-card__body">
                <div class="rr-form-wrap">
                    <div class="rr-form-grid">
                        <div class="rr-field rr-field--full">
                            <label class="rr-field__label" for="labor-project">@lang('construction::lang.project'){!! $req !!}</label>
                            <select id="labor-project" name="project_id" class="form-control select2" required>
                                <option value="">@lang('construction::lang.select_project')</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected((int) old('project_id') === $project->id)>{{ $project->code }} — {{ $project->name }}</option>
                                @endforeach
                            </select>
                            @error('project_id')<p class="rr-field__error">{{ $message }}</p>@enderror
                        </div>
                        @include('construction::labor.partials.identity_fields', ['sheet' => null, 'defaultNumber' => $nextNumber])
                    </div>
                </div>
            </div>
        </section>

        <footer class="rr-form-actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'arrow-forward'])<span>@lang('construction::lang.create_sheet_and_add_labor')</span></button>
            <a href="{{ route('construction.labor.index') }}" class="rr-btn rr-btn--secondary">@lang('messages.cancel')</a>
            <span class="rr-form-actions__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.add_labor_hint')</span>
        </footer>
    </form>
</main>
@endsection

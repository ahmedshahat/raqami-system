@extends('construction::layouts.module')
@section('title', __('construction::lang.edit_sheet'))

@php
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
    $fieldError = fn ($field) => $errors->has($field) ? 'rr-control--invalid' : '';
@endphp

@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-labor-form-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.labor.index') }}">@lang('construction::lang.labor_sheets')</a></li>
                    <li><a href="{{ route('construction.labor.show', $sheet->id) }}" class="rr-num">{{ $sheet->number }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.edit')</span></li>
                </ol>
            </nav>
            <h1 id="rr-labor-form-title" class="rr-head__title">@lang('construction::lang.edit_sheet')</h1>
            <p class="rr-head__sub">@lang('construction::lang.edit_labor_sheet_hint')</p>
        </div>
    </header>

    <form method="POST" class="rr-form" action="{{ route('construction.labor.update', $sheet->id) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="project_id" value="{{ $sheet->project_id }}">
        @include('construction::labor.partials.form_errors')

        <section class="rr-card" aria-labelledby="rr-labor-identity">
            <header class="rr-card__head">
                <div class="rr-card__titles">
                    <h2 id="rr-labor-identity" class="rr-card__title">@lang('construction::lang.sheet_identity')</h2>
                </div>
                <span class="rr-badge rr-badge--warning"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.draft')</span>
            </header>
            <div class="rr-card__body">
                <div class="rr-form-wrap">
                    <div class="rr-form-grid">
                        <div class="rr-field rr-field--full">
                            <span class="rr-field__label">@lang('construction::lang.project')</span>
                            <div class="rr-locked">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 16])<span><span class="rr-code">{{ $sheet->project->code }}</span> — {{ $sheet->project->name }}</span></div>
                        </div>
                        @include('construction::labor.partials.identity_fields', ['defaultNumber' => $sheet->number])
                    </div>
                </div>
            </div>
        </section>

        <footer class="rr-form-actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])<span>@lang('construction::lang.save_changes')</span></button>
            <a href="{{ route('construction.labor.show', $sheet->id) }}" class="rr-btn rr-btn--secondary">@lang('messages.cancel')</a>
        </footer>
    </form>
</main>
@endsection

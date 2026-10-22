@extends('construction::layouts.module')
@section('title', __('construction::lang.edit_document'))

@php
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
    $fieldError = fn ($field) => $errors->has($field) ? 'rr-control--invalid' : '';
@endphp

@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-material-form-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.materials.index') }}">@lang('construction::lang.material_documents')</a></li>
                    <li><a href="{{ route('construction.materials.show', $document->id) }}" class="rr-num">{{ $document->number }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.edit')</span></li>
                </ol>
            </nav>
            <h1 id="rr-material-form-title" class="rr-head__title">@lang('construction::lang.edit_document')</h1>
            <p class="rr-head__sub">@lang('construction::lang.edit_draft_hint')</p>
        </div>
    </header>

    <form method="POST" class="rr-form" action="{{ route('construction.materials.update', $document->id) }}">
        @csrf
        @method('PUT')
        @include('construction::labor.partials.form_errors')

        <section class="rr-card" aria-labelledby="rr-material-identity">
            <header class="rr-card__head">
                <div class="rr-card__titles">
                    <h2 id="rr-material-identity" class="rr-card__title">@lang('construction::lang.document_identity')</h2>
                    <p class="rr-card__desc">@lang('construction::lang.edit_document_identity_hint')</p>
                </div>
                <span class="rr-badge rr-badge--warning"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.draft')</span>
            </header>
            <div class="rr-card__body">
                <div class="rr-form-wrap">
                    <div class="rr-form-grid">
                        <div class="rr-field">
                            <span class="rr-field__label">@lang('construction::lang.project')</span>
                            <div class="rr-locked">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 16])<span><span class="rr-code">{{ $document->project->code }}</span> — {{ $document->project->name }}</span></div>
                        </div>
                        <div class="rr-field">
                            <span class="rr-field__label">@lang('construction::lang.source_warehouse')</span>
                            <div class="rr-locked">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 16])<span>{{ $document->location->name }}</span></div>
                            <p class="rr-field__hint">@lang('construction::lang.fixed_after_creation')</p>
                        </div>
                        @include('construction::materials.partials.identity_fields', ['defaultNumber' => $document->number, 'notesRows' => 4])
                    </div>
                </div>
            </div>
        </section>

        <footer class="rr-form-actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])<span>@lang('construction::lang.save_changes')</span></button>
            <a href="{{ route('construction.materials.show', $document->id) }}" class="rr-btn rr-btn--secondary">@lang('messages.cancel')</a>
        </footer>
    </form>
</main>
@endsection

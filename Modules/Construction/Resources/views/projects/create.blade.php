@extends('construction::layouts.module')
@section('title', __('construction::lang.new_project'))
@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-project-form-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.overview.index') }}">@lang('construction::lang.tab_overview')</a></li>
                    <li><a href="{{ route('construction.projects.index') }}">@lang('construction::lang.projects')</a></li>
                    <li><span aria-current="page">@lang('construction::lang.new_project')</span></li>
                </ol>
            </nav>
            <h1 id="rr-project-form-title" class="rr-head__title">@lang('construction::lang.new_project')</h1>
            <p class="rr-head__sub">@lang('construction::lang.project_form_intro')</p>
        </div>
    </header>
    <form method="POST" action="{{ route('construction.projects.store') }}" class="rr-form">
        @csrf
        @include('construction::projects.partials.form')
    </form>
</main>
@endsection
@section('module_javascript')
    @include('construction::projects.partials.consultant_script')
@endsection

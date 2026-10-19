@extends('construction::layouts.module')
@section('title', __('construction::lang.create_contract_draft'))
@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-contract-form-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.projects.index') }}">@lang('construction::lang.projects')</a></li>
                    <li><a href="{{ route('construction.projects.show', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.create_contract_draft')</span></li>
                </ol>
            </nav>
            <h1 id="rr-contract-form-title" class="rr-head__title">@lang('construction::lang.create_contract_draft')</h1>
            <p class="rr-head__sub">@lang('construction::lang.contract_form_intro')</p>
        </div>
    </header>
    <form method="POST" class="rr-form ct-contract-form" action="{{ route('construction.projects.contracts.store', $project->id) }}">
        @csrf
        @include('construction::contracts.partials.form')
    </form>
</main>
@endsection

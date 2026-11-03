@extends('construction::layouts.module')
@section('title', __('construction::lang.edit_contract_draft'))
@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-contract-form-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.projects.index') }}">@lang('construction::lang.projects')</a></li>
                    <li><a href="{{ route('construction.projects.show', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><a href="{{ route('construction.projects.contracts.show', [$project->id, $contract->id]) }}" class="rr-num">{{ $contract->contract_number }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.edit')</span></li>
                </ol>
            </nav>
            <h1 id="rr-contract-form-title" class="rr-head__title">@lang('construction::lang.edit_contract_draft')</h1>
            <p class="rr-head__sub">{{ $contract->title }}</p>
        </div>
    </header>
    <form method="POST" class="rr-form ct-contract-form" action="{{ route('construction.projects.contracts.update', [$project->id, $contract->id]) }}">
        @csrf
        @method('PUT')
        @include('construction::contracts.partials.form')
    </form>
</main>
@endsection

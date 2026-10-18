@extends('construction::layouts.module')
@section('title', __('construction::lang.edit_contract_draft'))
@section('module_content')
<section class="content ct-contract-form-page"><form method="POST" class="ct-contract-form" action="{{ route('construction.projects.contracts.update', [$project->id,$contract->id]) }}">@csrf @method('PUT') @include('construction::contracts.partials.form')</form></section>
@endsection

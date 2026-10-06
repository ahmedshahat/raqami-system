@extends('construction::layouts.module')
@section('title', __('construction::lang.edit_contract_draft'))
@section('module_content')
<section class="content"><form method="POST" action="{{ route('construction.projects.contracts.update', [$project->id,$contract->id]) }}">@csrf @method('PUT') @include('construction::contracts.partials.form')</form></section>
@endsection

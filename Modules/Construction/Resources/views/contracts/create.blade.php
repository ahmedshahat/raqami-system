@extends('construction::layouts.module')
@section('title', __('construction::lang.create_contract_draft'))
@section('module_content')
<section class="content"><form method="POST" action="{{ route('construction.projects.contracts.store', $project->id) }}">@csrf @include('construction::contracts.partials.form')</form></section>
@endsection

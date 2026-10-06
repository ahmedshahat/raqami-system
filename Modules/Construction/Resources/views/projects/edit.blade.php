@extends('construction::layouts.module')
@section('title', __('construction::lang.edit_project'))
@section('module_content')
<section class="content"><form method="POST" action="{{ route('construction.projects.update',$project->id) }}">@csrf @method('PUT') @include('construction::projects.partials.form')</form></section>
@endsection
@section('module_javascript')
    @include('construction::projects.partials.consultant_script')
@endsection

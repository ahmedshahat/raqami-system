@extends('construction::layouts.module')

@section('title', __('construction::lang.tab_subcontractors'))

@section('module_content')
<section class="content">
    <div class="ct-coming-soon">
        <div class="ct-coming-soon__icon"><i class="fas fa-people-carry"></i></div>
        <span>@lang('construction::lang.next_development_stage')</span>
        <h2>@lang('construction::lang.subcontractors_workspace_title')</h2>
        <p>@lang('construction::lang.subcontractors_workspace_description')</p>
        <div class="ct-feature-preview">
            @foreach(['subcontractors_feature_1', 'subcontractors_feature_2', 'subcontractors_feature_3'] as $feature)
                <div><i class="fas fa-check"></i><span>@lang('construction::lang.' . $feature)</span></div>
            @endforeach
        </div>
    </div>
</section>
@endsection

@extends('construction::layouts.module')

@section('title', __('construction::lang.tab_boq'))

@section('module_content')
<section class="content">
    <div class="ct-section-heading">
        <div>
            <span>@lang('construction::lang.available_now')</span>
            <h2>@lang('construction::lang.boq_workspace_title')</h2>
            <p>@lang('construction::lang.boq_workspace_description')</p>
        </div>
        <a href="{{ route('construction.cost-codes.index') }}" class="ct-btn ct-btn--primary">
            <i class="fas fa-tags"></i> @lang('construction::lang.manage_cost_codes')
        </a>
    </div>

    <div class="ct-project-card-grid">
        @forelse($projects as $project)
            <article class="ct-project-card">
                <div class="ct-project-card__head">
                    <span class="ct-status ct-status--{{ $project->status }}">@lang('construction::lang.'.$project->status)</span>
                    <span>{{ $project->code }}</span>
                </div>
                <h3>{{ $project->name }}</h3>
                <p><i class="far fa-building"></i> {{ $project->customer?->supplier_business_name ?: $project->customer?->name }}</p>
                <div class="ct-project-card__meta">
                    <span>
                        <small>@lang('construction::lang.approved_boqs')</small>
                        <b>{{ $project->approvedBoq?->name ?: __('construction::lang.not_approved_yet') }}</b>
                    </span>
                </div>
                <a href="{{ route('construction.projects.boq.index', $project->id) }}">
                    @lang('construction::lang.open_project_boq') <i class="fas fa-arrow-left"></i>
                </a>
            </article>
        @empty
            <div class="ct-empty ct-empty--wide">
                <i class="far fa-folder-open"></i>
                <p>@lang('construction::lang.no_projects')</p>
            </div>
        @endforelse
    </div>

    @if(method_exists($projects, 'links'))
        <div style="padding: 14px 16px;">
            {{ $projects->links() }}
        </div>
    @endif
</section>
@endsection

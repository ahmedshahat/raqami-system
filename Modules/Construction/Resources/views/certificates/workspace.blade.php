@extends('construction::layouts.module')

@section('title', __('construction::lang.tab_certificates'))

@section('module_content')
<section class="content">
    <div class="ct-section-heading">
        <div>
            <span>@lang('construction::lang.available_now')</span>
            <h2>@lang('construction::lang.customer_certificates')</h2>
            <p>@lang('construction::lang.certificates_description')</p>
        </div>
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
                        <small>@lang('construction::lang.contract_value')</small>
                        <b>@include('construction::partials.money', ['value' => optional($project->primaryContract)->original_value ?? 0])</b>
                    </span>
                    <span>
                        <small>@lang('construction::lang.tab_certificates')</small>
                        <b>{{ $project->customer_certificates_count }} @lang('construction::lang.tab_certificates')</b>
                    </span>
                </div>
                <a href="{{ route('construction.projects.certificates.index', $project->id) }}">
                    @lang('construction::lang.open_certificates') <i class="fas fa-arrow-left"></i>
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

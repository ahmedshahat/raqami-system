@extends('construction::layouts.module')
@section('title', __('construction::lang.boq_versions'))

@section('module_content')
@php
    $latestVersion = $versions->first();
    $approvedCount = $versions->where('status', 'approved')->count();
    $itemsCount = $versions->sum('items_count');
@endphp
<section class="content ct-boq-versions-page">
    <header class="ct-boq-landing-hero">
        <div class="ct-boq-landing-hero__copy">
            <span class="ct-boq-landing-hero__icon" aria-hidden="true"><i class="fa fa-list-ol"></i></span>
            <div><h1>@lang('construction::lang.boq_versions')</h1><p>{{ $project->name }} · {{ $project->code }}</p></div>
        </div>
        <div class="ct-boq-landing-hero__actions">
            <a href="{{ route('construction.cost-codes.index') }}" class="ct-boq-button ct-boq-button--glass"><i class="fa fa-tags" aria-hidden="true"></i> @lang('construction::lang.manage_cost_codes')</a>
            <a href="{{ route('construction.projects.show', $project->id) }}" class="ct-boq-button ct-boq-button--light"><i class="fa fa-arrow-right" aria-hidden="true"></i> @lang('construction::lang.project_details')</a>
        </div>
    </header>

    <div class="ct-boq-version-metrics" aria-label="@lang('construction::lang.boq_versions')">
        <article class="is-primary"><span><i class="fa fa-layer-group" aria-hidden="true"></i></span><div><small>@lang('construction::lang.boq_versions')</small><strong>{{ $versions->count() }}</strong></div></article>
        <article><span><i class="fa fa-check-circle" aria-hidden="true"></i></span><div><small>@lang('construction::lang.approved_boqs')</small><strong>{{ $approvedCount }}</strong></div></article>
        <article><span><i class="fa fa-list-ul" aria-hidden="true"></i></span><div><small>@lang('construction::lang.boq_items')</small><strong>{{ $itemsCount }}</strong></div></article>
        <article><span><i class="fa fa-coins" aria-hidden="true"></i></span><div><small>@lang('construction::lang.boq_sales_total')</small><strong>@include('construction::partials.money', ['value' => $latestVersion?->sales_total ?? 0])</strong></div></article>
    </div>

    <div class="ct-boq-versions-layout">
        <section class="ct-boq-versions-panel" aria-labelledby="ct-boq-versions-title">
            <header><div><h2 id="ct-boq-versions-title">@lang('construction::lang.boq_versions')</h2><p>@lang('construction::lang.boq_versions_description')</p></div><span>{{ $versions->count() }}</span></header>
            @if($versions->isEmpty())
                <div class="ct-boq-versions-empty"><span><i class="fa fa-clipboard-list" aria-hidden="true"></i></span><h3>@lang('construction::lang.no_boq_versions')</h3></div>
            @else
                <div class="ct-boq-version-list">
                    @foreach($versions as $version)
                        <article class="ct-boq-version-card {{ $loop->first ? 'is-current' : '' }}">
                            <div class="ct-boq-version-card__number">V{{ $version->version_number }}</div>
                            <div class="ct-boq-version-card__copy"><div><h3>{{ $version->name }}</h3><span class="ct-boq-state ct-boq-state--{{ $version->status }}">@lang('construction::lang.'.$version->status)</span></div><dl><div><dt>@lang('construction::lang.boq_items')</dt><dd>{{ $version->items_count }}</dd></div><div><dt>@lang('construction::lang.boq_sales_total')</dt><dd>@include('construction::partials.money', ['value' => $version->sales_total])</dd></div></dl></div>
                            <a href="{{ route('construction.projects.boq.show', [$project->id, $version->id]) }}" class="ct-boq-button ct-boq-button--primary"><span>@lang('construction::lang.open_boq')</span><i class="fa fa-arrow-left" aria-hidden="true"></i></a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @can('construction.boq.manage')
        <aside class="ct-boq-create-card">
            <header><span aria-hidden="true"><i class="fa fa-plus"></i></span><div><h2>@lang('construction::lang.new_boq_version')</h2><p>@lang('construction::lang.boq_versions_description')</p></div></header>
            <form method="POST" action="{{ route('construction.projects.boq.store', $project->id) }}">@csrf
                <div class="form-group"><label for="boq-version-name">@lang('construction::lang.boq_version_name') <em>*</em></label><input id="boq-version-name" name="name" value="{{ old('name', __('construction::lang.boq_default_name')) }}" class="form-control" maxlength="190" required></div>
                <div class="form-group"><label for="boq-version-notes">@lang('construction::lang.boq_notes')</label><textarea id="boq-version-notes" name="notes" class="form-control" rows="5" maxlength="5000">{{ old('notes') }}</textarea></div>
                <button class="ct-boq-button ct-boq-button--primary" type="submit"><i class="fa fa-plus" aria-hidden="true"></i> @lang('construction::lang.new_boq_version')</button>
            </form>
        </aside>
        @endcan
    </div>
</section>
@endsection

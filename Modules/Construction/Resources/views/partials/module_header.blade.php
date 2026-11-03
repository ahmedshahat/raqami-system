<section class="ct-hero ct-hero--compact ct-overview-hero" aria-labelledby="ct-overview-title">
    <div class="ct-overview-hero__shape ct-overview-hero__shape--one" aria-hidden="true"></div>
    <div class="ct-overview-hero__shape ct-overview-hero__shape--two" aria-hidden="true"></div>
    <div class="ct-hero__content">
        <div class="ct-overview-hero__identity" aria-hidden="true">
            <img src="{{ asset('img/logo-small.png') }}" width="72" height="72" alt="">
        </div>
        <div class="ct-hero__copy">
            <span class="ct-overview-hero__eyebrow"><i class="fas fa-hard-hat" aria-hidden="true"></i> @lang('construction::lang.command_center')</span>
            <h1 id="ct-overview-title">@lang('construction::lang.dashboard_title')</h1>
            <p>@lang('construction::lang.dashboard_description')</p>
            <div class="ct-overview-hero__meta">
                <span><i class="fas fa-circle" aria-hidden="true"></i> @lang('construction::lang.live_local_data')</span>
                <span><i class="far fa-calendar-alt" aria-hidden="true"></i> {{ @format_date(now()) }}</span>
            </div>
        </div>
        <div class="ct-hero__actions">
            @can('construction.project.create')
                <a href="{{ route('construction.projects.index', ['open_create' => 1]) }}" class="ct-btn ct-btn--light"><i class="fas fa-plus" aria-hidden="true"></i> @lang('construction::lang.new_project')</a>
            @endcan
            @can('construction.project.view')
                <a href="{{ route('construction.projects.index') }}" class="ct-btn ct-btn--glass"><i class="fas fa-building" aria-hidden="true"></i> @lang('construction::lang.all_projects')</a>
            @endcan
        </div>
    </div>
</section>

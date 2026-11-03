@extends('construction::layouts.module')

@section('title', __('construction::lang.install_module'))

@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-install-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 24])</span>
        <div class="rr-head__titles">
            <h1 id="rr-install-title" class="rr-head__title">@lang('construction::lang.install_module')</h1>
            <p class="rr-head__sub">@lang('construction::lang.install_explanation')</p>
        </div>
    </header>

    <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-install-action-title">
        <div class="rr-next">
            <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 22])</span>
            <div class="rr-next__copy">
                <h2 id="rr-install-action-title" class="rr-next__title">@lang('construction::lang.install_module')</h2>
                <p class="rr-next__text">Version <span class="rr-num">{{ $moduleVersion }}</span></p>
            </div>
            <form class="rr-inline-form" method="POST" action="{{ route('construction.install.store') }}">
                @csrf
                <button type="submit" class="rr-btn rr-btn--primary rr-btn--lg">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.install')</button>
            </form>
        </div>
    </section>
</main>
@endsection

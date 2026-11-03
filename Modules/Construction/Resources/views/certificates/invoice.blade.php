@extends('construction::layouts.module')
@section('title', __('construction::lang.construction_invoice').' '.$sell->invoice_no)

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-certificate-invoice-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--success" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.projects.certificates.index', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><a href="{{ route('construction.projects.certificates.show', [$project->id, $certificate->id]) }}" class="rr-num">{{ $certificate->number }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.construction_invoice')</span></li>
                </ol>
            </nav>
            <h1 id="rr-certificate-invoice-title" class="rr-head__title">@lang('construction::lang.invoice_created_with_number', ['number' => $sell->invoice_no])</h1>
            <p class="rr-head__sub">{{ $project->name }} · <span class="rr-code">{{ $certificate->number }}</span></p>
        </div>
        <div class="rr-head__actions">
            <a class="rr-btn rr-btn--secondary" href="{{ route('construction.projects.certificates.show', [$project->id, $certificate->id]) }}">@include('construction::partials.rr_icon', ['name' => 'arrow-back'])@lang('construction::lang.back_to_certificate')</a>
            <a class="rr-btn rr-btn--ghost" href="{{ route('sell.printInvoice', $sell->id) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'print'])@lang('construction::lang.print')</a>
            <a class="rr-btn rr-btn--ghost" href="{{ route('sell.downloadPdf', $sell->id) }}">@include('construction::partials.rr_icon', ['name' => 'download'])PDF</a>
        </div>
    </header>
</main>
{{-- محتوى الفاتورة من الكود الأساسي (sale_pos.show) برّه rr-page عشان تنسيقات نظام التصميم ما تأثرش عليه --}}
<section class="content ct-embedded-invoice-page" data-construction-invoice-container>
    <div class="ct-embedded-invoice-card">
        @include('sale_pos.show')
    </div>
</section>
@endsection

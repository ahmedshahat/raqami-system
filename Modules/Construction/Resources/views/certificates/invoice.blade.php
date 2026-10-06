@extends('construction::layouts.module')
@section('title', __('construction::lang.construction_invoice').' '.$sell->invoice_no)

@section('module_content')
<section class="content ct-embedded-invoice-page" data-construction-invoice-container>
    <header class="ct-embedded-invoice-head">
        <div>
            <span><i class="fa fa-file-invoice"></i> @lang('construction::lang.construction_invoice')</span>
            <h2>@lang('construction::lang.invoice_created_with_number', ['number' => $sell->invoice_no])</h2>
            <p>{{ $project->name }} · {{ $certificate->number }}</p>
        </div>
        <div class="ct-embedded-invoice-actions">
            <a class="ct-measurement-button ct-measurement-button--primary" href="{{ route('construction.projects.certificates.show', [$project->id, $certificate->id]) }}"><i class="fa fa-arrow-right"></i> @lang('construction::lang.back_to_certificate')</a>
            <a class="ct-measurement-button ct-measurement-button--soft" href="{{ route('sell.printInvoice', $sell->id) }}" target="_blank"><i class="fa fa-print"></i> @lang('construction::lang.print')</a>
            <a class="ct-measurement-button ct-measurement-button--soft" href="{{ route('sell.downloadPdf', $sell->id) }}"><i class="fa fa-file-pdf"></i> PDF</a>
        </div>
    </header>
    <div class="ct-embedded-invoice-card">
        @include('sale_pos.show')
    </div>
</section>
@endsection

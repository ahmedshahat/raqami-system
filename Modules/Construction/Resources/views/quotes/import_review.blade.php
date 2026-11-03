@extends('construction::layouts.module')
@section('title', __('construction::lang.quote_import_review'))

@php
    $rows = collect($batch->rows_json);
    $errorRows = $rows->filter(fn ($row) => count($row['errors']) > 0)->count();
    $validRows = $rows->count() - $errorRows;
    $hasErrors = $batch->error_count > 0;
    $importTotal = $rows->sum('total');
    $currencyParts = \Modules\Construction\Support\CurrencyFormatter::parts(0);
    $quantityPrecision = (int) session('business.quantity_precision', 2);
    // وحدات مكتوبة في الملف ومش موجودة في النظام: نعرض زرار إضافتها بدل ما المستخدم يدور عليها
    $missingUnits = $rows->filter(fn ($row) => empty($row['unit_id']) && trim((string) ($row['unit'] ?? '')) !== '')->pluck('unit')->unique()->values();
    $canCreateUnits = auth()->user()->can('unit.create') || (new \App\Utils\Util())->is_admin(auth()->user(), (int) session('user.business_id'));
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-import-title">

    {{-- رأس الصفحة --}}
    <header class="rr-head">
        <img class="rr-head__logo" src="{{ asset('img/logo-small.png') }}" width="56" height="56" alt="">
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.overview.index') }}">@lang('construction::lang.tab_overview')</a></li>
                    <li><a href="{{ route('construction.quotes.index') }}">@lang('construction::lang.quotes')</a></li>
                    <li><a href="{{ route('construction.quotes.show', $quote->id) }}" class="rr-num">{{ $quote->number }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.quote_import_review')</span></li>
                </ol>
            </nav>
            <h1 id="rr-import-title" class="rr-head__title">@lang('construction::lang.quote_import_review')</h1>
            <div class="rr-head__meta">
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])<bdi>{{ $batch->file_name }}</bdi></span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])@lang('construction::lang.import_preview_valid_until', ['time' => $batch->expires_at->format('H:i')])</span>
            </div>
        </div>
        <div class="rr-head__actions">
            <a class="rr-btn rr-btn--secondary" href="{{ route('construction.quotes.show', $quote->id) }}#quote-items">@include('construction::partials.rr_icon', ['name' => 'arrow-back'])@lang('construction::lang.back_to_quote')</a>
            @unless($hasErrors)
                <form class="rr-inline-form" method="POST" action="{{ route('construction.quotes.items.import.commit', [$quote->id, $batch->token]) }}">
                    @csrf
                    <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.confirm_import')</button>
                </form>
            @endunless
        </div>
    </header>

    {{-- ملخص الملف --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4" aria-label="@lang('construction::lang.quote_import_summary')">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.import_rows')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $batch->row_count }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.import_valid_rows')</div>
                <div class="rr-stat__value rr-stat__value--success"><span class="rr-num">{{ $validRows }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 18])@lang('construction::lang.import_error_rows')</div>
                <div class="rr-stat__value {{ $errorRows ? 'rr-stat__value--danger' : '' }}"><span class="rr-num">{{ $errorRows }}</span></div>
                @if($hasErrors)<div class="rr-stat__hint">@lang('construction::lang.import_errors_count', ['count' => $batch->error_count])</div>@endif
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.import_total')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $importTotal == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $importTotal])</span></div>
            </div>
        </div>
    </div>

    {{-- نتيجة الفحص --}}
    <section class="rr-callout {{ $hasErrors ? 'rr-callout--danger' : 'rr-callout--success' }}" role="status" aria-labelledby="rr-import-state-title">
        <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => $hasErrors ? 'info' : 'check-circle', 'size' => 22])</span>
        <div class="rr-callout__copy">
            <strong id="rr-import-state-title">{{ $hasErrors ? __('construction::lang.quote_import_needs_fix') : __('construction::lang.import_ready') }}</strong>
            <p>{{ $hasErrors ? __('construction::lang.import_fix_errors') : __('construction::lang.quote_import_confirm_hint') }}</p>
            @if($missingUnits->isNotEmpty())
                <p>@lang('construction::lang.import_missing_units', ['units' => $missingUnits->implode('، ')])</p>
            @endif
        </div>
        <div class="rr-callout__actions">
            @if($hasErrors)
                @if($missingUnits->isNotEmpty() && $canCreateUnits)
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" data-rr-unit-add>@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_unit')</button>
                @endif
                <a class="rr-btn rr-btn--secondary rr-btn--sm" href="{{ route('construction.quotes.import-template') }}">@include('construction::partials.rr_icon', ['name' => 'download'])@lang('construction::lang.download_import_template')</a>
                <a class="rr-btn rr-btn--primary rr-btn--sm" href="{{ route('construction.quotes.show', ['quote' => $quote->id, 'import' => 1]) }}">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.import_upload_again')</a>
            @endif
        </div>
    </section>

    {{-- صفوف الملف --}}
    <section class="rr-table-wrap" aria-labelledby="rr-import-rows-title">
        <div class="rr-table-head">
            <h2 id="rr-import-rows-title" class="rr-table-head__title">@lang('construction::lang.import_file_rows')</h2>
            @if($hasErrors && $validRows > 0)
                <label class="rr-toggle">
                    <input type="checkbox" class="rr-toggle__input" data-rr-errors-only>
                    <span>@lang('construction::lang.import_show_errors_only')</span>
                </label>
            @endif
        </div>
        <div class="rr-table-scroll">
            <table class="rr-table rr-table--wide">
                <caption class="rr-sr">@lang('construction::lang.import_file_rows')</caption>
                <thead>
                    <tr>
                        <th class="rr-th rr-th--num rr-col--index" scope="col">#</th>
                        <th class="rr-th" scope="col">@lang('construction::lang.boq_description')</th>
                        <th class="rr-th" scope="col">@lang('construction::lang.unit')</th>
                        <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.quantity')</th>
                        <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.unit_price')</th>
                        <th class="rr-th rr-th--num" scope="col"><span class="rr-th__in">@lang('construction::lang.total')@if($currencyParts['symbol'] !== '')<span class="rr-th__unit">({{ $currencyParts['symbol'] }})</span>@endif</span></th>
                        <th class="rr-th" scope="col">@lang('construction::lang.import_row_check')</th>
                    </tr>
                </thead>
                <tbody class="rr-tbody">
                    @foreach($rows as $row)
                        @php $rowHasErrors = count($row['errors']) > 0; @endphp
                        <tr class="rr-tr {{ $rowHasErrors ? 'rr-tr--danger' : '' }}" @unless($rowHasErrors) data-rr-valid-row @endunless>
                            <td class="rr-td rr-td--num rr-td--muted"><span class="rr-num">{{ $row['row_number'] }}</span></td>
                            <td class="rr-td rr-td--wrap rr-col--text">{{ $row['description'] ?: '—' }}</td>
                            <td class="rr-td rr-td--muted">{{ $row['unit'] ?: '—' }}</td>
                            <td class="rr-td rr-td--num"><span class="rr-num">{{ number_format((float) $row['quantity'], $quantityPrecision) }}</span></td>
                            <td class="rr-td rr-td--num"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($row['unit_price'])['amount'] }}</span></td>
                            <td class="rr-td rr-td--num"><strong class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($row['total'])['amount'] }}</strong></td>
                            <td class="rr-td rr-td--wrap rr-col--notes">
                                @if($rowHasErrors)
                                    <ul class="rr-error-list">
                                        @foreach($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
                                    </ul>
                                @else
                                    <span class="rr-badge rr-badge--success">@include('construction::partials.rr_icon', ['name' => 'check', 'size' => 14])@lang('construction::lang.valid_row')</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="rr-tfoot">
                    <tr>
                        <td class="rr-td" colspan="5">@lang('construction::lang.import_total')</td>
                        <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $importTotal])</span></td>
                        <td class="rr-td"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="rr-toolbar">
            <a class="rr-btn rr-btn--secondary" href="{{ route('construction.quotes.show', $quote->id) }}#quote-items">@include('construction::partials.rr_icon', ['name' => 'arrow-back'])@lang('construction::lang.back_to_quote')</a>
            @unless($hasErrors)
                <div class="rr-toolbar__end">
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.quotes.items.import.commit', [$quote->id, $batch->token]) }}">
                        @csrf
                        <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.confirm_import')</button>
                    </form>
                </div>
            @endunless
        </div>
    </section>
</main>
@include('construction::partials.unit_quick_create')
@endsection

@section('module_javascript')
<script>
$(function () {
    $('[data-rr-errors-only]').on('change', function () {
        $('[data-rr-valid-row]').prop('hidden', this.checked);
    });
});
</script>
@endsection

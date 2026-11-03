@extends('construction::layouts.module')
@section('title', __('construction::lang.create_material_return'))

@php
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
    $fieldError = fn ($field) => $errors->has($field) ? 'rr-control--invalid' : '';
@endphp

@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-material-return-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.materials.index') }}">@lang('construction::lang.material_documents')</a></li>
                    <li><a href="{{ route('construction.materials.show', $issue->id) }}" class="rr-num">{{ $issue->number }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.create_material_return')</span></li>
                </ol>
            </nav>
            <h1 id="rr-material-return-title" class="rr-head__title">@lang('construction::lang.create_material_return')</h1>
            <p class="rr-head__sub">@lang('construction::lang.material_return_hint', ['number' => $issue->number])</p>
        </div>
    </header>

    <div class="rr-callout rr-callout--info">
        <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 20])</span>
        <div class="rr-callout__copy">
            <strong>@lang('construction::lang.source_issue'): <a class="rr-code-link" href="{{ route('construction.materials.show', $issue->id) }}"><span class="rr-code">{{ $issue->number }}</span></a></strong>
            <p>{{ $issue->project->code }} — {{ $issue->project->name }} · {{ $issue->location->name }}</p>
        </div>
    </div>

    <form method="POST" class="rr-form" action="{{ route('construction.materials.returns.store', $issue->id) }}">
        @csrf
        @include('construction::labor.partials.form_errors')

        <section class="rr-card" aria-labelledby="rr-return-identity">
            <header class="rr-card__head">
                <span class="rr-step-num" aria-hidden="true">1</span>
                <div class="rr-card__titles">
                    <h2 id="rr-return-identity" class="rr-card__title">@lang('construction::lang.return_document_data')</h2>
                    <p class="rr-card__desc">@lang('construction::lang.return_document_data_hint')</p>
                </div>
            </header>
            <div class="rr-card__body">
                <div class="rr-form-wrap">
                    <div class="rr-form-grid">
                        @include('construction::materials.partials.identity_fields', ['document' => null, 'defaultNumber' => $nextNumber, 'notesRows' => 2])
                    </div>
                </div>
            </div>
        </section>

        <section class="rr-table-wrap" aria-labelledby="rr-return-lines">
            <div class="rr-table-head">
                <span class="rr-step-num" aria-hidden="true">2</span>
                <div>
                    <h2 id="rr-return-lines" class="rr-table-head__title">@lang('construction::lang.select_return_quantities')</h2>
                    <p class="rr-table-head__sub">@lang('construction::lang.select_return_quantities_hint')</p>
                </div>
            </div>
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.return_quantities')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.material_product')</th>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.project_item')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.issued_quantity')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.previously_returned')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.available_to_return')</th>
                            <th class="rr-th rr-th--num rr-th--focus" scope="col">@lang('construction::lang.return_quantity')</th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($issue->lines as $index => $line)
                            @php $remaining = max(0, (float) $line->quantity - (float) ($returned[$line->id] ?? 0)); @endphp
                            <tr class="rr-tr {{ $remaining <= 0 ? 'rr-tr--muted' : '' }}">
                                <td class="rr-td rr-td--wrap">
                                    <span class="rr-entity__name">{{ $line->variation->full_name }}</span>
                                    <div class="rr-td__sub">{{ $line->product->unit?->short_name }}</div>
                                    <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
                                </td>
                                <td class="rr-td rr-td--wrap">
                                    @if($line->boqItem)
                                        <span class="rr-code">{{ $line->boqItem->code }}</span> {{ $line->boqItem->description }}
                                    @else
                                        <span class="rr-td--muted">@lang('construction::lang.general_project_material')</span>
                                    @endif
                                </td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($line->quantity) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($returned[$line->id] ?? 0) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-badge rr-badge--{{ $remaining <= 0 ? 'neutral' : 'success' }}"><span class="rr-num">{{ @num_format($remaining) }}</span></span></td>
                                <td class="rr-td rr-td--num rr-td--focus rr-td--input">
                                    <div class="rr-control rr-control--sm {{ $errors->has('lines.' . $index . '.quantity') ? 'rr-control--invalid' : '' }}"><input name="lines[{{ $index }}][quantity]" value="{{ old('lines.' . $index . '.quantity', 0) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" aria-label="@lang('construction::lang.return_quantity'): {{ $line->variation->full_name }}" @disabled($remaining <= 0)></div>
                                    @error('lines.' . $index . '.quantity')<small class="rr-field__error">{{ $message }}</small>@enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="rr-form-actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])<span>@lang('construction::lang.save_return_draft')</span></button>
            <a href="{{ route('construction.materials.show', $issue->id) }}" class="rr-btn rr-btn--secondary">@lang('messages.cancel')</a>
            <span class="rr-form-actions__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.stock_changes_after_approval')</span>
        </footer>
    </form>
</main>
@endsection

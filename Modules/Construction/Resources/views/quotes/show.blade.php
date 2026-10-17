@extends('construction::layouts.module')
@section('title', $quote->number)

@php
    $quoteTones = ['draft' => 'neutral', 'sent' => 'brand', 'accepted' => 'success', 'rejected' => 'danger', 'expired' => 'warning', 'converted' => 'info'];
    $quoteState = $quote->project_id ? 'converted' : $quote->status;
    $isEditable = in_array($quote->status, ['draft', 'sent'], true) && ! $quote->project_id;
    $customerName = $quote->customer?->supplier_business_name ?: $quote->customer?->name;
    $expiryDate = $quote->quote_date->copy()->addDays((int) $quote->validity_days);
    $currencyParts = \Modules\Construction\Support\CurrencyFormatter::parts(0);
    $canManageItems = $isEditable && auth()->user()->can('construction.boq.manage');
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-quote-title">

    {{-- رأس الصفحة --}}
    <header class="rr-head">
        <img class="rr-head__logo" src="{{ asset('img/logo-small.png') }}" width="56" height="56" alt="">
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.overview.index') }}">@lang('construction::lang.tab_overview')</a></li>
                    <li><a href="{{ route('construction.quotes.index') }}">@lang('construction::lang.quotes')</a></li>
                    <li><span aria-current="page" class="rr-num">{{ $quote->number }}</span></li>
                </ol>
            </nav>
            <h1 id="rr-quote-title" class="rr-head__title">{{ $quote->title }}</h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $quoteTones[$quoteState] ?? 'neutral' }}">
                    @if($quoteState === 'converted')@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 14])@else<span class="rr-badge__dot" aria-hidden="true"></span>@endif
                    @lang('construction::lang.quote_status_' . $quoteState)
                </span>
                <span class="rr-code">{{ $quote->number }}</span>
                @if($customerName)<span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $customerName }}</span>@endif
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])<span class="rr-num">{{ @format_date($quote->quote_date) }}</span></span>
            </div>
        </div>
        <div class="rr-head__actions" role="group" aria-label="@lang('construction::lang.quote_actions_label')">
            @if($isEditable)
                @can('construction.project.create')
                    <button class="rr-btn rr-btn--secondary" type="button" data-toggle="modal" data-target="#ct-quote-edit">@include('construction::partials.rr_icon', ['name' => 'edit'])@lang('construction::lang.edit_quote')</button>
                @endcan
            @endif
            @if(! $quote->project_id)
                @can('construction.boq.approve')
                    <button class="rr-btn rr-btn--secondary" type="button" data-toggle="modal" data-target="#ct-quote-status">@include('construction::partials.rr_icon', ['name' => 'check-circle'])@lang('construction::lang.change_quote_status')</button>
                @endcan
            @endif
            <a class="rr-btn rr-btn--ghost" href="{{ route('construction.quotes.preview', $quote->id) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.preview')</a>
            <a class="rr-btn rr-btn--ghost" href="{{ route('construction.quotes.print', $quote->id) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'print'])@lang('construction::lang.print')</a>
            <a class="rr-btn rr-btn--ghost" href="{{ route('construction.quotes.pdf', $quote->id) }}">@include('construction::partials.rr_icon', ['name' => 'download'])PDF</a>
        </div>
    </header>

    {{-- المؤشرات --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.quote_total')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $quote->total == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $quote->total])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.quote_items_count')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $quote->items->count() }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])@lang('construction::lang.quote_expiry_date')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ @format_date($expiryDate) }}</span></div>
                <div class="rr-stat__hint"><span class="rr-num">{{ (int) $quote->validity_days }}</span> @lang('construction::lang.days')</div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 18])@lang('construction::lang.quote_linked_project')</div>
                <div class="rr-stat__value">
                    @if($quote->project)
                        <a class="rr-code-link" href="{{ route('construction.projects.show', $quote->project->id) }}"><span class="rr-num">{{ $quote->project->code }}</span></a>
                    @else
                        <span class="rr-money--zero">—</span>
                    @endif
                </div>
                @unless($quote->project)<div class="rr-stat__hint">@lang('construction::lang.quote_not_converted')</div>@endunless
            </div>
        </div>
    </div>

    {{-- الخطوة التالية --}}
    <section class="rr-card rr-card__body" aria-labelledby="rr-quote-next-title">
        <div class="rr-next">
            <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'arrow-forward', 'size' => 22])</span>
            <div class="rr-next__copy">
                <div class="rr-eyebrow">@lang('construction::lang.next_action')</div>
                @if($quote->project)
                    <h2 id="rr-quote-next-title" class="rr-next__title">@lang('construction::lang.quote_next_open_project')</h2>
                    <p class="rr-next__text">@lang('construction::lang.quote_converted_to', ['code' => $quote->project->code])</p>
                @elseif($quote->status === 'accepted')
                    <h2 id="rr-quote-next-title" class="rr-next__title">@lang('construction::lang.quote_next_convert')</h2>
                    <p class="rr-next__text">@lang('construction::lang.quote_ready_to_convert')</p>
                @elseif($quote->items->isEmpty())
                    <h2 id="rr-quote-next-title" class="rr-next__title">@lang('construction::lang.quote_next_add_items')</h2>
                    <p class="rr-next__text">@lang('construction::lang.quote_items_hint')</p>
                @elseif($isEditable)
                    <h2 id="rr-quote-next-title" class="rr-next__title">@lang('construction::lang.quote_next_review_status')</h2>
                    <p class="rr-next__text">@lang('construction::lang.quote_next_review_status_hint')</p>
                @else
                    <h2 id="rr-quote-next-title" class="rr-next__title">@lang('construction::lang.quote_next_preview')</h2>
                    <p class="rr-next__text">@lang('construction::lang.quote_next_preview_hint')</p>
                @endif
            </div>
            @if($quote->project)
                <a class="rr-btn rr-btn--secondary rr-btn--lg" href="{{ route('construction.projects.show', $quote->project->id) }}">@lang('construction::lang.open_project')@include('construction::partials.rr_icon', ['name' => 'arrow-forward'])</a>
            @elseif($quote->status === 'accepted')
                @can('construction.project.create')
                    <button type="button" class="rr-btn rr-btn--primary rr-btn--lg" data-toggle="modal" data-target="#ct-quote-convert">@include('construction::partials.rr_icon', ['name' => 'building'])@lang('construction::lang.convert_to_project')</button>
                @endcan
            @elseif($quote->items->isEmpty() && $canManageItems)
                <a class="rr-btn rr-btn--secondary rr-btn--lg" href="#quote-items">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_quote_items')</a>
            @elseif($isEditable)
                @can('construction.boq.approve')
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--lg" data-toggle="modal" data-target="#ct-quote-status">@include('construction::partials.rr_icon', ['name' => 'check-circle'])@lang('construction::lang.change_quote_status')</button>
                @endcan
            @else
                <a class="rr-btn rr-btn--secondary rr-btn--lg" href="{{ route('construction.quotes.preview', $quote->id) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.preview')</a>
            @endif
        </div>
    </section>

    <div class="rr-cols">
        <div class="rr-cols__main">

            {{-- بنود العرض --}}
            <section class="rr-table-wrap" aria-labelledby="rr-quote-items-title">
                <div class="rr-table-head">
                    <h2 id="rr-quote-items-title" class="rr-table-head__title">@lang('construction::lang.quote_items')</h2>
                    <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $quote->items->count() }}</span> @lang('construction::lang.items_count_label')</span>
                </div>
                @if($quote->items->isEmpty())
                    <div class="rr-empty">
                        <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 22])</span>
                        <h3 class="rr-empty__title">@lang('construction::lang.quote_items_empty')</h3>
                        @if($canManageItems)<p class="rr-empty__text">@lang('construction::lang.quote_items_hint')</p>@endif
                    </div>
                @else
                    <div class="rr-table-scroll">
                        <table class="rr-table">
                            <caption class="rr-sr">@lang('construction::lang.quote_items')</caption>
                            <thead>
                                <tr>
                                    <th class="rr-th" scope="col">@lang('construction::lang.boq_code')</th>
                                    <th class="rr-th" scope="col">@lang('construction::lang.boq_description')</th>
                                    <th class="rr-th" scope="col">@lang('construction::lang.unit')</th>
                                    <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.quantity')</th>
                                    <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.unit_price')</th>
                                    <th class="rr-th rr-th--num" scope="col"><span class="rr-th__in">@lang('construction::lang.total')@if($currencyParts['symbol'] !== '')<span class="rr-th__unit">({{ $currencyParts['symbol'] }})</span>@endif</span></th>
                                    @if($canManageItems)<th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>@endif
                                </tr>
                            </thead>
                            <tbody class="rr-tbody">
                                @foreach($quote->items as $item)
                                    <tr class="rr-tr">
                                        <td class="rr-td rr-td--wrap"><span class="rr-code">{{ $item->code }}</span></td>
                                        <td class="rr-td rr-td--wrap rr-col--text">
                                            <div>{{ $item->description }}</div>
                                            @if($item->notes)<div class="rr-td__sub">{{ $item->notes }}</div>@endif
                                        </td>
                                        <td class="rr-td rr-td--muted">{{ $item->unit }}</td>
                                        <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity((float) $item->quantity) }}</span></td>
                                        <td class="rr-td rr-td--num"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($item->unit_price)['amount'] }}</span></td>
                                        <td class="rr-td rr-td--num"><strong class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($item->total)['amount'] }}</strong></td>
                                        @if($canManageItems)
                                            <td class="rr-td rr-td--actions">
                                                <div class="rr-row-actions">
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-toggle="modal" data-target="#ct-quote-item-{{ $item->id }}" aria-label="@lang('construction::lang.edit_quote_item')" title="@lang('construction::lang.edit_quote_item')">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 18])</button>
                                                    <form class="rr-inline-form" method="POST" action="{{ route('construction.quotes.items.destroy', [$quote->id, $item->id]) }}" onsubmit="return confirm(@js(__('construction::lang.delete_item_confirmation')))">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="rr-btn rr-btn--danger-ghost rr-btn--sm rr-btn--icon" aria-label="@lang('construction::lang.delete_item')" title="@lang('construction::lang.delete_item')">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])</button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="rr-tfoot">
                                <tr>
                                    <td class="rr-td" colspan="5">@lang('construction::lang.quote_total')</td>
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $quote->total])</span></td>
                                    @if($canManageItems)<td class="rr-td"></td>@endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>

            {{-- إضافة البنود --}}
            @if($canManageItems)
                <section class="rr-card" id="quote-items" aria-labelledby="rr-quote-add-title">
                    <header class="rr-card__head">
                        <div class="rr-card__titles">
                            <h2 id="rr-quote-add-title" class="rr-card__title">@lang('construction::lang.add_quote_items')</h2>
                            <p class="rr-card__desc">@lang('construction::lang.quote_items_hint')</p>
                        </div>
                    </header>
                    <div class="rr-card__body rr-section">
                        <div class="rr-callout rr-callout--success">
                            <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 22])</span>
                            <div class="rr-callout__copy">
                                <strong>@lang('construction::lang.quote_import_excel')</strong>
                                <p>@lang('construction::lang.quote_import_hint')</p>
                            </div>
                            <div class="rr-callout__actions">
                                <a class="rr-btn rr-btn--secondary rr-btn--sm" href="{{ route('construction.quotes.import-template') }}">@include('construction::partials.rr_icon', ['name' => 'download'])@lang('construction::lang.download_import_template')</a>
                                <button class="rr-btn rr-btn--primary rr-btn--sm" type="button" data-toggle="modal" data-target="#ct-quote-import">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.import_boq_items')</button>
                            </div>
                        </div>
                        <div class="rr-divider"><span>@lang('construction::lang.or_add_manually')</span></div>
                    </div>
                    <form method="POST" action="{{ route('construction.quotes.items.store', $quote->id) }}">
                        @csrf
                        <div class="rr-table-scroll">
                            <table class="rr-table rr-table--wide">
                                <caption class="rr-sr">@lang('construction::lang.add_quote_items')</caption>
                                <thead>
                                    <tr>
                                        <th class="rr-th" scope="col">@lang('construction::lang.boq_code')</th>
                                        <th class="rr-th" scope="col">@lang('construction::lang.boq_description')<span class="rr-field__req" aria-hidden="true">*</span></th>
                                        <th class="rr-th" scope="col">@lang('construction::lang.unit')<span class="rr-field__req" aria-hidden="true">*</span></th>
                                        <th class="rr-th" scope="col">@lang('construction::lang.quantity')<span class="rr-field__req" aria-hidden="true">*</span></th>
                                        <th class="rr-th" scope="col">@lang('construction::lang.unit_price')<span class="rr-field__req" aria-hidden="true">*</span></th>
                                        <th class="rr-th" scope="col">@lang('construction::lang.notes')</th>
                                        <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.total')</th>
                                        <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                                    </tr>
                                </thead>
                                <tbody id="ct-quote-entry-rows"></tbody>
                            </table>
                        </div>
                        <div class="rr-toolbar">
                            <button class="rr-btn rr-btn--secondary rr-btn--sm" id="ct-quote-add-row" type="button">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_row')</button>
                            <div class="rr-toolbar__end">
                                <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_all_items')</button>
                            </div>
                        </div>
                    </form>
                </section>

                <template id="ct-quote-row-template">
                    <tr class="rr-tr">
                        <td class="rr-td rr-td--input rr-col--code"><div class="rr-control rr-control--sm"><input class="rr-control__input" name="items[__INDEX__][code]" maxlength="60" placeholder="ITM-001" aria-label="@lang('construction::lang.boq_code')"></div></td>
                        <td class="rr-td rr-td--input rr-col--text"><div class="rr-control rr-control--sm"><input class="rr-control__input" name="items[__INDEX__][description]" maxlength="500" required aria-label="@lang('construction::lang.boq_description')"></div></td>
                        <td class="rr-td rr-td--input rr-col--unit"><div class="rr-control rr-control--sm"><select class="rr-control__input rr-control__select" name="items[__INDEX__][unit_id]" required aria-label="@lang('construction::lang.unit')"><option value="">@lang('construction::lang.choose')</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->actual_name }} ({{ $unit->short_name }})</option>@endforeach</select><span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span></div></td>
                        <td class="rr-td rr-td--input rr-col--qty"><div class="rr-control rr-control--sm"><input class="rr-control__input rr-control__input--num ct-quote-qty" type="number" step="0.0001" min="0.0001" name="items[__INDEX__][quantity]" value="1" required aria-label="@lang('construction::lang.quantity')"></div></td>
                        <td class="rr-td rr-td--input rr-col--price"><div class="rr-control rr-control--sm"><input class="rr-control__input rr-control__input--num ct-quote-price" type="number" step="0.0001" min="0" name="items[__INDEX__][unit_price]" value="0" required aria-label="@lang('construction::lang.unit_price')"></div></td>
                        <td class="rr-td rr-td--input rr-col--notes"><div class="rr-control rr-control--sm"><input class="rr-control__input" name="items[__INDEX__][notes]" maxlength="1000" aria-label="@lang('construction::lang.notes')"></div></td>
                        <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm"><span class="print-money print-money--{{ $currencyParts['position'] }}">@if($currencyParts['position'] === 'before')@include('construction::partials.money_symbol', ['money' => $currencyParts, 'isSaudiRiyal' => $currencyParts['code'] === 'SAR', 'symbolSource' => asset('img/saudi-riyal-new.svg')])@endif<output class="rr-num">0</output>@if($currencyParts['position'] === 'after')@include('construction::partials.money_symbol', ['money' => $currencyParts, 'isSaudiRiyal' => $currencyParts['code'] === 'SAR', 'symbolSource' => asset('img/saudi-riyal-new.svg')])@endif</span></span></td>
                        <td class="rr-td rr-td--actions"><button class="rr-btn rr-btn--danger-ghost rr-btn--sm rr-btn--icon ct-quote-remove-row" type="button" aria-label="@lang('construction::lang.delete')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button></td>
                    </tr>
                </template>
            @endif
        </div>

        <div class="rr-cols__side">

            {{-- بيانات العرض --}}
            <section class="rr-card" aria-labelledby="rr-quote-details-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <div class="rr-eyebrow">@lang('construction::lang.quote_first_step')</div>
                        <h2 id="rr-quote-details-title" class="rr-card__title">@lang('construction::lang.quote_details')</h2>
                    </div>
                </header>
                <dl class="rr-deflist">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.quote_number')</dt><dd><span class="rr-code">{{ $quote->number }}</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.quote_date')</dt><dd><span class="rr-num">{{ @format_date($quote->quote_date) }}</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.customer')</dt><dd>{{ $customerName ?: '—' }}</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.quote_validity_days')</dt><dd><span class="rr-num">{{ (int) $quote->validity_days }}</span> @lang('construction::lang.days')</dd></div>
                </dl>
            </section>

            {{-- ملاحظات العرض --}}
            @if($quote->notes)
                <section class="rr-card" aria-labelledby="rr-quote-notes-title">
                    <header class="rr-card__head">
                        <div class="rr-card__titles">
                            <h2 id="rr-quote-notes-title" class="rr-card__title">@lang('construction::lang.quote_notes')</h2>
                            <p class="rr-card__desc">@lang('construction::lang.quote_notes_hint')</p>
                        </div>
                    </header>
                    <p class="rr-prose">{{ $quote->notes }}</p>
                </section>
            @endif
        </div>
    </div>
</main>

{{-- ═══ النوافذ ═══ --}}
@if($isEditable)
    @can('construction.project.create')
        @include('construction::quotes.partials.form_modal', ['mode' => 'edit', 'quote' => $quote])
    @endcan
@endif

@if($canManageItems)
    {{-- استيراد من Excel --}}
    <div class="modal fade rr-scope" id="ct-quote-import" tabindex="-1" role="dialog" aria-labelledby="rr-quote-import-title">
        <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
            <div class="modal-content rr-modal">
                <form method="POST" action="{{ route('construction.quotes.items.import.preview', $quote->id) }}" enctype="multipart/form-data">
                    @csrf
                    <header class="rr-modal__head">
                        <div class="rr-modal__titles">
                            <h2 class="rr-modal__title" id="rr-quote-import-title">@lang('construction::lang.quote_import_excel')</h2>
                            <p class="rr-modal__desc">@lang('construction::lang.quote_import_hint')</p>
                        </div>
                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                    </header>
                    <div class="rr-modal__body">
                        <ol class="rr-mini-steps" aria-label="@lang('construction::lang.quote_import_steps')">
                            <li><b>1</b>@lang('construction::lang.download_template_step')</li>
                            <li><b>2</b>@lang('construction::lang.fill_items_step')</li>
                            <li><b>3</b>@lang('construction::lang.review_import_step')</li>
                        </ol>
                        @error('quote_file')<div class="rr-alert rr-alert--danger" role="alert">{{ $message }}</div>@enderror
                        <div class="rr-field">
                            <label class="rr-field__label" for="ct-quote-file">@lang('construction::lang.choose_quote_excel_file')<span class="rr-field__req" aria-hidden="true">*</span></label>
                            <div class="rr-control"><input id="ct-quote-file" class="rr-control__input" type="file" name="quote_file" accept=".xlsx,.xls,.csv" required aria-describedby="ct-quote-file-help"></div>
                            <div class="rr-field__hint" id="ct-quote-file-help">@lang('construction::lang.quote_import_columns_hint')</div>
                        </div>
                        <div class="rr-alert">@lang('construction::lang.import_preview_hint')</div>
                    </div>
                    <footer class="rr-modal__foot">
                        <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'search'])@lang('construction::lang.preview_before_import')</button>
                        <button class="rr-btn rr-btn--secondary" type="button" data-dismiss="modal">@lang('messages.cancel')</button>
                        <a class="rr-btn rr-btn--ghost rr-modal__note" href="{{ route('construction.quotes.import-template') }}">@include('construction::partials.rr_icon', ['name' => 'download'])@lang('construction::lang.download_import_template')</a>
                    </footer>
                </form>
            </div>
        </div>
    </div>

    {{-- تعديل بند --}}
    @foreach($quote->items as $item)
        <div class="modal fade rr-scope" id="ct-quote-item-{{ $item->id }}" tabindex="-1" role="dialog" aria-labelledby="rr-quote-item-{{ $item->id }}-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.quotes.items.update', [$quote->id, $item->id]) }}">
                        @csrf @method('PUT')
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-quote-item-{{ $item->id }}-title">@lang('construction::lang.edit_quote_item')</h2>
                                <p class="rr-modal__desc"><span class="rr-code">{{ $item->code }}</span></p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-form-grid">
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="rr-item-{{ $item->id }}-code">@lang('construction::lang.boq_code')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                        <div class="rr-control"><input id="rr-item-{{ $item->id }}-code" class="rr-control__input" name="code" value="{{ $item->code }}" required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="rr-item-{{ $item->id }}-unit">@lang('construction::lang.unit')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                        <div class="rr-control">
                                            <select id="rr-item-{{ $item->id }}-unit" class="rr-control__input rr-control__select" name="unit_id" required>
                                                @foreach($units as $unit)<option value="{{ $unit->id }}" @selected($unit->id === $item->unit_id)>{{ $unit->actual_name }} ({{ $unit->short_name }})</option>@endforeach
                                            </select>
                                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                        </div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="rr-item-{{ $item->id }}-desc">@lang('construction::lang.boq_description')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                        <div class="rr-control"><input id="rr-item-{{ $item->id }}-desc" class="rr-control__input" name="description" value="{{ $item->description }}" required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="rr-item-{{ $item->id }}-qty">@lang('construction::lang.quantity')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                        <div class="rr-control"><input id="rr-item-{{ $item->id }}-qty" class="rr-control__input rr-control__input--num input_number input_quantity" name="quantity" value="{{ @format_quantity((float) $item->quantity) }}" required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="rr-item-{{ $item->id }}-price">@lang('construction::lang.unit_price')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                        <div class="rr-control"><input id="rr-item-{{ $item->id }}-price" class="rr-control__input rr-control__input--num input_number" name="unit_price" value="{{ @num_format((float) $item->unit_price) }}" required></div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="rr-item-{{ $item->id }}-notes">@lang('construction::lang.notes') <span class="rr-field__opt">(@lang('construction::lang.optional'))</span></label>
                                        <div class="rr-control rr-control--area"><textarea id="rr-item-{{ $item->id }}-notes" class="rr-control__input rr-control__input--short" name="notes" rows="2">{{ $item->notes }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save')</button>
                            <button class="rr-btn rr-btn--secondary" type="button" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif

@if(! $quote->project_id)
    @can('construction.boq.approve')
        {{-- تغيير الحالة --}}
        <div class="modal fade rr-scope" id="ct-quote-status" tabindex="-1" role="dialog" aria-labelledby="rr-quote-status-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact rr-modal-dialog--sm" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.quotes.status', $quote->id) }}">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-quote-status-title">@lang('construction::lang.change_quote_status')</h2>
                                <p class="rr-modal__desc"><span class="rr-code">{{ $quote->number }}</span></p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-field">
                                <label class="rr-field__label" for="rr-quote-status-select">@lang('construction::lang.quote_status')</label>
                                <div class="rr-control">
                                    <select id="rr-quote-status-select" class="rr-control__input rr-control__select" name="status">
                                        @foreach(\Modules\Construction\Entities\ConstructionQuote::STATUSES as $state)
                                            <option value="{{ $state }}" @selected($state === $quote->status)>@lang('construction::lang.quote_status_' . $state)</option>
                                        @endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save')</button>
                            <button class="rr-btn rr-btn--secondary" type="button" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endif

@if($quote->status === 'accepted' && ! $quote->project_id)
    @can('construction.project.create')
        @include('construction::quotes.partials.convert_modal', ['quote' => $quote])
    @endcan
@endif
@endsection

@section('module_javascript')
<script>
$(function () {
    var body = document.getElementById('ct-quote-entry-rows'), template = document.getElementById('ct-quote-row-template'), add = document.getElementById('ct-quote-add-row'), next = 0;
    if (body && template && add) {
        function update() {
            body.querySelectorAll('tr').forEach(function (row) {
                var qty = parseFloat(row.querySelector('.ct-quote-qty').value) || 0, price = parseFloat(row.querySelector('.ct-quote-price').value) || 0;
                row.querySelector('output').textContent = __currency_trans_from_en(qty * price, false);
            });
        }
        function addRow() {
            body.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(next++)));
            update();
        }
        add.addEventListener('click', addRow);
        body.addEventListener('input', update);
        body.addEventListener('click', function (event) {
            var button = event.target.closest('.ct-quote-remove-row');
            if (button && body.rows.length > 1) { button.closest('tr').remove(); update(); }
        });
        addRow();
    }
    @if($errors->has('quote_file') || request()->boolean('import'))$('#ct-quote-import').modal('show');@endif
});
</script>
@endsection

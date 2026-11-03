@extends('construction::layouts.module')
@section('title', __('construction::lang.quotes'))

@php
    $quoteTones = ['draft' => 'neutral', 'sent' => 'brand', 'accepted' => 'success', 'rejected' => 'danger', 'expired' => 'warning', 'converted' => 'info'];
    $currencySymbol = \Modules\Construction\Support\CurrencyFormatter::parts(0)['symbol'];
    $quoteStats = [
        ['', 'file', 'all_quotes', $quoteSummary->all_count],
        ['draft', 'edit', 'quote_status_draft', $quoteSummary->draft_count],
        ['accepted', 'check', 'quote_status_accepted', $quoteSummary->accepted_count],
        ['converted', 'building', 'quote_status_converted_short', $quoteSummary->converted_count],
    ];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-quotes-title">

    {{-- رأس الصفحة --}}
    <header class="rr-head">
        <img class="rr-head__logo" src="{{ asset('img/logo-small.png') }}" width="56" height="56" alt="">
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.overview.index') }}">@lang('construction::lang.tab_overview')</a></li>
                    <li><span aria-current="page">@lang('construction::lang.quotes')</span></li>
                </ol>
            </nav>
            <h1 id="rr-quotes-title" class="rr-head__title">@lang('construction::lang.quotes')</h1>
            <p class="rr-head__sub">@lang('construction::lang.quotes_intro')</p>
        </div>
        @can('construction.project.create')
            <div class="rr-head__actions">
                <button class="rr-btn rr-btn--primary" type="button" data-toggle="modal" data-target="#ct-quote-create">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.create_new_quote')</button>
            </div>
        @endcan
    </header>

    {{-- المؤشرات --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--5" style="--rr-stats: 5" aria-label="@lang('construction::lang.quote_portfolio_summary')">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.quote_total_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $quoteSummary->total_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $quoteSummary->total_value])</span></div>
            </div>
            @foreach($quoteStats as [$statStatus, $statIcon, $statLabel, $statCount])
                <a class="rr-stat" href="{{ route('construction.quotes.index', $statStatus !== '' ? ['status' => $statStatus] : []) }}" @if($status === $statStatus) aria-current="true" @endif>
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => $statIcon, 'size' => 18])@lang('construction::lang.' . $statLabel)</div>
                    <div class="rr-stat__value"><span class="rr-num">{{ (int) $statCount }}</span></div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- الخطوة التالية: عروض مقبولة تنتظر التحويل --}}
    @if((int) $quoteSummary->accepted_count > 0)
        <section class="rr-card rr-card__body" aria-labelledby="rr-quote-next-title">
            <div class="rr-next">
                <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'arrow-forward', 'size' => 22])</span>
                <div class="rr-next__copy">
                    <div class="rr-eyebrow">@lang('construction::lang.next_action')</div>
                    <h2 id="rr-quote-next-title" class="rr-next__title">@lang('construction::lang.quote_accepted_attention', ['count' => $quoteSummary->accepted_count])</h2>
                    <p class="rr-next__text">@lang('construction::lang.quote_accepted_attention_hint')</p>
                </div>
                <a class="rr-btn rr-btn--secondary rr-btn--lg" href="{{ route('construction.quotes.index', ['status' => 'accepted']) }}">@lang('construction::lang.overview_review_quotes')@include('construction::partials.rr_icon', ['name' => 'arrow-forward'])</a>
            </div>
        </section>
    @endif

    {{-- التصفية --}}
    <form class="rr-filter" role="search" method="GET" action="{{ route('construction.quotes.index') }}">
        <div class="rr-filter__fields">
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="rr-quote-search">@lang('construction::lang.search')</label>
                <div class="rr-control">
                    <span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'search', 'size' => 18])</span>
                    <input id="rr-quote-search" class="rr-control__input" type="search" name="q" value="{{ $search }}" placeholder="@lang('construction::lang.search_quotes')">
                </div>
            </div>
            <div class="rr-field">
                <label class="rr-field__label" for="rr-quote-status">@lang('construction::lang.status')</label>
                <div class="rr-control">
                    <select id="rr-quote-status" class="rr-control__input rr-control__select" name="status">
                        <option value="">@lang('construction::lang.quote_filter_all')</option>
                        @foreach(Modules\Construction\Entities\ConstructionQuote::STATUSES as $filterStatus)
                            <option value="{{ $filterStatus }}" @selected($status === $filterStatus)>@lang('construction::lang.quote_status_' . $filterStatus)</option>
                        @endforeach
                        <option value="converted" @selected($status === 'converted')>@lang('construction::lang.quote_status_converted')</option>
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
            </div>
        </div>
        <div class="rr-filter__actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'filter'])@lang('construction::lang.apply_filter')</button>
            @if($search !== '' || $status !== '')
                <a class="rr-btn rr-btn--ghost" href="{{ route('construction.quotes.index') }}">@lang('construction::lang.reset_filter')</a>
            @endif
        </div>
    </form>

    {{-- جدول العروض --}}
    <section class="rr-table-wrap" id="ct-quick-list" aria-labelledby="rr-quote-list-title">
        <div class="rr-table-head">
            <h2 id="rr-quote-list-title" class="rr-table-head__title">@lang('construction::lang.quotes')</h2>
        </div>
        @if($quotes->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 22])</span>
                @if($search !== '' || $status !== '')
                    <h3 class="rr-empty__title">@lang('construction::lang.quote_empty_filter_hint')</h3>
                    <a class="rr-btn rr-btn--secondary" href="{{ route('construction.quotes.index') }}">@lang('construction::lang.clear_filter')</a>
                @else
                    <h3 class="rr-empty__title">@lang('construction::lang.no_quotes')</h3>
                    <p class="rr-empty__text">@lang('construction::lang.quotes_intro')</p>
                    @can('construction.project.create')
                        <button class="rr-btn rr-btn--primary" type="button" data-toggle="modal" data-target="#ct-quote-create">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.create_new_quote')</button>
                    @endcan
                @endif
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table rr-table--wide">
                    <caption class="rr-sr">@lang('construction::lang.all_quotes')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.quote_number')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.quote_col_title')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.date')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.quote_col_items')</th>
                            <th class="rr-th rr-th--num" scope="col"><span class="rr-th__in">@lang('construction::lang.total')@if($currencySymbol !== '')<span class="rr-th__unit">({{ $currencySymbol }})</span>@endif</span></th>
                            <th class="rr-th" scope="col">@lang('construction::lang.quote_status')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotes as $quote)
                            @php
                                $quoteState = $quote->project_id ? 'converted' : $quote->status;
                                $quoteCustomer = $quote->customer?->supplier_business_name ?: $quote->customer?->name;
                            @endphp
                            <tr class="rr-tr" data-quote-id="{{ $quote->id }}">
                                <td class="rr-td rr-td--wrap"><a class="rr-code-link" href="{{ route('construction.quotes.show', $quote->id) }}"><span class="rr-code">{{ $quote->number }}</span></a></td>
                                <td class="rr-td rr-td--wrap rr-col--title">
                                    <div class="rr-entity__name">{{ $quote->title }}</div>
                                    @if($quoteCustomer)<div class="rr-td__sub">{{ $quoteCustomer }}</div>@endif
                                </td>
                                <td class="rr-td rr-td--wrap"><span class="rr-num">{{ @format_date($quote->quote_date) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $quote->items_count }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ (float) $quote->total == 0 ? 'rr-money--zero' : '' }}"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($quote->total)['amount'] }}</span></span></td>
                                <td class="rr-td">
                                    <span class="rr-badge rr-badge--{{ $quoteTones[$quoteState] ?? 'neutral' }}">
                                        @if($quoteState === 'converted')@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 14])@else<span class="rr-badge__dot" aria-hidden="true"></span>@endif
                                        @lang('construction::lang.quote_status_' . $quoteState)
                                    </span>
                                </td>
                                <td class="rr-td rr-td--wrap">
                                    @if($quote->project)<a class="rr-code-link" href="{{ route('construction.projects.show', $quote->project->id) }}"><span class="rr-code">{{ $quote->project->code }}</span></a>@else<span class="rr-td--muted">—</span>@endif
                                </td>
                                <td class="rr-td rr-td--actions">
                                    <div class="rr-row-actions ct-quote-row-actions">
                                        <a class="rr-btn rr-btn--secondary rr-btn--sm" href="{{ route('construction.quotes.show', $quote->id) }}">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.view')</a>
                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon ct-quote-menu-toggle" aria-label="@lang('construction::lang.quote_more_actions')" aria-haspopup="true" aria-expanded="false" aria-controls="ct-quote-menu-{{ $quote->id }}">@include('construction::partials.rr_icon', ['name' => 'more'])</button>
                                        <div class="rr-menu ct-quote-menu" id="ct-quote-menu-{{ $quote->id }}" role="menu">
                                            @if(in_array($quote->status, ['draft', 'sent'], true) && !$quote->project_id)
                                                @can('construction.project.create')<button type="button" role="menuitem" class="rr-menu__item" data-toggle="modal" data-target="#ct-quote-edit" data-quote-id="{{ $quote->id }}" data-quote-date="{{ $quote->quote_date->toDateString() }}" data-customer-id="{{ $quote->customer_id }}" data-quote-title="{{ $quote->title }}" data-validity-days="{{ $quote->validity_days }}" data-notes="{{ $quote->notes }}">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 18])@lang('construction::lang.edit_quote')</button>@endcan
                                            @endif
                                            <a class="rr-menu__item" role="menuitem" href="{{ route('construction.quotes.preview', $quote->id) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 18])@lang('construction::lang.preview')</a>
                                            <a class="rr-menu__item" role="menuitem" href="{{ route('construction.quotes.print', $quote->id) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'print', 'size' => 18])@lang('construction::lang.print_quote')</a>
                                            <a class="rr-menu__item" role="menuitem" href="{{ route('construction.quotes.pdf', $quote->id) }}">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 18])PDF</a>
                                            @if($quote->project)
                                                <a class="rr-menu__item" role="menuitem" href="{{ route('construction.projects.show', $quote->project->id) }}">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 18])@lang('construction::lang.open_project')</a>
                                            @elseif($quote->status === 'accepted')
                                                @can('construction.project.create')<button type="button" role="menuitem" class="rr-menu__item" data-toggle="modal" data-target="#ct-quote-convert" data-quote-id="{{ $quote->id }}" data-quote-title="{{ $quote->title }}">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18])@lang('construction::lang.convert_to_project')</button>@endcan
                                            @endif
                                            @if($quote->status === 'draft' && !$quote->project_id)
                                                @can('construction.project.delete')
                                                    <div class="rr-menu__sep" role="separator"></div>
                                                    <button type="button" role="menuitem" class="rr-menu__item rr-menu__item--danger ct-quote-menu-danger" data-toggle="modal" data-target="#ct-quote-delete" data-quote-id="{{ $quote->id }}" data-confirmation="{{ __('construction::lang.quote_delete_confirmation', ['number' => $quote->number]) }}">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])@lang('construction::lang.delete_quote')</button>
                                                @endcan
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('construction::partials.rr_pager', ['paginator' => $quotes, 'itemLabel' => __('construction::lang.quote_unit')])
        @endif
    </section>
</main>
@can('construction.project.create')
    @include('construction::quotes.partials.form_modal', ['mode' => 'create'])
@endcan
@can('construction.project.create')
{{-- quote => null صريح: متغير الـ foreach بتاع الجدول بيفضل موجود بعد اللوب، ومن غيره النافذة كانت بتتربط بآخر عرض في الصفحة --}}
@include('construction::quotes.partials.form_modal', ['mode' => 'edit', 'quote' => null])
@include('construction::quotes.partials.convert_modal', ['quote' => null])
@endcan
@can('construction.project.delete')
<div class="modal fade rr-scope" id="ct-quote-delete" tabindex="-1" role="dialog" aria-labelledby="rr-quote-delete-title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact rr-modal-dialog--sm" role="document">
        <div class="modal-content rr-modal">
            <form method="POST" data-ct-quick-create data-delete-url="{{ route('construction.quotes.destroy', ['quote' => '__QUOTE__']) }}">@csrf @method('DELETE')
                <header class="rr-modal__head">
                    <div class="rr-modal__titles"><h2 class="rr-modal__title" id="rr-quote-delete-title">@lang('construction::lang.delete_quote')</h2></div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                </header>
                <div class="rr-modal__body">
                    <div class="rr-alert rr-alert--danger ct-quick-errors" role="alert"></div>
                    <p class="rr-alert rr-alert--warning" id="ct-quote-delete-confirmation"></p>
                </div>
                <footer class="rr-modal__foot">
                    <button class="rr-btn rr-btn--danger" type="submit">@include('construction::partials.rr_icon', ['name' => 'trash'])@lang('construction::lang.delete_quote')</button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                </footer>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection
@include('construction::partials.quick_create')
@section('module_javascript')
<script>
$(function () {
    if (new URLSearchParams(location.search).has('open_create')) $('#ct-quote-create').modal('show');

    var activeMenu = null, activeToggle = null;
    function closeMenu(returnFocus) {
        if (!activeMenu) return;
        activeMenu.style.display = 'none';
        activeMenu.previousElementSibling.setAttribute('aria-expanded', 'false');
        if (returnFocus && activeToggle) activeToggle.focus();
        activeMenu = null;
        activeToggle = null;
    }
    $(document).on('click', '.ct-quote-menu-toggle', function (event) {
        event.stopPropagation();
        var menu = this.nextElementSibling;
        if (activeMenu === menu) { closeMenu(); return; }
        closeMenu();
        menu.style.display = 'block';
        menu.style.visibility = 'hidden';
        var button = this.getBoundingClientRect(), width = menu.offsetWidth, height = menu.offsetHeight;
        var isRtl = document.documentElement.dir === 'rtl' || getComputedStyle(document.body).direction === 'rtl';
        var menuLeft = isRtl ? button.left : button.right - width;
        menu.style.left = Math.max(8, Math.min(window.innerWidth - width - 8, menuLeft)) + 'px';
        menu.style.top = (button.bottom + height + 8 <= window.innerHeight || button.top < height + 8 ? button.bottom + 5 : button.top - height - 5) + 'px';
        menu.style.visibility = 'visible';
        this.setAttribute('aria-expanded', 'true');
        activeMenu = menu;
        activeToggle = this;
    });
    $(document).on('click', function (event) { if (!$(event.target).closest('.ct-quote-menu').length) closeMenu(); });
    $(document).on('keydown', function (event) { if (event.key === 'Escape') closeMenu(true); });
    $(window).on('resize scroll', closeMenu);
    $('.rr-table-scroll').on('scroll', closeMenu);

    $('#ct-quote-edit').on('show.bs.modal', function (event) {
        var trigger = event.relatedTarget, form = this.querySelector('form');
        if (!trigger || !form.dataset.updateUrl) return;
        form.action = form.dataset.updateUrl.replace('__QUOTE__', trigger.dataset.quoteId);
        form.elements.quote_date.value = trigger.dataset.quoteDate;
        form.elements.customer_id.value = trigger.dataset.customerId;
        form.elements.title.value = trigger.dataset.quoteTitle;
        form.elements.validity_days.value = trigger.dataset.validityDays;
        form.elements.notes.value = trigger.dataset.notes || '';
    });
    $('#ct-quote-convert').on('show.bs.modal', function (event) {
        var trigger = event.relatedTarget, form = this.querySelector('form');
        if (!trigger || !form.dataset.convertUrl) return;
        form.action = form.dataset.convertUrl.replace('__QUOTE__', trigger.dataset.quoteId);
        form.elements.project_name.value = trigger.dataset.quoteTitle;
    });
    $('#ct-quote-delete').on('show.bs.modal', function (event) {
        var trigger = event.relatedTarget, form = this.querySelector('form');
        if (!trigger) return;
        form.action = form.dataset.deleteUrl.replace('__QUOTE__', trigger.dataset.quoteId);
        this.querySelector('#ct-quote-delete-confirmation').textContent = trigger.dataset.confirmation;
    });
});
</script>
@endsection

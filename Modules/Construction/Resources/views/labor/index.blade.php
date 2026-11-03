@extends('construction::layouts.module')

@section('title', __('construction::lang.labor_sheets'))

@php
    $sheetTones = ['draft' => 'warning', 'approved' => 'success', 'cancelled' => 'neutral'];
    $isFiltered = ($search !== '' && $search !== null) || $selectedProjectId || $status;
    $sheetStats = [
        ['all', 'all_sheets', 'file', null],
        ['draft', 'draft_sheets', 'edit', 'draft'],
        ['approved', 'approved_sheets', 'check-circle', 'approved'],
        ['cancelled', 'cancelled_labor_sheets', 'close', 'cancelled'],
    ];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-labor-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'users', 'size' => 24])</span>
        <div class="rr-head__titles">
            <div class="rr-eyebrow">@lang('construction::lang.labor_cost_center')</div>
            <h1 id="rr-labor-title" class="rr-head__title">@lang('construction::lang.labor_sheets')</h1>
            <p class="rr-head__sub">@lang('construction::lang.labor_sheets_hint')</p>
        </div>
        @can('construction.labor.manage')
            <div class="rr-head__actions">
                <a href="{{ route('construction.labor.create') }}" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_labor_sheet')</a>
            </div>
        @endcan
    </header>

    {{-- المؤشرات: كل مؤشر رابط تصفية --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--5" style="--rr-stats: 5">
            @foreach($sheetStats as [$key, $label, $icon, $filterStatus])
                <a class="rr-stat" href="{{ route('construction.labor.index', array_filter(['status' => $filterStatus, 'project_id' => $selectedProjectId, 'q' => $search])) }}" @if($status === $filterStatus || (!$status && !$filterStatus)) aria-current="true" @endif>
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => $icon, 'size' => 18])@lang('construction::lang.' . $label)</div>
                    <div class="rr-stat__value"><span class="rr-num">{{ $stats[$key] }}</span></div>
                </a>
            @endforeach
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.approved_labor_cost')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $stats['approved_total'] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $stats['approved_total']])</span></div>
            </div>
        </div>
    </div>

    <section class="rr-table-wrap" aria-labelledby="rr-labor-list-title">
        <div class="rr-table-head rr-table-head--toolbar">
            <div class="rr-table-head__titles">
                <h2 id="rr-labor-list-title" class="rr-table-head__title">@lang('construction::lang.labor_sheets')</h2>
            </div>
            <form method="GET" action="{{ route('construction.labor.index') }}" class="rr-table-tools" role="search" data-rr-auto-filter>
                <div class="rr-control rr-control--sm rr-table-tools__search">
                    <span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'search', 'size' => 16])</span>
                    <label class="rr-sr" for="ct-labor-search">@lang('construction::lang.search_labor_sheets')</label>
                    <input id="ct-labor-search" class="rr-control__input" type="search" name="q" value="{{ $search }}" placeholder="@lang('construction::lang.search_labor_sheets')">
                </div>
                <div class="rr-control rr-control--sm rr-table-tools__select">
                    <label class="rr-sr" for="ct-labor-project">@lang('construction::lang.project')</label>
                    <select id="ct-labor-project" class="rr-control__input rr-control__select" name="project_id">
                        <option value="">@lang('construction::lang.all_projects')</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>
                        @endforeach
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
                <div class="rr-control rr-control--sm rr-table-tools__select">
                    <label class="rr-sr" for="ct-labor-status">@lang('construction::lang.status')</label>
                    <select id="ct-labor-status" class="rr-control__input rr-control__select" name="status">
                        <option value="">@lang('construction::lang.all_statuses')</option>
                        @foreach(['draft', 'approved', 'cancelled'] as $value)
                            <option value="{{ $value }}" @selected($status === $value)>@lang('construction::lang.' . $value)</option>
                        @endforeach
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
                <button class="rr-btn rr-btn--secondary rr-btn--sm" type="submit">@include('construction::partials.rr_icon', ['name' => 'filter', 'size' => 16])@lang('construction::lang.apply_filter')</button>
                @if($isFiltered)
                    <a class="rr-btn rr-btn--ghost rr-btn--sm" href="{{ route('construction.labor.index') }}">@lang('construction::lang.clear_filter')</a>
                @endif
            </form>
        </div>

        @if($sheets->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => $isFiltered ? 'search' : 'users', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_labor_sheets')</h3>
                <p class="rr-empty__text">@lang('construction::lang.no_labor_sheets_hint')</p>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.labor_sheets')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.sheet_number')</th>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.period')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.work_site')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.labor_lines')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.total_cost')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($sheets as $sheet)
                            @php
                                $sheetUrl = route('construction.labor.show', $sheet->id);
                                $sheetTotal = (float) ($sheet->lines_sum_total_cost ?: 0);
                            @endphp
                            <tr class="rr-tr {{ $sheet->status === 'cancelled' ? 'rr-tr--muted' : '' }}">
                                <td class="rr-td"><a class="rr-code-link" href="{{ $sheetUrl }}"><span class="rr-code">{{ $sheet->number }}</span></a></td>
                                <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $sheet->project->name }}</span><div class="rr-td__sub"><span class="rr-code">{{ $sheet->project->code }}</span></div></td>
                                <td class="rr-td"><span class="rr-num">{{ @format_date($sheet->period_start) }}</span> — <span class="rr-num">{{ @format_date($sheet->period_end) }}</span></td>
                                <td class="rr-td rr-td--wrap rr-td--muted">{{ $sheet->work_site ?: '—' }}</td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $sheet->lines_count }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ $sheetTotal == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $sheetTotal])</span></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $sheetTones[$sheet->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $sheet->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <div class="rr-row-actions">
                                        <a href="{{ $sheetUrl }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 18])@lang('construction::lang.open_sheet')</a>
                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr-labor-menu-{{ $sheet->id }}" aria-label="@lang('construction::lang.operations'): {{ $sheet->number }}">@include('construction::partials.rr_icon', ['name' => 'more'])</button>
                                        <div class="rr-menu" id="rr-labor-menu-{{ $sheet->id }}" role="menu">
                                            <a class="rr-menu__item" role="menuitem" href="{{ route('construction.labor.preview', $sheet->id) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'print', 'size' => 18])@lang('construction::lang.print_preview')</a>
                                            @if($sheet->status === 'draft')
                                                @can('construction.labor.manage')
                                                    <a class="rr-menu__item" role="menuitem" href="{{ route('construction.labor.edit', $sheet->id) }}">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 18])@lang('construction::lang.edit_sheet')</a>
                                                    <div class="rr-menu__sep" role="separator"></div>
                                                    <form method="POST" action="{{ route('construction.labor.destroy', $sheet->id) }}" class="rr-inline-form" onsubmit="return confirm(@js(__('construction::lang.delete_labor_sheet_confirmation')))">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rr-menu__item rr-menu__item--danger" role="menuitem">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])@lang('construction::lang.delete_draft')</button>
                                                    </form>
                                                @endcan
                                            @elseif($sheet->status === 'approved' && $canCancelApproved)
                                                <div class="rr-menu__sep" role="separator"></div>
                                                <a class="rr-menu__item rr-menu__item--danger" role="menuitem" href="{{ route('construction.labor.show', ['sheet' => $sheet->id, 'cancel' => 1]) }}">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])@lang('construction::lang.cancel_approved_labor_sheet')</a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('construction::partials.rr_pager', ['paginator' => $sheets, 'itemLabel' => __('construction::lang.labor_sheet')])
        @endif
    </section>
</main>
@include('construction::partials.rr_menu_script')
@endsection

@section('module_javascript')
<script>
$(function () {
    $('[data-rr-auto-filter] select').on('change', function () { this.form.submit(); });
});
</script>
@endsection

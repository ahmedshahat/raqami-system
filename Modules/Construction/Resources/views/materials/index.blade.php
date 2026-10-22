@extends('construction::layouts.module')
@section('title', __('construction::lang.material_documents'))

@php
    $isFiltered = ($search !== '' && $search !== null) || $selectedProjectId || $status || $type;
    $documentStats = [
        ['all', 'all_documents', 'file', [], !$status && !$type],
        ['draft', 'draft_documents', 'edit', ['status' => 'draft'], $status === 'draft'],
        ['approved', 'approved_documents', 'check-circle', ['status' => 'approved'], $status === 'approved'],
        ['issues', 'issue_documents', 'folder-open', ['type' => 'issue'], $type === 'issue'],
        ['returns', 'return_documents', 'arrow-back', ['type' => 'return'], $type === 'return'],
    ];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-materials-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 24])</span>
        <div class="rr-head__titles">
            <div class="rr-eyebrow">@lang('construction::lang.materials_workspace')</div>
            <h1 id="rr-materials-title" class="rr-head__title">@lang('construction::lang.material_documents')</h1>
            <p class="rr-head__sub">@lang('construction::lang.material_documents_hint')</p>
        </div>
        @can('construction.material.manage')
            <div class="rr-head__actions">
                <a href="{{ route('construction.materials.create', array_filter(['project_id' => $selectedProjectId])) }}" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_material_issue')</a>
            </div>
        @endcan
    </header>

    {{-- المؤشرات: كل مؤشر رابط تصفية --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--5" style="--rr-stats: 5">
            @foreach($documentStats as [$key, $label, $icon, $filter, $isCurrent])
                <a class="rr-stat" href="{{ route('construction.materials.index', array_filter($filter + ['project_id' => $selectedProjectId, 'q' => $search])) }}" @if($isCurrent) aria-current="true" @endif>
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => $icon, 'size' => 18])@lang('construction::lang.' . $label)</div>
                    <div class="rr-stat__value"><span class="rr-num">{{ $stats[$key] }}</span></div>
                </a>
            @endforeach
        </div>
    </div>

    <section class="rr-table-wrap" aria-labelledby="rr-materials-list-title">
        <div class="rr-table-head rr-table-head--toolbar">
            <div class="rr-table-head__titles">
                <h2 id="rr-materials-list-title" class="rr-table-head__title">@lang('construction::lang.material_documents')</h2>
            </div>
            <form method="GET" action="{{ route('construction.materials.index') }}" class="rr-table-tools" role="search" data-rr-auto-filter>
                <div class="rr-control rr-control--sm rr-table-tools__search">
                    <span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'search', 'size' => 16])</span>
                    <label class="rr-sr" for="ct-material-search">@lang('construction::lang.search_material_documents')</label>
                    <input id="ct-material-search" class="rr-control__input" type="search" name="q" value="{{ $search }}" placeholder="@lang('construction::lang.search_material_documents')">
                </div>
                <div class="rr-control rr-control--sm rr-table-tools__select">
                    <label class="rr-sr" for="ct-material-project">@lang('construction::lang.project')</label>
                    <select id="ct-material-project" class="rr-control__input rr-control__select" name="project_id">
                        <option value="">@lang('construction::lang.all_projects')</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>
                        @endforeach
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
                <div class="rr-control rr-control--sm rr-table-tools__select">
                    <label class="rr-sr" for="ct-material-type">@lang('construction::lang.document_type')</label>
                    <select id="ct-material-type" class="rr-control__input rr-control__select" name="type">
                        <option value="">@lang('construction::lang.all_document_types')</option>
                        <option value="issue" @selected($type === 'issue')>@lang('construction::lang.material_issue')</option>
                        <option value="return" @selected($type === 'return')>@lang('construction::lang.material_return')</option>
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
                <div class="rr-control rr-control--sm rr-table-tools__select">
                    <label class="rr-sr" for="ct-material-status">@lang('construction::lang.status')</label>
                    <select id="ct-material-status" class="rr-control__input rr-control__select" name="status">
                        <option value="">@lang('construction::lang.all_statuses')</option>
                        <option value="draft" @selected($status === 'draft')>@lang('construction::lang.draft')</option>
                        <option value="approved" @selected($status === 'approved')>@lang('construction::lang.approved')</option>
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
                <button class="rr-btn rr-btn--secondary rr-btn--sm" type="submit">@include('construction::partials.rr_icon', ['name' => 'filter', 'size' => 16])@lang('construction::lang.apply_filter')</button>
                @if($isFiltered)
                    <a class="rr-btn rr-btn--ghost rr-btn--sm" href="{{ route('construction.materials.index') }}">@lang('construction::lang.clear_filter')</a>
                @endif
            </form>
        </div>

        @if($documents->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => $isFiltered ? 'search' : 'folder-open', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_material_documents')</h3>
                <p class="rr-empty__text">@lang('construction::lang.no_material_documents_hint')</p>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.material_documents')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.document_number')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.document_type')</th>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.date')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.lines_count')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.amount')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($documents as $document)
                            @php
                                $documentUrl = route('construction.materials.show', $document->id);
                                $isReturn = $document->type === 'return';
                                $documentTotal = (float) ($document->lines_sum_total_cost ?: 0);
                            @endphp
                            <tr class="rr-tr">
                                <td class="rr-td">
                                    <a class="rr-code-link" href="{{ $documentUrl }}"><span class="rr-code">{{ $document->number }}</span></a>
                                    <div class="rr-td__sub">{{ $document->location->name }}</div>
                                </td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $isReturn ? 'warning' : 'info' }}">@include('construction::partials.rr_icon', ['name' => $isReturn ? 'arrow-back' : 'folder-open', 'size' => 14])@lang('construction::lang.material_' . $document->type)</span></td>
                                <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $document->project->name }}</span><div class="rr-td__sub"><span class="rr-code">{{ $document->project->code }}</span></div></td>
                                <td class="rr-td"><span class="rr-num">{{ @format_date($document->document_date) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $document->lines_count }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ $documentTotal == 0 ? 'rr-money--zero' : '' }}">@if($isReturn)− @endif @include('construction::partials.money', ['value' => $documentTotal])</span></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $document->status === 'approved' ? 'success' : 'warning' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $document->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <div class="rr-row-actions">
                                        <a href="{{ $documentUrl }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 18])@lang('construction::lang.open_document')</a>
                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr-material-menu-{{ $document->id }}" aria-label="@lang('construction::lang.operations'): {{ $document->number }}">@include('construction::partials.rr_icon', ['name' => 'more'])</button>
                                        <div class="rr-menu" id="rr-material-menu-{{ $document->id }}" role="menu">
                                            <a class="rr-menu__item" role="menuitem" href="{{ route('construction.materials.preview', $document->id) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'print', 'size' => 18])@lang('construction::lang.print_preview')</a>
                                            @if($document->status === 'draft')
                                                @can('construction.material.manage')
                                                    <a class="rr-menu__item" role="menuitem" href="{{ route('construction.materials.edit', $document->id) }}">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 18])@lang('construction::lang.edit_document')</a>
                                                    <div class="rr-menu__sep" role="separator"></div>
                                                    <form method="POST" action="{{ route('construction.materials.destroy', $document->id) }}" class="rr-inline-form" onsubmit="return confirm(@js(__('construction::lang.delete_material_confirmation')))">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rr-menu__item rr-menu__item--danger" role="menuitem">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])@lang('construction::lang.delete_draft')</button>
                                                    </form>
                                                @endcan
                                            @elseif(! $isReturn)
                                                @can('construction.material.manage')
                                                    <a class="rr-menu__item" role="menuitem" href="{{ route('construction.materials.returns.create', $document->id) }}">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 18])@lang('construction::lang.create_material_return')</a>
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
            @include('construction::partials.rr_pager', ['paginator' => $documents, 'itemLabel' => __('construction::lang.material_documents')])
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

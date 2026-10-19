@extends('construction::layouts.module')
@section('title', $version->name)

@php
    $versionTones = ['draft' => 'warning', 'approved' => 'success', 'superseded' => 'neutral'];
    $itemRows = $version->items->where('row_type', 'item');
    $sectionCount = $version->items->where('row_type', 'section')->count();
    $currencyParts = \Modules\Construction\Support\CurrencyFormatter::parts(0);
    $canManage = $canEditItems && auth()->user()->can('construction.boq.manage');
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-boq-title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.projects.index') }}">@lang('construction::lang.projects')</a></li>
                    <li><a href="{{ route('construction.projects.show', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.boq_workspace')</span></li>
                </ol>
            </nav>
            <h1 id="rr-boq-title" class="rr-head__title">{{ $version->name }}</h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $versionTones[$version->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $version->status)</span>
                <span class="rr-code">V{{ $version->version_number }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $project->name }}</span>
            </div>
        </div>
        <div class="rr-head__actions">
            @if($canEditItems)
                @if($version->status === 'draft')
                    @can('construction.boq.approve')
                        <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.boq.approve', [$project->id, $version->id]) }}" onsubmit="return confirm(@js(__('construction::lang.approve_boq_confirmation')))">
                            @csrf
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.approve_boq')</button>
                        </form>
                    @endcan
                @endif
                @if($version->status === 'approved' && ! $project->primaryContract)
                    @can('construction.contract.manage')
                        <a href="{{ route('construction.projects.contracts.create', ['project' => $project->id, 'boq_version_id' => $version->id]) }}" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.create_contract_from_boq')</a>
                    @endcan
                @endif
                @can('construction.boq.manage')
                    <a href="#add-boq-item" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_item')</a>
                @endcan
            @elseif($version->status === 'approved' && ! $project->primaryContract)
                @can('construction.contract.manage')
                    <a href="{{ route('construction.projects.contracts.create', ['project' => $project->id, 'boq_version_id' => $version->id]) }}" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.create_contract_from_boq')</a>
                @endcan
            @elseif($project->primaryContract)
                @can('construction.contract.view')
                    <a href="{{ route('construction.projects.contracts.show', [$project->id, $project->primaryContract->id]) }}" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.open_contract')</a>
                @endcan
            @endif
            <a href="{{ route('construction.projects.boq.export', [$project->id, $version->id]) }}" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'download'])@lang('construction::lang.export_excel')</a>
        </div>
    </header>

    {{-- ═══ أرقام سريعة ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.boq_total_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $version->sales_total == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $version->sales_total])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.quote_col_items')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $itemRows->count() }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 18])@lang('construction::lang.boq_section_count')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $sectionCount }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'clock', 'size' => 18])@lang('construction::lang.boq_versions')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $versions->count() }}</span></div>
                <div class="rr-stat__hint">@lang('construction::lang.boq_current_version') <span class="rr-code">V{{ $version->version_number }}</span></div>
            </div>
        </div>
    </div>

    @if($version->status === 'approved' && ! $canEditItems)
        <div class="rr-alert rr-alert--success" role="status"><div><strong>@lang('construction::lang.boq_approved')</strong> @lang('construction::lang.approved_next_contract')</div></div>
    @elseif($version->status === 'superseded')
        <div class="rr-alert" role="status"><div><strong>@lang('construction::lang.superseded')</strong> — @lang('construction::lang.old_boq_revision_hint')</div></div>
    @endif

    <div class="rr-cols">
        <div class="rr-cols__main">

            {{-- ═══ جدول البنود ═══ --}}
            <section class="rr-table-wrap" aria-labelledby="rr-boq-items-title">
                <div class="rr-table-head">
                    <h2 id="rr-boq-items-title" class="rr-table-head__title">@lang('construction::lang.boq_items')</h2>
                    <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $itemRows->count() }}</span> @lang('construction::lang.items_count_label')</span>
                </div>
                @if($version->items->isEmpty())
                    <div class="rr-empty">
                        <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 22])</span>
                        <h3 class="rr-empty__title">@lang('construction::lang.simple_boq_empty')</h3>
                        <p class="rr-empty__text">@lang('construction::lang.simple_boq_empty_hint')</p>
                    </div>
                @else
                    <div class="rr-table-scroll">
                        <table class="rr-table">
                            <caption class="rr-sr">@lang('construction::lang.boq_items')</caption>
                            <thead>
                                <tr>
                                    <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.item_name')</th>
                                    <th class="rr-th" scope="col">@lang('construction::lang.unit')</th>
                                    <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.quantity')</th>
                                    <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.unit_price')</th>
                                    <th class="rr-th rr-th--num" scope="col"><span class="rr-th__in">@lang('construction::lang.total')@if($currencyParts['symbol'] !== '')<span class="rr-th__unit">({{ $currencyParts['symbol'] }})</span>@endif</span></th>
                                    @if($canManage)<th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>@endif
                                </tr>
                            </thead>
                            <tbody class="rr-tbody">
                                @foreach($version->items as $item)
                                    @if($item->row_type === 'section')
                                        <tr class="rr-tr rr-tr--section">
                                            <td class="rr-td" colspan="{{ $canManage ? 6 : 5 }}"><span class="rr-section-row">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 16]){{ $item->description }}@if($item->code)<span class="rr-code">{{ $item->code }}</span>@endif</span></td>
                                        </tr>
                                    @else
                                        <tr class="rr-tr">
                                            <td class="rr-td rr-td--wrap">
                                                <div class="rr-entity__name">{{ $item->description }}</div>
                                                <span class="rr-code">{{ $item->code }}</span>
                                            </td>
                                            <td class="rr-td rr-td--muted">{{ $item->unit ?: '—' }}</td>
                                            <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity((float) $item->contract_quantity) }}</span></td>
                                            <td class="rr-td rr-td--num"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($item->sales_unit_price)['amount'] }}</span></td>
                                            <td class="rr-td rr-td--num"><strong class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($item->sales_total)['amount'] }}</strong></td>
                                            @if($canManage)
                                                <td class="rr-td rr-td--actions">
                                                    <div class="rr-row-actions">
                                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-toggle="modal" data-target="#ct-edit-item-{{ $item->id }}" aria-label="@lang('construction::lang.edit_project_item')" title="@lang('construction::lang.edit_project_item')">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 18])</button>
                                                        <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.boq.items.destroy', [$project->id, $version->id, $item->id]) }}" onsubmit="return confirm(@js(__('construction::lang.delete_item_confirmation')))">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="rr-btn rr-btn--danger-ghost rr-btn--sm rr-btn--icon" aria-label="@lang('messages.delete')" title="@lang('messages.delete')">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot class="rr-tfoot">
                                <tr>
                                    <td class="rr-td" colspan="4">@lang('construction::lang.boq_total_value')</td>
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $version->sales_total])</span></td>
                                    @if($canManage)<td class="rr-td"></td>@endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>

            {{-- ═══ إضافة بنود ═══ --}}
            @if($canManage)
                <section class="rr-card" id="add-boq-item" aria-labelledby="rr-boq-add-title">
                    <header class="rr-card__head">
                        <div class="rr-card__titles">
                            <h2 id="rr-boq-add-title" class="rr-card__title">@lang('construction::lang.add_multiple_items')</h2>
                            <p class="rr-card__desc">@lang('construction::lang.add_multiple_items_hint')</p>
                        </div>
                    </header>
                    <form method="POST" action="{{ route('construction.projects.boq.items.store', [$project->id, $version->id]) }}" id="ct-boq-batch-form">
                        @csrf
                        @php $formRows = old('items', [['description' => '', 'unit_id' => '', 'contract_quantity' => 0, 'sales_unit_price' => 0]]); @endphp
                        <div class="rr-entry" id="ct-boq-entry-rows">
                            @foreach($formRows as $rowIndex => $row)
                                <div class="rr-entry-row ct-boq-entry-row">
                                    <span class="rr-entry-row__num ct-boq-entry-number" aria-hidden="true">{{ $loop->iteration }}</span>
                                    <div class="rr-entry-row__fields">
                                        <input type="hidden" name="items[{{ $rowIndex }}][row_type]" value="item">
                                        <input type="hidden" name="items[{{ $rowIndex }}][item_kind]" value="standard">
                                        <div class="rr-entry-field rr-entry-field--grow">
                                            <label class="rr-entry-field__label" for="boq-row-{{ $rowIndex }}-desc">@lang('construction::lang.item_name'){!! $req !!}</label>
                                            <div class="rr-control rr-control--sm"><input id="boq-row-{{ $rowIndex }}-desc" name="items[{{ $rowIndex }}][description]" class="rr-control__input" maxlength="500" value="{{ $row['description'] ?? '' }}" placeholder="@lang('construction::lang.item_name_example')" required></div>
                                        </div>
                                        <div class="rr-entry-field rr-entry-field--unit">
                                            <label class="rr-entry-field__label" for="boq-row-{{ $rowIndex }}-unit">@lang('construction::lang.unit'){!! $req !!}</label>
                                            <div class="rr-control rr-control--sm"><select id="boq-row-{{ $rowIndex }}-unit" name="items[{{ $rowIndex }}][unit_id]" class="rr-control__input rr-control__select" required><option value="">@lang('construction::lang.choose')</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string) ($row['unit_id'] ?? '') === (string) $unit->id)>{{ $unit->actual_name }} ({{ $unit->short_name }})</option>@endforeach</select><span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span></div>
                                        </div>
                                        <span class="rr-entry-row__break" aria-hidden="true"></span>
                                        <div class="rr-entry-field rr-entry-field--num">
                                            <label class="rr-entry-field__label" for="boq-row-{{ $rowIndex }}-qty">@lang('construction::lang.quantity'){!! $req !!}</label>
                                            <div class="rr-control rr-control--sm"><input type="text" id="boq-row-{{ $rowIndex }}-qty" name="items[{{ $rowIndex }}][contract_quantity]" class="rr-control__input rr-control__input--num input_number input_quantity ct-boq-quantity" value="{{ @format_quantity($row['contract_quantity'] ?? 0) }}" required></div>
                                        </div>
                                        <div class="rr-entry-field rr-entry-field--num">
                                            <label class="rr-entry-field__label" for="boq-row-{{ $rowIndex }}-price">@lang('construction::lang.unit_price'){!! $req !!}</label>
                                            <div class="rr-control rr-control--sm"><input type="text" id="boq-row-{{ $rowIndex }}-price" name="items[{{ $rowIndex }}][sales_unit_price]" class="rr-control__input rr-control__input--num input_number ct-boq-price" value="{{ @num_format($row['sales_unit_price'] ?? 0) }}" required></div>
                                        </div>
                                        <div class="rr-entry-field rr-entry-field--total">
                                            <span class="rr-entry-field__label">@lang('construction::lang.row_total')</span>
                                            <div class="rr-entry-field__value" aria-live="polite"><output class="ct-boq-row-total rr-num">0.00</output></div>
                                        </div>
                                    </div>
                                    <button class="rr-btn rr-btn--danger-ghost rr-btn--sm rr-btn--icon ct-boq-remove-row" type="button" title="@lang('construction::lang.remove_row')" aria-label="@lang('construction::lang.remove_row')">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])</button>
                                </div>
                            @endforeach
                        </div>
                        <div class="rr-toolbar">
                            <button class="rr-btn rr-btn--secondary rr-btn--sm" type="button" id="ct-add-boq-row">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_row')</button>
                            <div class="rr-toolbar__end">
                                <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_all_items')</button>
                            </div>
                        </div>
                    </form>
                </section>
            @endif
        </div>

        <aside class="rr-cols__side">

            {{-- ═══ الإصدارات ═══ --}}
            <section class="rr-card" aria-labelledby="rr-boq-versions-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-boq-versions-title" class="rr-card__title">@lang('construction::lang.boq_versions')</h2>
                        <p class="rr-card__desc">@lang('construction::lang.revision_history_hint')</p>
                    </div>
                </header>
                <ul class="rr-list">
                    @foreach($versions as $historyVersion)
                        @php $isCurrentVersion = $historyVersion->id === $version->id; @endphp
                        <li class="rr-list__row {{ $isCurrentVersion ? 'rr-list__row--current' : '' }}">
                            <span class="rr-chip rr-chip--sm {{ $isCurrentVersion ? '' : 'rr-chip--neutral' }}" aria-hidden="true"><span class="rr-num">V{{ $historyVersion->version_number }}</span></span>
                            <div class="rr-list__main">
                                @if($isCurrentVersion)
                                    <span class="rr-list__title" aria-current="page">{{ $historyVersion->name }}</span>
                                @else
                                    <a class="rr-list__title" href="{{ route('construction.projects.boq.show', [$project->id, $historyVersion->id]) }}">{{ $historyVersion->name }}</a>
                                @endif
                                <span class="rr-list__meta"><span class="rr-num">{{ $historyVersion->items_count }}</span> @lang('construction::lang.items_count_label')</span>
                            </div>
                            <span class="rr-badge rr-badge--{{ $versionTones[$historyVersion->status] ?? 'neutral' }}">@lang('construction::lang.' . $historyVersion->status)</span>
                        </li>
                    @endforeach
                </ul>
                @can('construction.boq.manage')
                    <div class="rr-toolbar">
                        <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" data-toggle="modal" data-target="#ct-boq-version-create">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 16])@lang('construction::lang.new_boq_version')</button>
                        @if($version->status !== 'draft')
                            <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.boq.revise', [$project->id, $version->id]) }}">
                                @csrf
                                <button class="rr-btn rr-btn--ghost rr-btn--sm" type="submit">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 16])@lang('construction::lang.create_revision')</button>
                            </form>
                        @endif
                    </div>
                @endcan
            </section>

            {{-- ═══ الاستيراد والتصدير ═══ --}}
            <section class="rr-card" id="boq-import" aria-labelledby="rr-boq-tools-title">
                <header class="rr-card__head">
                    <span class="rr-chip rr-chip--success" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])</span>
                    <div class="rr-card__titles">
                        <h2 id="rr-boq-tools-title" class="rr-card__title">@lang('construction::lang.import_export_tools')</h2>
                        <p class="rr-card__desc">@lang('construction::lang.import_export_tools_hint')</p>
                    </div>
                </header>
                <div class="rr-card__body rr-section">
                    @if($canManage)
                        <button type="button" class="rr-btn rr-btn--primary rr-btn--sm" data-toggle="modal" data-target="#ct-boq-import">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 16])@lang('construction::lang.import_boq_items')</button>
                    @elseif(! $canEditItems)
                        <p class="rr-field__hint">@lang('construction::lang.import_requires_draft_version')</p>
                    @endif
                    <div class="rr-row-actions">
                        <a href="{{ route('construction.boq.import-template') }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 16])@lang('construction::lang.download_import_template')</a>
                        <a href="{{ route('construction.projects.boq.export', [$project->id, $version->id]) }}" class="rr-btn rr-btn--ghost rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 16])@lang('construction::lang.export_excel')</a>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</main>

{{-- ═══ النوافذ ═══ --}}
@can('construction.boq.manage')
    <div class="modal fade rr-scope" id="ct-boq-version-create" tabindex="-1" role="dialog" aria-labelledby="rr-boq-version-create-title">
        <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact rr-modal-dialog--sm" role="document">
            <div class="modal-content rr-modal">
                <form method="POST" action="{{ route('construction.projects.boq.store', $project->id) }}" data-ct-quick-create data-ct-open-mode="redirect">
                    @csrf
                    <header class="rr-modal__head">
                        <div class="rr-modal__titles"><h2 class="rr-modal__title" id="rr-boq-version-create-title">@lang('construction::lang.new_boq_version')</h2></div>
                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                    </header>
                    <div class="rr-modal__body">
                        <div class="rr-alert rr-alert--danger ct-quick-errors" role="alert"></div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="rr-boq-version-name">@lang('construction::lang.boq_version_name'){!! $req !!}</label>
                            <div class="rr-control"><input id="rr-boq-version-name" class="rr-control__input" name="name" value="{{ old('name', __('construction::lang.boq_default_name')) }}" maxlength="190" required></div>
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="rr-boq-version-notes">@lang('construction::lang.boq_notes') <span class="rr-field__opt">(@lang('construction::lang.optional'))</span></label>
                            <div class="rr-control rr-control--area"><textarea id="rr-boq-version-notes" class="rr-control__input rr-control__input--short" name="notes" rows="3" maxlength="5000">{{ old('notes') }}</textarea></div>
                        </div>
                    </div>
                    <footer class="rr-modal__foot">
                        <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_boq_version')</button>
                        <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                    </footer>
                </form>
            </div>
        </div>
    </div>
@endcan

@if($canManage)
    {{-- استيراد من Excel --}}
    <div class="modal fade rr-scope" id="ct-boq-import" tabindex="-1" role="dialog" aria-labelledby="rr-boq-import-title">
        <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
            <div class="modal-content rr-modal">
                <form method="POST" action="{{ route('construction.projects.boq.import.preview', [$project->id, $version->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <header class="rr-modal__head">
                        <div class="rr-modal__titles">
                            <h2 class="rr-modal__title" id="rr-boq-import-title">@lang('construction::lang.import_boq_items')</h2>
                            <p class="rr-modal__desc">@lang('construction::lang.import_export_tools_hint')</p>
                        </div>
                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                    </header>
                    <div class="rr-modal__body">
                        <ol class="rr-mini-steps" aria-label="@lang('construction::lang.quote_import_steps')">
                            <li><b>1</b>@lang('construction::lang.download_template_step')</li>
                            <li><b>2</b>@lang('construction::lang.fill_items_step')</li>
                            <li><b>3</b>@lang('construction::lang.review_import_step')</li>
                        </ol>
                        @error('boq_file')<div class="rr-alert rr-alert--danger" role="alert">{{ $message }}</div>@enderror
                        <div class="rr-field">
                            <label class="rr-field__label" for="ct-boq-file">@lang('construction::lang.choose_quote_excel_file'){!! $req !!}</label>
                            <div class="rr-control"><input id="ct-boq-file" class="rr-control__input" type="file" name="boq_file" accept=".xlsx,.xls,.csv" required></div>
                        </div>
                        <div class="rr-alert">@lang('construction::lang.import_preview_hint')</div>
                    </div>
                    <footer class="rr-modal__foot">
                        <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'search'])@lang('construction::lang.preview_before_import')</button>
                        <button class="rr-btn rr-btn--secondary" type="button" data-dismiss="modal">@lang('messages.cancel')</button>
                        <a class="rr-btn rr-btn--ghost rr-modal__note" href="{{ route('construction.boq.import-template') }}">@include('construction::partials.rr_icon', ['name' => 'download'])@lang('construction::lang.download_import_template')</a>
                    </footer>
                </form>
            </div>
        </div>
    </div>

    {{-- تعديل بند --}}
    @foreach($itemRows as $item)
        <div class="modal fade rr-scope" id="ct-edit-item-{{ $item->id }}" tabindex="-1" role="dialog" aria-labelledby="rr-boq-item-{{ $item->id }}-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.projects.boq.items.update', [$project->id, $version->id, $item->id]) }}">
                        @csrf @method('PUT')
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-boq-item-{{ $item->id }}-title">@lang('construction::lang.edit_project_item')</h2>
                                <p class="rr-modal__desc"><span class="rr-code">{{ $item->code }}</span></p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-form-grid">
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="rr-boq-item-{{ $item->id }}-code">@lang('construction::lang.boq_code'){!! $req !!}</label>
                                        <div class="rr-control"><input id="rr-boq-item-{{ $item->id }}-code" class="rr-control__input" name="code" value="{{ $item->code }}" required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="rr-boq-item-{{ $item->id }}-unit">@lang('construction::lang.unit'){!! $req !!}</label>
                                        <div class="rr-control">
                                            <select id="rr-boq-item-{{ $item->id }}-unit" class="rr-control__input rr-control__select" name="unit_id" required>
                                                @foreach($units as $unit)<option value="{{ $unit->id }}" @selected($item->unit_id == $unit->id)>{{ $unit->actual_name }} ({{ $unit->short_name }})</option>@endforeach
                                            </select>
                                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                        </div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="rr-boq-item-{{ $item->id }}-desc">@lang('construction::lang.boq_description'){!! $req !!}</label>
                                        <div class="rr-control"><input id="rr-boq-item-{{ $item->id }}-desc" class="rr-control__input" name="description" value="{{ $item->description }}" required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="rr-boq-item-{{ $item->id }}-qty">@lang('construction::lang.quantity'){!! $req !!}</label>
                                        <div class="rr-control"><input id="rr-boq-item-{{ $item->id }}-qty" class="rr-control__input rr-control__input--num input_number input_quantity" name="contract_quantity" value="{{ @format_quantity((float) $item->contract_quantity) }}" required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="rr-boq-item-{{ $item->id }}-price">@lang('construction::lang.unit_price'){!! $req !!}</label>
                                        <div class="rr-control"><input id="rr-boq-item-{{ $item->id }}-price" class="rr-control__input rr-control__input--num input_number" name="sales_unit_price" value="{{ @num_format((float) $item->sales_unit_price) }}" required></div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="rr-boq-item-{{ $item->id }}-notes">@lang('construction::lang.notes') <span class="rr-field__opt">(@lang('construction::lang.optional'))</span></label>
                                        <div class="rr-control rr-control--area"><textarea id="rr-boq-item-{{ $item->id }}-notes" class="rr-control__input rr-control__input--short" name="notes" rows="2">{{ $item->notes }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button type="submit" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('messages.save')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
@include('construction::partials.quick_create')

@section('module_javascript')
@if($canManage)
<template id="ct-boq-row-template">
    <div class="rr-entry-row ct-boq-entry-row">
        <span class="rr-entry-row__num ct-boq-entry-number" aria-hidden="true"></span>
        <div class="rr-entry-row__fields">
            <input type="hidden" data-name="row_type" value="item">
            <input type="hidden" data-name="item_kind" value="standard">
            <div class="rr-entry-field rr-entry-field--grow">
                <label class="rr-entry-field__label">@lang('construction::lang.item_name'){!! $req !!}</label>
                <div class="rr-control rr-control--sm"><input data-name="description" class="rr-control__input" maxlength="500" placeholder="@lang('construction::lang.item_name_example')" required></div>
            </div>
            <div class="rr-entry-field rr-entry-field--unit">
                <label class="rr-entry-field__label">@lang('construction::lang.unit'){!! $req !!}</label>
                <div class="rr-control rr-control--sm"><select data-name="unit_id" class="rr-control__input rr-control__select" required><option value="">@lang('construction::lang.choose')</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->actual_name }} ({{ $unit->short_name }})</option>@endforeach</select><span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span></div>
            </div>
            <span class="rr-entry-row__break" aria-hidden="true"></span>
            <div class="rr-entry-field rr-entry-field--num">
                <label class="rr-entry-field__label">@lang('construction::lang.quantity'){!! $req !!}</label>
                <div class="rr-control rr-control--sm"><input type="text" data-name="contract_quantity" class="rr-control__input rr-control__input--num input_number input_quantity ct-boq-quantity" value="{{ @format_quantity(0) }}" required></div>
            </div>
            <div class="rr-entry-field rr-entry-field--num">
                <label class="rr-entry-field__label">@lang('construction::lang.unit_price'){!! $req !!}</label>
                <div class="rr-control rr-control--sm"><input type="text" data-name="sales_unit_price" class="rr-control__input rr-control__input--num input_number ct-boq-price" value="{{ @num_format(0) }}" required></div>
            </div>
            <div class="rr-entry-field rr-entry-field--total">
                <span class="rr-entry-field__label">@lang('construction::lang.row_total')</span>
                <div class="rr-entry-field__value" aria-live="polite"><output class="ct-boq-row-total rr-num">0.00</output></div>
            </div>
        </div>
        <button class="rr-btn rr-btn--danger-ghost rr-btn--sm rr-btn--icon ct-boq-remove-row" type="button" title="@lang('construction::lang.remove_row')" aria-label="@lang('construction::lang.remove_row')">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])</button>
    </div>
</template>
<script>
(function () {
    var rows = document.getElementById('ct-boq-entry-rows');
    var addButton = document.getElementById('ct-add-boq-row');
    var template = document.getElementById('ct-boq-row-template');
    if (!rows || !addButton || !template) return;

    function ensureCurrencyGlobals() {
        var defaults = {
            __currency_symbol: '',
            __currency_thousand_separator: ',',
            __currency_decimal_separator: '.',
            __currency_symbol_placement: 'after',
            __currency_precision: 2,
            __quantity_precision: 2
        };
        var fields = {
            __currency_symbol: '__symbol',
            __currency_thousand_separator: '__thousand',
            __currency_decimal_separator: '__decimal',
            __currency_symbol_placement: '__symbol_placement',
            __currency_precision: '__precision',
            __quantity_precision: '__quantity_precision'
        };
        Object.keys(fields).forEach(function (key) {
            if (typeof window[key] !== 'undefined') return;
            var field = document.getElementById(fields[key]);
            window[key] = field && field.value !== '' ? field.value : defaults[key];
        });
    }
    ensureCurrencyGlobals();

    // كل صف كارت (rr-entry-row)؛ الأسماء والـ ids والترقيم بيتعادوا بعد أي إضافة أو حذف
    function refreshRows() {
        var allRows = rows.querySelectorAll('.ct-boq-entry-row');
        allRows.forEach(function (row, index) {
            row.querySelector('.ct-boq-entry-number').textContent = index + 1;
            row.querySelectorAll('[data-name]').forEach(function (input) {
                input.name = 'items[' + index + '][' + input.dataset.name + ']';
                if (input.type !== 'hidden') input.id = 'boq-row-' + index + '-' + input.dataset.name;
            });
            row.querySelectorAll('label.rr-entry-field__label').forEach(function (label) {
                var input = label.parentElement.querySelector('[data-name]');
                if (input) label.htmlFor = input.id;
            });
            row.querySelector('.ct-boq-remove-row').disabled = allRows.length === 1;
            updateTotal(row);
        });
    }
    function updateTotal(row) {
        var quantity = __number_uf(row.querySelector('.ct-boq-quantity').value) || 0;
        var price = __number_uf(row.querySelector('.ct-boq-price').value) || 0;
        row.querySelector('.ct-boq-row-total').textContent = __currency_trans_from_en(quantity * price, false);
    }
    addButton.addEventListener('click', function () {
        rows.appendChild(template.content.cloneNode(true));
        refreshRows();
        rows.lastElementChild.querySelector('[data-name="description"]').focus();
    });
    rows.addEventListener('click', function (event) {
        var button = event.target.closest('.ct-boq-remove-row');
        if (button && rows.querySelectorAll('.ct-boq-entry-row').length > 1) { button.closest('.ct-boq-entry-row').remove(); refreshRows(); }
    });
    rows.addEventListener('input', function (event) {
        if (event.target.matches('.ct-boq-quantity,.ct-boq-price')) updateTotal(event.target.closest('.ct-boq-entry-row'));
    });
    rows.querySelectorAll('input,select').forEach(function (input) { if (!input.dataset.name && input.name) input.dataset.name = input.name.match(/\[([^\]]+)\]$/)[1]; });
    refreshRows();
})();
</script>
@endif
<script>
$(function () {
    @if($errors->has('boq_file') || request()->boolean('import'))$('#ct-boq-import').modal('show');@endif
});
</script>
@endsection

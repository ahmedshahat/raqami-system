@extends('construction::layouts.module')
@section('title', $document->number)

@php
    $isReturn = $document->type === 'return';
    $isDraft = $document->isEditable();
    $canManage = auth()->user()->can('construction.material.manage');
    $documentTotal = (float) $document->lines->sum('total_cost');
    $columnCount = $isReturn ? 6 : 7;
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
    $currentStep = ! $isDraft ? 4 : ($document->lines->isEmpty() ? 2 : 3);
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-material-title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg {{ $isReturn ? 'rr-chip--warning' : 'rr-chip--info' }}" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => $isReturn ? 'arrow-back' : 'folder-open', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.materials.index') }}">@lang('construction::lang.material_documents')</a></li>
                    <li><a href="{{ route('construction.projects.show', $document->project_id) }}" class="rr-num">{{ $document->project->code }}</a></li>
                    <li><span aria-current="page" class="rr-num">{{ $document->number }}</span></li>
                </ol>
            </nav>
            <h1 id="rr-material-title" class="rr-head__title">@lang('construction::lang.material_' . $document->type) <span class="rr-num">{{ $document->number }}</span></h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $isDraft ? 'warning' : 'success' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $document->status)</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $document->project->name }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'map-pin', 'size' => 18]){{ $document->location->name }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])<span class="rr-num">{{ @format_date($document->document_date) }}</span></span>
            </div>
        </div>
        <div class="rr-head__actions">
            @if($isDraft && $canManage)
                <a href="{{ route('construction.materials.edit', $document->id) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'edit'])@lang('construction::lang.edit_document')</a>
            @elseif(! $isDraft && ! $isReturn && $canManage)
                <a href="{{ route('construction.materials.returns.create', $document->id) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'arrow-back'])@lang('construction::lang.create_material_return')</a>
            @endif
            @if($document->accountingPosting?->mapping)
                @can('construction.accounting.view')
                    <button type="button" class="rr-btn rr-btn--secondary btn-modal" data-href="{{ route('journal-entry.show', $document->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal">@include('construction::partials.rr_icon', ['name' => 'list'])@lang('construction::lang.view_accounting_entry')</button>
                @endcan
            @endif
            <a href="{{ route('construction.materials.preview', $document->id) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.print_preview')</a>
            <a href="{{ route('construction.materials.print', $document->id) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'print'])@lang('construction::lang.print')</a>
        </div>
    </header>

    @if(! $isReturn && $isDraft)
        @include('construction::materials.partials.steps', ['current' => $currentStep])
    @endif

    @if($document->parentIssue)
        <div class="rr-callout rr-callout--info">
            <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 20])</span>
            <div class="rr-callout__copy"><strong>@lang('construction::lang.return_for_issue') <a class="rr-code-link" href="{{ route('construction.materials.show', $document->parentIssue->id) }}"><span class="rr-code">{{ $document->parentIssue->number }}</span></a></strong></div>
        </div>
    @endif

    {{-- ═══ الاعتماد ═══ --}}
    @if($isDraft)
        @can('construction.material.approve')
            <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-material-next-title">
                <div class="rr-next">
                    <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 22])</span>
                    <div class="rr-next__copy">
                        <h2 id="rr-material-next-title" class="rr-next__title">@lang('construction::lang.ready_for_approval')</h2>
                        <p class="rr-next__text">@lang('construction::lang.approval_locks_document')</p>
                    </div>
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.materials.approve', $document->id) }}" onsubmit="return confirm(@js(__('construction::lang.approve_material_confirmation')))">
                        @csrf
                        <button class="rr-btn rr-btn--primary rr-btn--lg" type="submit" @disabled($document->lines->isEmpty())>@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.approve_and_post_stock')</button>
                    </form>
                </div>
            </section>
        @endcan
    @endif

    {{-- ═══ الأرقام ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--2" style="--rr-stats: 2">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.lines_count')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $document->lines->count() }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.document_total')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $documentTotal == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $documentTotal])</span></div>
                @if($isReturn)<div class="rr-stat__sub">@lang('construction::lang.material_return')</div>@endif
            </div>
        </div>
    </div>

    @if($document->notes)
        <div class="rr-alert rr-alert--info"><div><strong>@lang('construction::lang.notes'):</strong> {{ $document->notes }}</div></div>
    @endif

    {{-- ═══ إضافة خامة ═══ --}}
    @if($isDraft && ! $isReturn && $canManage)
        <section class="rr-card" aria-labelledby="rr-material-add-title">
            <header class="rr-card__head">
                <div class="rr-card__titles">
                    <h2 id="rr-material-add-title" class="rr-card__title">@lang('construction::lang.add_material')</h2>
                    <p class="rr-card__desc">@lang('construction::lang.stock_changes_after_approval')</p>
                </div>
            </header>
            <form method="POST" action="{{ route('construction.materials.lines.store', $document->id) }}">
                @csrf
                <div class="rr-entry">
                    <div class="rr-entry-row">
                        <div class="rr-entry-row__fields">
                            <div class="rr-entry-field rr-entry-field--grow rr-field">
                                <label class="rr-entry-field__label" for="material-line-product">@lang('construction::lang.material_product'){!! $req !!}</label>
                                <select id="material-line-product" name="variation_id" class="form-control select2" required>
                                    <option value="">@lang('construction::lang.select_material')</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->product_name }}@if($product->group_name !== 'DUMMY') — {{ $product->group_name }} / {{ $product->variation_name }}@endif ({{ $product->sub_sku }}) — @lang('construction::lang.available'): {{ @num_format($product->qty_available) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="rr-entry-field rr-entry-field--num">
                                <label class="rr-entry-field__label" for="material-line-qty">@lang('construction::lang.quantity'){!! $req !!}</label>
                                <div class="rr-control rr-control--sm"><input id="material-line-qty" name="quantity" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                            </div>
                            <span class="rr-entry-row__break"></span>
                            <div class="rr-entry-field rr-entry-field--grow rr-field">
                                <label class="rr-entry-field__label" for="material-line-item">@lang('construction::lang.project_item_optional')</label>
                                <select id="material-line-item" name="boq_item_id" class="form-control select2">
                                    <option value="">@lang('construction::lang.general_project_material')</option>
                                    @foreach($boqItems as $item)<option value="{{ $item->id }}">{{ $item->code }} — {{ $item->description }}</option>@endforeach
                                </select>
                            </div>
                            <div class="rr-entry-field rr-entry-field--note">
                                <label class="rr-entry-field__label" for="material-line-notes">@lang('construction::lang.notes')</label>
                                <div class="rr-control rr-control--sm"><input id="material-line-notes" name="notes" class="rr-control__input" placeholder="@lang('construction::lang.line_notes_optional')"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rr-toolbar">
                    <span class="rr-toolbar__hint">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.add_materials_step_hint')</span>
                    <div class="rr-toolbar__end">
                        <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_material')</button>
                    </div>
                </div>
            </form>
        </section>
    @endif

    {{-- ═══ سطور المستند ═══ --}}
    <section class="rr-table-wrap" aria-labelledby="rr-material-lines-title">
        <div class="rr-table-head">
            <div>
                <h2 id="rr-material-lines-title" class="rr-table-head__title">@lang('construction::lang.material_lines')</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.material_lines_hint')</p>
            </div>
            <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $document->lines->count() }}</span></span>
        </div>
        @if($document->lines->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.material_document_empty')</h3>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.material_lines')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-th--num" scope="col">#</th>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.material_product')</th>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.project_item')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.quantity')</th>
                            @unless($isReturn)<th class="rr-th rr-th--num" scope="col">@lang('construction::lang.returned_quantity')</th>@endunless
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.unit_cost')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.total_cost')</th>
                            @if($isDraft && $canManage)<th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>@endif
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($document->lines as $line)
                            @php $returnedQuantity = (float) ($returnedBySource[$line->id] ?? 0); @endphp
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--num rr-td--muted"><span class="rr-num">{{ $loop->iteration }}</span></td>
                                <td class="rr-td rr-td--wrap">
                                    <span class="rr-entity__name">{{ $line->variation->full_name }}</span>
                                    <div class="rr-td__sub">{{ $line->product->unit?->short_name }}@if($line->notes) · {{ $line->notes }}@endif</div>
                                </td>
                                <td class="rr-td rr-td--wrap">
                                    @if($line->boqItem)
                                        <span class="rr-code">{{ $line->boqItem->code }}</span> {{ $line->boqItem->description }}
                                    @else
                                        <span class="rr-td--muted">@lang('construction::lang.general_project_material')</span>
                                    @endif
                                </td>
                                <td class="rr-td rr-td--num"><strong class="rr-num">{{ @num_format($line->quantity) }}</strong></td>
                                @unless($isReturn)
                                    <td class="rr-td rr-td--num"><span class="rr-num {{ $returnedQuantity == 0 ? 'rr-td--muted' : '' }}">{{ $returnedQuantity == 0 ? '—' : @num_format($returnedQuantity) }}</span></td>
                                @endunless
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($line->unit_cost)['amount'] }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $line->total_cost])</span></td>
                                @if($isDraft && $canManage)
                                    <td class="rr-td rr-td--actions">
                                        <div class="rr-row-actions">
                                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-toggle="modal" data-target="#edit-line-{{ $line->id }}" title="@lang('construction::lang.edit_line')" aria-label="@lang('construction::lang.edit_line'): {{ $line->variation->full_name }}">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 16])</button>
                                            <form class="rr-inline-form" method="POST" action="{{ route('construction.materials.lines.destroy', [$document->id, $line->id]) }}" onsubmit="return confirm(@js(__('messages.sure')))">
                                                @csrf @method('DELETE')
                                                <button class="rr-btn rr-btn--danger-ghost rr-btn--sm rr-btn--icon" type="submit" title="@lang('construction::lang.delete_line')" aria-label="@lang('construction::lang.delete_line'): {{ $line->variation->full_name }}">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 16])</button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="rr-tfoot">
                        <tr>
                            <td class="rr-td" colspan="{{ $columnCount - 1 }}">@lang('construction::lang.document_total')</td>
                            <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $documentTotal])</span></td>
                            @if($isDraft && $canManage)<td class="rr-td"></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>
</main>

{{-- ═══ تعديل سطر ═══ --}}
@if($isDraft && $canManage)
    @foreach($document->lines as $line)
        <div class="modal fade rr-scope" id="edit-line-{{ $line->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-line-{{ $line->id }}-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.materials.lines.update', [$document->id, $line->id]) }}">
                        @csrf @method('PUT')
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="edit-line-{{ $line->id }}-title">@lang('construction::lang.edit_line')</h2>
                                <p class="rr-modal__desc">{{ $line->variation->full_name }}</p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-form-grid">
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="edit-line-{{ $line->id }}-qty">@lang('construction::lang.quantity'){!! $req !!}</label>
                                        <div class="rr-control"><input id="edit-line-{{ $line->id }}-qty" name="quantity" value="{{ @num_format($line->quantity) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                                    </div>
                                    @unless($isReturn)
                                        <div class="rr-field">
                                            <label class="rr-field__label" for="edit-line-{{ $line->id }}-item">@lang('construction::lang.project_item_optional')</label>
                                            <div class="rr-control">
                                                <select id="edit-line-{{ $line->id }}-item" name="boq_item_id" class="rr-control__input rr-control__select">
                                                    <option value="">@lang('construction::lang.general_project_material')</option>
                                                    @foreach($boqItems as $item)<option value="{{ $item->id }}" @selected($line->boq_item_id === $item->id)>{{ $item->code }} — {{ $item->description }}</option>@endforeach
                                                </select>
                                                <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                            </div>
                                        </div>
                                    @endunless
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="edit-line-{{ $line->id }}-notes">@lang('construction::lang.notes')</label>
                                        <div class="rr-control rr-control--area"><textarea id="edit-line-{{ $line->id }}-notes" name="notes" class="rr-control__input rr-control__input--short" rows="3">{{ $line->notes }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_changes')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection

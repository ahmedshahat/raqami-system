@extends('construction::layouts.module')

@section('title', __('construction::lang.tab_subcontractors'))

@php
    $agreementTones = ['draft' => 'warning', 'approved' => 'success', 'completed' => 'info', 'cancelled' => 'neutral'];
    $isFiltered = $search !== '' && $search !== null || $selectedProjectId || $status;
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
    $agreementStats = [
        ['all', 'all_agreements', 'file', null],
        ['draft', 'draft_agreements', 'edit', 'draft'],
        ['approved', 'approved_agreements', 'check-circle', 'approved'],
    ];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-subcontracts-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'users', 'size' => 24])</span>
        <div class="rr-head__titles">
            <div class="rr-eyebrow">@lang('construction::lang.subcontract_management')</div>
            <h1 id="rr-subcontracts-title" class="rr-head__title">@lang('construction::lang.subcontract_agreements')</h1>
            <p class="rr-head__sub">@lang('construction::lang.subcontract_phase_one_hint')</p>
        </div>
        @can('construction.subcontract.manage')
            <div class="rr-head__actions">
                <button type="button" class="rr-btn rr-btn--primary" data-toggle="modal" data-target="#create-subcontract">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_subcontract')</button>
            </div>
        @endcan
    </header>

    {{-- المؤشرات: كل مؤشر رابط تصفية --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4">
            @foreach($agreementStats as [$key, $label, $icon, $filterStatus])
                <a class="rr-stat" href="{{ route('construction.subcontractors.index', array_filter(['status' => $filterStatus, 'project_id' => $selectedProjectId, 'q' => $search])) }}" @if($status === $filterStatus || (!$status && !$filterStatus)) aria-current="true" @endif>
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => $icon, 'size' => 18])@lang('construction::lang.' . $label)</div>
                    <div class="rr-stat__value"><span class="rr-num">{{ $stats[$key] }}</span></div>
                </a>
            @endforeach
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.approved_subcontract_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $stats['approved_total'] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $stats['approved_total']])</span></div>
            </div>
        </div>
    </div>

    <section class="rr-table-wrap" id="ct-quick-list" aria-labelledby="rr-subcontracts-list-title">
        <div class="rr-table-head rr-table-head--toolbar">
            <div class="rr-table-head__titles">
                <h2 id="rr-subcontracts-list-title" class="rr-table-head__title">@lang('construction::lang.subcontract_agreements')</h2>
            </div>
            <form method="GET" action="{{ route('construction.subcontractors.index') }}" class="rr-table-tools" role="search" data-rr-auto-filter>
                <div class="rr-control rr-control--sm rr-table-tools__search">
                    <span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'search', 'size' => 16])</span>
                    <label class="rr-sr" for="ct-subcontract-search">@lang('construction::lang.search_subcontracts')</label>
                    <input id="ct-subcontract-search" class="rr-control__input" type="search" name="q" value="{{ $search }}" placeholder="@lang('construction::lang.search_subcontracts')">
                </div>
                <div class="rr-control rr-control--sm rr-table-tools__select">
                    <label class="rr-sr" for="ct-subcontract-project">@lang('construction::lang.project')</label>
                    <select id="ct-subcontract-project" class="rr-control__input rr-control__select" name="project_id">
                        <option value="">@lang('construction::lang.all_projects')</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>
                        @endforeach
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
                <div class="rr-control rr-control--sm rr-table-tools__select">
                    <label class="rr-sr" for="ct-subcontract-status">@lang('construction::lang.status')</label>
                    <select id="ct-subcontract-status" class="rr-control__input rr-control__select" name="status">
                        <option value="">@lang('construction::lang.all_statuses')</option>
                        @foreach(['draft', 'approved', 'completed', 'cancelled'] as $value)
                            <option value="{{ $value }}" @selected($status === $value)>@lang('construction::lang.' . $value)</option>
                        @endforeach
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
                <button class="rr-btn rr-btn--secondary rr-btn--sm" type="submit">@include('construction::partials.rr_icon', ['name' => 'filter', 'size' => 16])@lang('construction::lang.apply_filter')</button>
                @if($isFiltered)
                    <a class="rr-btn rr-btn--ghost rr-btn--sm" href="{{ route('construction.subcontractors.index') }}">@lang('construction::lang.clear_filter')</a>
                @endif
            </form>
        </div>

        @if($subcontracts->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => $isFiltered ? 'search' : 'users', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_subcontracts')</h3>
                <p class="rr-empty__text">@lang('construction::lang.no_subcontracts_hint')</p>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.subcontract_agreements')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.agreement_number')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.subcontractor')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.assigned_items')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.retention_percent')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.agreement_value')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($subcontracts as $contract)
                            @php
                                $contractUrl = route('construction.subcontractors.show', $contract->id);
                                $canDelete = $contract->isEditable() && auth()->user()->can('construction.subcontract.manage');
                            @endphp
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--wrap">
                                    <a class="rr-code-link" href="{{ $contractUrl }}"><span class="rr-code">{{ $contract->number }}</span></a>
                                    <div class="rr-td__sub">{{ $contract->title }}</div>
                                </td>
                                <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $contract->subcontractor->supplier_business_name ?: $contract->subcontractor->name }}</span></td>
                                <td class="rr-td rr-td--wrap">{{ $contract->project->name }}<div class="rr-td__sub"><span class="rr-code">{{ $contract->project->code }}</span></div></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $contract->items_count }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($contract->retention_percent) }}%</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ (float) $contract->total_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $contract->total_value])</span></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $agreementTones[$contract->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $contract->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <div class="rr-row-actions">
                                        <a href="{{ $contractUrl }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 18])@lang('construction::lang.open_agreement')</a>
                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="rr-subcontract-menu-{{ $contract->id }}" aria-label="@lang('construction::lang.operations'): {{ $contract->number }}">@include('construction::partials.rr_icon', ['name' => 'more'])</button>
                                        <div class="rr-menu" id="rr-subcontract-menu-{{ $contract->id }}" role="menu">
                                            <a class="rr-menu__item" role="menuitem" href="{{ route('construction.subcontractors.preview', $contract->id) }}" target="_blank" rel="noopener">@include('construction::partials.rr_icon', ['name' => 'print', 'size' => 18])@lang('construction::lang.print_preview')</a>
                                            @if($canDelete)
                                                <div class="rr-menu__sep" role="separator"></div>
                                                <form method="POST" action="{{ route('construction.subcontractors.destroy', $contract->id) }}" class="rr-inline-form" onsubmit="return confirm(@js(__('construction::lang.delete_subcontract_confirmation')))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rr-menu__item rr-menu__item--danger" role="menuitem">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])@lang('construction::lang.delete_draft')</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('construction::partials.rr_pager', ['paginator' => $subcontracts, 'itemLabel' => __('construction::lang.subcontract_agreement')])
        @endif
    </section>
</main>

@can('construction.subcontract.manage')
<div class="modal fade rr-scope" id="create-subcontract" tabindex="-1" role="dialog" aria-labelledby="rr-subcontract-create-title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--lg rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <form method="POST" action="{{ route('construction.subcontractors.store') }}">
                @csrf
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr-subcontract-create-title">@lang('construction::lang.new_subcontract')</h2>
                        <p class="rr-modal__desc"><span class="rr-code">{{ $nextNumber }}</span> · @lang('construction::lang.subcontract_create_hint')</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                </header>
                <div class="rr-modal__body">
                    @if($errors->any())
                        <div class="rr-alert rr-alert--danger" role="alert"><ul class="rr-error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    <div class="rr-form-wrap">
                        <div class="rr-form-grid rr-form-grid--3">
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-subcontract-new-project">@lang('construction::lang.project'){!! $req !!}</label>
                                <select id="ct-subcontract-new-project" name="project_id" class="form-control select2" required>
                                    <option value="">@lang('construction::lang.select_project')</option>
                                    @foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="rr-field">
                                <div class="rr-field__head">
                                    <label class="rr-field__label" for="subcontractor_id">@lang('construction::lang.subcontractor'){!! $req !!}</label>
                                    @can('supplier.create')
                                        <button type="button" class="rr-link-btn ct-add-supplier-link btn-modal" data-href="{{ action([\App\Http\Controllers\ContactController::class, 'create'], ['type' => 'supplier']) }}" data-container=".ct-subcontract-contact-modal">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 14])@lang('construction::lang.create_supplier_first')</button>
                                    @endcan
                                </div>
                                <select id="subcontractor_id" name="subcontractor_id" class="form-control select2" required>
                                    <option value="">@lang('construction::lang.select_subcontractor')</option>
                                    @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('subcontractor_id') == $supplier->id)>{{ $supplier->supplier_business_name ?: $supplier->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-subcontract-retention">@lang('construction::lang.retention_percent'){!! $req !!}</label>
                                <div class="rr-control"><input id="ct-subcontract-retention" name="retention_percent" value="{{ old('retention_percent', 5) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required aria-describedby="ct-subcontract-retention-hint" title="@lang('construction::lang.retention_simple_hint')"><span class="rr-control__affix">%</span></div>
                                <p class="rr-sr" id="ct-subcontract-retention-hint">@lang('construction::lang.retention_simple_hint')</p>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-subcontract-title">@lang('construction::lang.agreement_title'){!! $req !!}</label>
                                <div class="rr-control"><input id="ct-subcontract-title" name="title" value="{{ old('title') }}" class="rr-control__input" required maxlength="190" placeholder="@lang('construction::lang.agreement_title_placeholder')"></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-subcontract-start">@lang('construction::lang.start_date')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcontract-start" name="start_date" value="{{ old('start_date') }}" class="rr-control__input ct-date-picker" readonly placeholder="@lang('construction::lang.select_date')"></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-subcontract-end">@lang('construction::lang.end_date')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcontract-end" name="end_date" value="{{ old('end_date') }}" class="rr-control__input ct-date-picker" readonly placeholder="@lang('construction::lang.select_date')"></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-subcontract-notes">@lang('construction::lang.notes') <span class="rr-field__opt">@lang('construction::lang.optional')</span></label>
                                <div class="rr-control"><input id="ct-subcontract-notes" name="notes" value="{{ old('notes') }}" class="rr-control__input" maxlength="5000" placeholder="@lang('construction::lang.subcontract_notes_placeholder')"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="rr-modal__foot">
                    <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.create_agreement')</button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                    <p class="rr-modal__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.after_saving_agreement_hint')</p>
                </footer>
            </form>
        </div>
    </div>
</div>
@can('supplier.create')
    <div class="modal fade contact_modal ct-subcontract-contact-modal" tabindex="-1" role="dialog" aria-hidden="true"></div>
@endcan
@endcan
@include('construction::partials.rr_menu_script')
@endsection

@push('ct_quick_js')
<script>
$(function () {
    $('[data-rr-auto-filter] select').on('change', function () { this.form.submit(); });

    var supplierPopupPending = false;

    $(document).on('click', '.ct-add-supplier-link', function () {
        supplierPopupPending = true;
    });

    $(document).on('show.bs.modal', '.ct-subcontract-contact-modal', function () {
        $(this).css('z-index', 1070);
        setTimeout(function () {
            $('.modal-backdrop').last().css('z-index', 1060);
        }, 0);
    });

    $(document).on('hidden.bs.modal', '.ct-subcontract-contact-modal', function () {
        if ($('#create-subcontract').hasClass('in')) {
            $('body').addClass('modal-open');
        }
    });

    window.addEventListener('contactAdded', function (event) {
        if (!supplierPopupPending || !event.detail) {
            return;
        }

        var supplier = event.detail;
        var supplierName = supplier.supplier_business_name || supplier.name;
        var supplierSelect = $('#subcontractor_id');

        if (supplier.id && supplierName) {
            if (!supplierSelect.find('option[value="' + supplier.id + '"]').length) {
                supplierSelect.append(new Option(supplierName, supplier.id, true, true));
            }
            supplierSelect.val(supplier.id).trigger('change');
        }

        supplierPopupPending = false;
    });
});
</script>
@endpush

@if($errors->any())
    @push('ct_quick_js')<script>$(function(){$('#create-subcontract').modal('show')})</script>@endpush
@endif

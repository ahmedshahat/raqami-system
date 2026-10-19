@extends('construction::layouts.module')
@section('title', $subcontract->number)

@php
    $agreementTones = ['draft' => 'warning', 'approved' => 'success', 'completed' => 'info', 'cancelled' => 'neutral'];
    $certificateTones = ['draft' => 'warning', 'approved' => 'success', 'cancelled' => 'neutral'];
    $isEditable = $subcontract->isEditable();
    $isApproved = $subcontract->status === 'approved';
    $retentionValue = (float) $subcontract->retentionValue();
    $subcontractorName = $subcontract->subcontractor->supplier_business_name ?: $subcontract->subcontractor->name;
    $calculationTypes = ['linear_meter', 'square_meter', 'cubic_meter', 'unit', 'percentage', 'lump_sum'];
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-subcontract-title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'users', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.subcontractors.index') }}">@lang('construction::lang.subcontract_agreements')</a></li>
                    <li><span aria-current="page" class="rr-num">{{ $subcontract->number }}</span></li>
                </ol>
            </nav>
            <h1 id="rr-subcontract-title" class="rr-head__title">{{ $subcontract->title }}</h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $agreementTones[$subcontract->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $subcontract->status)</span>
                <span class="rr-code">{{ $subcontract->number }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'user', 'size' => 18]){{ $subcontractorName }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $subcontract->project->name }}</span>
            </div>
        </div>
        <div class="rr-head__actions">
            @if($isEditable)
                @can('construction.subcontract.manage')
                    <button type="button" class="rr-btn rr-btn--secondary" data-toggle="modal" data-target="#edit-subcontract">@include('construction::partials.rr_icon', ['name' => 'edit'])@lang('construction::lang.edit_agreement')</button>
                @endcan
            @endif
            <a href="{{ route('construction.subcontractors.preview', $subcontract->id) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.print_preview')</a>
            <a href="{{ route('construction.subcontractors.print', $subcontract->id) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'print'])@lang('construction::lang.print')</a>
        </div>
    </header>

    @if($errors->any())
        <div class="rr-alert rr-alert--danger" role="alert">
            <ul class="rr-error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ═══ الخطوة التالية ═══ --}}
    @if($isEditable)
        @can('construction.subcontract.approve')
            <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-subcontract-next-title">
                <div class="rr-next">
                    <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 22])</span>
                    <div class="rr-next__copy">
                        <div class="rr-eyebrow">@lang('construction::lang.next_action')</div>
                        <h2 id="rr-subcontract-next-title" class="rr-next__title">@lang('construction::lang.ready_for_approval')</h2>
                        <p class="rr-next__text">@lang('construction::lang.subcontract_approval_locks')</p>
                    </div>
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.subcontractors.approve', $subcontract->id) }}" onsubmit="return confirm(@js(__('construction::lang.approve_subcontract_confirmation')))">
                        @csrf
                        <button class="rr-btn rr-btn--primary rr-btn--lg" type="submit" @disabled($subcontract->items->isEmpty())>@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.approve_subcontract')</button>
                    </form>
                </div>
            </section>
        @endcan
    @elseif($isApproved)
        @can('construction.subcontract.manage')
            <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-subcontract-next-title">
                <div class="rr-next">
                    <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 22])</span>
                    <div class="rr-next__copy">
                        <div class="rr-eyebrow">@lang('construction::lang.next_action')</div>
                        <h2 id="rr-subcontract-next-title" class="rr-next__title">@lang('construction::lang.new_subcontract_certificate')</h2>
                        <p class="rr-next__text">@lang('construction::lang.certificate_stage_two_hint')</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--primary rr-btn--lg" data-toggle="modal" data-target="#create-subcontract-certificate">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_subcontract_certificate')</button>
                </div>
            </section>
        @endcan
    @endif

    {{-- ═══ أرقام الاتفاقية ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.agreement_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $subcontract->total_value == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $subcontract->total_value])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 18])@lang('construction::lang.expected_retention')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $retentionValue == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $retentionValue])</span></div>
                <div class="rr-stat__hint"><span class="rr-num">{{ @num_format($subcontract->retention_percent) }}%</span> @lang('construction::lang.retention_percent')</div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.net_after_retention')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg rr-money--pos">@include('construction::partials.money', ['value' => (float) $subcontract->total_value - $retentionValue])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.assigned_items')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $subcontract->items->count() }}</span></div>
            </div>
        </div>
    </div>

    <div class="rr-cols">
        <div class="rr-cols__main">
            {{-- نطاق الأعمال --}}
            <section class="rr-table-wrap" aria-labelledby="rr-subcontract-items-title">
                <div class="rr-table-head">
                    <div>
                        <h2 id="rr-subcontract-items-title" class="rr-table-head__title">@lang('construction::lang.assigned_items')</h2>
                        <p class="rr-table-head__sub">@lang('construction::lang.assigned_items_hint')</p>
                    </div>
                    <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $subcontract->items->count() }}</span></span>
                </div>
                @if($isEditable)
                    @can('construction.subcontract.manage')
                        <form method="POST" action="{{ route('construction.subcontractors.items.store', $subcontract->id) }}" class="rr-filter rr-filter--flat" aria-label="@lang('construction::lang.add_assigned_item')">
                            @csrf
                            <div class="rr-filter__fields">
                                <div class="rr-field rr-filter__grow">
                                    <label class="rr-field__label" for="ct-subitem-boq">@lang('construction::lang.project_item_optional')</label>
                                    <select id="ct-subitem-boq" name="boq_item_id" class="form-control select2 ct-subcontract-boq">
                                        <option value="">@lang('construction::lang.custom_scope_item')</option>
                                        @foreach($boqItems as $item)<option value="{{ $item->id }}" data-total="{{ $item->sales_total }}">{{ $item->code }} — {{ $item->description }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="rr-field rr-filter__grow">
                                    <label class="rr-field__label" for="ct-subitem-description">@lang('construction::lang.work_description'){!! $req !!}</label>
                                    <div class="rr-control"><input id="ct-subitem-description" name="description" class="rr-control__input" maxlength="500"></div>
                                </div>
                                <div class="rr-field">
                                    <label class="rr-field__label" for="ct-subitem-type">@lang('construction::lang.calculation_type'){!! $req !!}</label>
                                    <div class="rr-control">
                                        <select id="ct-subitem-type" name="calculation_type" class="rr-control__input rr-control__select ct-subcontract-type">
                                            @foreach($calculationTypes as $type)<option value="{{ $type }}">@lang('construction::lang.subcalc_' . $type)</option>@endforeach
                                        </select>
                                        <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                    </div>
                                </div>
                                <div class="rr-field ct-subcontract-qty">
                                    <label class="rr-field__label" for="ct-subitem-qty">@lang('construction::lang.quantity'){!! $req !!}</label>
                                    <div class="rr-control"><input id="ct-subitem-qty" name="quantity" value="1" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                                </div>
                                <div class="rr-field ct-subcontract-rate">
                                    <label class="rr-field__label" for="ct-subitem-rate">@lang('construction::lang.subcontract_unit_rate'){!! $req !!}</label>
                                    <div class="rr-control"><input id="ct-subitem-rate" name="unit_rate" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                                </div>
                            </div>
                            <div class="rr-filter__actions">
                                <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_item')</button>
                            </div>
                        </form>
                    @endcan
                @endif
                @if($subcontract->items->isEmpty())
                    <div class="rr-empty">
                        <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 22])</span>
                        <h3 class="rr-empty__title">@lang('construction::lang.no_assigned_items')</h3>
                    </div>
                @else
                    <div class="rr-table-scroll">
                        <table class="rr-table">
                            <caption class="rr-sr">@lang('construction::lang.assigned_items')</caption>
                            <thead>
                                <tr>
                                    <th class="rr-th rr-col--index" scope="col">#</th>
                                    <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.work_description')</th>
                                    <th class="rr-th" scope="col">@lang('construction::lang.project_item')</th>
                                    <th class="rr-th" scope="col">@lang('construction::lang.calculation_type')</th>
                                    <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.quantity')</th>
                                    <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.subcontract_unit_rate')</th>
                                    <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.total')</th>
                                    @if($isEditable)<th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>@endif
                                </tr>
                            </thead>
                            <tbody class="rr-tbody">
                                @foreach($subcontract->items as $line)
                                    <tr class="rr-tr">
                                        <td class="rr-td rr-td--muted"><span class="rr-num">{{ $loop->iteration }}</span></td>
                                        <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $line->description }}</span></td>
                                        <td class="rr-td">@if($line->boqItem)<span class="rr-code">{{ $line->boqItem->code }}</span>@else<span class="rr-td--muted">—</span>@endif</td>
                                        <td class="rr-td"><span class="rr-badge">@lang('construction::lang.subcalc_' . $line->calculation_type)</span></td>
                                        <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($line->quantity) }}</span></td>
                                        <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $line->unit_rate])</span></td>
                                        <td class="rr-td rr-td--num"><strong class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $line->total_value])</strong></td>
                                        @if($isEditable)
                                            <td class="rr-td rr-td--actions">
                                                @can('construction.subcontract.manage')
                                                    <div class="rr-row-actions">
                                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-toggle="modal" data-target="#edit-subitem-{{ $line->id }}" title="@lang('construction::lang.edit_assigned_item')" aria-label="@lang('construction::lang.edit_assigned_item'): {{ $line->description }}">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 16])</button>
                                                        <form class="rr-inline-form" method="POST" action="{{ route('construction.subcontractors.items.destroy', [$subcontract->id, $line->id]) }}" onsubmit="return confirm(@js(__('messages.sure')))">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" title="@lang('messages.delete')" aria-label="@lang('messages.delete'): {{ $line->description }}">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 16])</button>
                                                        </form>
                                                    </div>
                                                @endcan
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="rr-tfoot">
                                <tr>
                                    <td class="rr-td" colspan="6">@lang('construction::lang.agreement_value')</td>
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $subcontract->total_value])</span></td>
                                    @if($isEditable)<td class="rr-td"></td>@endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="rr-cols__side">
            <section class="rr-card" aria-labelledby="rr-subcontract-data-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-subcontract-data-title" class="rr-card__title">@lang('construction::lang.agreement_data')</h2>
                    </div>
                </header>
                <dl class="rr-deflist">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.agreement_number')</dt><dd><span class="rr-code">{{ $subcontract->number }}</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.subcontractor')</dt><dd>{{ $subcontractorName }}</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.project')</dt><dd><a class="rr-code-link" href="{{ route('construction.projects.show', $subcontract->project->id) }}"><span class="rr-code">{{ $subcontract->project->code }}</span></a></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.start_date')</dt><dd>@if($subcontract->start_date)<span class="rr-num">{{ @format_date($subcontract->start_date) }}</span>@else<span class="rr-td--muted">—</span>@endif</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.end_date')</dt><dd>@if($subcontract->end_date)<span class="rr-num">{{ @format_date($subcontract->end_date) }}</span>@else<span class="rr-td--muted">—</span>@endif</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.retention_percent')</dt><dd><span class="rr-num">{{ @num_format($subcontract->retention_percent) }}%</span></dd></div>
                </dl>
                @if($subcontract->notes)
                    <div class="rr-prose">{{ $subcontract->notes }}</div>
                @endif
            </section>
        </aside>
    </div>

    {{-- ═══ مستخلصات المقاول ═══ --}}
    <section class="rr-table-wrap" aria-labelledby="rr-subcontract-certificates-title">
        <div class="rr-table-head">
            <div>
                <h2 id="rr-subcontract-certificates-title" class="rr-table-head__title">@lang('construction::lang.subcontract_certificates')</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.certificate_stage_two_hint')</p>
            </div>
            @if($isApproved)
                @can('construction.subcontract.manage')
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" data-toggle="modal" data-target="#create-subcontract-certificate">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 16])@lang('construction::lang.new_subcontract_certificate')</button>
                @endcan
            @endif
        </div>
        @if($subcontract->certificates->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_subcontract_certificates')</h3>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.subcontract_certificates')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.certificate_number')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.certificate_date')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.gross_certificate')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.retention_value')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.net_certificate_due')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($subcontract->certificates as $certificate)
                            @php $certificateUrl = route('construction.subcontractors.certificates.show', [$subcontract->id, $certificate->id]); @endphp
                            <tr class="rr-tr">
                                <td class="rr-td">
                                    <a class="rr-code-link" href="{{ $certificateUrl }}"><span class="rr-code">{{ $certificate->number }}</span></a>
                                    @if($certificate->period_from || $certificate->period_to)
                                        <div class="rr-td__sub"><span class="rr-num">{{ $certificate->period_from ? @format_date($certificate->period_from) : '—' }}</span> ← <span class="rr-num">{{ $certificate->period_to ? @format_date($certificate->period_to) : '—' }}</span></div>
                                    @endif
                                </td>
                                <td class="rr-td"><span class="rr-num">{{ @format_date($certificate->certificate_date) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $certificate->gross_value])</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $certificate->retention_value])</span></td>
                                <td class="rr-td rr-td--num"><strong class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $certificate->net_value])</strong></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $certificateTones[$certificate->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $certificate->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <div class="rr-row-actions">
                                        <a href="{{ $certificateUrl }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 18])@lang('construction::lang.open_certificate')</a>
                                        <a href="{{ route('construction.subcontractors.certificates.preview', [$subcontract->id, $certificate->id]) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" title="@lang('construction::lang.print_preview')" aria-label="@lang('construction::lang.print_preview'): {{ $certificate->number }}">@include('construction::partials.rr_icon', ['name' => 'print', 'size' => 16])</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ═══ دفعات المقدم ═══ --}}
    @if($isApproved)
        <section class="rr-table-wrap" aria-labelledby="rr-subcontract-advances-title">
            <div class="rr-table-head">
                <div>
                    <h2 id="rr-subcontract-advances-title" class="rr-table-head__title">@lang('construction::lang.subcontract_advances')</h2>
                    <p class="rr-table-head__sub">@lang('construction::lang.subcontract_advances_hint')</p>
                </div>
                @can('construction.payment.record')
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" data-toggle="modal" data-target="#record-subcontract-advance">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 16])@lang('construction::lang.record_subcontract_advance')</button>
                @endcan
            </div>
            <div class="rr-statgroup-wrap">
                <div class="rr-statgroup rr-statgroup--3 rr-statgroup--flat" style="--rr-stats: 3">
                    <div class="rr-stat">
                        <div class="rr-stat__label">@lang('construction::lang.advances_paid')</div>
                        <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $advanceTotal == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $advanceTotal])</span></div>
                    </div>
                    <div class="rr-stat">
                        <div class="rr-stat__label">@lang('construction::lang.advances_recovered')</div>
                        <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $advanceRecovered == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $advanceRecovered])</span></div>
                    </div>
                    <div class="rr-stat">
                        <div class="rr-stat__label">@lang('construction::lang.advance_available')</div>
                        <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $advanceRemaining == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $advanceRemaining])</span></div>
                    </div>
                </div>
            </div>
            @if($subcontract->advances->isEmpty())
                <div class="rr-empty">
                    <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 22])</span>
                    <h3 class="rr-empty__title">@lang('construction::lang.no_subcontract_advances')</h3>
                </div>
            @else
                <div class="rr-table-scroll">
                    <table class="rr-table">
                        <caption class="rr-sr">@lang('construction::lang.subcontract_advances')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th" scope="col">@lang('construction::lang.advance_number')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.advance_date')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.amount')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_method')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                                <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($subcontract->advances as $advance)
                                <tr class="rr-tr">
                                    <td class="rr-td"><span class="rr-code">{{ $advance->number }}</span></td>
                                    <td class="rr-td"><span class="rr-num">{{ @format_date($advance->advance_date) }}</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ $advance->isActive() ? '' : 'rr-money--zero' }}">@include('construction::partials.money', ['value' => $advance->amount])</span></td>
                                    <td class="rr-td">{{ $paymentTypes[$advance->method] ?? $advance->method }}</td>
                                    <td class="rr-td"><span class="rr-badge rr-badge--{{ $advance->isActive() ? 'success' : 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.advance_status_' . $advance->status)</span></td>
                                    <td class="rr-td rr-td--actions">
                                        <div class="rr-row-actions">
                                            @can('construction.accounting.view')
                                                @if($advance->accountingPosting?->mapping)
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon btn-modal" data-href="{{ route('journal-entry.show', $advance->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_accounting_entry')" aria-label="@lang('construction::lang.view_accounting_entry')">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 16])</button>
                                                @endif
                                                @if($advance->cancellationAccountingPosting?->mapping)
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon btn-modal" data-href="{{ route('journal-entry.show', $advance->cancellationAccountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_reversal_accounting_entry')" aria-label="@lang('construction::lang.view_reversal_accounting_entry')">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 16])</button>
                                                @endif
                                            @endcan
                                            @if($advance->isActive())
                                                @can('construction.cancel')
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm ct-cancel-advance" data-toggle="modal" data-target="#cancel-subcontract-advance" data-action="{{ route('construction.subcontractors.advances.cancel', [$subcontract->id, $advance->id]) }}">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 16])@lang('construction::lang.cancel_subcontract_advance')</button>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</main>

{{-- ═══ النوافذ ═══ --}}
@if($isEditable)
    @can('construction.subcontract.manage')
        <div class="modal fade rr-scope" id="edit-subcontract" tabindex="-1" role="dialog" aria-labelledby="rr-edit-subcontract-title">
            <div class="modal-dialog rr-modal-dialog" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.subcontractors.update', $subcontract->id) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="project_id" value="{{ $subcontract->project_id }}">
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-edit-subcontract-title">@lang('construction::lang.edit_agreement')</h2>
                                <p class="rr-modal__desc"><span class="rr-code">{{ $subcontract->number }}</span></p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-form-grid">
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-edit-subcontractor">@lang('construction::lang.subcontractor'){!! $req !!}</label>
                                        <div class="rr-control">
                                            <select id="ct-edit-subcontractor" name="subcontractor_id" class="rr-control__input rr-control__select">
                                                @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected($subcontract->subcontractor_id === $supplier->id)>{{ $supplier->supplier_business_name ?: $supplier->name }}</option>@endforeach
                                            </select>
                                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                        </div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-edit-title">@lang('construction::lang.agreement_title'){!! $req !!}</label>
                                        <div class="rr-control"><input id="ct-edit-title" name="title" value="{{ $subcontract->title }}" class="rr-control__input" required maxlength="190"></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-edit-start">@lang('construction::lang.start_date')</label>
                                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-edit-start" name="start_date" value="{{ $subcontract->start_date ? @format_date($subcontract->start_date) : '' }}" class="rr-control__input ct-date-picker" readonly></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-edit-end">@lang('construction::lang.end_date')</label>
                                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-edit-end" name="end_date" value="{{ $subcontract->end_date ? @format_date($subcontract->end_date) : '' }}" class="rr-control__input ct-date-picker" readonly></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-edit-retention">@lang('construction::lang.retention_percent')</label>
                                        <div class="rr-control"><input id="ct-edit-retention" name="retention_percent" value="{{ @num_format($subcontract->retention_percent) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal"><span class="rr-control__affix">%</span></div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="ct-edit-notes">@lang('construction::lang.notes')</label>
                                        <div class="rr-control rr-control--area"><textarea id="ct-edit-notes" name="notes" class="rr-control__input rr-control__input--short" rows="3">{{ $subcontract->notes }}</textarea></div>
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

        @foreach($subcontract->items as $line)
            <div class="modal fade rr-scope" id="edit-subitem-{{ $line->id }}" tabindex="-1" role="dialog" aria-labelledby="rr-edit-subitem-title-{{ $line->id }}">
                <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                    <div class="modal-content rr-modal">
                        <form method="POST" action="{{ route('construction.subcontractors.items.update', [$subcontract->id, $line->id]) }}">
                            @csrf
                            @method('PUT')
                            <header class="rr-modal__head">
                                <div class="rr-modal__titles">
                                    <h2 class="rr-modal__title" id="rr-edit-subitem-title-{{ $line->id }}">@lang('construction::lang.edit_assigned_item')</h2>
                                    <p class="rr-modal__desc">{{ $line->description }}</p>
                                </div>
                                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                            </header>
                            <div class="rr-modal__body">
                                <div class="rr-form-wrap">
                                    <div class="rr-form-grid">
                                        <div class="rr-field rr-field--full">
                                            <label class="rr-field__label" for="ct-subitem-boq-{{ $line->id }}">@lang('construction::lang.project_item_optional')</label>
                                            <div class="rr-control">
                                                <select id="ct-subitem-boq-{{ $line->id }}" name="boq_item_id" class="rr-control__input rr-control__select ct-subcontract-boq">
                                                    <option value="">@lang('construction::lang.custom_scope_item')</option>
                                                    @foreach($boqItems as $item)<option value="{{ $item->id }}" data-total="{{ $item->sales_total }}" @selected($line->boq_item_id === $item->id)>{{ $item->code }} — {{ $item->description }}</option>@endforeach
                                                </select>
                                                <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                            </div>
                                        </div>
                                        <div class="rr-field rr-field--full">
                                            <label class="rr-field__label" for="ct-subitem-description-{{ $line->id }}">@lang('construction::lang.work_description')</label>
                                            <div class="rr-control"><input id="ct-subitem-description-{{ $line->id }}" name="description" value="{{ $line->description }}" class="rr-control__input" maxlength="500"></div>
                                        </div>
                                        <div class="rr-field rr-field--full">
                                            <label class="rr-field__label" for="ct-subitem-type-{{ $line->id }}">@lang('construction::lang.calculation_type')</label>
                                            <div class="rr-control">
                                                <select id="ct-subitem-type-{{ $line->id }}" name="calculation_type" class="rr-control__input rr-control__select ct-subcontract-type">
                                                    @foreach($calculationTypes as $type)<option value="{{ $type }}" @selected($line->calculation_type === $type)>@lang('construction::lang.subcalc_' . $type)</option>@endforeach
                                                </select>
                                                <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                            </div>
                                        </div>
                                        <div class="rr-field ct-subcontract-qty">
                                            <label class="rr-field__label" for="ct-subitem-qty-{{ $line->id }}">@lang('construction::lang.quantity')</label>
                                            <div class="rr-control"><input id="ct-subitem-qty-{{ $line->id }}" name="quantity" value="{{ @num_format($line->quantity) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal"></div>
                                        </div>
                                        <div class="rr-field ct-subcontract-rate">
                                            <label class="rr-field__label" for="ct-subitem-rate-{{ $line->id }}">@lang('construction::lang.subcontract_unit_rate')</label>
                                            <div class="rr-control"><input id="ct-subitem-rate-{{ $line->id }}" name="unit_rate" value="{{ @num_format($line->unit_rate) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal"></div>
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
    @endcan
@endif

@if($isApproved)
    @can('construction.payment.record')
        <div class="modal fade rr-scope" id="record-subcontract-advance" tabindex="-1" role="dialog" aria-labelledby="rr-record-advance-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.subcontractors.advances.store', $subcontract->id) }}">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-record-advance-title">@lang('construction::lang.record_subcontract_advance')</h2>
                                <p class="rr-modal__desc"><span class="rr-code">{{ $subcontract->number }}</span> · {{ $subcontractorName }}</p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-form-grid">
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-advance-date">@lang('construction::lang.advance_date'){!! $req !!}</label>
                                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-advance-date" name="advance_date" value="{{ old('advance_date', @format_date(now())) }}" class="rr-control__input ct-date-picker" readonly required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-advance-amount">@lang('construction::lang.amount'){!! $req !!}</label>
                                        <div class="rr-control"><input id="ct-advance-amount" name="amount" value="{{ old('amount') }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-advance-method">@lang('construction::lang.payment_method'){!! $req !!}</label>
                                        <div class="rr-control">
                                            <select id="ct-advance-method" name="method" class="rr-control__input rr-control__select" required>
                                                @foreach($paymentTypes as $key => $label)<option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>@endforeach
                                            </select>
                                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                        </div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-advance-account">@lang('construction::lang.cash_bank_account')</label>
                                        <select id="ct-advance-account" name="account_id" class="form-control select2">
                                            <option value="">@lang('construction::lang.select_account')</option>
                                            @foreach($paymentAccounts as $id => $name)<option value="{{ $id }}" @selected((string) old('account_id') === (string) $id)>{{ $name }}</option>@endforeach
                                        </select>
                                    </div>
                                    @if($accountingSettings)
                                        <div class="rr-field rr-field--full">
                                            <label class="rr-field__label" for="ct-advance-accounting-account">@lang('construction::lang.accounting_cash_bank_account'){!! $req !!}</label>
                                            <select id="ct-advance-accounting-account" name="accounting_account_id" class="form-control select2" required>
                                                <option value="">@lang('construction::lang.select_account')</option>
                                                @foreach($accountingPaymentAccounts as $id => $name)<option value="{{ $id }}" @selected((string) old('accounting_account_id') === (string) $id)>{{ $name }}</option>@endforeach
                                            </select>
                                        </div>
                                    @endif
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="ct-advance-reference">@lang('construction::lang.payment_reference')</label>
                                        <div class="rr-control"><input id="ct-advance-reference" name="reference_no" value="{{ old('reference_no') }}" class="rr-control__input"></div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="ct-advance-notes">@lang('construction::lang.notes')</label>
                                        <div class="rr-control rr-control--area"><textarea id="ct-advance-notes" name="notes" class="rr-control__input rr-control__input--short" rows="3">{{ old('notes') }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_advance')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @can('construction.cancel')
        <div class="modal fade rr-scope" id="cancel-subcontract-advance" tabindex="-1" role="dialog" aria-labelledby="rr-cancel-advance-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-cancel-advance-title">@lang('construction::lang.cancel_subcontract_advance')</h2>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-alert rr-alert--warning"><div>@lang('construction::lang.cancellation_creates_reversal_notice')</div></div>
                            <div class="rr-form-wrap">
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="ct-cancel-advance-reason">@lang('construction::lang.cancellation_reason'){!! $req !!}</label>
                                    <div class="rr-control rr-control--area"><textarea id="ct-cancel-advance-reason" name="cancellation_reason" class="rr-control__input rr-control__input--short" rows="3" required minlength="5"></textarea></div>
                                </div>
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="ct-cancel-advance-password">@lang('construction::lang.manager_password'){!! $req !!}</label>
                                    <div class="rr-control"><input type="password" id="ct-cancel-advance-password" name="current_password" class="rr-control__input" required autocomplete="current-password"></div>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--danger" type="submit">@lang('construction::lang.confirm_cancellation')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @can('construction.subcontract.manage')
        <div class="modal fade rr-scope" id="create-subcontract-certificate" tabindex="-1" role="dialog" aria-labelledby="rr-create-subcert-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.subcontractors.certificates.store', $subcontract->id) }}">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-create-subcert-title">@lang('construction::lang.new_subcontract_certificate')</h2>
                                <p class="rr-modal__desc">@lang('construction::lang.certificate_stage_two_hint')</p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-form-grid">
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-subcert-new-date">@lang('construction::lang.certificate_date'){!! $req !!}</label>
                                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcert-new-date" name="certificate_date" value="{{ @format_date(now()) }}" class="rr-control__input ct-date-picker" readonly required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-subcert-new-retention-due">@lang('construction::lang.retention_due_date')</label>
                                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcert-new-retention-due" name="retention_due_date" class="rr-control__input ct-date-picker" readonly></div>
                                        <p class="rr-field__hint">@lang('construction::lang.retention_due_date_hint')</p>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-subcert-new-from">@lang('construction::lang.period_from')</label>
                                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcert-new-from" name="period_from" class="rr-control__input ct-date-picker" readonly></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-subcert-new-to">@lang('construction::lang.period_to')</label>
                                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcert-new-to" name="period_to" class="rr-control__input ct-date-picker" readonly></div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="ct-subcert-new-notes">@lang('construction::lang.notes')</label>
                                        <div class="rr-control rr-control--area"><textarea id="ct-subcert-new-notes" name="notes" class="rr-control__input rr-control__input--short" rows="3"></textarea></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.create_certificate_draft')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endif
@endsection

@push('ct_quick_js')
<script>
$(function () {
    // نوع الحساب: المقطوعية كميتها 1، والنسبة سعرها إجمالي بند المشروع
    function sync($type) {
        var $form = $type.closest('form'), type = $type.val();
        var $qty = $form.find('.ct-subcontract-qty input'), $rate = $form.find('.ct-subcontract-rate input');
        var $boq = $form.find('.ct-subcontract-boq option:selected');
        $qty.prop('readonly', false);
        $rate.prop('readonly', false);
        if (type === 'lump_sum') { $qty.val('1').prop('readonly', true); }
        if (type === 'percentage') { $rate.val($boq.data('total') || 0).prop('readonly', true); }
    }
    $(document).on('change', '.ct-subcontract-type, .ct-subcontract-boq', function () { sync($(this).closest('form').find('.ct-subcontract-type')); });
    $('.ct-subcontract-type').each(function () { sync($(this)); });
    $(document).on('click', '.ct-cancel-advance', function () { $('#cancel-subcontract-advance form').attr('action', $(this).data('action')); });
});
</script>
@endpush
@if($errors->hasAny(['advance_date', 'amount', 'method', 'account_id', 'accounting_account_id', 'reference_no']))
    @push('ct_quick_js')<script>$(function(){$('#record-subcontract-advance').modal('show')})</script>@endpush
@endif

@extends('construction::layouts.module')

@section('title', __('construction::lang.tab_contracts'))

@section('module_content')
<section class="content">
    <div class="ct-section-heading">
        <div>
            <span>@lang('construction::lang.contract_control')</span>
            <h2>@lang('construction::lang.contracts_and_terms')</h2>
            <p>@lang('construction::lang.contracts_tab_description')</p>
        </div>
    </div>

    @if($contractSummary)
    <div class="ct-contract-summary">
        <div>
            <small>@lang('construction::lang.total_contract_value')</small>
            <strong>@include('construction::partials.money', ['value' => $contractSummary->total_value])</strong>
        </div>
        <div>
            <small>@lang('construction::lang.total_advance_payments')</small>
            <strong>@include('construction::partials.money', ['value' => $contractSummary->advance_value])</strong>
        </div>
        <div>
            <small>@lang('construction::lang.performance_bonds')</small>
            <strong>@include('construction::partials.money', ['value' => $contractSummary->bond_value])</strong>
        </div>
    </div>
    @endif

    <article class="ct-panel ct-contract-table">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>@lang('construction::lang.project')</th>
                        <th>@lang('construction::lang.contract_number')</th>
                        <th>@lang('construction::lang.contract_type')</th>
                        <th>@lang('construction::lang.original_value')</th>
                        <th>@lang('construction::lang.retention_percent')</th>
                        <th>@lang('construction::lang.status')</th>
                        <th class="ct-contract-actions-heading">@lang('construction::lang.actions')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contracts as $contract)
                    <tr>
                        <td>
                            <a href="{{ route('construction.projects.show', $contract->project_id) }}">{{ $contract->project?->name }}</a>
                            <small>{{ $contract->project?->code }}</small>
                        </td>
                        <td>
                            @can('construction.contract.view')
                                <a href="{{ route('construction.projects.contracts.show', [$contract->project_id, $contract->id]) }}">{{ $contract->contract_number ?: '—' }}</a>
                            @else
                                {{ $contract->contract_number ?: '—' }}
                            @endcan
                        </td>
                        <td>@lang('construction::lang.' . $contract->contract_type)</td>
                        <td><b>@include('construction::partials.money', ['value' => $contract->original_value])</b></td>
                        <td>{{ @num_format((float) $contract->retention_percent) }}%</td>
                        <td><span class="ct-status ct-status--{{ $contract->status }}">@lang('construction::lang.' . $contract->status)</span></td>
                        <td class="ct-contract-actions-cell">
                            <div class="ct-contract-row-actions">
                                <button type="button" class="ct-contract-menu-toggle" aria-label="@lang('construction::lang.actions')" aria-haspopup="true" aria-expanded="false" aria-controls="ct-contract-menu-{{ $contract->id }}">
                                    <i class="fa fa-ellipsis-v"></i>
                                    <span>@lang('construction::lang.actions')</span>
                                    <i class="fa fa-chevron-down ct-caret-icon"></i>
                                </button>
                                <div class="ct-contract-menu" id="ct-contract-menu-{{ $contract->id }}" role="menu">
                                    @can('construction.contract.view')
                                        <a role="menuitem" href="{{ route('construction.projects.contracts.show', [$contract->project_id, $contract->id]) }}"><i class="fa fa-folder-open"></i> @lang('construction::lang.view_contract')</a>
                                    @endcan
                                    <a role="menuitem" href="{{ route('construction.projects.contracts.preview', [$contract->project_id, $contract->id]) }}" target="_blank" rel="noopener"><i class="fa fa-eye"></i> @lang('construction::lang.print_preview')</a>
                                    <a role="menuitem" href="{{ route('construction.projects.contracts.print', [$contract->project_id, $contract->id]) }}" target="_blank" rel="noopener"><i class="fa fa-print"></i> @lang('construction::lang.print')</a>
                                    <a role="menuitem" href="{{ route('construction.projects.contracts.pdf', [$contract->project_id, $contract->id]) }}"><i class="fa fa-file-pdf"></i> PDF</a>

                                    @if($contract->status === 'draft')
                                        @can('construction.contract.manage')
                                            <a role="menuitem" href="{{ route('construction.projects.contracts.edit', [$contract->project_id, $contract->id]) }}"><i class="fa fa-edit"></i> @lang('construction::lang.edit')</a>
                                        @endcan
                                        @can('construction.contract.approve')
                                            <form method="POST" action="{{ route('construction.projects.contracts.activate', [$contract->project_id, $contract->id]) }}" onsubmit="return confirm('{{ __('construction::lang.activate_contract_confirmation') }}')" style="margin:0;">
                                                @csrf
                                                <button type="submit" role="menuitem" class="ct-contract-menu-approve"><i class="fa fa-check-circle text-success"></i> @lang('construction::lang.next_approve_contract')</button>
                                            </form>
                                        @endcan
                                    @endif

                                    @if($contract->status === 'active')
                                        @can('construction.contract.manage')
                                            <a role="menuitem" href="{{ route('construction.projects.contracts.adjustments.index', [$contract->project_id, $contract->id]) }}"><i class="fa fa-plus-circle text-primary"></i> @lang('construction::lang.contract_adjustments_short')</a>
                                        @endcan
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="ct-empty">
                                <p>@lang('construction::lang.no_contracts')</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($contracts, 'links'))
            <div style="padding: 14px 16px;">
                {{ $contracts->links() }}
            </div>
        @endif
    </article>
</section>
@endsection

@section('module_javascript')
    <script src="{{ asset('modules/construction/js/dashboard.js?v=' . config('construction.module_version')) }}"></script>
@endsection

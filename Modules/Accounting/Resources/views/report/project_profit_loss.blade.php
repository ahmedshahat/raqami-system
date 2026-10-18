@extends('layouts.app')

@section('title', __('accounting::lang.project_profit_loss'))

@section('content')
@include('accounting::layouts.nav')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('accounting::lang.project_profit_loss')</h1>
    <p>@lang('accounting::lang.project_profit_loss_description')</p>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <form method="GET" action="{{ route('accounting.projectProfitLoss') }}">
                <div class="row">
                    <div class="col-md-5 form-group">
                        <label>@lang('accounting::lang.project_cost_center') *</label>
                        <select name="construction_project_id" class="form-control select2" required>
                            <option value="">@lang('accounting::lang.select_project')</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected((int) $project_id === $project->id)>{{ $project->code }} — {{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group"><label>@lang('accounting::lang.from_date')</label><input type="date" name="start_date" value="{{ $start_date }}" class="form-control"></div>
                    <div class="col-md-3 form-group"><label>@lang('accounting::lang.to_date')</label><input type="date" name="end_date" value="{{ $end_date }}" class="form-control"></div>
                    <div class="col-md-1 form-group"><label>&nbsp;</label><button class="btn btn-primary form-control"><i class="fa fa-search"></i></button></div>
                </div>
            </form>
        </div>
    </div>

    @if($project_id)
        <div class="row">
            <div class="col-md-4"><div class="small-box bg-green"><div class="inner"><h3>@format_currency($income_total)</h3><p>@lang('accounting::lang.project_income')</p></div></div></div>
            <div class="col-md-4"><div class="small-box bg-red"><div class="inner"><h3>@format_currency($expense_total)</h3><p>@lang('accounting::lang.project_expenses')</p></div></div></div>
            <div class="col-md-4"><div class="small-box {{ $net_profit >= 0 ? 'bg-blue' : 'bg-orange' }}"><div class="inner"><h3>@format_currency($net_profit)</h3><p>@lang('accounting::lang.project_net_profit')</p></div></div></div>
        </div>
        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title">@lang('accounting::lang.project_account_movements')</h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>@lang('accounting::lang.account')</th><th>@lang('accounting::lang.account_type')</th><th>@lang('accounting::lang.debit')</th><th>@lang('accounting::lang.credit')</th><th>@lang('accounting::lang.net_amount')</th></tr></thead>
                    <tbody>
                        @forelse($accounts as $account)
                            @php($net = $account->account_primary_type === 'income' ? $account->credit_total - $account->debit_total : $account->debit_total - $account->credit_total)
                            <tr><td>{{ $account->name }}</td><td>@lang('accounting::lang.'.$account->account_primary_type)</td><td>@format_currency($account->debit_total)</td><td>@format_currency($account->credit_total)</td><td><strong>@format_currency($net)</strong></td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center">@lang('accounting::lang.no_project_accounting_movements')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
@stop

<?php

namespace Modules\Accounting\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Accounting\Entities\AccountingAccTransMapping;
use Modules\Accounting\Utils\AccountingUtil;
use Yajra\DataTables\Facades\DataTables;

class VoucherController extends Controller
{
    protected $util;

    protected $moduleUtil;

    protected $accountingUtil;

    public function __construct(Util $util, ModuleUtil $moduleUtil, AccountingUtil $accountingUtil)
    {
        $this->util = $util;
        $this->moduleUtil = $moduleUtil;
        $this->accountingUtil = $accountingUtil;
    }

    public function index(Request $request)
    {
        $business_id = $this->businessId();
        $this->authorizeAccounting('accounting.view_journal', $business_id);

        if ($request->ajax()) {
            $vouchers = AccountingAccTransMapping::query()
                ->where('accounting_acc_trans_mappings.business_id', $business_id)
                ->whereIn('accounting_acc_trans_mappings.type', ['receipt_voucher', 'payment_voucher'])
                ->join('users as u', 'accounting_acc_trans_mappings.created_by', '=', 'u.id')
                ->leftJoin('business_locations as bl', 'accounting_acc_trans_mappings.business_location_id', '=', 'bl.id')
                ->join('accounting_accounts_transactions as vat', function ($join) {
                    $join->on('vat.acc_trans_mapping_id', '=', 'accounting_acc_trans_mappings.id')
                        ->where('vat.map_type', '=', 'cash_account')
                        ->whereIn('vat.sub_type', ['receipt_voucher', 'payment_voucher']);
                })
                ->join('accounting_accounts as cash_account', 'vat.accounting_account_id', '=', 'cash_account.id')
                ->select([
                    'accounting_acc_trans_mappings.id',
                    'accounting_acc_trans_mappings.type',
                    'accounting_acc_trans_mappings.ref_no',
                    'accounting_acc_trans_mappings.operation_date',
                    'accounting_acc_trans_mappings.party_name',
                    'accounting_acc_trans_mappings.payment_method',
                    'accounting_acc_trans_mappings.status',
                    'accounting_acc_trans_mappings.note',
                    'vat.amount',
                    'cash_account.name as cash_account_name',
                    'bl.name as location_name',
                    DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by"),
                ]);

            if ($request->filled('voucher_type')) {
                $vouchers->where('accounting_acc_trans_mappings.type', $request->voucher_type);
            }
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $vouchers->whereDate('accounting_acc_trans_mappings.operation_date', '>=', $request->start_date)
                    ->whereDate('accounting_acc_trans_mappings.operation_date', '<=', $request->end_date);
            }

            return DataTables::of($vouchers)
                ->addColumn('action', function ($row) {
                    $showUrl = action([self::class, 'show'], [$row->id]);
                    $printUrl = action([self::class, 'print'], [$row->id]);
                    $html = '<div class="btn-group voucher-action-group"><button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info dropdown-toggle" data-toggle="dropdown">'.__('messages.actions').' <span class="caret"></span></button><ul class="dropdown-menu dropdown-menu-right">';
                    $html .= '<li><a href="#" class="btn-modal" data-href="'.$showUrl.'" data-container=".view_modal"><i class="fas fa-eye"></i> '.__('messages.view').'</a></li>';
                    $html .= '<li><a href="'.$printUrl.'" target="_blank"><i class="fas fa-print"></i> '.__('accounting::lang.print_voucher').'</a></li>';
                    if ($row->status === 'active' && auth()->user()->can('accounting.edit_journal')) {
                        $html .= '<li><a href="'.action([self::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                    }
                    if ($row->status === 'active' && auth()->user()->can('accounting.delete_journal')) {
                        $html .= '<li><a href="#" class="cancel-voucher" data-href="'.action([self::class, 'destroy'], [$row->id]).'"><i class="fas fa-ban"></i> '.__('accounting::lang.cancel_voucher').'</a></li>';
                    }
                    return $html.'</ul></div>';
                })
                ->editColumn('type', function ($row) {
                    return __('accounting::lang.'.$row->type);
                })
                ->editColumn('status', function ($row) {
                    $class = $row->status === 'active' ? 'label-success' : 'label-danger';
                    return '<span class="label '.$class.'">'.__('accounting::lang.voucher_'.$row->status).'</span>';
                })
                ->editColumn('payment_method', function ($row) {
                    return __('accounting::lang.payment_method_'.$row->payment_method);
                })
                ->editColumn('amount', function ($row) {
                    return $this->util->num_f($row->amount, true);
                })
                ->editColumn('operation_date', function ($row) {
                    return $this->util->format_date($row->operation_date, true);
                })
                ->rawColumns(['action', 'status'])
                ->make(true);
        }

        return view('accounting::voucher.index');
    }

    public function create(Request $request)
    {
        $business_id = $this->businessId();
        $this->authorizeAccounting('accounting.add_journal', $business_id);
        $voucher_type = $this->normalizeType($request->get('type', 'receipt_voucher'));

        return view('accounting::voucher.create', $this->formData($business_id, $voucher_type));
    }

    public function store(Request $request)
    {
        $business_id = $this->businessId();
        $this->authorizeAccounting('accounting.add_journal', $business_id);
        $voucher_type = $this->normalizeType($request->voucher_type);
        $data = $this->validateVoucher($request, $business_id, $voucher_type);

        DB::beginTransaction();
        try {
            $ref_no = $data['ref_no'] ?? null;
            if (empty($ref_no)) {
                $referenceType = $voucher_type === 'receipt_voucher' ? 'accounting_receipt_voucher' : 'accounting_payment_voucher';
                $prefix = $voucher_type === 'receipt_voucher' ? 'RCV-' : 'PAY-';
                $count = $this->util->setAndGetReferenceCount($referenceType, $business_id);
                $ref_no = $this->util->generateReferenceNumber($referenceType, $count, $business_id, $prefix);
            }

            $voucher = $this->saveVoucher(new AccountingAccTransMapping(), $data, $voucher_type, $ref_no, $business_id);
            DB::commit();

            return redirect()->route('vouchers.index')->with('status', [
                'success' => 1,
                'msg' => __('accounting::lang.voucher_added_successfully'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error($e);
            return back()->withInput()->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    public function show($id)
    {
        $business_id = $this->businessId();
        $this->authorizeAccounting('accounting.view_journal', $business_id);
        [$voucher, $cashLine, $offsetLine] = $this->voucherDetails($id, $business_id);

        return view('accounting::voucher.show', compact('voucher', 'cashLine', 'offsetLine'));
    }

    public function edit($id)
    {
        $business_id = $this->businessId();
        $this->authorizeAccounting('accounting.edit_journal', $business_id);
        [$voucher, $cashLine, $offsetLine] = $this->voucherDetails($id, $business_id);
        abort_if($voucher->status !== 'active', 422, __('accounting::lang.cancelled_voucher_cannot_be_edited'));

        return view('accounting::voucher.edit', array_merge(
            $this->formData($business_id, $voucher->type),
            compact('voucher', 'cashLine', 'offsetLine')
        ));
    }

    public function update(Request $request, $id)
    {
        $business_id = $this->businessId();
        $this->authorizeAccounting('accounting.edit_journal', $business_id);
        $voucher = $this->findVoucher($id, $business_id);
        abort_if($voucher->status !== 'active', 422, __('accounting::lang.cancelled_voucher_cannot_be_edited'));
        $data = $this->validateVoucher($request, $business_id, $voucher->type, $voucher->id);

        DB::beginTransaction();
        try {
            $this->saveVoucher($voucher, $data, $voucher->type, $data['ref_no'], $business_id);
            DB::commit();
            return redirect()->route('vouchers.index')->with('status', [
                'success' => 1,
                'msg' => __('accounting::lang.voucher_updated_successfully'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error($e);
            return back()->withInput()->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    public function destroy($id)
    {
        $business_id = $this->businessId();
        $this->authorizeAccounting('accounting.delete_journal', $business_id);

        DB::beginTransaction();
        try {
            $voucher = $this->findVoucher($id, $business_id);
            if ($voucher->status !== 'active') {
                return ['success' => 0, 'msg' => __('accounting::lang.voucher_already_cancelled')];
            }

            $lines = AccountingAccountsTransaction::where('acc_trans_mapping_id', $voucher->id)
                ->where('sub_type', $voucher->type)
                ->whereIn('map_type', ['cash_account', 'offset_account'])
                ->get();
            foreach ($lines as $line) {
                AccountingAccountsTransaction::create([
                    'acc_trans_mapping_id' => $voucher->id,
                    'accounting_account_id' => $line->accounting_account_id,
                    'amount' => $line->amount,
                    'type' => $line->type === 'debit' ? 'credit' : 'debit',
                    'sub_type' => 'voucher_reversal',
                    'map_type' => $line->map_type.'_reversal',
                    'created_by' => request()->session()->get('user.id'),
                    'operation_date' => now(),
                    'note' => __('accounting::lang.voucher_cancellation').' '.$voucher->ref_no,
                ]);
            }
            $voucher->status = 'cancelled';
            $voucher->cancelled_at = now();
            $voucher->cancelled_by = request()->session()->get('user.id');
            $voucher->save();
            DB::commit();

            return ['success' => 1, 'msg' => __('accounting::lang.voucher_cancelled_successfully')];
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error($e);
            return ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }
    }

    public function print($id)
    {
        $business_id = $this->businessId();
        $this->authorizeAccounting('accounting.view_journal', $business_id);
        [$voucher, $cashLine, $offsetLine] = $this->voucherDetails($id, $business_id);
        $business = Business::findOrFail($business_id);
        $location = $voucher->business_location_id
            ? BusinessLocation::where('business_id', $business_id)->find($voucher->business_location_id)
            : null;
        $amountInWords = $this->util->numToWord((float) $cashLine->amount, 'ar');
        $createdBy = User::find($voucher->created_by);
        $cancelledBy = $voucher->cancelled_by ? User::find($voucher->cancelled_by) : null;

        return view('accounting::voucher.print', compact(
            'voucher', 'cashLine', 'offsetLine', 'business', 'location', 'amountInWords', 'createdBy', 'cancelledBy'
        ));
    }

    private function saveVoucher($voucher, array $data, $voucher_type, $ref_no, $business_id)
    {
        $date = $this->util->uf_date($data['operation_date'], true);
        $amount = $this->util->num_uf($data['amount']);
        $user_id = request()->session()->get('user.id');

        $voucher->business_id = $business_id;
        $voucher->business_location_id = $data['business_location_id'];
        $voucher->ref_no = $ref_no;
        $voucher->type = $voucher_type;
        $voucher->created_by = $voucher->exists ? $voucher->created_by : $user_id;
        $voucher->operation_date = $date;
        $voucher->party_name = $data['party_name'];
        $voucher->payment_method = $data['payment_method'];
        $voucher->payment_reference = $data['payment_reference'] ?? null;
        $voucher->note = $data['note'];
        $voucher->status = 'active';
        $voucher->save();

        $types = $this->accountingUtil->getVoucherEntryTypes($voucher_type);
        foreach (['cash_account', 'offset_account'] as $mapType) {
            AccountingAccountsTransaction::updateOrCreate(
                [
                    'acc_trans_mapping_id' => $voucher->id,
                    'map_type' => $mapType,
                    'sub_type' => $voucher_type,
                ],
                [
                    'accounting_account_id' => $data[$mapType.'_id'],
                    'amount' => $amount,
                    'type' => $types[$mapType],
                    'created_by' => $user_id,
                    'operation_date' => $date,
                    'note' => $data['note'],
                ]
            );
        }

        return $voucher;
    }

    private function validateVoucher(Request $request, $business_id, $voucher_type, $voucher_id = null)
    {
        $data = $request->validate([
            'business_location_id' => ['required', Rule::exists('business_locations', 'id')->where('business_id', $business_id)],
            'ref_no' => ['nullable', 'string', 'max:100'],
            'operation_date' => ['required', 'string'],
            'party_name' => ['required', 'string', 'max:191'],
            'payment_method' => ['required', Rule::in(array_keys($this->paymentMethods()))],
            'payment_reference' => ['nullable', 'string', 'max:191'],
            'cash_account_id' => ['required', Rule::exists('accounting_accounts', 'id')->where('business_id', $business_id)->where('status', 'active')],
            'offset_account_id' => ['required', 'different:cash_account_id', Rule::exists('accounting_accounts', 'id')->where('business_id', $business_id)->where('status', 'active')],
            'amount' => ['required'],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        if ((float) $this->util->num_uf($data['amount']) <= 0) {
            abort(422, __('accounting::lang.amount_must_be_greater_than_zero'));
        }
        if (! empty($data['ref_no'])) {
            $duplicate = AccountingAccTransMapping::where('business_id', $business_id)
                ->where('type', $voucher_type)
                ->where('ref_no', $data['ref_no'])
                ->when($voucher_id, function ($query) use ($voucher_id) {
                    $query->where('id', '!=', $voucher_id);
                })->exists();
            abort_if($duplicate, 422, __('accounting::lang.duplicate_voucher_number'));
        }

        return $data;
    }

    private function formData($business_id, $voucher_type)
    {
        return [
            'voucher_type' => $voucher_type,
            'locations' => BusinessLocation::forDropdown($business_id, false, false, true, true),
            'paymentMethods' => $this->paymentMethods(),
        ];
    }

    private function paymentMethods()
    {
        return [
            'cash' => __('accounting::lang.payment_method_cash'),
            'bank_transfer' => __('accounting::lang.payment_method_bank_transfer'),
            'cheque' => __('accounting::lang.payment_method_cheque'),
            'card' => __('accounting::lang.payment_method_card'),
            'other' => __('accounting::lang.payment_method_other'),
        ];
    }

    private function voucherDetails($id, $business_id)
    {
        $voucher = $this->findVoucher($id, $business_id);
        $lines = AccountingAccountsTransaction::with('account')
            ->where('acc_trans_mapping_id', $voucher->id)
            ->where('sub_type', $voucher->type)
            ->whereIn('map_type', ['cash_account', 'offset_account'])
            ->get()->keyBy('map_type');

        abort_if(! isset($lines['cash_account'], $lines['offset_account']), 404);
        return [$voucher, $lines['cash_account'], $lines['offset_account']];
    }

    private function findVoucher($id, $business_id)
    {
        return AccountingAccTransMapping::where('business_id', $business_id)
            ->whereIn('type', ['receipt_voucher', 'payment_voucher'])
            ->findOrFail($id);
    }

    private function normalizeType($type)
    {
        abort_unless(in_array($type, ['receipt_voucher', 'payment_voucher'], true), 404);
        return $type;
    }

    private function businessId()
    {
        return request()->session()->get('user.business_id');
    }

    private function authorizeAccounting($permission, $business_id)
    {
        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! auth()->user()->can($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }
}

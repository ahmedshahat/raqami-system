<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Cheque;
use App\Contact;
use App\Utils\ChequeUtil;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Datatables;
use DB;
use Illuminate\Http\Request;

class ChequeController extends Controller
{
    protected $chequeUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    public function __construct(ChequeUtil $chequeUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        $this->chequeUtil = $chequeUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Cheque types the current user may see.
     */
    private function allowedTypes()
    {
        $types = [];
        if (auth()->user()->can('sell.payments')) {
            $types[] = Cheque::TYPE_RECEIVABLE;
        }
        if (auth()->user()->can('purchase.payments')) {
            $types[] = Cheque::TYPE_PAYABLE;
        }

        return $types;
    }

    private function authorizeType($type)
    {
        if (! in_array($type, $this->allowedTypes())) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Cheques report: customer cheques, supplier cheques and cheques cash flow.
     */
    public function index(Request $request)
    {
        $types = $this->allowedTypes();
        if (empty($types)) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $summaries = [];
        foreach ($types as $type) {
            $summaries[$type] = $this->chequeUtil->summary($business_id, $type);
        }

        $cash_flow = $this->chequeUtil->cashFlow($business_id, 12, in_array(Cheque::TYPE_RECEIVABLE, $types), in_array(Cheque::TYPE_PAYABLE, $types));

        $customers = in_array(Cheque::TYPE_RECEIVABLE, $types) ? Contact::customersDropdown($business_id, false) : [];
        $suppliers = in_array(Cheque::TYPE_PAYABLE, $types) ? Contact::suppliersDropdown($business_id, false) : [];
        $business_locations = BusinessLocation::forDropdown($business_id);

        $active_tab = in_array($request->get('tab'), array_merge($types, ['cash_flow'])) ? $request->get('tab') : $types[0];

        return view('cheque.index')->with(compact('types', 'summaries', 'cash_flow', 'customers', 'suppliers', 'business_locations', 'active_tab'));
    }

    /**
     * Cheques of one type with the report filters applied (shared by the table and the print page).
     */
    private function filteredQuery(Request $request, $business_id, $type)
    {
        $query = $this->chequeUtil->query($business_id, $type)
            ->leftJoin('contacts as c', 'cheques.contact_id', '=', 'c.id')
            ->leftJoin('transactions as t', 'cheques.transaction_id', '=', 't.id')
            ->leftJoin('business_locations as bl', 'cheques.location_id', '=', 'bl.id')
            ->select(
                'cheques.id',
                'cheques.type',
                'cheques.cheque_number',
                'cheques.bank_name',
                'cheques.amount',
                'cheques.received_on',
                'cheques.due_date',
                'cheques.status',
                'cheques.status_changed_on',
                'cheques.status_note',
                'cheques.note',
                'cheques.transaction_id',
                'c.name as contact_name',
                'c.supplier_business_name',
                't.invoice_no',
                't.ref_no',
                'bl.name as location_name'
            );

        $status = $request->get('status', Cheque::STATUS_PENDING);
        if (! empty($status) && $status != 'all') {
            $query->where('cheques.status', $status);
        }
        if (! empty($request->get('contact_id'))) {
            $query->where('cheques.contact_id', $request->get('contact_id'));
        }
        if (! empty($request->get('location_id'))) {
            $query->where('cheques.location_id', $request->get('location_id'));
        }
        if (! empty($request->get('bank_name'))) {
            $query->where('cheques.bank_name', 'like', '%'.$request->get('bank_name').'%');
        }
        if (! empty($request->get('start_date')) && ! empty($request->get('end_date'))) {
            $query->whereBetween('cheques.due_date', [$request->get('start_date'), $request->get('end_date')]);
        }
        if ($request->get('due_bucket') == 'overdue') {
            $query->where('cheques.due_date', '<', Carbon::today()->format('Y-m-d'));
        }

        return $query;
    }

    /**
     * DataTable rows for one cheque type.
     */
    public function data(Request $request, $type)
    {
        $this->authorizeType($type);
        $business_id = $request->session()->get('user.business_id');

        $query = $this->filteredQuery($request, $business_id, $type);

        $today = Carbon::today();
        $statuses = $this->statusLabels($type);

        return Datatables::of($query)
            ->editColumn('cheque_number', function ($row) {
                return '<span class="rr-code">'.e($row->cheque_number).'</span>';
            })
            ->editColumn('contact_name', function ($row) {
                $html = '<span class="rr-entity__name">'.e($row->contact_name).'</span>';
                if (! empty($row->supplier_business_name)) {
                    $html .= '<div class="rr-td__sub">'.e($row->supplier_business_name).'</div>';
                }

                return $html;
            })
            ->editColumn('bank_name', function ($row) {
                return ! empty($row->bank_name) ? e($row->bank_name) : '<span class="rr-muted">—</span>';
            })
            ->addColumn('invoice', function ($row) {
                if (empty($row->transaction_id)) {
                    return '<span class="rr-muted">'.__('cheque.on_account').'</span>';
                }
                $controller = $row->type == Cheque::TYPE_RECEIVABLE ? SellController::class : PurchaseController::class;
                $label = $row->type == Cheque::TYPE_RECEIVABLE ? $row->invoice_no : $row->ref_no;

                return '<a href="#" data-href="'.action([$controller, 'show'], [$row->transaction_id]).'" class="btn-modal rr-code-link" data-container=".view_modal"><span class="rr-code">'.e($label).'</span></a>';
            })
            ->editColumn('amount', function ($row) {
                return '<span class="rr-num cheque-amount" data-orig-value="'.$row->amount.'">'.$this->transactionUtil->num_f($row->amount).'</span>';
            })
            ->editColumn('received_on', function ($row) {
                return ! empty($row->received_on) ? '<span class="rr-num">'.$this->transactionUtil->format_date($row->received_on).'</span>' : '';
            })
            ->editColumn('due_date', function ($row) {
                return '<span class="rr-num">'.$this->transactionUtil->format_date($row->due_date).'</span>';
            })
            ->addColumn('days_left', function ($row) use ($today) {
                if ($row->status != Cheque::STATUS_PENDING) {
                    return '<span class="rr-muted">—</span>';
                }
                $days = $today->diffInDays(Carbon::parse($row->due_date)->startOfDay(), false);
                if ($days < 0) {
                    return '<span class="rr-badge rr-badge--danger">'.__('cheque.days_overdue', ['days' => abs($days)]).'</span>';
                }
                if ($days == 0) {
                    return '<span class="rr-badge rr-badge--warning">'.__('cheque.due_today').'</span>';
                }

                return '<span class="rr-badge '.($days <= 7 ? 'rr-badge--warning' : '').'">'.__('cheque.days_remaining', ['days' => $days]).'</span>';
            })
            ->editColumn('status', function ($row) use ($statuses) {
                $tones = [
                    Cheque::STATUS_PENDING => 'rr-badge--info',
                    Cheque::STATUS_COLLECTED => 'rr-badge--success',
                    Cheque::STATUS_BOUNCED => 'rr-badge--danger',
                    Cheque::STATUS_CANCELLED => '',
                ];
                $html = '<span class="rr-badge '.($tones[$row->status] ?? '').'"><span class="rr-badge__dot"></span>'.($statuses[$row->status] ?? $row->status).'</span>';
                if (! empty($row->status_changed_on) && $row->status != Cheque::STATUS_PENDING) {
                    $html .= '<div class="rr-td__sub"><span class="rr-num">'.$this->transactionUtil->format_date($row->status_changed_on).'</span></div>';
                }

                return $html;
            })
            ->addColumn('action', function ($row) {
                $items = '';
                if ($row->status == Cheque::STATUS_PENDING) {
                    $collect_label = $row->type == Cheque::TYPE_RECEIVABLE ? __('cheque.collect') : __('cheque.clear');
                    $items .= '<button type="button" role="menuitem" class="rr-menu__item btn-modal" data-container=".cheque_modal" data-href="'.action([self::class, 'actionForm'], [$row->id, 'collect']).'"><i class="fas fa-check rr-icon"></i>'.$collect_label.'</button>';
                    $items .= '<button type="button" role="menuitem" class="rr-menu__item btn-modal" data-container=".cheque_modal" data-href="'.action([self::class, 'actionForm'], [$row->id, 'cancel']).'"><i class="fas fa-ban rr-icon"></i>'.__('cheque.cancel').'</button>';
                    $items .= '<button type="button" role="menuitem" class="rr-menu__item rr-menu__item--danger btn-modal" data-container=".cheque_modal" data-href="'.action([self::class, 'actionForm'], [$row->id, 'bounce']).'"><i class="fas fa-undo rr-icon"></i>'.__('cheque.bounce').'</button>';
                }
                if (! empty($row->transaction_id)) {
                    $controller = $row->type == Cheque::TYPE_RECEIVABLE ? SellController::class : PurchaseController::class;
                    if (! empty($items)) {
                        $items .= '<div class="rr-menu__sep"></div>';
                    }
                    $items .= '<button type="button" role="menuitem" class="rr-menu__item btn-modal" data-container=".view_modal" data-href="'.action([$controller, 'show'], [$row->transaction_id]).'"><i class="fas fa-eye rr-icon"></i>'.__('cheque.view_invoice').'</button>';
                }
                if (empty($items)) {
                    return '';
                }
                $menu_id = 'cheque-menu-'.$row->id;

                return '<button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-cheque-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="'.$menu_id.'" aria-label="'.e(__('messages.actions')).'"><i class="fas fa-ellipsis-v"></i></button>'
                    .'<div class="rr-menu" id="'.$menu_id.'" role="menu">'.$items.'</div>';
            })
            ->setRowClass(function ($row) use ($today) {
                if ($row->status != Cheque::STATUS_PENDING) {
                    return 'rr-tr';
                }
                $days = $today->diffInDays(Carbon::parse($row->due_date)->startOfDay(), false);

                return 'rr-tr'.($days < 0 ? ' rr-tr--danger' : ($days <= 7 ? ' rr-tr--warning' : ''));
            })
            ->filterColumn('contact_name', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('c.name', 'like', "%{$keyword}%")
                        ->orWhere('c.supplier_business_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('invoice', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('t.invoice_no', 'like', "%{$keyword}%")
                        ->orWhere('t.ref_no', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('invoice', 't.invoice_no $1')
            ->orderColumn('days_left', 'cheques.due_date $1')
            ->rawColumns(['cheque_number', 'contact_name', 'bank_name', 'invoice', 'amount', 'received_on', 'due_date', 'days_left', 'status', 'action'])
            ->make(true);
    }

    /**
     * Printable page for a cheques tab (customers / suppliers with the current filters) or the cash flow.
     */
    public function printReport(Request $request, $type)
    {
        $business_id = $request->session()->get('user.business_id');
        $business = \App\Business::findOrFail($business_id);
        $types = $this->allowedTypes();
        if (empty($types)) {
            abort(403, 'Unauthorized action.');
        }

        $cheques = collect();
        $summary = null;
        $cash_flow = null;
        $filters = [];

        if ($type == 'cash_flow') {
            $cash_flow = $this->chequeUtil->cashFlow($business_id, 12, in_array(Cheque::TYPE_RECEIVABLE, $types), in_array(Cheque::TYPE_PAYABLE, $types));
        } else {
            $this->authorizeType($type);
            $summary = $this->chequeUtil->summary($business_id, $type, $request->get('location_id'));
            $cheques = $this->filteredQuery($request, $business_id, $type)->orderBy('cheques.due_date')->get();

            $statuses = $this->statusLabels($type);
            $status = $request->get('status', Cheque::STATUS_PENDING);
            $filters[__('cheque.status')] = $status == 'all' ? __('cheque.all') : ($statuses[$status] ?? $status);
            if (! empty($request->get('contact_id'))) {
                $contact = Contact::where('business_id', $business_id)->find($request->get('contact_id'));
                $filters[$type == Cheque::TYPE_RECEIVABLE ? __('cheque.customer') : __('cheque.supplier')] = $contact->name ?? '';
            }
            if (! empty($request->get('location_id'))) {
                $filters[__('purchase.business_location')] = BusinessLocation::where('business_id', $business_id)->where('id', $request->get('location_id'))->value('name');
            }
            if (! empty($request->get('bank_name'))) {
                $filters[__('cheque.bank_name')] = $request->get('bank_name');
            }
            if (! empty($request->get('start_date')) && ! empty($request->get('end_date'))) {
                $filters[__('cheque.due_date')] = $this->transactionUtil->format_date($request->get('start_date')).' - '.$this->transactionUtil->format_date($request->get('end_date'));
            }
            if ($request->get('due_bucket') == 'overdue') {
                $filters[__('cheque.due_date')] = __('cheque.overdue');
            }
        }

        $statuses = $type == 'cash_flow' ? [] : $this->statusLabels($type);
        $logo = ! empty($business->logo) ? asset('uploads/business_logos/'.$business->logo) : null;

        return view('cheque.print')->with(compact('type', 'types', 'business', 'logo', 'cheques', 'summary', 'cash_flow', 'filters', 'statuses'));
    }

    /**
     * Modal for collect / bounce / cancel.
     */
    public function actionForm(Request $request, $id, $action)
    {
        if (! in_array($action, ['collect', 'bounce', 'cancel'])) {
            abort(404);
        }
        $business_id = $request->session()->get('user.business_id');
        $cheque = Cheque::where('business_id', $business_id)->with(['contact', 'transaction'])->findOrFail($id);
        $this->authorizeType($cheque->type);

        $accounts = $action == 'collect' ? $this->moduleUtil->accountsDropdown($business_id, true, false, true) : [];

        $due_amount = null;
        if ($action == 'collect' && ! empty($cheque->transaction)) {
            $due_amount = $cheque->transaction->final_total - $this->transactionUtil->getTotalPaid($cheque->transaction_id);
        }

        return view('cheque.action_modal')->with(compact('cheque', 'action', 'accounts', 'due_amount'));
    }

    public function performAction(Request $request, $id, $action)
    {
        if (! in_array($action, ['collect', 'bounce', 'cancel'])) {
            abort(404);
        }
        $business_id = $request->session()->get('user.business_id');
        $cheque = Cheque::where('business_id', $business_id)->findOrFail($id);
        $this->authorizeType($cheque->type);

        try {
            $date = $request->input('action_date');
            $date = ! empty($date) ? Carbon::parse($date)->format('Y-m-d').' '.Carbon::now()->format('H:i:s') : Carbon::now()->toDateTimeString();
            $note = $request->input('status_note');

            DB::beginTransaction();

            if ($action == 'collect') {
                $this->chequeUtil->collect($business_id, $cheque->id, $date, $request->input('account_id'), $note);
                $msg = $cheque->type == Cheque::TYPE_RECEIVABLE ? __('cheque.collected_success') : __('cheque.cleared_success');
            } elseif ($action == 'bounce') {
                $this->chequeUtil->changeStatus($business_id, $cheque->id, Cheque::STATUS_BOUNCED, $date, $note);
                $msg = __('cheque.bounced_success');
            } else {
                $this->chequeUtil->changeStatus($business_id, $cheque->id, Cheque::STATUS_CANCELLED, $date, $note);
                $msg = __('cheque.cancelled_success');
            }

            DB::commit();
            $output = ['success' => true, 'msg' => $msg];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => $e->getMessage() == __('cheque.only_pending') ? $e->getMessage() : __('messages.something_went_wrong')];
        }

        return $output;
    }

    private function statusLabels($type)
    {
        $labels = Cheque::statuses();
        if ($type == Cheque::TYPE_PAYABLE) {
            $labels[Cheque::STATUS_PENDING] = __('cheque.status_pending_payable');
            $labels[Cheque::STATUS_COLLECTED] = __('cheque.status_cleared');
        }

        return $labels;
    }
}

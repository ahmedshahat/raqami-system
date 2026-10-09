<?php

namespace Modules\Construction\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Construction\Entities\ConstructionAccountingSetting;

class AccountingSettingsController extends BaseController
{
    public function index()
    {
        $this->authorizeSettings();

        $settings = ConstructionAccountingSetting::firstOrNew(['business_id' => $this->businessId()]);
        $accounts = AccountingAccount::where('business_id', $this->businessId())
            ->where('status', 'active')
            ->orderBy('account_primary_type')->orderBy('name')->get(['id', 'name', 'account_primary_type']);

        return view('construction::settings.accounting', compact('settings', 'accounts'));
    }

    public function createAccounts()
    {
        $this->authorizeSettings();

        $created = 0;
        $linked = [];
        DB::transaction(function () use (&$created, &$linked) {
            foreach ($this->definitions() as $field => $definition) {
                $account = AccountingAccount::where('business_id', $this->businessId())
                    ->whereIn('name', $definition['aliases'])->first();
                if (! $account) {
                    $account = AccountingAccount::create([
                        'business_id' => $this->businessId(),
                        'name' => $definition['name'],
                        'account_primary_type' => $definition['primary'],
                        'account_sub_type_id' => $definition['sub_type'],
                        'detail_type_id' => $definition['detail_type'],
                        'status' => 'active',
                        'created_by' => auth()->id(),
                    ]);
                    $created++;
                }
                $linked[$field] = $account->id;
            }

            ConstructionAccountingSetting::updateOrCreate(
                ['business_id' => $this->businessId()],
                $linked + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]
            );
        });

        return redirect()->route('construction.settings.accounting.index')->with('status', [
            'success' => 1,
            'msg' => __('construction::lang.construction_accounts_ready', ['created' => $created]),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeSettings();
        $rules = [];
        foreach (ConstructionAccountingSetting::ACCOUNT_FIELDS as $field) {
            $rules[$field] = ['required', 'integer', Rule::exists('accounting_accounts', 'id')
                ->where(fn ($query) => $query->where('business_id', $this->businessId())->where('status', 'active'))];
        }
        $validated = $request->validate($rules);

        ConstructionAccountingSetting::updateOrCreate(
            ['business_id' => $this->businessId()],
            $validated + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]
        );

        return back()->with('status', ['success' => 1, 'msg' => __('construction::lang.accounting_settings_saved')]);
    }

    private function authorizeSettings(): void
    {
        $this->authorizePermission('construction.access');
        abort_unless(
            $this->isAdmin()
                || auth()->user()->can('construction.settings.manage')
                || auth()->user()->can('accounting.manage_accounts'),
            403,
            __('construction::lang.unauthorized')
        );
        abort_unless(
            class_exists(AccountingAccount::class) && Schema::hasTable('accounting_accounts') &&
            (app()->environment('local') || (new ModuleUtil())->hasThePermissionInSubscription($this->businessId(), 'accounting_module')),
            422,
            __('construction::lang.accounting_module_required')
        );
    }

    private function definitions(): array
    {
        return [
            'construction_revenue_account_id' => $this->definition('إيرادات المقاولات', ['إيرادات المقاولات'], 'income', 11, 100),
            'customer_receivable_account_id' => $this->definition('حسابات العملاء', ['حسابات العملاء', 'Accounts Receivable (A/R)'], 'asset', 1, 16),
            'customer_retention_account_id' => $this->definition('محتجزات ضمان لدى العملاء', ['محتجزات ضمان لدى العملاء'], 'asset', 1, 16),
            'customer_advance_account_id' => $this->definition('دفعات مقدمة من العملاء', ['دفعات مقدمة من العملاء'], 'liability', 8, 60),
            'customer_deduction_account_id' => $this->definition('خصومات واستقطاعات لدى العملاء', ['خصومات واستقطاعات لدى العملاء'], 'asset', 1, 16),
            'sales_tax_payable_account_id' => $this->definition('ضريبة قيمة مضافة مستحقة', ['ضريبة قيمة مضافة مستحقة', 'Sales and service tax payable'], 'liability', 8, 60),
            'inventory_account_id' => $this->definition('المخزون', ['المخزون', 'مخزون البضاعة'], 'asset', 2, 21),
            'material_cost_account_id' => $this->definition('تكلفة مواد المشاريع', ['تكلفة مواد المشاريع', 'مواد خام - تكلفة مبيعات'], 'expenses', 13, 114),
            'labor_cost_account_id' => $this->definition('تكلفة عمالة المشاريع', ['تكلفة عمالة المشاريع', 'أجور مباشرة - تكلفة مبيعات'], 'expenses', 13, 114),
            'subcontract_cost_account_id' => $this->definition('تكلفة مقاولي الباطن', ['تكلفة مقاولي الباطن', 'مقاولو الباطن - تكلفة مبيعات'], 'expenses', 13, 114),
            'subcontract_payable_account_id' => $this->definition('مستحقات مقاولي الباطن', ['مستحقات مقاولي الباطن'], 'liability', 6, 58),
            'subcontract_retention_account_id' => $this->definition('محتجزات ضمان مقاولي الباطن', ['محتجزات ضمان مقاولي الباطن'], 'liability', 8, 60),
            'subcontract_deduction_account_id' => $this->definition('خصومات واستقطاعات مقاولي الباطن', ['خصومات واستقطاعات مقاولي الباطن'], 'income', 12, 111),
            'cash_account_id' => $this->definition('الصندوق والنقدية', ['الصندوق والنقدية'], 'asset', 3, 31),
        ];
    }

    private function definition(string $name, array $aliases, string $primary, int $subType, int $detailType): array
    {
        return compact('name', 'aliases', 'primary') + ['sub_type' => $subType, 'detail_type' => $detailType];
    }
}

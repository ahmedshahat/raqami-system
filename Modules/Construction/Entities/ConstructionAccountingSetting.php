<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;

class ConstructionAccountingSetting extends Model
{
    protected $guarded = ['id'];

    public const ACCOUNT_FIELDS = [
        'construction_revenue_account_id',
        'customer_receivable_account_id',
        'customer_retention_account_id',
        'customer_advance_account_id',
        'customer_deduction_account_id',
        'sales_tax_payable_account_id',
        'inventory_account_id',
        'material_cost_account_id',
        'labor_cost_account_id',
        'subcontract_cost_account_id',
        'subcontract_payable_account_id',
        'subcontract_retention_account_id',
        'subcontract_deduction_account_id',
        'cash_account_id',
    ];
}

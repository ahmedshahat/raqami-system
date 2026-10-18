<?php

namespace Modules\Construction\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Entities\AccountingAccTransMapping;

class ConstructionAccountingPosting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function mapping()
    {
        return $this->belongsTo(AccountingAccTransMapping::class, 'accounting_mapping_id');
    }

    protected static function booted(): void
    {
        static::created(function (self $posting) {
            $projectId = $posting->resolveProjectId();
            if (! $projectId) {
                return;
            }

            $posting->mapping()->update(['construction_project_id' => $projectId]);
            \Modules\Accounting\Entities\AccountingAccountsTransaction::where(
                'acc_trans_mapping_id', $posting->accounting_mapping_id
            )->update(['construction_project_id' => $projectId]);
        });
    }

    private function resolveProjectId(): ?int
    {
        $models = [
            'labor_sheet' => ConstructionLaborSheet::class,
            'labor_payment' => ConstructionLaborPayment::class,
            'material_document' => ConstructionMaterialDocument::class,
            'customer_certificate' => ConstructionCustomerCertificate::class,
            'customer_retention_release' => ConstructionCustomerRetentionRelease::class,
            'subcontract_certificate' => ConstructionSubcontractCertificate::class,
            'subcontract_payment' => ConstructionSubcontractPayment::class,
            'subcontract_advance' => ConstructionSubcontractAdvance::class,
            'subcontract_retention_release' => ConstructionSubcontractRetentionRelease::class,
        ];

        if (isset($models[$this->source_type])) {
            return ($models[$this->source_type])::where('business_id', $this->business_id)
                ->whereKey($this->source_id)->value('project_id');
        }

        if ($this->source_type === 'customer_collection') {
            $transactionId = \App\TransactionPayment::where('business_id', $this->business_id)
                ->whereKey($this->source_id)->value('transaction_id');
            return $transactionId
                ? ConstructionCustomerCertificate::where('business_id', $this->business_id)
                    ->where('invoice_transaction_id', $transactionId)->value('project_id')
                : null;
        }

        return null;
    }
}

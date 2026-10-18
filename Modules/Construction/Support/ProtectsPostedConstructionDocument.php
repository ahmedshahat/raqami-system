<?php

namespace Modules\Construction\Support;

use Illuminate\Validation\ValidationException;
use Modules\Construction\Entities\ConstructionAccountingPosting;

trait ProtectsPostedConstructionDocument
{
    protected static function bootProtectsPostedConstructionDocument(): void
    {
        static::deleting(function ($model) {
            $sourceType = $model->constructionAccountingSourceType();
            if (ConstructionAccountingPosting::where('business_id', $model->business_id)
                ->where('source_type', $sourceType)->where('source_id', $model->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'document' => __('construction::lang.posted_document_cannot_be_deleted'),
                ]);
            }
        });
    }

    abstract protected function constructionAccountingSourceType(): string;
}

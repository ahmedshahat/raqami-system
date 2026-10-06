<?php

namespace Modules\Construction\Support;

use Modules\Construction\Entities\ConstructionAuditLog;
use Modules\Construction\Entities\ConstructionProject;

class AuditDescription
{
    public static function for(ConstructionAuditLog $log, ConstructionProject $project): string
    {
        $user = $log->user?->user_full_name ?: ($log->user?->username ?: __('construction::lang.unknown_user'));
        $subject = $log->auditable;
        $values = array_merge($log->old_values ?: [], $log->new_values ?: []);
        $item = $values['code'] ?? $values['description'] ?? $subject?->code ?? $subject?->description ?? __('construction::lang.unspecified_item');

        $replacements = ['user' => $user, 'item' => $item, 'project' => $project->code];

        return match ($log->event) {
            'project_created' => $project->quote
                ? __('construction::lang.audit_description_project_created_from_quote', $replacements + ['quote' => $project->quote->number])
                : __('construction::lang.audit_description_project_created', $replacements),
            'project_item_created' => __('construction::lang.audit_description_project_item_created', $replacements),
            'project_item_updated' => __('construction::lang.audit_description_project_item_updated', $replacements),
            'project_item_deleted' => __('construction::lang.audit_description_project_item_deleted', $replacements),
            'project_items_imported' => __('construction::lang.audit_description_project_items_imported', $replacements + ['count' => $values['rows'] ?? 0]),
            'project_items_approved' => __('construction::lang.audit_description_project_items_approved', $replacements),
            'contract_approved' => __('construction::lang.audit_description_contract_approved', $replacements + ['contract' => $subject?->contract_number ?: __('construction::lang.contract')]),
            'contract_adjustment_created' => __('construction::lang.audit_description_adjustment_created', $replacements),
            'contract_adjustment_updated' => __('construction::lang.audit_description_adjustment_updated', $replacements),
            'contract_adjustment_deleted' => __('construction::lang.audit_description_adjustment_deleted', $replacements),
            'contract_adjustment_approved' => __('construction::lang.audit_description_adjustment_approved', $replacements),
            'measurement_approved' => __('construction::lang.audit_description_measurement_approved', $replacements + ['number' => $subject?->number ?: '—']),
            'certificate_approved' => __('construction::lang.audit_description_certificate_approved', $replacements + ['number' => $subject?->number ?: '—']),
            default => __('construction::lang.audit_description_generic', $replacements + ['operation' => __('construction::lang.audit_'.$log->event)]),
        };
    }
}

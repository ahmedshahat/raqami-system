<?php

namespace Modules\Construction\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\Construction\Entities\ConstructionAuditLog;

class AuditTrail
{
    public static function record(string $event, Model $subject, ?int $projectId, array $old = [], array $new = []): void
    {
        ConstructionAuditLog::create([
            'business_id' => (int) ($subject->business_id ?: session('user.business_id')),
            'project_id' => $projectId,
            'auditable_type' => get_class($subject),
            'auditable_id' => $subject->getKey(),
            'event' => $event,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}

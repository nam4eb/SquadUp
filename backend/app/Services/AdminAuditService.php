<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AdminAuditService
{
    public function record(string $action, Model $target, array $metadata = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => auth()->id(), 'action' => $action,
            'target_type' => $target::class, 'target_id' => $target->getKey(),
            'metadata' => $metadata ?: null, 'ip_address' => request()->ip(),
        ]);
    }
}

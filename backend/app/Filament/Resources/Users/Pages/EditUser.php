<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function afterSave(): void
    {
        AuditLog::create([
            'actor_id' => auth()->id(), 'action' => 'ADMIN_UPDATE_USER',
            'target_type' => $this->record::class, 'target_id' => $this->record->getKey(),
            'metadata' => ['changed' => array_keys($this->record->getChanges())],
            'ip_address' => request()->ip(),
        ]);
    }
}

<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Services\AdminAuditService;
use Filament\Resources\Pages\EditRecord;

class EditActivity extends EditRecord
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function afterSave(): void
    {
        app(AdminAuditService::class)->record('ADMIN_MODERATE_ACTIVITY', $this->record, [
            'changed' => array_keys($this->record->getChanges()),
        ]);
    }
}

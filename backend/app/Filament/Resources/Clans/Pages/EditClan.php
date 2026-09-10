<?php

namespace App\Filament\Resources\Clans\Pages;

use App\Filament\Resources\Clans\ClanResource;
use App\Services\AdminAuditService;
use Filament\Resources\Pages\EditRecord;

class EditClan extends EditRecord
{
    protected static string $resource = ClanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function afterSave(): void
    {
        app(AdminAuditService::class)->record('ADMIN_MODERATE_CLAN', $this->record, [
            'changed' => array_keys($this->record->getChanges()),
        ]);
    }
}

<?php

namespace App\Filament\Resources\ActivityCategories\Pages;

use App\Filament\Resources\ActivityCategories\ActivityCategoryResource;
use App\Services\AdminAuditService;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityCategory extends EditRecord
{
    protected static string $resource = ActivityCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(AdminAuditService::class)->record('ADMIN_UPDATE_ACTIVITY_CATEGORY', $this->record, [
            'changed' => array_keys($this->record->getChanges()),
        ]);
    }
}

<?php

namespace App\Filament\Resources\ActivityTopics\Pages;

use App\Filament\Resources\ActivityTopics\ActivityTopicResource;
use App\Services\AdminAuditService;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityTopic extends EditRecord
{
    protected static string $resource = ActivityTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(AdminAuditService::class)->record('ADMIN_UPDATE_ACTIVITY_TOPIC', $this->record, [
            'changed' => array_keys($this->record->getChanges()),
        ]);
    }
}

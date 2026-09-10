<?php

namespace App\Filament\Resources\ActivityTopics\Pages;

use App\Filament\Resources\ActivityTopics\ActivityTopicResource;
use App\Services\AdminAuditService;
use Filament\Resources\Pages\CreateRecord;

class CreateActivityTopic extends CreateRecord
{
    protected static string $resource = ActivityTopicResource::class;

    protected function afterCreate(): void
    {
        app(AdminAuditService::class)->record('ADMIN_CREATE_ACTIVITY_TOPIC', $this->record);
    }
}

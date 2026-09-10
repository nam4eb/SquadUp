<?php

namespace App\Filament\Resources\ActivityCategories\Pages;

use App\Filament\Resources\ActivityCategories\ActivityCategoryResource;
use App\Services\AdminAuditService;
use Filament\Resources\Pages\CreateRecord;

class CreateActivityCategory extends CreateRecord
{
    protected static string $resource = ActivityCategoryResource::class;

    protected function afterCreate(): void
    {
        app(AdminAuditService::class)->record('ADMIN_CREATE_ACTIVITY_CATEGORY', $this->record);
    }
}

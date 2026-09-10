<?php

namespace App\Filament\Resources\ActivityTopics\Pages;

use App\Filament\Resources\ActivityTopics\ActivityTopicResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivityTopics extends ListRecords
{
    protected static string $resource = ActivityTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

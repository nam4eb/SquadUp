<?php

namespace App\Filament\Resources\ActivityRatings\Pages;

use App\Filament\Resources\ActivityRatings\ActivityRatingResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityRatings extends ListRecords
{
    protected static string $resource = ActivityRatingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

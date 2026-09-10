<?php

namespace App\Filament\Resources\Clans\Pages;

use App\Filament\Resources\Clans\ClanResource;
use Filament\Resources\Pages\ListRecords;

class ListClans extends ListRecords
{
    protected static string $resource = ClanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

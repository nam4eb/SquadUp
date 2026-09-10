<?php

namespace App\Filament\Resources\ReportReasons\Pages;

use App\Filament\Resources\ReportReasons\ReportReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReportReasons extends ListRecords
{
    protected static string $resource = ReportReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

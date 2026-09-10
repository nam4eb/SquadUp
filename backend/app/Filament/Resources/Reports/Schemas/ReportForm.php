<?php

namespace App\Filament\Resources\Reports\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')->options([
                    'reported' => 'Reported', 'under_review' => 'Under review',
                    'resolved' => 'Resolved', 'dismissed' => 'Dismissed',
                ])->required(),
                Textarea::make('resolution')->maxLength(4000)->columnSpanFull(),
            ]);
    }
}

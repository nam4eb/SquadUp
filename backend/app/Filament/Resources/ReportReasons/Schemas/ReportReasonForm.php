<?php

namespace App\Filament\Resources\ReportReasons\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReportReasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->required()->alphaDash()->maxLength(40)->unique(ignoreRecord: true),
                TextInput::make('label')->required()->maxLength(120),
                Textarea::make('description')->maxLength(2000)->columnSpanFull(),
                Toggle::make('is_active')->default(true),
                TextInput::make('sort_order')->numeric()->default(0),
            ]);
    }
}

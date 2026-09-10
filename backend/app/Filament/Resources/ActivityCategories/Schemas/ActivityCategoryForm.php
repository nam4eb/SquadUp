<?php

namespace App\Filament\Resources\ActivityCategories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ActivityCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('slug')->required()->maxLength(140)->unique(ignoreRecord: true),
                Textarea::make('description')->maxLength(2000)->columnSpanFull(),
                TextInput::make('icon')->maxLength(120),
                ColorPicker::make('color'),
                TextInput::make('sort_order')->numeric()->default(0),
                Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->required(),
                Toggle::make('is_visible')->default(true),
            ]);
    }
}

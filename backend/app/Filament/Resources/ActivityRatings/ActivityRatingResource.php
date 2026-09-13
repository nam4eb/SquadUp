<?php

namespace App\Filament\Resources\ActivityRatings;

use App\Filament\Resources\ActivityRatings\Pages\ListActivityRatings;
use App\Filament\Resources\ActivityRatings\Tables\ActivityRatingsTable;
use App\Models\ActivityRating;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ActivityRatingResource extends Resource
{
    protected static ?string $model = ActivityRating::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Activity ratings';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ActivityRatingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityRatings::route('/')];
    }
}

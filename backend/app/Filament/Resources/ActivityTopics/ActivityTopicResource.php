<?php

namespace App\Filament\Resources\ActivityTopics;

use App\Filament\Resources\ActivityTopics\Pages\CreateActivityTopic;
use App\Filament\Resources\ActivityTopics\Pages\EditActivityTopic;
use App\Filament\Resources\ActivityTopics\Pages\ListActivityTopics;
use App\Filament\Resources\ActivityTopics\Schemas\ActivityTopicForm;
use App\Filament\Resources\ActivityTopics\Tables\ActivityTopicsTable;
use App\Models\ActivityTopic;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivityTopicResource extends Resource
{
    protected static ?string $model = ActivityTopic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ActivityTopicForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityTopicsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityTopics::route('/'),
            'create' => CreateActivityTopic::route('/create'),
            'edit' => EditActivityTopic::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

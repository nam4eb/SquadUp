<?php

namespace App\Filament\Resources\Activities\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('host.display_name')->label('Host')->searchable(),
                TextColumn::make('topic.name')->label('Topic'),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('visibility')->badge(),
                TextColumn::make('starts_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'open' => 'Open', 'locked' => 'Locked', 'ongoing' => 'Ongoing',
                    'completed' => 'Completed', 'cancelled' => 'Cancelled',
                ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}

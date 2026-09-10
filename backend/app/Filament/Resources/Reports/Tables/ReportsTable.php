<?php

namespace App\Filament\Resources\Reports\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reason')->badge()->searchable(),
                TextColumn::make('reportable_type')->label('Target')->formatStateUsing(fn (string $state) => class_basename($state)),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'reported' => 'Reported', 'under_review' => 'Under review',
                    'resolved' => 'Resolved', 'dismissed' => 'Dismissed',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

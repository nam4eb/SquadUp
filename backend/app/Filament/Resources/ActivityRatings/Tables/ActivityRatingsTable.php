<?php

namespace App\Filament\Resources\ActivityRatings\Tables;

use App\Models\ActivityRating;
use App\Models\AuditLog;
use App\Services\ReputationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivityRatingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('activity.title')->label('Activity')->searchable()->sortable(),
                TextColumn::make('rater.display_name')->label('From')->searchable(),
                TextColumn::make('ratee.display_name')->label('To')->searchable(),
                TextColumn::make('sport.name')->badge()->placeholder('General'),
                TextColumn::make('sportsmanship')->numeric()->sortable(),
                TextColumn::make('skill')->numeric()->sortable(),
                TextColumn::make('reliability')->numeric()->sortable(),
                TextColumn::make('comment')->limit(80)->wrap()->toggleable(),
                TextColumn::make('invalidated_at')->label('Moderation')->dateTime()->placeholder('Active')->sortable(),
            ])
            ->filters([
                SelectFilter::make('sport')->relationship('sport', 'name'),
            ])
            ->recordActions([
                Action::make('invalidate')
                    ->label('Invalidate')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ActivityRating $record): bool => $record->invalidated_at === null)
                    ->action(function (ActivityRating $record): void {
                        $record->update([
                            'invalidated_at' => now(),
                            'invalidated_by' => auth()->id(),
                            'invalidation_reason' => 'Invalidated by moderator',
                        ]);
                        app(ReputationService::class)->refresh($record->ratee_id, $record->sport_id);
                        AuditLog::create([
                            'actor_id' => auth()->id(), 'action' => 'ADMIN_INVALIDATE_RATING',
                            'target_type' => $record::class, 'target_id' => $record->getKey(),
                            'metadata' => ['ratee_id' => $record->ratee_id, 'sport_id' => $record->sport_id],
                            'ip_address' => request()->ip(),
                        ]);
                        Notification::make()->title('Rating invalidated')->success()->send();
                    }),
                Action::make('restore')
                    ->label('Restore')
                    ->requiresConfirmation()
                    ->visible(fn (ActivityRating $record): bool => $record->invalidated_at !== null)
                    ->action(function (ActivityRating $record): void {
                        $record->update([
                            'invalidated_at' => null,
                            'invalidated_by' => null,
                            'invalidation_reason' => null,
                        ]);
                        app(ReputationService::class)->refresh($record->ratee_id, $record->sport_id);
                        AuditLog::create([
                            'actor_id' => auth()->id(), 'action' => 'ADMIN_RESTORE_RATING',
                            'target_type' => $record::class, 'target_id' => $record->getKey(),
                            'metadata' => ['ratee_id' => $record->ratee_id, 'sport_id' => $record->sport_id],
                            'ip_address' => request()->ip(),
                        ]);
                        Notification::make()->title('Rating restored')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('actor.display_name')->label('Administrator')->searchable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('target_type')->label('Target')->formatStateUsing(
                    fn (?string $state) => $state ? class_basename($state) : 'System',
                ),
                TextColumn::make('target_id')->copyable()->toggleable(),
                TextColumn::make('ip_address')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')->options(
                    fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all(),
                ),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

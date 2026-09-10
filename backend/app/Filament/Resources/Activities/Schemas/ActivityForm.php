<?php

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->required()->maxLength(180),
                Textarea::make('description')->maxLength(10000)->columnSpanFull(),
                Select::make('status')->options([
                    'draft' => 'Draft', 'open' => 'Open', 'full' => 'Full', 'locked' => 'Locked',
                    'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'expired' => 'Expired',
                ])->required(),
                Select::make('visibility')->options([
                    'public' => 'Public', 'friends' => 'Friends', 'clan' => 'Clan',
                    'invite_only' => 'Invite only', 'private' => 'Private',
                ])->required(),
                TextInput::make('location_name')->maxLength(240),
                Textarea::make('notes')->maxLength(4000)->columnSpanFull(),
            ]);
    }
}

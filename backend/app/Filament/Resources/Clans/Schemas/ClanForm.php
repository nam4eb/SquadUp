<?php

namespace App\Filament\Resources\Clans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('slug')->disabled(),
                Textarea::make('description')->maxLength(5000)->columnSpanFull(),
                Select::make('visibility')->options(['public' => 'Public', 'private' => 'Private'])->required(),
                Select::make('join_policy')->options([
                    'open' => 'Open', 'approval' => 'Approval', 'invite_only' => 'Invite only',
                ])->required(),
                Select::make('status')->options([
                    'active' => 'Active', 'suspended' => 'Suspended', 'deleted' => 'Deleted',
                ])->required(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')->disabled(),
                TextInput::make('display_name')->required()->maxLength(120),
                TextInput::make('email')->email()->disabled(),
                Textarea::make('bio')->maxLength(1000)->columnSpanFull(),
                TextInput::make('location')->maxLength(160),
                Select::make('account_status')->options([
                    'active' => 'Active', 'suspended' => 'Suspended',
                    'banned' => 'Banned', 'deleted' => 'Deleted',
                ])->required(),
                Toggle::make('is_admin')->label('CRM administrator'),
            ]);
    }
}

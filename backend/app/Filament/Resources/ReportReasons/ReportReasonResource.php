<?php

namespace App\Filament\Resources\ReportReasons;

use App\Filament\Resources\ReportReasons\Pages\CreateReportReason;
use App\Filament\Resources\ReportReasons\Pages\EditReportReason;
use App\Filament\Resources\ReportReasons\Pages\ListReportReasons;
use App\Filament\Resources\ReportReasons\Schemas\ReportReasonForm;
use App\Filament\Resources\ReportReasons\Tables\ReportReasonsTable;
use App\Models\ReportReason;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReportReasonResource extends Resource
{
    protected static ?string $model = ReportReason::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ReportReasonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportReasonsTable::configure($table);
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
            'index' => ListReportReasons::route('/'),
            'create' => CreateReportReason::route('/create'),
            'edit' => EditReportReason::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\ReportReasons\Pages;

use App\Filament\Resources\ReportReasons\ReportReasonResource;
use App\Services\AdminAuditService;
use Filament\Resources\Pages\EditRecord;

class EditReportReason extends EditRecord
{
    protected static string $resource = ReportReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function afterSave(): void
    {
        app(AdminAuditService::class)->record('ADMIN_UPDATE_REPORT_REASON', $this->record, [
            'changed' => array_keys($this->record->getChanges()),
        ]);
    }
}

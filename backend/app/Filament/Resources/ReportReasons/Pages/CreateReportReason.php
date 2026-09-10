<?php

namespace App\Filament\Resources\ReportReasons\Pages;

use App\Filament\Resources\ReportReasons\ReportReasonResource;
use App\Services\AdminAuditService;
use Filament\Resources\Pages\CreateRecord;

class CreateReportReason extends CreateRecord
{
    protected static string $resource = ReportReasonResource::class;

    protected function afterCreate(): void
    {
        app(AdminAuditService::class)->record('ADMIN_CREATE_REPORT_REASON', $this->record);
    }
}

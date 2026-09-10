<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\EditRecord;

class EditReport extends EditRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['reviewed_by'] = auth()->id();
        $data['reviewed_at'] = now();

        return $data;
    }

    protected function afterSave(): void
    {
        AuditLog::create([
            'actor_id' => auth()->id(), 'action' => 'ADMIN_REVIEW_REPORT',
            'target_type' => $this->record::class, 'target_id' => $this->record->getKey(),
            'metadata' => ['status' => $this->record->status],
            'ip_address' => request()->ip(),
        ]);
    }
}

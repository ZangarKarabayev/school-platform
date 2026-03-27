<?php

namespace App\Filament\Resources\Schools\Pages;

use App\Filament\Resources\Schools\SchoolResource;
use App\Jobs\SyncFaceIdStudentsJob;
use App\Models\Terminal;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSchool extends EditRecord
{
    protected static string $resource = SchoolResource::class;

    protected array $terminalIds = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->terminalIds = array_map('intval', $data['terminal_ids'] ?? []);
        unset($data['terminal_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncTerminals();
        $this->queueSyncJobsForAssignedTerminals();
    }

    protected function syncTerminals(): void
    {
        $detachQuery = Terminal::query()->where('school_id', $this->record->id);

        if ($this->terminalIds !== []) {
            $detachQuery->whereNotIn('id', $this->terminalIds);
        }

        $detachQuery->update(['school_id' => null]);

        if ($this->terminalIds === []) {
            return;
        }

        Terminal::query()
            ->whereIn('id', $this->terminalIds)
            ->update(['school_id' => $this->record->id]);
    }

    protected function queueSyncJobsForAssignedTerminals(): void
    {
        Terminal::query()
            ->where('school_id', $this->record->id)
            ->whereNotNull('device_id')
            ->pluck('device_id')
            ->map(fn ($deviceId) => trim((string) $deviceId))
            ->filter()
            ->unique()
            ->each(fn (string $deviceId) => SyncFaceIdStudentsJob::dispatch($this->record->id, $deviceId));
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

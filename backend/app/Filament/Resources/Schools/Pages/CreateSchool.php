<?php

namespace App\Filament\Resources\Schools\Pages;

use App\Filament\Resources\Schools\SchoolResource;
use App\Jobs\SyncFaceIdStudentsJob;
use App\Models\Terminal;
use Filament\Resources\Pages\CreateRecord;

class CreateSchool extends CreateRecord
{
    protected static string $resource = SchoolResource::class;

    protected array $terminalIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->terminalIds = array_map('intval', $data['terminal_ids'] ?? []);
        unset($data['terminal_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncTerminals();
        $this->queueSyncJobsForAssignedTerminals();
    }

    protected function syncTerminals(): void
    {
        Terminal::query()
            ->where('school_id', $this->record->id)
            ->whereNotIn('id', $this->terminalIds ?: [0])
            ->update(['school_id' => null]);

        if ($this->terminalIds === []) {
            return;
        }

        $selectedTerminals = Terminal::query()
            ->whereIn('id', $this->terminalIds)
            ->get();

        $selectedDeviceIds = $selectedTerminals
            ->pluck('device_id')
            ->filter()
            ->unique()
            ->values();

        if ($selectedDeviceIds->isNotEmpty()) {
            Terminal::query()
                ->whereIn('device_id', $selectedDeviceIds)
                ->update(['school_id' => null]);
        }

        Terminal::query()
            ->whereIn('id', $selectedTerminals->pluck('id'))
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
}

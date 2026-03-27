<?php

namespace App\Filament\Resources\Schools\Pages;

use App\Filament\Resources\Schools\SchoolResource;
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
}

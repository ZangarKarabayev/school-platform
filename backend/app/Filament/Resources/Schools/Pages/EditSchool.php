<?php

namespace App\Filament\Resources\Schools\Pages;

use App\Filament\Resources\Schools\SchoolResource;
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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\AcademicClasses\Pages;

use App\Filament\Resources\AcademicClasses\AcademicClassResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcademicClass extends EditRecord
{
    protected static string $resource = AcademicClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
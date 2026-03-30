<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected ?string $mealBenefitType = null;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->mealBenefitType = filled($data['meal_benefit_type'] ?? null)
            ? (string) $data['meal_benefit_type']
            : null;

        unset($data['meal_benefit_type']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Student $student */
        $student = $this->record->loadMissing('latestMealBenefit');

        if ($this->mealBenefitType !== null && $student->latestMealBenefit?->type !== $this->mealBenefitType) {
            $student->mealBenefits()->create([
                'type' => $this->mealBenefitType,
            ]);
        }
    }
}

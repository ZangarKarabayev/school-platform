<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected ?string $mealBenefitType = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->mealBenefitType = filled($data['meal_benefit_type'] ?? null)
            ? (string) $data['meal_benefit_type']
            : null;

        unset($data['meal_benefit_type']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Student $student */
        $student = $this->record;

        if ($this->mealBenefitType !== null) {
            $student->mealBenefits()->create([
                'type' => $this->mealBenefitType,
            ]);
        }
    }
}

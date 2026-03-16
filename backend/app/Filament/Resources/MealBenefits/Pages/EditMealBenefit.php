<?php

namespace App\Filament\Resources\MealBenefits\Pages;

use App\Filament\Resources\MealBenefits\MealBenefitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMealBenefit extends EditRecord
{
    protected static string $resource = MealBenefitResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
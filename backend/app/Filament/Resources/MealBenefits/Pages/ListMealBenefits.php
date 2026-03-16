<?php

namespace App\Filament\Resources\MealBenefits\Pages;

use App\Filament\Resources\MealBenefits\MealBenefitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMealBenefits extends ListRecords
{
    protected static string $resource = MealBenefitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
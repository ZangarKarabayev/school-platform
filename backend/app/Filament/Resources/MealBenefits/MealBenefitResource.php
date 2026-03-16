<?php

namespace App\Filament\Resources\MealBenefits;

use App\Filament\Resources\MealBenefits\Pages\CreateMealBenefit;
use App\Filament\Resources\MealBenefits\Pages\EditMealBenefit;
use App\Filament\Resources\MealBenefits\Pages\ListMealBenefits;
use App\Filament\Resources\MealBenefits\Schemas\MealBenefitForm;
use App\Filament\Resources\MealBenefits\Tables\MealBenefitsTable;
use App\Models\MealBenefit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MealBenefitResource extends Resource
{
    protected static ?string $model = MealBenefit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $recordTitleAttribute = 'type';

    public static function form(Schema $schema): Schema
    {
        return MealBenefitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MealBenefitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMealBenefits::route('/'),
            'create' => CreateMealBenefit::route('/create'),
            'edit' => EditMealBenefit::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.labels.meal_benefits');
    }

    public static function getModelLabel(): string
    {
        return __('admin.labels.meal_benefit');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.labels.meal_benefits');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.org_structure');
    }
}
<?php

namespace App\Filament\Resources\AcademicClasses;

use App\Filament\Resources\AcademicClasses\Pages\CreateAcademicClass;
use App\Filament\Resources\AcademicClasses\Pages\EditAcademicClass;
use App\Filament\Resources\AcademicClasses\Pages\ListAcademicClasses;
use App\Filament\Resources\AcademicClasses\Schemas\AcademicClassForm;
use App\Filament\Resources\AcademicClasses\Tables\AcademicClassesTable;
use App\Models\AcademicClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AcademicClassResource extends Resource
{
    protected static ?string $model = AcademicClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return AcademicClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcademicClassesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcademicClasses::route('/'),
            'create' => CreateAcademicClass::route('/create'),
            'edit' => EditAcademicClass::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.labels.academic_classes');
    }

    public static function getModelLabel(): string
    {
        return __('admin.labels.academic_class');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.labels.academic_classes');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.settings');
    }
}
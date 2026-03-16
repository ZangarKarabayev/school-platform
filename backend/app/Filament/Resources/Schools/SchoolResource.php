<?php

namespace App\Filament\Resources\Schools;

use App\Filament\Resources\Schools\Pages\CreateSchool;
use App\Filament\Resources\Schools\Pages\EditSchool;
use App\Filament\Resources\Schools\Pages\ListSchools;
use App\Filament\Resources\Schools\Schemas\SchoolForm;
use App\Filament\Resources\Schools\Tables\SchoolsTable;
use App\Modules\Organizations\Models\School;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SchoolResource extends Resource
{
    protected static ?string $model = School::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $recordTitleAttribute = 'name_ru';

    public static function form(Schema $schema): Schema
    {
        return SchoolForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchools::route('/'),
            'create' => CreateSchool::route('/create'),
            'edit' => EditSchool::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.labels.schools');
    }

    public static function getModelLabel(): string
    {
        return __('admin.labels.school');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.labels.schools');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.org_structure');
    }
}
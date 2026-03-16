<?php

namespace App\Filament\Resources\Students;

use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Schemas\StudentForm;
use App\Filament\Resources\Students\Tables\StudentsTable;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.labels.students');
    }

    public static function getModelLabel(): string
    {
        return __('admin.labels.student');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.labels.students');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.org_structure');
    }
}
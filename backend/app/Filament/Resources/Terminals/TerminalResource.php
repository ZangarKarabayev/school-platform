<?php

namespace App\Filament\Resources\Terminals;

use App\Filament\Resources\Terminals\Pages\CreateTerminal;
use App\Filament\Resources\Terminals\Pages\EditTerminal;
use App\Filament\Resources\Terminals\Pages\ListTerminals;
use App\Filament\Resources\Terminals\Schemas\TerminalForm;
use App\Filament\Resources\Terminals\Tables\TerminalsTable;
use App\Models\Terminal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TerminalResource extends Resource
{
    protected static ?string $model = Terminal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $recordTitleAttribute = 'device_id';

    public static function form(Schema $schema): Schema
    {
        return TerminalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TerminalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTerminals::route('/'),
            'create' => CreateTerminal::route('/create'),
            'edit' => EditTerminal::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.labels.terminals');
    }

    public static function getModelLabel(): string
    {
        return __('admin.labels.terminal');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.labels.terminals');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.org_structure');
    }
}

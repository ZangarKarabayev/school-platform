<?php

namespace App\Filament\Resources\MenuItems;

use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\Resources\MenuItems\Schemas\MenuItemForm;
use App\Filament\Resources\MenuItems\Tables\MenuItemsTable;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MenuItemResource extends Resource
{
    protected static ?string $model = MenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return MenuItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MenuItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuItems::route('/'),
            'edit' => EditMenuItem::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.labels.menu_items');
    }

    public static function getModelLabel(): string
    {
        return __('admin.labels.menu_item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.labels.menu_items');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.settings');
    }
}
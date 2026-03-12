<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('admin.labels.code'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label(__('admin.labels.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('description')
                    ->label(__('admin.labels.description'))
                    ->maxLength(65535),
                Toggle::make('is_system')
                    ->label(__('admin.labels.system_role'))
                    ->default(true),
                CheckboxList::make('permissions')
                    ->label(__('admin.labels.permissions'))
                    ->relationship('permissions', 'name')
                    ->columns(2),
            ]);
    }
}

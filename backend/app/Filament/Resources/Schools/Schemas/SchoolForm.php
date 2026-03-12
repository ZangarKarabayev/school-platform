<?php

namespace App\Filament\Resources\Schools\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('district_id')
                    ->label(__('admin.labels.district'))
                    ->relationship('district', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label(__('admin.labels.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label(__('admin.labels.code'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('bin')
                    ->label(__('admin.labels.bin'))
                    ->maxLength(12)
                    ->unique(ignoreRecord: true),
                TextInput::make('address')
                    ->label(__('admin.labels.address'))
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label(__('admin.labels.active'))
                    ->default(true),
            ]);
    }
}

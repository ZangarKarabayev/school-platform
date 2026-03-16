<?php

namespace App\Filament\Resources\Districts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DistrictForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('region_id')
                    ->label(__('admin.labels.region'))
                    ->relationship('region', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name_ru')
                    ->label(__('admin.labels.name_ru'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_kk')
                    ->label(__('admin.labels.name_kk'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label(__('admin.labels.code'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
            ]);
    }
}
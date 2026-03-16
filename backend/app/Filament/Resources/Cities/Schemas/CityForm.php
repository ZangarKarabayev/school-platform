<?php

namespace App\Filament\Resources\Cities\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('district_id')
                    ->label(__('admin.labels.district'))
                    ->relationship('district', 'name')
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
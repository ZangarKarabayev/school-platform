<?php

namespace App\Filament\Resources\Regions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RegionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
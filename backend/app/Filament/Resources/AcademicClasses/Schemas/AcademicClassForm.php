<?php

namespace App\Filament\Resources\AcademicClasses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AcademicClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('grade')
                    ->label(__('admin.labels.grade'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(11)
                    ->required(),
                TextInput::make('letter')
                    ->label(__('admin.labels.letter'))
                    ->required()
                    ->maxLength(2),
            ]);
    }
}
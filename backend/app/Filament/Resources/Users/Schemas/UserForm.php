<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('last_name')
                    ->label(__('admin.labels.last_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('first_name')
                    ->label(__('admin.labels.first_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('middle_name')
                    ->label(__('admin.labels.middle_name'))
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('admin.labels.phone'))
                    ->tel()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                Select::make('status')
                    ->label(__('admin.labels.status'))
                    ->options([
                        'active' => __('admin.status.active'),
                        'blocked' => __('admin.status.blocked'),
                        'pending' => __('admin.status.pending'),
                    ])
                    ->default('active')
                    ->required(),
                Select::make('preferred_locale')
                    ->label(__('admin.labels.language'))
                    ->options([
                        'ru' => __('admin.language.ru'),
                        'kk' => __('admin.language.kk'),
                    ])
                    ->default('ru')
                    ->required(),
                CheckboxList::make('roles')
                    ->label(__('admin.labels.roles'))
                    ->relationship('roles', 'name')
                    ->columns(2),
            ]);
    }
}

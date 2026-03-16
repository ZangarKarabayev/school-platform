<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('menu_label')
                    ->label(__('admin.labels.name'))
                    ->content(fn ($record): string => $record ? (string) __('ui.menu.' . $record->key) : '-'),
                Placeholder::make('key')
                    ->label(__('admin.labels.code'))
                    ->content(fn ($record): string => $record?->key ?? '-'),
                Toggle::make('enabled')
                    ->label(__('admin.labels.enabled'))
                    ->inline(false)
                    ->default(true)
                    ->required(),
            ]);
    }
}
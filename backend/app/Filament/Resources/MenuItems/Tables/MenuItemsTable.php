<?php

namespace App\Filament\Resources\MenuItems\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label(__('admin.labels.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('admin.labels.name'))
                    ->state(fn ($record): string => __('ui.menu.' . $record->key))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('key', 'like', "%{$search}%");
                    }),
                IconColumn::make('enabled')
                    ->label(__('admin.labels.enabled'))
                    ->boolean(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
<?php

namespace App\Filament\Resources\Regions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_ru')
                    ->label(__('admin.labels.name_ru'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_kk')
                    ->label(__('admin.labels.name_kk'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('admin.labels.code'))
                    ->searchable(),
                TextColumn::make('districts_count')
                    ->label(__('admin.labels.districts_count'))
                    ->counts('districts'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
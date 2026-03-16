<?php

namespace App\Filament\Resources\Districts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DistrictsTable
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
                TextColumn::make('region.name_ru')
                    ->label(__('admin.labels.region'))
                    ->sortable(),
                TextColumn::make('cities_count')
                    ->label(__('admin.labels.cities_count'))
                    ->counts('cities'),
                TextColumn::make('schools_count')
                    ->label(__('admin.labels.schools_count'))
                    ->counts('schools'),
            ])
            ->filters([
                SelectFilter::make('region')
                    ->relationship('region', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->label(__('admin.labels.region')),
            ])
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
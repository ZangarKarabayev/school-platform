<?php

namespace App\Filament\Resources\Schools\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SchoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.labels.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('admin.labels.code'))
                    ->searchable(),
                TextColumn::make('district.name')
                    ->label(__('admin.labels.district'))
                    ->sortable(),
                TextColumn::make('district.region.name')
                    ->label(__('admin.labels.region'))
                    ->sortable(),
                TextColumn::make('bin')
                    ->label(__('admin.labels.bin'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('admin.labels.active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('district')
                    ->relationship('district', 'name')
                    ->label(__('admin.labels.district')),
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

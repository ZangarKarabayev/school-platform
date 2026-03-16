<?php

namespace App\Filament\Resources\AcademicClasses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AcademicClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('grade')
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('admin.labels.class_full_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grade')
                    ->label(__('admin.labels.grade'))
                    ->sortable(),
                TextColumn::make('letter')
                    ->label(__('admin.labels.letter'))
                    ->sortable(),
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
<?php

namespace App\Filament\Resources\Students\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')->label(__('admin.labels.full_name'))->searchable(['last_name', 'first_name', 'middle_name']),
                TextColumn::make('iin')->label(__('admin.labels.iin'))->searchable(),
                TextColumn::make('classroom.full_name')->label(__('admin.labels.class_full_name'))->sortable(),
                TextColumn::make('school.name_ru')->label(__('admin.labels.organization'))->sortable(),
                TextColumn::make('student_number')->label(__('admin.labels.student_number'))->searchable(),
                TextColumn::make('status')->label(__('admin.labels.status')),
            ])
            ->filters([
                SelectFilter::make('classroom')->relationship('classroom', 'full_name')->label(__('admin.labels.class_full_name')),
                SelectFilter::make('school')
                    ->relationship('school', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->label(__('admin.labels.organization')),
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

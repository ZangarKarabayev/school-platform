<?php

namespace App\Filament\Resources\Students\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;
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
                TextColumn::make('photo_synced_at')
                    ->label(__('ui.students.photo_synced_at'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('status')->label(__('admin.labels.status')),
            ])
            ->filters([
                SelectFilter::make('classroom')->relationship('classroom', 'full_name')->label(__('admin.labels.class_full_name')),
                SelectFilter::make('school')
                    ->relationship('school', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->label(__('admin.labels.organization')),
                SelectFilter::make('photo_sync')
                    ->label(__('ui.students.photo_sync_status'))
                    ->options([
                        'synced' => __('ui.students.photo_synced'),
                        'not_synced' => __('ui.students.photo_not_synced'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'synced' => $query->whereNotNull('photo_synced_at'),
                            'not_synced' => $query->where(function (Builder $syncQuery): void {
                                $syncQuery
                                    ->whereNull('photo_synced_at')
                                    ->orWhereColumn('photo_updated_at', '>', 'photo_synced_at');
                            }),
                            default => $query,
                        };
                    }),
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

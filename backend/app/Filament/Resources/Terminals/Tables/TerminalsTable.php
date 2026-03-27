<?php

namespace App\Filament\Resources\Terminals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TerminalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('device_id')
                    ->label(__('admin.labels.device_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('school.name_ru')
                    ->label(__('admin.labels.school'))
                    ->formatStateUsing(fn ($state, $record): string => $record->school?->display_name ?? '-')
                    ->sortable(),
                TextColumn::make('ip')
                    ->label('IP')
                    ->searchable(),
                TextColumn::make('mac_addr')
                    ->label('MAC')
                    ->searchable(),
                TextColumn::make('time')
                    ->label(__('admin.labels.last_heartbeat'))
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('school')
                    ->relationship('school', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->label(__('admin.labels.school')),
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

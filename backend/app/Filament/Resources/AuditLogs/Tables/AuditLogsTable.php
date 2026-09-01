<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Время')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('actor_name')
                    ->label('Пользователь')
                    ->description(fn ($record): ?string => $record->actor_phone)
                    ->searchable(['actor_name', 'actor_phone']),
                TextColumn::make('roles')
                    ->label('Роли')
                    ->badge()
                    ->separator(','),
                TextColumn::make('event')
                    ->label('Событие')
                    ->badge()
                    ->searchable(),
                TextColumn::make('route_name')
                    ->label('Маршрут')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('method')
                    ->label('Метод')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('status_code')
                    ->label('Статус')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('subject_type')
                    ->label('Объект')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '-' : class_basename($state))
                    ->description(fn ($record): ?string => $record->subject_id === null ? null : '#'.$record->subject_id)
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Событие')
                    ->options([
                        'http.request' => 'HTTP-запрос',
                        'auth.login' => 'Вход',
                        'auth.logout' => 'Выход',
                        'model.created' => 'Создание записи',
                        'model.updated' => 'Изменение записи',
                        'model.deleted' => 'Удаление записи',
                    ]),
                SelectFilter::make('method')
                    ->label('HTTP-метод')
                    ->options(array_combine(
                        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}

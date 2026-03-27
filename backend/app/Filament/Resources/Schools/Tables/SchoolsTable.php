<?php

namespace App\Filament\Resources\Schools\Tables;

use App\Jobs\SyncFaceIdStudentsJob;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                TextColumn::make('district.name_ru')
                    ->label(__('admin.labels.district'))
                    ->sortable(),
                TextColumn::make('district.region.name_ru')
                    ->label(__('admin.labels.region'))
                    ->sortable(),
                TextColumn::make('bin')
                    ->label(__('admin.labels.bin'))
                    ->searchable(),
                TextColumn::make('assigned_terminal')
                    ->label(__('admin.labels.terminals'))
                    ->badge()
                    ->state(fn ($record): string => $record->terminals()
                        ->whereNotNull('device_id')
                        ->orderBy('id')
                        ->pluck('device_id')
                        ->map(fn ($deviceId) => trim((string) $deviceId))
                        ->filter()
                        ->unique()
                        ->implode(', ')),
                IconColumn::make('is_active')
                    ->label(__('admin.labels.active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('district')
                    ->relationship('district', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->label(__('admin.labels.district')),
            ])
            ->recordActions([
                Action::make('send_to_terminal')
                    ->label('Отправить на терминал')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('primary')
                    ->action(function ($record): void {
                        $deviceIds = $record->terminals()
                            ->whereNotNull('device_id')
                            ->pluck('device_id')
                            ->map(fn ($deviceId) => trim((string) $deviceId))
                            ->filter()
                            ->unique()
                            ->values();

                        if ($deviceIds->isEmpty()) {
                            Notification::make()
                                ->title('У школы нет привязанных терминалов')
                                ->warning()
                                ->send();

                            return;
                        }

                        $deviceIds->each(
                            fn (string $deviceId) => SyncFaceIdStudentsJob::dispatch($record->id, $deviceId)
                        );

                        Notification::make()
                            ->title("Постановлено в очередь терминалов: {$deviceIds->count()}")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

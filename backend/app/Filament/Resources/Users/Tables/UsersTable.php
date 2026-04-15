<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('admin.labels.full_name'))
                    ->searchable(['last_name', 'first_name', 'middle_name'])
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('admin.labels.phone'))
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('admin.labels.roles'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('admin.labels.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active'
                        ? __('admin.status.active')
                        : __('admin.status.inactive')),
                ToggleColumn::make('is_active')
                    ->label(__('admin.labels.active'))
                    ->getStateUsing(fn (User $record): bool => $record->status === 'active')
                    ->updateStateUsing(function (User $record, bool $state): void {
                        $record->forceFill([
                            'status' => $state ? 'active' : 'inactive',
                        ])->save();
                    }),
                TextColumn::make('last_login_at')
                    ->label(__('admin.labels.last_login'))
                    ->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => __('admin.status.active'),
                        'inactive' => __('admin.status.inactive'),
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'active' => $query->where('status', 'active'),
                            'inactive' => $query->where('status', '!=', 'active'),
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

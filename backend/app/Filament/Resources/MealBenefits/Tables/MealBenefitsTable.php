<?php

namespace App\Filament\Resources\MealBenefits\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MealBenefitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')->label(__('admin.labels.student'))->searchable(['last_name', 'first_name', 'middle_name']),
                TextColumn::make('type')->label(__('admin.labels.type')),
                TextColumn::make('voucher_update_datetime')->label(__('admin.labels.voucher_update_datetime'))->dateTime(),
                TextColumn::make('start_date')->label(__('admin.labels.start_date'))->date(),
                TextColumn::make('end_date')->label(__('admin.labels.end_date'))->date(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'susn' => 'susn',
                    'voucher' => 'voucher',
                    'paid' => 'paid',
                ]),
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

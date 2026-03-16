<?php

namespace App\Filament\Resources\MealBenefits\Schemas;

use App\Models\MealBenefit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class MealBenefitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')->label(__('admin.labels.student'))->relationship('student', 'last_name')->getOptionLabelFromRecordUsing(fn ($record): string => $record->full_name)->searchable()->preload()->required(),
                Select::make('type')->label(__('admin.labels.type'))->options(array_combine(MealBenefit::TYPES, MealBenefit::TYPES))->required(),
                DateTimePicker::make('voucher_update_datetime')->label(__('admin.labels.voucher_update_datetime')),
                DatePicker::make('start_date')->label(__('admin.labels.start_date')),
                DatePicker::make('end_date')->label(__('admin.labels.end_date')),
            ]);
    }
}
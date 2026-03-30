<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\MealBenefit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('iin')->label(__('admin.labels.iin'))->maxLength(12)->unique(ignoreRecord: true),
                TextInput::make('last_name')->label(__('admin.labels.last_name'))->maxLength(100),
                TextInput::make('first_name')->label(__('admin.labels.first_name'))->maxLength(100),
                TextInput::make('middle_name')->label(__('admin.labels.middle_name'))->maxLength(100),
                DatePicker::make('birth_date')->label(__('admin.labels.birth_date')),
                Select::make('gender')->label(__('admin.labels.gender'))->options([
                    'male' => __('admin.labels.male'),
                    'female' => __('admin.labels.female'),
                ]),
                Select::make('classroom_id')->label(__('admin.labels.class_full_name'))->relationship('classroom', 'full_name')->searchable()->preload(),
                Select::make('school_id')
                    ->label(__('admin.labels.organization'))
                    ->relationship('school', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->searchable()
                    ->preload(),
                Select::make('language')->label(__('admin.labels.language'))->options([
                    'ru' => 'RU',
                    'kk' => 'KK',
                ]),
                Select::make('shift')->label(__('admin.labels.shift'))->options([
                    1 => '1',
                    2 => '2',
                ]),
                TextInput::make('school_year')->label(__('admin.labels.school_year'))->maxLength(9),
                Select::make('meal_benefit_type')
                    ->label(__('admin.labels.status'))
                    ->options(collect(MealBenefit::TYPES)->mapWithKeys(function (string $type): array {
                        $label = __('admin.meal_benefit_types.' . $type);

                        return [
                            $type => $label !== 'admin.meal_benefit_types.' . $type
                                ? $label
                                : str_replace('_', ' ', ucfirst($type)),
                        ];
                    })->all())
                    ->afterStateHydrated(function (Select $component, $record): void {
                        if ($record) {
                            $component->state($record->latestMealBenefit?->type);
                        }
                    }),
            ]);
    }
}

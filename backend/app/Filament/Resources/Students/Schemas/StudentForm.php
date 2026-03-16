<?php

namespace App\Filament\Resources\Students\Schemas;

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
                TextInput::make('phone')->label(__('admin.labels.phone'))->maxLength(20),
                TextInput::make('address')->label(__('admin.labels.address'))->maxLength(65535),
                TextInput::make('student_number')->label(__('admin.labels.student_number'))->maxLength(20),
                Select::make('language')->label(__('admin.labels.language'))->options([
                    'ru' => 'RU',
                    'kk' => 'KK',
                ]),
                Select::make('shift')->label(__('admin.labels.shift'))->options([
                    1 => '1',
                    2 => '2',
                ]),
                TextInput::make('school_year')->label(__('admin.labels.school_year'))->maxLength(9),
                Select::make('status')->label(__('admin.labels.status'))->options([
                    'active' => __('admin.status.active'),
                    'archived' => __('admin.labels.archived'),
                ]),
            ]);
    }
}

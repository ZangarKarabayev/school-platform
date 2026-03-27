<?php

namespace App\Filament\Resources\Terminals\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TerminalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_id')
                    ->label(__('admin.labels.school'))
                    ->relationship('school', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->searchable()
                    ->preload(),
                TextInput::make('device_id')
                    ->label(__('admin.labels.device_id'))
                    ->numeric()
                    ->unique(ignoreRecord: true),
                TextInput::make('ip')
                    ->label('IP')
                    ->maxLength(255),
                TextInput::make('mac_addr')
                    ->label('MAC')
                    ->maxLength(255),
                DateTimePicker::make('time')
                    ->label(__('admin.labels.last_heartbeat'))
                    ->seconds(false),
            ]);
    }
}

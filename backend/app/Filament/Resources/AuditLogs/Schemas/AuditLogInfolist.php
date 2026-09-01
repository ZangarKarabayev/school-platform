<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Действие')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->label('Время')->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('event')->label('Событие')->badge(),
                        TextEntry::make('status_code')->label('HTTP-статус')->badge()->placeholder('-'),
                        TextEntry::make('actor_name')->label('Пользователь')->placeholder('Системная операция'),
                        TextEntry::make('actor_phone')->label('Телефон')->placeholder('-'),
                        TextEntry::make('roles')->label('Роли')->badge()->placeholder('-'),
                        TextEntry::make('route_name')->label('Маршрут')->placeholder('-'),
                        TextEntry::make('method')->label('Метод')->badge()->placeholder('-'),
                        TextEntry::make('ip_address')->label('IP')->placeholder('-'),
                        TextEntry::make('action')->label('Обработчик')->columnSpanFull()->placeholder('-'),
                        TextEntry::make('url')->label('URL')->columnSpanFull()->copyable()->placeholder('-'),
                        TextEntry::make('user_agent')->label('Браузер / клиент')->columnSpanFull()->placeholder('-'),
                    ]),
                Section::make('Объект')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('subject_type')->label('Тип')->placeholder('-'),
                        TextEntry::make('subject_id')->label('ID')->placeholder('-'),
                        self::jsonEntry('old_values', 'Старые значения'),
                        self::jsonEntry('new_values', 'Новые значения'),
                        self::jsonEntry('metadata', 'Дополнительные данные'),
                    ]),
            ]);
    }

    private static function jsonEntry(string $name, string $label): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state(fn ($record): string => empty($record->{$name})
                ? '-'
                : (string) json_encode($record->{$name}, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->copyable()
            ->columnSpanFull()
            ->extraAttributes(['class' => 'font-mono whitespace-pre-wrap break-all']);
    }
}

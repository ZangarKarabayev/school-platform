<?php

namespace App\Filament\Resources\Schools\Schemas;

use App\Models\Terminal;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('district_id')
                    ->label(__('admin.labels.district'))
                    ->relationship('district', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name_ru')
                    ->label(__('admin.labels.name_ru'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_kk')
                    ->label(__('admin.labels.name_kk'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label(__('admin.labels.code'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('bin')
                    ->label(__('admin.labels.bin'))
                    ->maxLength(12)
                    ->unique(ignoreRecord: true),
                TextInput::make('address')
                    ->label(__('admin.labels.address'))
                    ->maxLength(255),
                Select::make('terminal_ids')
                    ->label(__('admin.labels.terminals'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(function ($record): array {
                        $currentSchoolId = $record?->id;
                        $occupiedDeviceIds = Terminal::query()
                            ->whereNotNull('school_id')
                            ->when($currentSchoolId !== null, fn ($query) => $query->where('school_id', '!=', $currentSchoolId))
                            ->whereNotNull('device_id')
                            ->pluck('device_id')
                            ->map(fn ($deviceId) => (string) $deviceId)
                            ->unique()
                            ->all();

                        return Terminal::query()
                            ->orderByRaw('CASE WHEN device_id IS NULL THEN 1 ELSE 0 END')
                            ->orderBy('device_id')
                            ->orderBy('id')
                            ->get()
                            ->filter(function (Terminal $terminal) use ($currentSchoolId, $occupiedDeviceIds): bool {
                                if ($currentSchoolId !== null && $terminal->school_id === $currentSchoolId) {
                                    return true;
                                }

                                if ($terminal->school_id !== null) {
                                    return false;
                                }

                                if ($terminal->device_id === null) {
                                    return true;
                                }

                                return ! in_array((string) $terminal->device_id, $occupiedDeviceIds, true);
                            })
                            ->unique(fn (Terminal $terminal) => $terminal->device_id ?: 'terminal-' . $terminal->id)
                            ->mapWithKeys(fn (Terminal $terminal): array => [
                                $terminal->id => (string) ($terminal->device_id ?? $terminal->id),
                            ])
                            ->all();
                    })
                    ->afterStateHydrated(function (Select $component, $record): void {
                        $component->state($record?->terminals()->pluck('id')->all() ?? []);
                    })
                    ->helperText('Можно выбрать несколько терминалов, но только свободные. Если `device_id` уже привязан к другой школе, он не показывается в списке.'),
                TextInput::make('kitchen_access_token')
                    ->label('Токен кухни')
                    ->required()
                    ->default(fn (): string => Str::random(40))
                    ->minLength(24)
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->suffixAction(
                        Action::make('generateKitchenToken')
                            ->label('Сгенерировать')
                            ->icon('heroicon-m-arrow-path')
                            ->requiresConfirmation()
                            ->modalHeading('Изменить токен кухни?')
                            ->modalDescription('После изменения токена старый QR кухни перестанет работать. Понадобится распечатать или раздать новый QR для этой школы.')
                            ->action(fn (Set $set) => $set('kitchen_access_token', Str::random(40))),
                        isInline: true,
                    )
                    ->helperText('Создаётся и редактируется в админке школы. Этот токен попадает в QR кухни и открывает страницу `/kitchen/{token}`. После изменения токена старый QR кухни перестанет работать.'),
                Placeholder::make('kitchen_qr')
                    ->label('QR кухни')
                    ->content(function ($record): HtmlString|string {
                        if (! $record?->kitchen_access_token) {
                            return 'Сохраните школу, чтобы получить QR кухни.';
                        }

                        $url = route('kitchen.access', $record->kitchen_access_token);

                        return new HtmlString(
                            '<div style="font-size:12px;line-height:1.5;word-break:break-all;color:#4e607d;">' . $url . '</div>'
                        );
                    }),
                Toggle::make('is_active')
                    ->label(__('admin.labels.active'))
                    ->default(true),
            ]);
    }
}
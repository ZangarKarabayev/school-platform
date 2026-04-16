<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Modules\Access\Enums\RoleCode;
use App\Modules\Access\Models\Role;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class UserForm
{
    private static ?array $roleIdsByCode = null;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('last_name')
                    ->label(__('admin.labels.last_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('first_name')
                    ->label(__('admin.labels.first_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('middle_name')
                    ->label(__('admin.labels.middle_name'))
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('admin.labels.phone'))
                    ->tel()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                ToggleButtons::make('status')
                    ->label(__('admin.labels.status'))
                    ->options([
                        'active' => __('admin.status.active'),
                        'inactive' => __('admin.status.inactive'),
                    ])
                    ->default('active')
                    ->inline()
                    ->afterStateHydrated(function (ToggleButtons $component, $state): void {
                        $component->state($state === 'active' ? 'active' : 'inactive');
                    })
                    ->dehydrateStateUsing(fn (?string $state): string => $state === 'active' ? 'active' : 'inactive')
                    ->required(),
                CheckboxList::make('roles')
                    ->label(__('admin.labels.roles'))
                    ->relationship('roles', 'name')
                    ->live()
                    ->afterStateUpdated(function (?array $state, Set $set): void {
                        if (! self::shouldShowSchool($state)) {
                            $set('school_id', null);
                        }

                        if (! self::shouldShowDistrict($state)) {
                            $set('district_id', null);
                        }

                        if (! self::shouldShowRegion($state)) {
                            $set('region_id', null);
                        }
                    })
                    ->columns(2),
                Select::make('school_id')
                    ->label(__('admin.labels.school'))
                    ->relationship('school', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => self::shouldShowSchool($get('roles')) || filled($get('school_id')))
                    ->required(fn (Get $get): bool => self::shouldShowSchool($get('roles'))),
                Select::make('district_id')
                    ->label(__('admin.labels.district'))
                    ->relationship('district', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => self::shouldShowDistrict($get('roles')))
                    ->required(fn (Get $get): bool => self::shouldShowDistrict($get('roles'))),
                Select::make('region_id')
                    ->label(__('admin.labels.region'))
                    ->relationship('region', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->display_name)
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => self::shouldShowRegion($get('roles')))
                    ->required(fn (Get $get): bool => self::shouldShowRegion($get('roles'))),
                Select::make('preferred_locale')
                    ->label(__('admin.labels.language'))
                    ->options([
                        'ru' => __('admin.language.ru'),
                        'kk' => __('admin.language.kk'),
                    ])
                    ->default('ru')
                    ->required(),
            ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeScopeAssignments(array $data): array
    {
        $roles = is_array($data['roles'] ?? null) ? $data['roles'] : [];

        if (! self::shouldShowSchool($roles)) {
            $data['school_id'] = null;
        }

        if (! self::shouldShowDistrict($roles)) {
            $data['district_id'] = null;
        }

        if (! self::shouldShowRegion($roles)) {
            $data['region_id'] = null;
        }

        return $data;
    }

    private static function shouldShowSchool(?array $selectedRoleIds): bool
    {
        if (self::hasAnySelectedRole($selectedRoleIds, [
            RoleCode::SuperAdmin->value,
            RoleCode::SupportAdmin->value,
        ])) {
            return false;
        }

        return self::hasAnySelectedRole($selectedRoleIds, [
            RoleCode::Teacher->value,
            RoleCode::Director->value,
        ]);
    }

    private static function shouldShowDistrict(?array $selectedRoleIds): bool
    {
        if (self::hasAnySelectedRole($selectedRoleIds, [
            RoleCode::SuperAdmin->value,
            RoleCode::SupportAdmin->value,
        ])) {
            return false;
        }

        return self::hasAnySelectedRole($selectedRoleIds, [
            RoleCode::DistrictOperator->value,
        ]);
    }

    private static function shouldShowRegion(?array $selectedRoleIds): bool
    {
        if (self::hasAnySelectedRole($selectedRoleIds, [
            RoleCode::SuperAdmin->value,
            RoleCode::SupportAdmin->value,
        ])) {
            return false;
        }

        return self::hasAnySelectedRole($selectedRoleIds, [
            RoleCode::RegionOperator->value,
        ]);
    }

    /**
     * @param array<int|string>|null $selectedRoleIds
     * @param array<int, string> $roleCodes
     */
    private static function hasAnySelectedRole(?array $selectedRoleIds, array $roleCodes): bool
    {
        if ($selectedRoleIds === null || $selectedRoleIds === []) {
            return false;
        }

        $selectedRoleIds = array_map(static fn ($value): int => (int) $value, $selectedRoleIds);
        $roleIdsByCode = self::roleIdsByCode();
        $expectedRoleIds = array_values(array_intersect_key($roleIdsByCode, array_flip($roleCodes)));

        return count(array_intersect($selectedRoleIds, $expectedRoleIds)) > 0;
    }

    /**
     * @return array<string, int>
     */
    private static function roleIdsByCode(): array
    {
        if (self::$roleIdsByCode !== null) {
            return self::$roleIdsByCode;
        }

        return self::$roleIdsByCode = Role::query()
            ->whereIn('code', array_column(RoleCode::cases(), 'value'))
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code): array => [(string) $code => (int) $id])
            ->all();
    }
}

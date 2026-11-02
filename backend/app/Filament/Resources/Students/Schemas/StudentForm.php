<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\AcademicClass;
use App\Models\MealBenefit;
use App\Models\Student;
use App\Rules\ValidSchoolYear;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                FileUpload::make('photo')
                    ->label('Фото')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('user/photos')
                    ->visibility('public')
                    ->fetchFileInformation(false)
                    ->getUploadedFileUsing(function (BaseFileUpload $component, string $file, string|array|null $storedFileNames, ?Student $record): ?array {
                        if (! $component->getDisk()->exists($file)) {
                            return null;
                        }

                        return [
                            'name' => is_string($storedFileNames) ? $storedFileNames : basename($file),
                            'size' => 0,
                            'type' => null,
                            'url' => $record?->photo === $file
                                ? route('students.photo.show', $record)
                                : url('storage/'.ltrim($file, '/')),
                        ];
                    })
                    ->avatar()
                    ->openable()
                    ->downloadable(),
                DatePicker::make('birth_date')->label(__('admin.labels.birth_date')),
                Select::make('gender')->label(__('admin.labels.gender'))->options([
                    'male' => __('admin.labels.male'),
                    'female' => __('admin.labels.female'),
                ]),
                Select::make('classroom_id')
                    ->label(__('admin.labels.class_full_name'))
                    ->relationship(
                        name: 'classroom',
                        titleAttribute: 'full_name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->where('grade', '>', 0)
                            ->orderBy('grade')
                            ->orderBy('letter'),
                    )
                    ->searchable(['full_name'])
                    ->preload()
                    ->optionsLimit(fn (): int => max(1, AcademicClass::query()->where('grade', '>', 0)->count())),
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
                TextInput::make('school_year')
                    ->label(__('admin.labels.school_year'))
                    ->default('2026-2027')
                    ->required()
                    ->length(9)
                    ->placeholder('2026-2027')
                    ->rules([new ValidSchoolYear]),
                Select::make('meal_benefit_type')
                    ->label(__('admin.labels.status'))
                    ->options(collect(MealBenefit::TYPES)->mapWithKeys(function (string $type): array {
                        $label = __('admin.meal_benefit_types.'.$type);

                        return [
                            $type => $label !== 'admin.meal_benefit_types.'.$type
                                ? $label
                                : str_replace('_', ' ', ucfirst($type)),
                        ];
                    })->all())
                    ->afterStateHydrated(function (Select $component, $record): void {
                        if ($record) {
                            $component->state($record->latestMealBenefit?->type);
                        }
                    }),
                Section::make(__('admin.labels.parents'))
                    ->schema([
                        Repeater::make('parents')
                            ->hiddenLabel()
                            ->addActionLabel(__('admin.labels.add_parent'))
                            ->reorderable(false)
                            ->relationship()
                            ->schema([
                                TextInput::make('full_name')->label(__('admin.labels.parent_full_name'))->maxLength(255),
                                TextInput::make('iin')->label(__('admin.labels.iin'))->nullable()->regex('/^[0-9]{12}$/')->maxLength(12),
                                TextInput::make('phone')->label(__('admin.labels.phone'))->tel()->mask('+7 (999) 999-99-99')->placeholder('+7 (777) 777-77-77')->required()->maxLength(20)->regex('/^(?:\+7|8)(?:[ ()-]*[0-9]){10}$/'),
                            ])
                            ->deleteAction(fn (Action $action): Action => $action->requiresConfirmation()->modalHeading(__('admin.labels.remove_parent_confirm')))
                            ->defaultItems(0)
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}

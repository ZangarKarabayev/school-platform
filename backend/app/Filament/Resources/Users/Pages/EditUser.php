<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Modules\Access\Enums\RoleCode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return UserForm::normalizeScopeAssignments($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetPassword')
                ->label('Сбросить пароль')
                ->icon('heroicon-m-key')
                ->color('warning')
                ->visible(fn (): bool => $this->canResetPasswords())
                ->form([
                    TextInput::make('password')
                        ->label('Новый пароль')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->same('passwordConfirmation'),
                    TextInput::make('passwordConfirmation')
                        ->label('Подтверждение пароля')
                        ->password()
                        ->revealable()
                        ->required()
                        ->dehydrated(false),
                ])
                ->modalHeading('Сброс пароля')
                ->modalDescription('Укажите новый пароль для этого пользователя.')
                ->action(function (array $data): void {
                    if (! $this->canResetPasswords()) {
                        throw new AuthorizationException();
                    }

                    $this->record->forceFill([
                        'password' => $data['password'],
                    ])->save();

                    Notification::make()
                        ->title('Пароль обновлён')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    private function canResetPasswords(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        $user->loadMissing('roles');

        return $user->hasRole(RoleCode::SuperAdmin->value)
            || $user->hasRole(RoleCode::SupportAdmin->value);
    }
}

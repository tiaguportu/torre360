<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ChangePassword extends BaseEditProfile
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    public static function getLabel(): string
    {
        return 'Mudar Senha';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent()
                    ->label('Nova Senha')
                    ->hintAction(
                        Action::make('generatePassword')
                            ->label('Gerar Senha Forte')
                            ->icon('heroicon-m-key')
                            ->action(function (Set $set) {
                                $password = Str::password(16);
                                $set('password', $password);
                                $set('passwordConfirmation', $password);
                            })
                    ),
                $this->getPasswordConfirmationFormComponent()
                    ->label('Confirmar Nova Senha'),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected ?string $plainPassword = null;

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/edit-profile.form.password.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(Password::default())
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->dehydrateStateUsing(function ($state): string {
                $this->plainPassword = (string) $state;

                return Hash::make($state);
            })
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->visible(fn (Get $get): bool => filled($get('password')))
            ->dehydrated(false);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $novaSenhaPura = $this->plainPassword;

        $record = parent::handleRecordUpdate($record, $data);

        if (filled($novaSenhaPura)) {
            // 1. Invalida outras sessões do usuário no guard de autenticação do Laravel
            if (method_exists(auth(), 'logoutOtherDevices')) {
                try {
                    auth()->logoutOtherDevices($novaSenhaPura);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // 2. Defesa em profundidade: revoga fisicamente sessões concorrentes na tabela sessions
            if (config('session.driver') === 'database' && SchemaFacade::hasTable(config('session.table', 'sessions'))) {
                $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;

                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $record->getAuthIdentifier())
                    ->when($currentSessionId, fn ($query) => $query->where('id', '!=', $currentSessionId))
                    ->delete();
            }

            // 3. Atualiza o hash da nova senha na sessão atual para mantê-la autenticada
            if (request()->hasSession()) {
                request()->session()->put([
                    'password_hash_'.Filament::getAuthGuard() => $record->password,
                ]);
            }

            // 4. Auditoria de segurança no canal auth
            activity('auth')
                ->performedOn($record)
                ->causedBy($record)
                ->withProperties([
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'acao' => 'senha_alterada_sessoes_revogadas',
                ])
                ->log("O usuário {$record->name} alterou sua senha. Todas as sessões concorrentes em outros dispositivos foram revogadas.");
        }

        return $record;
    }
}

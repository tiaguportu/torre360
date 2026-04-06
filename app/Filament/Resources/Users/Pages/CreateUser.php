<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Pessoa;
use App\Models\User;
use App\Notifications\WelcomeUserMail;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $data = $this->data;

        if ($data['create_pessoa'] ?? false) {
            /** @var User $user */
            $user = $this->record;

            $pessoa = Pessoa::create([
                'nome' => $user->name,
                'email' => $user->email,
            ]);

            $user->pessoas()->attach($pessoa->id);
        }

        if ($data['send_credentials'] ?? false) {
            /** @var User $user */
            $user = $this->record;
            $password = $data['password'];

            $user->notify(new WelcomeUserMail($password));
        }
    }
}

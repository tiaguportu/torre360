<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Notifications\WelcomeUserMail;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $data = $this->data;

        if ($data['send_credentials'] ?? false) {
            /** @var \App\Models\User $user */
            $user = $this->record;
            $password = $data['password'];

            $user->notify(new WelcomeUserMail($password));
        }
    }
}

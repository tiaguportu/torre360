<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\UserUpdatedMail;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $data = $this->data;

        if ($data['send_credentials'] ?? false) {
            /** @var User $user */
            $user = $this->record;
            $password = $data['password'] ?? null;

            $user->notify(new UserUpdatedMail($password));
        }
    }
}

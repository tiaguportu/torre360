<?php

namespace App\Filament\Resources\EmailLogs\Pages;

use App\Filament\Resources\EmailLogs\EmailLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailLog extends CreateRecord
{
    protected static string $resource = EmailLogResource::class;
}

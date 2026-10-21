<?php

declare(strict_types=1);

namespace App\Filament\Resources\ParecerDescritivos\Pages;

use App\Filament\Resources\ParecerDescritivos\ParecerDescritivoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewParecerDescritivo extends ViewRecord
{
    protected static string $resource = ParecerDescritivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoManutencaos\Pages;

use App\Filament\Resources\PlanoManutencaos\PlanoManutencaoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanoManutencao extends EditRecord
{
    protected static string $resource = PlanoManutencaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

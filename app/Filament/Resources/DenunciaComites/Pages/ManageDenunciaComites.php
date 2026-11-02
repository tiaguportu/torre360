<?php

namespace App\Filament\Resources\DenunciaComites\Pages;

use App\Filament\Resources\DenunciaComites\DenunciaComiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDenunciaComites extends ManageRecords
{
    protected static string $resource = DenunciaComiteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Novo membro')];
    }
}

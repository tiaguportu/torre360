<?php

declare(strict_types=1);

namespace App\Filament\Resources\CicloAvaliacaoDocentes\Pages;

use App\Filament\Resources\CicloAvaliacaoDocentes\CicloAvaliacaoDocenteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCicloAvaliacaoDocente extends EditRecord
{
    protected static string $resource = CicloAvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

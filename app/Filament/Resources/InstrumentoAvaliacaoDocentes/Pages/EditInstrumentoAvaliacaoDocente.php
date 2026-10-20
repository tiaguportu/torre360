<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstrumentoAvaliacaoDocentes\Pages;

use App\Filament\Resources\InstrumentoAvaliacaoDocentes\InstrumentoAvaliacaoDocenteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstrumentoAvaliacaoDocente extends EditRecord
{
    protected static string $resource = InstrumentoAvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstrumentoAvaliacaoDocentes\Pages;

use App\Filament\Resources\InstrumentoAvaliacaoDocentes\InstrumentoAvaliacaoDocenteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInstrumentoAvaliacaoDocente extends CreateRecord
{
    protected static string $resource = InstrumentoAvaliacaoDocenteResource::class;
}

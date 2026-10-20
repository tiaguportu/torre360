<?php

declare(strict_types=1);

namespace App\Filament\Resources\CicloAvaliacaoDocentes\Pages;

use App\Filament\Resources\CicloAvaliacaoDocentes\CicloAvaliacaoDocenteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCicloAvaliacaoDocente extends CreateRecord
{
    protected static string $resource = CicloAvaliacaoDocenteResource::class;
}

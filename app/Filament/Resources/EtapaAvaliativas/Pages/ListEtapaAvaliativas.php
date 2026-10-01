<?php

namespace App\Filament\Resources\EtapaAvaliativas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\EtapaAvaliativas\EtapaAvaliativaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEtapaAvaliativas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = EtapaAvaliativaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('🪜', 'Etapas Avaliativas', 'Divisões do período letivo (bimestres, trimestres etc.).', 'EtapaAvaliativa',
                'Veja nome, período letivo e datas de início e fim.', 'Cadastre uma etapa dentro de um período letivo.', 'Ajuste nome ou datas da etapa.'),
        ];
    }
}

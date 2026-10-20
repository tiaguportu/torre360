<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstrumentoAvaliacaoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\InstrumentoAvaliacaoDocentes\InstrumentoAvaliacaoDocenteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstrumentoAvaliacaoDocentes extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = InstrumentoAvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro(
                '📋',
                'Instrumentos de Avaliação Docente',
                'Roteiros de critérios usados pela coordenação para observar o professor em sala.',
                'InstrumentoAvaliacaoDocente',
                'Veja cada instrumento, a escala de notas, quantos critérios tem e em quantos ciclos já foi usado.',
                'Defina o nome, a escala (1 a 4, 5 ou 10) e os critérios, cada um com um peso.',
                'Altere nome, descrição e situação. Depois que o instrumento for usado em uma avaliação, os critérios ficam congelados para preservar o histórico.',
                dica: 'Para mudar o roteiro de um instrumento já usado, crie um novo instrumento e use-o no próximo ciclo.',
            ),
        ];
    }
}

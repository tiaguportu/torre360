<?php

declare(strict_types=1);

namespace App\Filament\Resources\CicloAvaliacaoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CicloAvaliacaoDocentes\CicloAvaliacaoDocenteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCicloAvaliacaoDocentes extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CicloAvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro(
                '🔄',
                'Ciclos de Avaliação Docente',
                'Períodos em que a coordenação observa os professores, com o instrumento de cada ciclo.',
                'CicloAvaliacaoDocente',
                'Veja os ciclos, o período, o instrumento e quantas avaliações cada um já tem.',
                'Informe o nome (ex.: 1º Semestre 2026), as datas de início e fim e o instrumento de avaliação.',
                'Ajuste nome e datas, ou desative o ciclo para encerrá-lo. O instrumento não pode ser trocado depois que há avaliações.',
                dica: 'Ciclo com avaliações não pode ser excluído: encerre-o em vez disso, e o histórico permanece.',
            ),
        ];
    }
}

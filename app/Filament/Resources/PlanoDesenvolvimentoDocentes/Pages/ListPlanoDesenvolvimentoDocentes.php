<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\PlanoDesenvolvimentoDocenteResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\ListRecords;

class ListPlanoDesenvolvimentoDocentes extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = PlanoDesenvolvimentoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        $professor = auth()->user()?->atuaComoProfessor();

        return [
            $this->ajudaAction('Planos de Desenvolvimento', $professor
                ? HelpContent::make('🚩', 'Meu Plano de Desenvolvimento', 'Combinados com a coordenação para você crescer nos pontos que a avaliação apontou.')
                    ->passos('Como acompanhar', [
                        'Abra o plano e leia o objetivo e as ações. Confirme com "Estou ciente do plano".',
                        'Em cada ação, use "Registrar andamento": marque em andamento e, ao terminar, conclua descrevendo o resultado.',
                        'A coordenação conclui o plano quando todas as ações estiverem encerradas.',
                    ])
                    ->alerta('O plano só aparece para você depois que a coordenação o ativa.')
                : HelpContent::make('🚩', 'Planos de Desenvolvimento', 'Planos individuais criados a partir das avaliações finalizadas.')
                    ->passos('Como conduzir', [
                        'Em Avaliações de Desempenho, abra uma avaliação finalizada e clique em "Criar plano de desenvolvimento": o sistema sugere uma ação para cada critério abaixo de 70% da escala.',
                        'Ajuste o objetivo, a data de revisão e as ações (descrição e prazo). Compare com a autoavaliação do professor, abaixo do formulário.',
                        'Clique em "Ativar plano": o professor passa a vê-lo e a registrar o andamento.',
                        'Acompanhe as ações atrasadas na lista e conclua o plano quando todas estiverem encerradas.',
                    ])
                    ->alerta('Dado sensível: o conteúdo do plano fica cifrado e o professor só o vê depois de ativado. Quem é avaliado não edita nem conclui o próprio plano.')),
        ];
    }
}

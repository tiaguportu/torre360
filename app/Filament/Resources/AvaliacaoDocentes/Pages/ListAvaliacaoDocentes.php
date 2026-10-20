<?php

declare(strict_types=1);

namespace App\Filament\Resources\AvaliacaoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\AvaliacaoDocentes\AvaliacaoDocenteResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvaliacaoDocentes extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = AvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova observação'),
            $this->ajudaAction('Avaliações de Desempenho', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        if ($user->atuaComoProfessor()) {
            return HelpContent::make('📈', 'Minhas Avaliações de Desempenho', 'Veja as avaliações de desempenho em que você foi observado(a) pela coordenação.')
                ->secao('🎯 O que você pode fazer?', [
                    ['📋', 'Listagem', 'Aparecem somente as suas avaliações já finalizadas, com ciclo, data, avaliador e pontuação.'],
                    ['👁️', 'Abrir', 'Veja a nota de cada critério, as observações e o parecer da coordenação.'],
                ])
                ->alerta('Avaliações em rascunho ainda não são visíveis para você, e você não vê as avaliações de outros professores.');
        }

        return HelpContent::make('📈', 'Avaliações de Desempenho', 'Observações da coordenação sobre os professores, em ciclos, com nota por critério e parecer.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja as avaliações por professor, ciclo, data, avaliador, pontuação e situação (rascunho ou finalizada).'],
                $user->can('Create:AvaliacaoDocente') ? ['🆕', 'Nova observação', 'Escolha o ciclo, o professor e a data. O sistema cria uma linha para cada critério do instrumento do ciclo.'] : null,
                $user->can('Update:AvaliacaoDocente') ? ['✏️', 'Avaliar', 'Dê uma nota a cada critério, registre observações e o parecer. O rascunho pode ser salvo e retomado.'] : null,
                $user->can('Finalizar:AvaliacaoDocente') ? ['✅', 'Finalizar', 'Calcula a pontuação (média ponderada, em % da nota máxima) e libera a avaliação para o professor avaliado. Depois disso ela não pode mais ser alterada.'] : null,
                $user->can('Reabrir:AvaliacaoDocente') ? ['🔓', 'Reabrir', 'Devolve uma avaliação finalizada a rascunho para correção; a pontuação é recalculada ao finalizar de novo.'] : null,
            ])
            ->alerta('Dado sensível: o parecer e as observações ficam cifrados. O professor avaliado só vê a avaliação depois de finalizada, e nenhum professor vê as de colegas.')
            ->dica('O histórico de cada professor também aparece na aba "Avaliações de Desempenho" da ficha do funcionário.');
    }
}

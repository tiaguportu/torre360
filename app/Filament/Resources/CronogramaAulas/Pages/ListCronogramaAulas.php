<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListCronogramaAulas extends ListRecords
{
    protected static string $resource = CronogramaAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pendencias')
                ->label('Pendências de Chamada')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->url(fn (): string => CronogramaAulaResource::getUrl('pendencias')),
            Action::make('calendar')
                ->label('Visualizar Calendário')
                ->icon('heroicon-o-calendar')
                ->url(fn (): string => CronogramaAulaResource::getUrl('calendar')),
            Action::make('verificaConflitos')
                ->label('Verificar Conflitos')
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->url(fn (): string => CronogramaAulaResource::getUrl('verifica-conflitos'))
                ->visible(fn () => auth()->user()->can('VerificaConflitos:CronogramaAula')),
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cronograma de Aulas')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $activeRole = session('active_role');

        $canCreate = $user->can('Create:CronogramaAula');
        $canConflitos = $user->can('VerificaConflitos:CronogramaAula');

        $html = '<p>Nesta página você gerencia o horário semanal de aulas da instituição.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Visualizar Grade:</strong> Acompanhe o horário de cada disciplina, professor e turma.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Horário:</strong> Registre um novo horário de aula definindo o dia da semana, turno e período.</li>';
        }

        if ($canConflitos) {
            $html .= '<li><strong>Verificar Conflitos:</strong> Utilize o botão vermelho para rodar uma análise e descobrir se há professores ou salas ocupados no mesmo horário em turmas diferentes.</li>';
        }

        $html .= '<li><strong>Calendário:</strong> Veja uma visão visual (estilo agenda) de todos os horários clicando em "Visualizar Calendário".</li>';
        $html .= '<li><strong>Frequência:</strong> Professores podem utilizar as ações de tabela para lançar a chamada diretamente a partir do cronograma.</li>';
        $html .= '</ul>';

        return $html;
    }
}

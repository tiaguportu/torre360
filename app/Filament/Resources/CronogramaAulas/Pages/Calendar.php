<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\Page;

class Calendar extends Page
{
    protected static string $resource = CronogramaAulaResource::class;

    protected string $view = 'filament.resources.cronograma-aulas.pages.calendar-refactored';

    protected static ?string $title = 'Calendário de Aulas';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Calendário de Aulas')
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

        $html = '<p>Nesta página você visualiza a grade e o calendário de aulas e avaliações agendadas.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Navegar pelo calendário:</strong> Alterne entre as visualizações por mês, semana ou dia através dos botões no topo do calendário.</li>';
        $html .= '<li><strong>Filtrar eventos:</strong> Utilize os filtros de turmas, disciplinas e professores para localizar aulas e avaliações específicas.</li>';
        $html .= '<li><strong>Detalhes da aula:</strong> Passe o cursor sobre o evento para visualizar a disciplina, professor, horários e conteúdo ministrado.</li>';

        if ($user && $user->can('View:CronogramaAula')) {
            $html .= '<li><strong>Visualizar registro:</strong> Clique em um evento de aula para abrir a tela com os detalhes completos.</li>';
        }

        $html .= '</ul>';

        if ($activeRole === 'responsavel') {
            $html .= '<p class="text-sm text-gray-500 mt-2"><strong>Aviso:</strong> Você está visualizando apenas os horários e eventos das turmas vinculadas aos seus dependentes.</p>';
        } elseif ($activeRole === 'professor') {
            $html .= '<p class="text-sm text-gray-500 mt-2"><strong>Aviso:</strong> Você está visualizando apenas as aulas e avaliações sob sua responsabilidade docente.</p>';
        }

        return $html;
    }
}

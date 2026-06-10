<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListPreceptorias extends ListRecords
{
    protected static string $resource = PreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agendar')
                ->label('Agendar Preceptoria')
                ->color('info')
                ->icon('heroicon-o-calendar-date-range')
                ->url(fn () => $this->getResource()::getUrl('agendar'))
                ->visible(fn () => auth()->user()->can('Agendar:Preceptoria')),

            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Preceptorias')
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

        $canCreate = $user->can('Create:Preceptoria');
        $canAgendar = $user->can('Agendar:Preceptoria');

        $html = '<p>Nesta página você acompanha e gerencia os atendimentos de preceptoria.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Visualizar Atendimentos:</strong> Veja a lista de preceptorias, datas, professores e alunos vinculados.</li>';

        if ($canAgendar) {
            $html .= '<li><strong>Agendar Preceptoria:</strong> Clique no botão azul para abrir a tela de agendamento simplificado.</li>';
        }

        if ($canCreate) {
            $html .= '<li><strong>Criar Horários:</strong> Utilize o botão de cadastro para liberar novos horários na agenda dos professores.</li>';
        }

        if ($activeRole === 'professor') {
            $html .= '<li><strong>Meus Atendimentos:</strong> Você verá apenas os horários em que você é o preceptor.</li>';
        }

        $html .= '<li><strong>Ações de Tabela:</strong> Utilize os ícones na tabela para registrar o que foi conversado ou visualizar o histórico.</li>';
        $html .= '</ul>';

        return $html;
    }
}

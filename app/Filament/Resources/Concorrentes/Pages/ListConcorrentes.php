<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concorrentes\Pages;

use App\Filament\Resources\Concorrentes\ConcorrenteResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListConcorrentes extends ListRecords
{
    protected static string $resource = ConcorrenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Concorrentes & Battlecards')
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

        $html = '<p>A tela de <strong>Concorrentes & Battlecards</strong> gerencia a inteligência competitiva da escola, mapeando as outras instituições da região para subsidiar a equipe comercial.</p>';
        $html .= '<h3>Funcionalidades disponíveis:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Mapeamento de Escolas:</strong> Registre colégios concorrentes, localização, linha pedagógica e estimativa de mensalidade.</li>';
        $html .= '<li><strong>🛡️ Battlecards de Vendas:</strong> Em cada escola, cadastre os pontos fortes deles (o que a família elogia), as vulnerabilidades conhecidas e os <strong>diferenciais matadores da nossa escola</strong> para virar o jogo.</li>';
        $html .= '<li><strong>Radar de Perdas:</strong> Acompanhe quantas famílias foram perdidas para cada colégio e quais foram os fatores determinantes (Preço, Proximidade, Proposta Pedagógica, etc.).</li>';

        if ($user && $user->can('Create:Concorrente')) {
            $html .= '<li><strong>Cadastrar Concorrente:</strong> Clique no botão superior "+ Novo Concorrente" para cadastrar uma nova escola e seus diferenciais.</li>';
        }

        $html .= '</ul>';
        $html .= '<p><strong>Dica Comercial:</strong> Mantenha os diferenciais da nossa escola sempre atualizados para que os consultores tenham argumentos fortes e éticos durante o atendimento.</p>';

        return $html;
    }
}

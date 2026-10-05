<?php

declare(strict_types=1);

namespace App\Filament\Resources\Objecoes\Pages;

use App\Filament\Resources\Objecoes\ObjecaoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListObjecoes extends ListRecords
{
    protected static string $resource = ObjecaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Matriz de Objeções Comerciais')
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

        $html = '<p>A <strong>Matriz de Objeções Comerciais</strong> é o repositório oficial de roteiros e argumentos para preparar a equipe de atendimento a contornar dúvidas e resistências das famílias.</p>';
        $html .= '<h3>O que você encontra aqui:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Categorias Estratégicas:</strong> Dúvidas separadas por Preço, Distância, Método Pedagógico, Estrutura e Vagas.</li>';
        $html .= '<li><strong>Roteiros Testados:</strong> Respostas prontas focadas em agregar valor educacional e transmitir acolhimento e autoridade.</li>';
        $html .= '<li><strong>Perguntas de Ouro:</strong> Perguntas reflexivas para que os pais pensem no futuro do aluno e avancem para a matrícula.</li>';

        if ($user && $user->can('Create:Objecao')) {
            $html .= '<li><strong>Cadastrar Nova Objeção:</strong> Clique no botão "+ Nova Objeção" para registrar novas dúvidas observadas no atendimento.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}

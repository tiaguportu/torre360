<?php

declare(strict_types=1);

namespace App\Filament\Resources\Objecoes\Pages;

use App\Filament\Resources\Objecoes\ObjecaoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateObjecao extends CreateRecord
{
    protected static string $resource = ObjecaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Objeção')
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
        $html = '<p>Nesta tela você cadastra uma nova objeção para orientar os consultores.</p>';
        $html .= '<h3>Dicas de preenchimento:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Título & Categoria:</strong> Seja claro sobre o foco da objeção (ex: Preço, Turno Integral).</li>';
        $html .= '<li><strong>Como a família fala:</strong> Use as palavras reais que os pais costumam dizer no atendimento.</li>';
        $html .= '<li><strong>Resposta Sugerida:</strong> Escreva um texto natural, acolhedor e com gatilhos de segurança e valor.</li>';
        $html .= '<li><strong>Pergunta de Ouro:</strong> Formule uma pergunta aberta para devolver a reflexão aos pais.</li>';
        $html .= '</ul>';

        return $html;
    }
}

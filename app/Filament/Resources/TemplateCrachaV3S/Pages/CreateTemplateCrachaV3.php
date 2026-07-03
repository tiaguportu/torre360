<?php

namespace App\Filament\Resources\TemplateCrachaV3S\Pages;

use App\Filament\Resources\TemplateCrachaV3S\TemplateCrachaV3Resource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplateCrachaV3 extends CreateRecord
{
    protected static string $resource = TemplateCrachaV3Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Template de Crachá V3')
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
        $html = '<p>Nesta página você cria um novo template de crachá V3 usando o editor Moveable.</p>';
        $html .= '<h3>Como criar um template:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Nome do Template:</strong> Informe um nome descritivo para identificar o crachá.</li>';
        $html .= '<li><strong>Tipo de Entidade:</strong> Selecione se o crachá é para uma Pessoa genérica ou vinculado a uma Turma (com dados do aluno).</li>';
        $html .= '<li><strong>Dimensões:</strong> Defina a largura e altura em pixels. O editor converterá automaticamente para mm.</li>';
        $html .= '<li><strong>Salvar e Editar:</strong> Após salvar, use o botão "Editar Canvas do Crachá V3" para abrir o editor Moveable em uma nova aba.</li>';
        $html .= '</ul>';

        return $html;
    }
}

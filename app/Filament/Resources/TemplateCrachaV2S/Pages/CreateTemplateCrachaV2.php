<?php

namespace App\Filament\Resources\TemplateCrachaV2S\Pages;

use App\Filament\Resources\TemplateCrachaV2S\TemplateCrachaV2Resource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplateCrachaV2 extends CreateRecord
{
    protected static string $resource = TemplateCrachaV2Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Template de Crachá V2')
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
        $html = '<p>Nesta página você pode criar um novo modelo de crachá versão 2 (V2).</p>';
        $html .= '<h3>Instruções:</h3>';
        $html .= '<ol>';
        $html .= '<li>Preencha o <strong>Nome</strong> do template.</li>';
        $html .= '<li>Escolha o <strong>Tipo de Entidade</strong> (Pessoa ou Turma). Isso define quais variáveis estarão disponíveis no editor gráfico.</li>';
        $html .= '<li>Defina as dimensões de <strong>Largura</strong> e <strong>Altura</strong> em pixels (px).</li>';
        $html .= '<li>Clique em <strong>Criar</strong>. Após criar, você poderá abrir o editor gráfico para desenhar o crachá e inserir os campos dinâmicos.</li>';
        $html .= '</ol>';

        return $html;
    }
}

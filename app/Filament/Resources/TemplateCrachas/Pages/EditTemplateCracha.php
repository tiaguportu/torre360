<?php

namespace App\Filament\Resources\TemplateCrachas\Pages;

use App\Filament\Resources\TemplateCrachas\TemplateCrachaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditTemplateCracha extends EditRecord
{
    protected static string $resource = TemplateCrachaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Template de Crachá')
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
        $html = '<p>Edite as configurações do template de crachá e desenhe o layout interativo.</p>';
        $html .= '<h3>Editor Visual de Crachá:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Adicionar Variáveis:</strong> Use os botões do painel para colocar caixas com `{nome}`, `{profissao}`, etc. que serão substituídas com dados reais ao imprimir.</li>';
        $html .= '<li><strong>Imagem de Fundo:</strong> Carregue uma imagem local no canvas para servir de plano de fundo do crachá.</li>';
        $html .= '<li><strong>Manipulação com o Mouse:</strong> Arraste os elementos para reposicionar, e use os cantos para redimensionar ou rotacionar.</li>';
        $html .= '<li><strong>Formatação de Texto:</strong> Ao selecionar um texto, você pode alterar sua cor, tamanho, alinhamento e estilos (negrito/itálico).</li>';
        $html .= '</ul>';

        return $html;
    }
}

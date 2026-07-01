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
        $html .= '<h3>Conversão de Pixels (px) para Milímetros (mm) na Impressão:</h3>';
        $html .= '<p>O PDF gerado é dimensionado para papel <strong>A4</strong>. Para obter tamanhos físicos exatos no papel, multiplique a medida em milímetros por <strong>3.7795</strong> para descobrir os pixels correspondentes:</p>';
        $html .= '<ul>';
        $html .= '<li><strong>Tamanho Padrão (54 mm x 86 mm):</strong> Configure como <strong>204 px</strong> de largura e <strong>325 px</strong> de altura.</li>';
        $html .= '<li><strong>Tamanho Médio (80 mm x 110 mm):</strong> Configure como <strong>302 px</strong> de largura e <strong>416 px</strong> de altura.</li>';
        $html .= '<li><strong>Tamanho Grande (90 mm x 130 mm):</strong> Configure como <strong>340 px</strong> de largura e <strong>491 px</strong> de altura.</li>';
        $html .= '</ul>';
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

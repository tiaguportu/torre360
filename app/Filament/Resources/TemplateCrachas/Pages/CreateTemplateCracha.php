<?php

namespace App\Filament\Resources\TemplateCrachas\Pages;

use App\Filament\Resources\TemplateCrachas\TemplateCrachaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplateCracha extends CreateRecord
{
    protected static string $resource = TemplateCrachaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Template de Crachá')
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
        $html = '<p>Nesta página você pode criar um novo template de crachá definindo seu nome e dimensões em pixels.</p>';
        $html .= '<h3>Conversão de Pixels (px) para Milímetros (mm) na Impressão:</h3>';
        $html .= '<p>O PDF gerado é dimensionado para papel <strong>A4</strong>. Para obter tamanhos físicos exatos no papel, multiplique a medida em milímetros por <strong>3.7795</strong> para descobrir os pixels correspondentes:</p>';
        $html .= '<ul>';
        $html .= '<li><strong>Tamanho Padrão (54 mm x 86 mm):</strong> Configure como <strong>204 px</strong> de largura e <strong>325 px</strong> de altura.</li>';
        $html .= '<li><strong>Tamanho Médio (80 mm x 110 mm):</strong> Configure como <strong>302 px</strong> de largura e <strong>416 px</strong> de altura.</li>';
        $html .= '<li><strong>Tamanho Grande (90 mm x 130 mm):</strong> Configure como <strong>340 px</strong> de largura e <strong>491 px</strong> de altura.</li>';
        $html .= '</ul>';
        $html .= '<h3>Instruções de Preenchimento:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Nome do Template:</strong> Identificação amigável (ex: "Crachá Estudantil 2026").</li>';
        $html .= '<li><strong>Largura e Altura:</strong> O tamanho em pixels para a área de edição. O padrão sugerido é 300px por 480px (vertical).</li>';
        $html .= '<li><strong>Editor de Layout:</strong> Após salvar as informações básicas, você poderá posicionar caixas de texto com o mouse, alterar cores e carregar uma imagem de fundo para o crachá.</li>';
        $html .= '</ul>';

        return $html;
    }
}

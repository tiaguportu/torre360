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
        $html .= '<h3>Instruções de Preenchimento:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Nome do Template:</strong> Identificação amigável (ex: "Crachá Estudantil 2026").</li>';
        $html .= '<li><strong>Largura e Altura:</strong> O tamanho em pixels para a área de edição. O padrão sugerido é 300px por 480px (vertical).</li>';
        $html .= '<li><strong>Editor de Layout:</strong> Após salvar as informações básicas, você poderá posicionar caixas de texto com o mouse, alterar cores e carregar uma imagem de fundo para o crachá.</li>';
        $html .= '</ul>';

        return $html;
    }
}

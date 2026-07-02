<?php

namespace App\Filament\Resources\TemplateCrachaV2S\Pages;

use App\Filament\Resources\TemplateCrachaV2S\TemplateCrachaV2Resource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditTemplateCrachaV2 extends EditRecord
{
    protected static string $resource = TemplateCrachaV2Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Template de Crachá V2')
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
        $html = '<p>Nesta página você pode alterar as configurações básicas do template de crachá e acessar o editor gráfico.</p>';
        $html .= '<h3>Recursos Disponíveis:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Configurações do Crachá V2:</strong> Permite alterar o nome e as dimensões (largura/altura).</li>';
        $html .= '<li><strong>Editor de Canvas:</strong> No formulário abaixo, utilize o botão "Editar Canvas do Crachá V2" para abrir o editor gráfico em uma nova aba.</li>';
        $html .= '<li><strong>SVG-Edit:</strong> Dentro do editor gráfico, utilize a barra lateral para inserir tags dinâmicas como o nome e a foto do aluno, posicionando-os livremente.</li>';
        $html .= '</ul>';

        return $html;
    }
}

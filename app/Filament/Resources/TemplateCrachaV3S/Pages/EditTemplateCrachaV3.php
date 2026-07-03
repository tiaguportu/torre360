<?php

namespace App\Filament\Resources\TemplateCrachaV3S\Pages;

use App\Filament\Resources\TemplateCrachaV3S\TemplateCrachaV3Resource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditTemplateCrachaV3 extends EditRecord
{
    protected static string $resource = TemplateCrachaV3Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Template de Crachá V3')
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
        $html = '<p>Nesta página você pode alterar as configurações básicas do template de crachá V3 e acessar o editor gráfico Moveable.</p>';
        $html .= '<h3>Recursos Disponíveis:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Configurações do Crachá V3:</strong> Permite alterar o nome, tipo de entidade e as dimensões (largura/altura).</li>';
        $html .= '<li><strong>Editor de Canvas:</strong> No formulário abaixo, utilize o botão "Editar Canvas do Crachá V3" para abrir o editor Moveable em uma nova aba.</li>';
        $html .= '<li><strong>Moveable:</strong> Dentro do editor, arraste, redimensione e rotacione elementos. Use o painel lateral para inserir variáveis dinâmicas da entidade selecionada.</li>';
        $html .= '</ul>';

        return $html;
    }
}

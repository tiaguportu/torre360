<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditUnidade extends EditRecord
{
    protected static string $resource = UnidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Unidade Escolar')
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

        $canUpdate = $user->can('Update:Unidade');
        $canDelete = $user->can('Delete:Unidade');

        $html = '<p>Nesta página você pode editar as informações da Unidade Escolar selecionada.</p>';

        if ($canUpdate) {
            $html .= '<h3>Edição de Dados</h3>';
            $html .= '<ul>';
            $html .= '<li>Altere a situação de funcionamento (Em atividade, Paralisada, Extinta), Código INEP ou dados de localização.</li>';
            $html .= '<li>Atualize telefone no formato (99)99999-999, e-mail e mantenedores vinculados.</li>';
            $html .= '</ul>';
        }

        if ($canDelete) {
            $html .= '<h3>Exclusão</h3>';
            $html .= '<ul>';
            $html .= '<li><strong>Excluir:</strong> Permite remover a unidade caso não possua turmas ou dependências associadas.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}

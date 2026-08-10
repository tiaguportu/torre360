<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\InteressadoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditInteressado extends EditRecord
{
    protected static string $resource = InteressadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Interessado')
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

        $html = '<p>Nesta página você pode editar os dados de um interessado (lead) no sistema CRM.</p>';
        $html .= '<h3>O que você pode fazer:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Dados do Negócio:</strong> Atualize o status, origem, consultor responsável, temperatura e valor estimado.</li>';
        $html .= '<li><strong>Resumo:</strong> Visualize os dias no funil, total de contatos e temperatura calculada automaticamente.</li>';
        $html .= '<li><strong>Dependentes:</strong> Gerencie os alunos vinculados ao interessado.</li>';
        $html .= '<li><strong>Histórico:</strong> Na aba inferior, registre e visualize todas as interações com este lead.</li>';

        if ($user->can('Delete:Interessado')) {
            $html .= '<li><strong>Excluir:</strong> Use o botão vermelho "Excluir" para remover o lead.</li>';
        }

        $html .= '</ul>';
        $html .= '<p><strong>Dica:</strong> Mantenha o campo "Próximo Contato" sempre atualizado. O sistema alerta automaticamente quando o contato está atrasado.</p>';

        return $html;
    }
}

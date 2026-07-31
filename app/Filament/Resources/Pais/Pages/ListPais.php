<?php

namespace App\Filament\Resources\Pais\Pages;

use App\Filament\Resources\Pais\PaisResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListPais extends ListRecords
{
    protected static string $resource = PaisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastros de Países')
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

        $canCreate = $user->can('Create:Pais');
        $canUpdate = $user->can('Update:Pais');

        $html = '<p>Gerenciamento da base de países cadastrados no sistema para nacionalidade e dados geográficos.</p>';
        $html .= '<h3>Funcionalidades:</h3>';
        $html .= '<ul>';

        if ($canCreate) {
            $html .= '<li><strong>Cadastrar País:</strong> Clique em "Novo país" para adicionar um novo país à base e informar seu código INEP.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Altere o nome, sigla e o código INEP oficial do país.</li>';
        }

        $html .= '<li><strong>Visualizar Código INEP:</strong> A tabela exibe o código oficial utilizado nas exportações do Educacenso.</li>';
        $html .= '</ul>';

        return $html;
    }
}

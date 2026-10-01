<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Usuários')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'usuarios-cadastro')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>Cada pessoa que acessa o Torre360 precisa de um <strong>usuário próprio</strong>, com um <strong>papel (role)</strong> que define exatamente o que ela pode ver e fazer no sistema.</p>';
        $html .= '<h4>Como cadastrar um novo usuário:</h4>';
        $html .= '<ol>';
        $html .= '<li>Clique em <em>"Criar Usuário"</em> e preencha nome e e-mail.</li>';
        $html .= '<li>Gere uma senha forte automaticamente pelo botão ao lado do campo Senha, ou digite uma própria.</li>';
        $html .= '<li>Escolha o(s) <strong>Papel (Role)</strong> — Professor, Secretaria, Coordenador, etc. — que define as permissões do usuário.</li>';
        $html .= '<li>Opcionalmente, vincule o usuário a uma Pessoa já cadastrada, ou marque para criar uma automaticamente.</li>';
        $html .= '</ol>';
        $html .= '<p>Use o toggle <em>"Enviar informações de acesso"</em> para notificar a pessoa por e-mail com os dados de login.</p>';

        return $html;
    }
}

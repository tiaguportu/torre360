<?php

namespace App\Filament\Resources\Rematriculas\Pages;

use App\Filament\Resources\Rematriculas\RematriculaResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListRematriculas extends ListRecords
{
    protected static string $resource = RematriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Acompanhamento de Rematrículas')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'rematriculas-lista')->orderBy('ordem')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $canUpdate = $user->can('Update:Rematricula');

        $html = '<p>Nesta página a secretaria acompanha em tempo real o andamento das <strong>Rematrículas Escolares</strong> realizadas pelos pais no Portal da Família.</p>';
        $html .= '<h4>Funcionalidades:</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Status dos Alunos:</strong> Veja quem já iniciou a rematrícula, quem confirmou dados, quem está aguardando assinatura de contrato ou já teve a rematrícula concluída.</li>';
        if ($canUpdate) {
            $html .= '<li><strong>Efetivar Rematrícula:</strong> Acione o botão <em>"Efetivar Rematrícula"</em> na linha do aluno para gerar a nova matrícula ativa no próximo período e o contrato automaticamente.</li>';
        }
        $html .= '<li><strong>Ver Contrato:</strong> Acesse o contrato gerado com as faturas e dados de assinatura digital.</li>';
        $html .= '</ul>';

        return $html;
    }
}

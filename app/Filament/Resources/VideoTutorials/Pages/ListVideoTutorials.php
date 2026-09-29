<?php

namespace App\Filament\Resources\VideoTutorials\Pages;

use App\Filament\Resources\VideoTutorials\VideoTutorialResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListVideoTutorials extends ListRecords
{
    protected static string $resource = VideoTutorialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Central de Vídeos Tutoriais')
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
        $canCreate = $user->can('Create:VideoTutorial');

        $html = '<p>Esta é a <strong>Central de Ajuda</strong> do Torre360: aqui ficam os vídeos curtos que explicam como usar as principais funcionalidades do sistema.</p>';
        $html .= '<h3>Como usar?</h3><ul>';
        $html .= '<li><strong>Assistir:</strong> Clique para abrir o vídeo direto no navegador, sem precisar baixar nada.</li>';
        $html .= '<li><strong>Baixar / Abrir Link:</strong> Baixa o arquivo de vídeo (ou abre o link externo, quando o vídeo estiver hospedado fora do sistema).</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Vídeo:</strong> Envie um arquivo de vídeo (até 40MB) ou informe um link do YouTube/Vimeo. Se marcar uma "Tela relacionada", o vídeo também aparece no botão de Ajuda daquela tela.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}

<?php

namespace App\Filament\Resources\CicloPreceptorias\Pages;

use App\Filament\Resources\CicloPreceptorias\CicloPreceptoriaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateCicloPreceptoria extends CreateRecord
{
    protected static string $resource = CicloPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Ciclo de Preceptoria')
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
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Preencha os campos abaixo para cadastrar um novo registro de <strong>Ciclo de Preceptoria</strong>.</p>';

        if ($user && $user->can('Create:CicloPreceptoria')) {
            $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Instruções de Preenchimento:</h4>';
            $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
            $html .= '<li>Preencha todos os campos obrigatórios sinalizados antes de submeter.</li>';
            $html .= '<li>Utilize <strong>Criar</strong> para salvar o registro ou <strong>Criar e criar outro</strong> para novos cadastros contínuos.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Candidatos\Pages;

use App\Filament\Resources\Candidatos\CandidatoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateCandidato extends CreateRecord
{
    protected static string $resource = CandidatoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['cadastrado_por_user_id'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('Create:Candidato')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-4 text-sm">
                <h3 class="font-bold text-base text-gray-900 dark:text-gray-100">Cadastro de Candidato ao Banco de Talentos</h3>
                <p class="text-gray-600 dark:text-gray-400">Nesta tela você cadastra novos interessados em trabalhar na instituição escolar.</p>

                <div class="space-y-2">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200">Como usar a IA nesta página:</h4>
                    <ul class="list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-400">
                        <li><strong>Extração de Currículo:</strong> Anexe o currículo em PDF ou Imagem e clique em <em>"Interpretar Currículo com IA ✨"</em>. O sistema preenche nome, pretensão de vaga, escola atual, local de moradia, pretensão salarial e extrai a foto do candidato automaticamente.</li>
                        <li><strong>Áudios de Entrevista (WhatsApp):</strong> Anexe a gravação de áudio do entrevistador e clique em <em>"Interpretar Áudio com IA 🎙️✨"</em> para resumir as impressões e complementar dados cadastrais.</li>
                    </ul>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Os arquivos originais ficam arquivados com segurança para fins de auditoria e conformidade.</p>
            </div>
        ';
    }
}

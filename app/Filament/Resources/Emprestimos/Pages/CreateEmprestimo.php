<?php

namespace App\Filament\Resources\Emprestimos\Pages;

use App\Filament\Resources\Emprestimos\EmprestimoResource;
use App\Models\Emprestimo;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEmprestimo extends CreateRecord
{
    protected static string $resource = EmprestimoResource::class;

    protected function handleRecordCreation(array $data): Emprestimo
    {
        try {
            return Emprestimo::emprestar(
                (int) $data['livro_id'],
                (int) $data['matricula_id'],
                $data['data_emprestimo'] ?? null,
                $data['data_prevista_devolucao'] ?? null,
            );
        } catch (\DomainException $e) {
            Notification::make()
                ->danger()
                ->title('Empréstimo não registrado')
                ->body($e->getMessage())
                ->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Empréstimo')
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
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Preencha os campos abaixo para cadastrar um novo registro de <strong>Empréstimo</strong>.</p>';

        if ($user && $user->can('Create:Emprestimo')) {
            $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Instruções de Preenchimento:</h4>';
            $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
            $html .= '<li>Preencha todos os campos obrigatórios sinalizados antes de submeter.</li>';
            $html .= '<li>Utilize <strong>Criar</strong> para salvar o registro ou <strong>Criar e criar outro</strong> para novos cadastros contínuos.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}

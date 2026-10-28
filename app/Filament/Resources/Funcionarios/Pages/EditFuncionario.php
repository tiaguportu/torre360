<?php

namespace App\Filament\Resources\Funcionarios\Pages;

use App\Filament\Resources\Funcionarios\FuncionarioResource;
use App\Models\Funcionario;
use App\Services\VinculoFuncionarioService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFuncionario extends EditRecord
{
    protected static string $resource = FuncionarioResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset(
            $data['data_admissao'],
            $data['data_desligamento'],
            $data['data_admissao_display'],
            $data['data_desligamento_display'],
            $data['salario_inicial']
        );

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('corrigir_admissao')
                ->label('Corrigir data de admissão')
                ->icon('heroicon-o-calendar')
                ->color('warning')
                ->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->getRecord()) && $this->getRecord()->isAtivo())
                ->modalHeading('Corrigir data de admissão')
                ->modalDescription('Altera a data oficial de admissão do funcionário e alinha o início da primeira vigência contratual. A retificação gera registro de auditoria permanente.')
                ->modalSubmitActionLabel('Salvar nova data')
                ->form([
                    DatePicker::make('nova_data')
                        ->label('Nova data de admissão')
                        ->native(false)
                        ->default(fn (): ?\Illuminate\Support\Carbon => $this->getRecord()->data_admissao)
                        ->required(),
                    Textarea::make('justificativa')
                        ->label('Justificativa')
                        ->required()
                        ->minLength(10)
                        ->helperText('Informe o motivo da retificação (não registre dados de saúde ou de processo trabalhista).')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    /** @var Funcionario $funcionario */
                    $funcionario = $this->getRecord();

                    try {
                        $resultado = app(VinculoFuncionarioService::class)->corrigirDataAdmissao(
                            $funcionario,
                            $data['nova_data'],
                            $data['justificativa'],
                            auth()->user()
                        );
                    } catch (\DomainException $e) {
                        Notification::make()
                            ->title('Não foi possível corrigir a data de admissão')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    if (! empty($resultado['avisos'])) {
                        Notification::make()
                            ->title('Data de admissão corrigida com avisos')
                            ->body(implode(' ', $resultado['avisos']))
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Data de admissão corrigida com sucesso')
                            ->success()
                            ->send();
                    }

                    $this->redirect(FuncionarioResource::getUrl('edit', ['record' => $funcionario]));
                }),
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Funcionário')
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
        /** @var Funcionario $record */
        $record = $this->getRecord();

        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Nesta tela você pode revisar e atualizar os dados do registro selecionado de <strong>Funcionário</strong>.</p>';
        $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Ações disponíveis:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';

        if ($user && $user->can('Update:Funcionario')) {
            $html .= '<li><strong>Salvar alterações:</strong> Grava no sistema as modificações feitas nos campos do formulário (cargo, regime, carga horária, unidade). As datas de admissão e desligamento não são editadas diretamente no formulário.</li>';
            if ($record->isAtivo()) {
                $html .= '<li><strong>Corrigir data de admissão:</strong> Permite retificar formalmente a data de admissão do funcionário ativo com registro de justificativa em log de auditoria, atualizando também o início da primeira vigência contratual.</li>';
            }
        }

        if ($user && $user->can('Delete:Funcionario')) {
            $html .= '<li><strong>Excluir:</strong> Permite remover este registro caso não possua vínculos dependentes.</li>';
        }

        if ($user && $user->can('View:Funcionario')) {
            $html .= '<li><strong>Visualizar:</strong> Exibe a ficha completa com os dados salvos deste registro.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}

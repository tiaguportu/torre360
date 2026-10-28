<?php

namespace App\Filament\Resources\Funcionarios\Pages;

use App\Filament\Resources\Funcionarios\FuncionarioResource;
use App\Models\ContratoTrabalho;
use App\Models\Funcionario;
use App\Services\VinculoFuncionarioService;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateFuncionario extends CreateRecord
{
    protected static string $resource = FuncionarioResource::class;

    protected ?string $salarioInicial = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->salarioInicial = isset($data['salario_inicial']) && filled($data['salario_inicial'])
            ? (string) $data['salario_inicial']
            : null;

        unset($data['salario_inicial']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            /** @var Funcionario $funcionario */
            $funcionario = static::getModel()::create($data);

            if ($this->salarioInicial !== null && auth()->user()?->can('create', ContratoTrabalho::class)) {
                app(VinculoFuncionarioService::class)->abrirContratoInicial(
                    $funcionario,
                    $this->salarioInicial,
                    auth()->user()
                );
            }

            return $funcionario;
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Funcionário')
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
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Preencha os campos abaixo para cadastrar um novo registro de <strong>Funcionário</strong>.</p>';

        if ($user && $user->can('Create:Funcionario')) {
            $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Instruções de Preenchimento:</h4>';
            $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
            $html .= '<li>Preencha a pessoa, o cargo, o regime de contratação e a <strong>data de admissão</strong> obrigatória.</li>';
            if ($user->can('Create:ContratoTrabalho')) {
                $html .= '<li><strong>Salário inicial (opcional):</strong> Cria automaticamente o primeiro contrato de trabalho com início na data de admissão. Reajustes posteriores são feitos por aditivo na aba Contratos de Trabalho.</li>';
            }
            $html .= '<li>A data de desligamento não é preenchida aqui: ela é registrada automaticamente ao concluir um processo de desligamento.</li>';
            $html .= '<li>Utilize <strong>Criar</strong> para salvar o registro ou <strong>Criar e criar outro</strong> para novos cadastros contínuos.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}

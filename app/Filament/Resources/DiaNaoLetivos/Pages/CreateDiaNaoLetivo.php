<?php

namespace App\Filament\Resources\DiaNaoLetivos\Pages;

use App\Filament\Resources\DiaNaoLetivos\DiaNaoLetivoResource;
use App\Models\DiaNaoLetivo;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateDiaNaoLetivo extends CreateRecord
{
    protected static string $resource = DiaNaoLetivoResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();

        if (! isset($data['data_fim']) || ! $data['data_fim']) {
            return;
        }

        $periodoLetivoId = $data['periodo_letivo_id'];
        $descricaoBase = $data['descricao'];
        $flagAtivo = $data['flag_ativo'];
        $dataInicio = Carbon::parse($data['data'])->addDay();
        $dataFim = Carbon::parse($data['data_fim']);

        $currentDate = $dataInicio;

        while ($currentDate->lessThanOrEqualTo($dataFim)) {
            // Verifica a descrição para cada dia específico se for fim de semana ou feriado
            $descricao = $descricaoBase;
            if ($currentDate->isSaturday()) {
                $descricao = 'Sábado';
            } elseif ($currentDate->isSunday()) {
                $descricao = 'Domingo';
            } else {
                $feriado = DiaNaoLetivo::getFeriadoNacional($currentDate);
                if ($feriado) {
                    $descricao = $feriado;
                }
            }

            // Cria o registro se não existir um para a mesma data no mesmo período letivo
            // para evitar erro de restrição de unicidade que adicionamos anteriormente
            DiaNaoLetivo::updateOrCreate(
                [
                    'periodo_letivo_id' => $periodoLetivoId,
                    'data' => $currentDate->toDateString(),
                ],
                [
                    'descricao' => $descricao,
                    'flag_ativo' => $flagAtivo,
                ]
            );

            $currentDate->addDay();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Dia Não Letivo')
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
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Preencha os campos abaixo para cadastrar um novo registro de <strong>Dia Não Letivo</strong>.</p>';

        if ($user && $user->can('Create:DiaNaoLetivo')) {
            $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Instruções de Preenchimento:</h4>';
            $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
            $html .= '<li>Preencha todos os campos obrigatórios sinalizados antes de submeter.</li>';
            $html .= '<li>Utilize <strong>Criar</strong> para salvar o registro ou <strong>Criar e criar outro</strong> para novos cadastros contínuos.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}

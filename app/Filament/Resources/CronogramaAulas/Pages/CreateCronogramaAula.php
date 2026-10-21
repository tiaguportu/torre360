<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use App\Models\CronogramaAula;
use App\Models\PeriodoLetivo;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateCronogramaAula extends CreateRecord
{
    protected static string $resource = CronogramaAulaResource::class;

    public bool $another = false;

    public function create(bool $another = false): void
    {
        $this->another = $another;

        parent::create($another);
    }

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();

        if (! ($data['replicar_periodo'] ?? false)) {
            return;
        }

        $periodoLetivo = PeriodoLetivo::with('diasNaoLetivos')->find($data['periodo_letivo_id']);

        if (! $periodoLetivo || ! $periodoLetivo->data_fim) {
            return;
        }

        $dataInicio = Carbon::parse($data['data'])->addDay();
        $dataFimPeriodo = Carbon::parse($periodoLetivo->data_fim);
        $diasNaoLetivos = $periodoLetivo->diasNaoLetivos->pluck('data')->toArray();
        $diasSemana = $data['dias_semana'] ?? [];

        if (empty($diasSemana)) {
            return;
        }

        $currentDate = $dataInicio;

        while ($currentDate->lessThanOrEqualTo($dataFimPeriodo)) {
            // Verifica se o dia da semana atual está nos selecionados
            if (in_array($currentDate->dayOfWeek, $diasSemana)) {
                // Verifica se a data atual é um dia não letivo
                if (! in_array($currentDate->toDateString(), $diasNaoLetivos)) {
                    $newRecord = $this->record->replicate();
                    $newRecord->data = $currentDate->toDateString();
                    $newRecord->save();
                }
            }

            $currentDate->addDay();
        }
    }

    protected function fillForm(): void
    {
        if ($this->another && $this->record) {
            $data = $this->record->toArray();

            // 1) a nova hora_inicio é igual a hora_fim que foi salva
            // 2) a nova hora_fim é igual a nova hora_inicio mais a diferença entre a antiga hora_fim e a antiga hora_inicio
            try {
                $inicio = Carbon::parse($data['hora_inicio']);
                $fim = Carbon::parse($data['hora_fim']);
                $duracaoEmMinutos = $inicio->diffInMinutes($fim);

                $novaHoraInicio = $data['hora_fim'];
                $novaHoraFim = Carbon::parse($novaHoraInicio)->addMinutes($duracaoEmMinutos)->format('H:i');

                // Mantém os dados da aula anterior mas atualiza os horários
                $data['hora_inicio'] = $novaHoraInicio;
                $data['hora_fim'] = $novaHoraFim;
            } catch (\Exception $e) {
                // Em caso de erro no parse dos horários, mantém os dados originais
            }

            $this->form->fill($data);

            return;
        }

        // Se não for "Salvar e Criar Outro", busca o último registro editado
        $lastRecord = CronogramaAula::orderByDesc('updated_at')->first();

        if ($lastRecord) {
            $data = $lastRecord->toArray();

            try {
                $inicio = Carbon::parse($data['hora_inicio']);
                $fim = Carbon::parse($data['hora_fim']);
                $duracaoEmMinutos = $inicio->diffInMinutes($fim);

                $novaHoraInicio = $data['hora_fim'];
                $novaHoraFim = Carbon::parse($novaHoraInicio)->addMinutes($duracaoEmMinutos)->format('H:i');

                $data['hora_inicio'] = $novaHoraInicio;
                $data['hora_fim'] = $novaHoraFim;
            } catch (\Exception $e) {
                // Em caso de erro, mantém os dados do registro original
            }

            // Define o valor padrão para replicar_periodo se necessário
            $data['replicar_periodo'] = false;
            if (isset($data['data'])) {
                try {
                    $data['dias_semana'] = [Carbon::parse($data['data'])->dayOfWeek];
                } catch (\Exception $e) {
                }
            }

            // Remove campo legado para evitar erro de banco de dados
            unset($data['replicar_semanalmente']);

            $this->form->fill($data);

            return;
        }

        // Caso de criação simples: remove possíveis resquícios da sessão anterior
        if (isset($this->data['replicar_semanalmente'])) {
            unset($this->data['replicar_semanalmente']);
        }

        parent::fillForm();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Cronograma de Aula')
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
        $html = '<p class="text-sm text-gray-600 dark:text-gray-300">Preencha os campos abaixo para cadastrar um novo registro de <strong>Cronograma de Aula</strong>.</p>';

        if ($user && $user->can('Create:CronogramaAula')) {
            $html .= '<h4 class="font-semibold text-gray-800 dark:text-gray-100 mt-2 text-sm">Instruções de Preenchimento:</h4>';
            $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
            $html .= '<li>Preencha todos os campos obrigatórios sinalizados antes de submeter.</li>';
            $html .= '<li>Utilize <strong>Criar</strong> para salvar o registro ou <strong>Criar e criar outro</strong> para novos cadastros contínuos.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}

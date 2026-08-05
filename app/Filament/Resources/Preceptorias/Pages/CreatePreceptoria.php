<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePreceptoria extends CreateRecord
{
    protected static string $resource = PreceptoriaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tipoSelecao = $data['tipo_selecao_data'] ?? 'datas_especificas';
        unset($data['tipo_selecao_data']);

        $datasFinais = [];

        if ($tipoSelecao === 'intervalo') {
            $dataInicio = isset($data['data_inicio_range']) ? Carbon::parse($data['data_inicio_range']) : null;
            $dataFim = isset($data['data_fim_range']) ? Carbon::parse($data['data_fim_range']) : null;
            $diasSemana = array_map('intval', $data['dias_semana_range'] ?? []);

            unset($data['data_inicio_range'], $data['data_fim_range'], $data['dias_semana_range'], $data['datas']);

            if ($dataInicio && $dataFim) {
                $periodo = CarbonPeriod::create($dataInicio, $dataFim);
                foreach ($periodo as $date) {
                    if (! empty($diasSemana) && ! in_array($date->dayOfWeek, $diasSemana, true)) {
                        continue;
                    }
                    $datasFinais[] = $date->format('Y-m-d');
                }
            }
        } else {
            $datasInput = $data['datas'] ?? [];
            unset($data['datas'], $data['data_inicio_range'], $data['data_fim_range'], $data['dias_semana_range']);

            foreach ($datasInput as $d) {
                $dataStr = is_array($d) ? ($d['data'] ?? current($d)) : $d;
                if ($dataStr) {
                    $datasFinais[] = Carbon::parse($dataStr)->format('Y-m-d');
                }
            }
        }

        if (empty($datasFinais)) {
            $datasFinais = [$data['data'] ?? now()->format('Y-m-d')];
        }

        $records = [];
        foreach ($datasFinais as $dStr) {
            $recordData = array_merge($data, [
                'data' => $dStr,
            ]);

            $records[] = static::getModel()::create($recordData);
        }

        $quantidade = count($records);
        if ($quantidade > 1) {
            Notification::make()
                ->title("{$quantidade} preceptorias criadas com sucesso!")
                ->success()
                ->send();
        }

        return $records[0];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Preceptoria')
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
        $html = '<p>Nesta página você realiza o cadastro de novos horários/instâncias de preceptoria.</p>';
        $html .= '<h3>Instruções de Preenchimento:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Ciclo de Preceptoria:</strong> Selecione o ciclo letivo ou avaliativo correspondente.</li>';
        $html .= '<li><strong>Modo de Seleção de Datas:</strong> Escolha entre:';
        $html .= '<ul>';
        $html .= '<li><em>Datas Específicas:</em> Selecione um ou múltiplos dias avulsos.</li>';
        $html .= '<li><em>Intervalo de Datas (Range):</em> Informe uma Data Inicial e Data Final. Opcionalmente, selecione dias da semana para filtrar o intervalo (ex: criar apenas em terças e quintas).</li>';
        $html .= '</ul>';
        $html .= '</li>';
        $html .= '<li><strong>Horários:</strong> Defina o horário de início e término da preceptoria.</li>';
        $html .= '<li><strong>Vínculos:</strong> Selecione o professor responsável e, se aplicável, o aluno/matrícula a quem o horário se destina.</li>';
        $html .= '</ul>';

        return $html;
    }
}

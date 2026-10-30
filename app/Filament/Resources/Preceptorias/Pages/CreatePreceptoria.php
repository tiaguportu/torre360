<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Filament\Resources\Preceptorias\Schemas\PreceptoriaRecorrenciaForm;
use App\Services\PreceptoriaAgendaService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreatePreceptoria extends CreateRecord
{
    protected static string $resource = PreceptoriaResource::class;

    /** Campos que só existem no formulário para descrever a recorrência; não são colunas. */
    private const CAMPOS_DE_RECORRENCIA = [
        'data_inicio_range', 'data_fim_range', 'dias_semana_range', 'intervalo_semanas',
        'ignorar_fins_de_semana', 'ignorar_feriados', 'ignorar_dias_nao_letivos', 'previa_recorrencia',
    ];

    private bool $criouSerie = false;

    protected function handleRecordCreation(array $data): Model
    {
        $tipoSelecao = $data['tipo_selecao_data'] ?? 'datas_especificas';
        unset($data['tipo_selecao_data']);

        if ($tipoSelecao === 'intervalo') {
            return $this->criarRecorrencia($data);
        }

        $datasInput = $data['datas'] ?? [];
        $data = Arr::except($data, ['datas', ...self::CAMPOS_DE_RECORRENCIA]);

        $datasFinais = [];
        foreach ($datasInput as $d) {
            $dataStr = is_array($d) ? ($d['data'] ?? current($d)) : $d;
            if ($dataStr) {
                $datasFinais[] = Carbon::parse($dataStr)->format('Y-m-d');
            }
        }

        if (empty($datasFinais)) {
            $datasFinais = [$data['data'] ?? now()->format('Y-m-d')];
        }

        // datas em que o professor (ou o aluno) já tem preceptoria no horário são puladas, como na recorrência
        $resultado = app(PreceptoriaAgendaService::class)->gerarNasDatas($data, $datasFinais);
        $criadas = $resultado['criadas'];

        if ($criadas === [] || count($criadas) > 1 || $resultado['conflitos'] !== [] || $resultado['conflitos_aluno'] !== []) {
            if (PreceptoriaRecorrenciaForm::avisarResultado($resultado) === null) {
                throw new Halt;
            }
        }

        return $criadas[0];
    }

    /**
     * Cria a série recorrente pelo serviço: pula fins de semana, feriados e dias não letivos conforme
     * pedido e não duplica horário que o professor já tem.
     */
    private function criarRecorrencia(array $data): Model
    {
        $criadas = PreceptoriaRecorrenciaForm::criar($data);

        if ($criadas === null) {
            throw new Halt;
        }

        $this->criouSerie = count($criadas) > 1;

        return $criadas[0];
    }

    protected function getRedirectUrl(): string
    {
        return $this->criouSerie
            ? static::getResource()::getUrl('index')
            : parent::getRedirectUrl();
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
        $html .= '<li><em>Datas Específicas:</em> Selecione um ou múltiplos dias avulsos. Datas em que o professor (ou o aluno, se já informado) tem outra preceptoria no mesmo horário são puladas.</li>';
        $html .= '<li><em>Recorrente (janela de datas):</em> Informe a Data Inicial e a Final (ao escolher o ciclo, a janela dele é sugerida), os dias da semana (ex: só terças e quintas) e de quantas em quantas semanas repetir. Por padrão ficam de fora os finais de semana, os feriados nacionais e os dias não letivos do período letivo do ciclo; desligue qualquer um para incluí-los. A prévia mostra o que será criado e o que foi ignorado, e datas em que o professor já tem preceptoria no horário são puladas.</li>';
        $html .= '</ul>';
        $html .= '</li>';
        $html .= '<li><strong>Horários:</strong> Defina o horário de início e término da preceptoria.</li>';
        $html .= '<li><strong>Vínculos:</strong> Selecione o professor responsável e, se aplicável, o aluno/matrícula a quem o horário se destina.</li>';
        $html .= '</ul>';

        return $html;
    }
}

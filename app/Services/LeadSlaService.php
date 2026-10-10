<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Interessado;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Serviço responsável pelo cálculo e monitoramento do SLA de 1ª resposta no CRM.
 *
 * Características:
 *  - Prazo customizável por lead (com fallback para config('crm.sla.primeira_resposta_minutos_padrao'));
 *  - Cálculo estrito considerando horário comercial (dias úteis e faixa de horário de expediente);
 *  - Identificação de estouro e emissão de alertas direcionados ao consultor responsável;
 *  - Prevenção de duplicidade de alertas através do registro `sla_estouro_notificado_em`.
 */
class LeadSlaService
{
    /**
     * Retorna o prazo de SLA configurado para o lead em minutos comerciais.
     */
    public function prazoMinutosParaLead(Interessado $lead): int
    {
        return (int) ($lead->sla_primeira_resposta_minutos
            ?: config('crm.sla.primeira_resposta_minutos_padrao', 120));
    }

    /**
     * Calcula a data e hora limite exata em que o SLA de 1ª resposta expira,
     * debitando os minutos estritamente dentro do horário comercial configurado.
     */
    public function calcularDataLimite(Interessado $lead, ?Carbon $inicio = null): Carbon
    {
        $cursor = ($inicio ?? $lead->created_at ?? now())->copy();
        $minutosRestantes = $this->prazoMinutosParaLead($lead);

        $horaInicioStr = (string) config('crm.sla.horario_comercial.inicio', '08:00');
        $horaFimStr = (string) config('crm.sla.horario_comercial.fim', '18:00');
        /** @var list<int> $diasUteis */
        $diasUteis = config('crm.sla.horario_comercial.dias_uteis', [1, 2, 3, 4, 5]);

        // Ajusta cursor inicial se cair fora do horário comercial
        $this->ajustarParaProximoExpediente($cursor, $horaInicioStr, $horaFimStr, $diasUteis);

        while ($minutosRestantes > 0) {
            $fimDoDia = $cursor->copy()->setTimeFromTimeString($horaFimStr);
            $minutosDisponiveisHoje = (int) $cursor->diffInMinutes($fimDoDia, false);

            if ($minutosDisponiveisHoje <= 0) {
                // Já atingiu ou passou o fim do expediente de hoje; avança para o próximo dia útil
                $cursor->addDay()->setTimeFromTimeString($horaInicioStr);
                $this->ajustarParaProximoExpediente($cursor, $horaInicioStr, $horaFimStr, $diasUteis);

                continue;
            }

            if ($minutosRestantes <= $minutosDisponiveisHoje) {
                $cursor->addMinutes($minutosRestantes);
                $minutosRestantes = 0;
            } else {
                $minutosRestantes -= $minutosDisponiveisHoje;
                $cursor->addDay()->setTimeFromTimeString($horaInicioStr);
                $this->ajustarParaProximoExpediente($cursor, $horaInicioStr, $horaFimStr, $diasUteis);
            }
        }

        return $cursor;
    }

    /**
     * Verifica se o lead estourou o SLA de 1ª resposta.
     */
    public function slaEstourado(Interessado $lead, ?Carbon $agora = null): bool
    {
        // Se já houve primeiro contato registrado, SLA foi cumprido
        if ($lead->data_primeiro_contato !== null) {
            return false;
        }

        // Se o lead já está em etapa final (ganho ou descarte), SLA não se aplica mais
        if ($lead->status?->is_final) {
            return false;
        }

        $momentoAtual = $agora ?? now();
        $dataLimite = $this->calcularDataLimite($lead);

        return $momentoAtual->greaterThan($dataLimite);
    }

    /**
     * Retorna resumo analítico do SLA do lead para exibição em badges e formulários.
     *
     * @return array{
     *     respondido: bool,
     *     estourado: bool,
     *     limite: Carbon,
     *     prazo_minutos: int,
     *     texto: string,
     *     cor: string
     * }
     */
    public function resumoSla(Interessado $lead, ?Carbon $agora = null): array
    {
        $limite = $this->calcularDataLimite($lead);
        $prazo = $this->prazoMinutosParaLead($lead);
        $momentoAtual = $agora ?? now();

        if ($lead->data_primeiro_contato !== null) {
            return [
                'respondido' => true,
                'estourado' => false,
                'limite' => $limite,
                'prazo_minutos' => $prazo,
                'texto' => 'Respondido em '.$lead->data_primeiro_contato->format('d/m H:i'),
                'cor' => 'success',
            ];
        }

        if ($lead->status?->is_final) {
            return [
                'respondido' => false,
                'estourado' => false,
                'limite' => $limite,
                'prazo_minutos' => $prazo,
                'texto' => 'Finalizado',
                'cor' => 'gray',
            ];
        }

        if ($momentoAtual->greaterThan($limite)) {
            $minutosEstouro = (int) $limite->diffInMinutes($momentoAtual);
            $textoEstouro = $minutosEstouro >= 60
                ? sprintf('SLA Estourado há %dh %02dmin', intdiv($minutosEstouro, 60), $minutosEstouro % 60)
                : sprintf('SLA Estourado há %d min', $minutosEstouro);

            return [
                'respondido' => false,
                'estourado' => true,
                'limite' => $limite,
                'prazo_minutos' => $prazo,
                'texto' => $textoEstouro,
                'cor' => 'danger',
            ];
        }

        $minutosRestantes = (int) $momentoAtual->diffInMinutes($limite);
        $textoRestante = $minutosRestantes >= 60
            ? sprintf('SLA: resta %dh %02dmin útil', intdiv($minutosRestantes, 60), $minutosRestantes % 60)
            : sprintf('SLA: resta %d min útil', $minutosRestantes);

        return [
            'respondido' => false,
            'estourado' => false,
            'limite' => $limite,
            'prazo_minutos' => $prazo,
            'texto' => $textoRestante,
            'cor' => 'warning',
        ];
    }

    /**
     * Varre leads ativos sem 1ª resposta e com SLA estourado, enviando notificação
     * no painel do consultor responsável e registrando a data do disparo.
     *
     * @return int Quantidade de leads cujo estouro foi notificado
     */
    public function verificarENotificarEstouros(): int
    {
        /** @var Collection<int, Interessado> $leadsCandidatos */
        $leadsCandidatos = Interessado::query()
            ->with(['usuario', 'pessoa', 'status'])
            ->whereNotNull('usuario_id')
            ->whereNull('data_primeiro_contato')
            ->whereNull('sla_estouro_notificado_em')
            ->whereHas('status', fn ($q) => $q->where('is_final', false))
            ->get();

        $notificados = 0;

        foreach ($leadsCandidatos as $lead) {
            if (! $this->slaEstourado($lead)) {
                continue;
            }

            $consultor = $lead->usuario;

            if ($consultor instanceof User) {
                $nomeLead = $lead->pessoa?->nome ?? "Lead #{$lead->id}";
                $prazoMin = $this->prazoMinutosParaLead($lead);

                Notification::make()
                    ->title("⚠️ SLA Estourado: {$nomeLead} sem 1ª resposta")
                    ->body("O lead {$nomeLead} aguarda primeiro contato há mais tempo que o SLA estabelecido ({$prazoMin} min comerciais).")
                    ->icon('heroicon-o-clock')
                    ->danger()
                    ->actions([
                        NotificationAction::make('atender')
                            ->label('Ver Lead')
                            ->url("/admin/interessados/{$lead->id}/edit")
                            ->button(),
                    ])
                    ->sendToDatabase($consultor);

                $lead->forceFill([
                    'sla_estouro_notificado_em' => now(),
                ])->saveQuietly();

                $notificados++;
            }
        }

        return $notificados;
    }

    /**
     * Ajusta o cursor para que fique obrigatoriamente dentro de um dia útil e horário comercial.
     *
     * @param  list<int>  $diasUteis
     */
    private function ajustarParaProximoExpediente(
        Carbon $cursor,
        string $horaInicioStr,
        string $horaFimStr,
        array $diasUteis
    ): void {
        [$hInicio, $mInicio] = array_map('intval', explode(':', $horaInicioStr));
        [$hFim, $mFim] = array_map('intval', explode(':', $horaFimStr));

        while (true) {
            // Se for dia não útil, avança para o dia seguinte no horário inicial
            if (! in_array($cursor->dayOfWeekIso, $diasUteis, true)) {
                $cursor->addDay()->setTime($hInicio, $mInicio, 0);

                continue;
            }

            $minutosNoDia = $cursor->hour * 60 + $cursor->minute;
            $minutosInicioExpediente = $hInicio * 60 + $mInicio;
            $minutosFimExpediente = $hFim * 60 + $mFim;

            // Se for antes do início do expediente de um dia útil, ajusta para o horário inicial
            if ($minutosNoDia < $minutosInicioExpediente) {
                $cursor->setTime($hInicio, $mInicio, 0);

                return;
            }

            // Se for após o término do expediente, avança para o próximo dia no horário inicial
            if ($minutosNoDia >= $minutosFimExpediente) {
                $cursor->addDay()->setTime($hInicio, $mInicio, 0);

                continue;
            }

            // Dentro do expediente e em dia útil: válido
            return;
        }
    }
}

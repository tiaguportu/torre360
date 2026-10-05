<?php

namespace App\Services;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Enums\StatusVisitaInteressado;
use App\Mail\MensagemGenericaMail;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\ReguaFollowUp;
use App\Models\ReguaFollowUpLog;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ReguaFollowUpService
{
    /**
     * Processa a régua de follow-up diária do CRM para a data informada.
     *
     * @return array{
     *     data_execucao: string,
     *     dry_run: bool,
     *     total_regras: int,
     *     total_candidatos_analisados: int,
     *     total_notificacoes_enviadas: int,
     *     detalhes: array<int, array{
     *         regra_nome: string,
     *         gatilho: string,
     *         dias_offset: int,
     *         canal: string,
     *         candidatos_encontrados: int,
     *         notificacoes_enviadas: int
     *     }>
     * }
     */
    public function processarReguaDiaria(Carbon $dataReferencia, bool $dryRun = false): array
    {
        $regras = ReguaFollowUp::where('is_ativo', true)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        $resultado = [
            'data_execucao' => $dataReferencia->format('d/m/Y'),
            'dry_run' => $dryRun,
            'total_regras' => $regras->count(),
            'total_candidatos_analisados' => 0,
            'total_notificacoes_enviadas' => 0,
            'detalhes' => [],
        ];

        foreach ($regras as $regra) {
            $enviadosRegra = 0;
            $candidatos = $this->obterCandidatosParaRegra($regra, $dataReferencia);

            $resultado['total_candidatos_analisados'] += $candidatos->count();

            foreach ($candidatos as $candidato) {
                $interessado = $candidato['interessado'];
                $visita = $candidato['visita'] ?? null;

                if (! $this->atendeFiltrosOpcionais($regra, $interessado)) {
                    continue;
                }

                if ($this->jaFoiEnviado($regra, $interessado, $visita, $dataReferencia)) {
                    continue;
                }

                if ($dryRun) {
                    $enviadosRegra++;
                    $resultado['total_notificacoes_enviadas']++;

                    continue;
                }

                $resEnvio = $this->enviarNotificacao($regra, $interessado, $visita, $dataReferencia);
                if ($resEnvio['sucesso']) {
                    $enviadosRegra++;
                    $resultado['total_notificacoes_enviadas']++;
                }
            }

            $resultado['detalhes'][] = [
                'regra_nome' => $regra->nome,
                'gatilho' => $regra->gatilho->getLabel(),
                'dias_offset' => $regra->dias_offset,
                'canal' => $regra->canal->getLabel(),
                'candidatos_encontrados' => $candidatos->count(),
                'notificacoes_enviadas' => $enviadosRegra,
            ];
        }

        return $resultado;
    }

    /**
     * Envia imediatamente uma notificação para um lead/visita e registra log e histórico.
     *
     * @return array{sucesso: bool, mensagem: string}
     */
    public function enviarNotificacao(
        ReguaFollowUp $regra,
        Interessado $interessado,
        ?VisitaInteressado $visita = null,
        ?Carbon $dataEnvio = null
    ): array {
        $dataEnvio = $dataEnvio ?? now();
        $interpolado = $regra->interpolarMensagem($interessado, $visita);

        if ($regra->canal === CanalReguaFollowUp::Email) {
            $pessoa = $interessado->pessoa;
            $destinatario = $pessoa?->email;

            if (empty($destinatario)) {
                $this->registrarLog($regra, $interessado, $visita, 'email', null, $interpolado, 'falha', 'Lead/Responsável sem e-mail cadastrado.', $dataEnvio);

                return ['sucesso' => false, 'mensagem' => 'E-mail não cadastrado.'];
            }

            if ($pessoa->aceita_comunicacao === false) {
                $this->registrarLog($regra, $interessado, $visita, 'email', $destinatario, $interpolado, 'falha', 'Responsável optou por não receber comunicações (LGPD).', $dataEnvio);

                return ['sucesso' => false, 'mensagem' => 'Responsável solicitou opt-out de comunicações.'];
            }

            try {
                Mail::to($destinatario)->send(new MensagemGenericaMail(
                    $interpolado['assunto'],
                    $interpolado['mensagem']
                ));

                // Registra no histórico de contatos do lead
                $tipoContatoId = TipoContatoInteressado::where('nome', 'like', '%E-mail%')->value('id') ?? 3;
                HistoricoContato::create([
                    'interessado_id' => $interessado->id,
                    'tipo_contato_interessado_id' => $tipoContatoId,
                    'data_contato' => now(),
                    'usuario_id' => null, // Envio do sistema
                    'relato' => "E-mail automático enviado pela Régua de Follow-up ({$regra->nome}): \"{$interpolado['assunto']}\"",
                    'resultado' => 'contato_realizado',
                    // Disparo do sistema: não conta como interação (senão zera o "lead estagnado" sem ninguém ter falado com a família).
                    'automatico' => true,
                ]);

                $this->registrarLog($regra, $interessado, $visita, 'email', $destinatario, $interpolado, 'sucesso', null, $dataEnvio);

                return ['sucesso' => true, 'mensagem' => 'E-mail enviado com sucesso.'];
            } catch (Throwable $e) {
                Log::error('Erro ao enviar e-mail da Régua de Follow-up: '.$e->getMessage(), [
                    'regra_id' => $regra->id,
                    'interessado_id' => $interessado->id,
                ]);

                $this->registrarLog($regra, $interessado, $visita, 'email', $destinatario, $interpolado, 'falha', $e->getMessage(), $dataEnvio);

                return ['sucesso' => false, 'mensagem' => 'Erro no transporte de e-mail: '.$e->getMessage()];
            }
        }

        // Canal: Notificação Interna do Sistema
        try {
            $destinatarios = collect();

            if ($interessado->usuario) {
                $destinatarios->push($interessado->usuario);
            } else {
                // Notifica administradores caso o lead não tenha consultor
                $admins = User::role(['super_admin', 'admin'])->where('is_active', true)->get();
                $destinatarios = $destinatarios->merge($admins);
            }

            foreach ($destinatarios as $destinatarioUser) {
                Notification::make()
                    ->title($interpolado['assunto'])
                    ->body(strip_tags($interpolado['mensagem']))
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->warning()
                    ->actions([
                        Action::make('visualizar')
                            ->label('Ver Lead')
                            ->url("/admin/interessados/{$interessado->id}/edit"),
                    ])
                    ->sendToDatabase($destinatarioUser);
            }

            $nomesDestinatarios = $destinatarios->pluck('name')->join(', ');
            $this->registrarLog($regra, $interessado, $visita, 'notificacao_sistema', $nomesDestinatarios, $interpolado, 'sucesso', null, $dataEnvio);

            return ['sucesso' => true, 'mensagem' => 'Notificação interna gerada com sucesso.'];
        } catch (Throwable $e) {
            $this->registrarLog($regra, $interessado, $visita, 'notificacao_sistema', 'sistema', $interpolado, 'falha', $e->getMessage(), $dataEnvio);

            return ['sucesso' => false, 'mensagem' => 'Erro ao gerar notificação interna: '.$e->getMessage()];
        }
    }

    /**
     * Localiza os registros candidatos para avaliação da regra com base no momento do gatilho e no offset.
     *
     * @return Collection<int, array{interessado: Interessado, visita: ?VisitaInteressado}>
     */
    protected function obterCandidatosParaRegra(ReguaFollowUp $regra, Carbon $dataReferencia): Collection
    {
        $candidatos = collect();

        switch ($regra->gatilho) {
            case GatilhoReguaFollowUp::VisitaLembrete:
                // Visitas agendadas cuja data coincida com (dataReferencia + offset)
                $dataAlvo = $dataReferencia->copy()->addDays($regra->dias_offset)->format('Y-m-d');
                $visitas = VisitaInteressado::where('status', StatusVisitaInteressado::Agendada)
                    ->whereDate('data_hora', $dataAlvo)
                    ->with(['interessado.pessoa', 'interessado.dependentes.serie', 'interessado.usuario', 'dependente.serie'])
                    ->get();

                foreach ($visitas as $visita) {
                    if ($visita->interessado) {
                        $candidatos->push([
                            'interessado' => $visita->interessado,
                            'visita' => $visita,
                        ]);
                    }
                }
                break;

            case GatilhoReguaFollowUp::VisitaRealizada:
                // Visitas realizadas cuja data coincida com (dataReferencia - offset)
                $dataAlvo = $dataReferencia->copy()->subDays($regra->dias_offset)->format('Y-m-d');
                $visitas = VisitaInteressado::where('status', StatusVisitaInteressado::Realizada)
                    ->whereDate('data_hora', $dataAlvo)
                    ->with(['interessado.pessoa', 'interessado.dependentes.serie', 'interessado.usuario', 'dependente.serie'])
                    ->get();

                foreach ($visitas as $visita) {
                    if ($visita->interessado) {
                        $candidatos->push([
                            'interessado' => $visita->interessado,
                            'visita' => $visita,
                        ]);
                    }
                }
                break;

            case GatilhoReguaFollowUp::VisitaFaltou:
                // Visitas com falta cuja data coincida com (dataReferencia - offset)
                $dataAlvo = $dataReferencia->copy()->subDays($regra->dias_offset)->format('Y-m-d');
                $visitas = VisitaInteressado::where('status', StatusVisitaInteressado::Faltou)
                    ->whereDate('data_hora', $dataAlvo)
                    ->with(['interessado.pessoa', 'interessado.dependentes.serie', 'interessado.usuario', 'dependente.serie'])
                    ->get();

                foreach ($visitas as $visita) {
                    if ($visita->interessado) {
                        $candidatos->push([
                            'interessado' => $visita->interessado,
                            'visita' => $visita,
                        ]);
                    }
                }
                break;

            case GatilhoReguaFollowUp::LeadCriado:
                // Leads criados em (dataReferencia - offset)
                $dataAlvo = $dataReferencia->copy()->subDays($regra->dias_offset)->format('Y-m-d');
                $leads = Interessado::ativos()
                    ->whereDate('created_at', $dataAlvo)
                    ->with(['pessoa', 'dependentes.serie', 'usuario'])
                    ->get();

                foreach ($leads as $lead) {
                    $candidatos->push([
                        'interessado' => $lead,
                        'visita' => null,
                    ]);
                }
                break;

            case GatilhoReguaFollowUp::LeadEstagnado:
                // Leads ativos sem contato há >= offset dias (utiliza o escopo nativo)
                $leads = Interessado::ativos()
                    ->estagnados($regra->dias_offset)
                    ->with(['pessoa', 'dependentes.serie', 'usuario'])
                    ->get();

                foreach ($leads as $lead) {
                    $candidatos->push([
                        'interessado' => $lead,
                        'visita' => null,
                    ]);
                }
                break;

            case GatilhoReguaFollowUp::ContatoAtrasado:
                // Leads ativos cuja data_proximo_contato venceu há X dias
                $dataAlvo = $dataReferencia->copy()->subDays($regra->dias_offset)->format('Y-m-d');
                $leads = Interessado::ativos()
                    ->whereDate('data_proximo_contato', $dataAlvo)
                    ->with(['pessoa', 'dependentes.serie', 'usuario'])
                    ->get();

                foreach ($leads as $lead) {
                    $candidatos->push([
                        'interessado' => $lead,
                        'visita' => null,
                    ]);
                }
                break;
        }

        return $candidatos;
    }

    /**
     * Verifica se o interessado cumpre os filtros opcionais da regra (origem e status).
     */
    protected function atendeFiltrosOpcionais(ReguaFollowUp $regra, Interessado $interessado): bool
    {
        if ($regra->origem_interessado_id && $interessado->origem_interessado_id !== $regra->origem_interessado_id) {
            return false;
        }

        if ($regra->status_interessado_id && $interessado->status_interessado_id !== $regra->status_interessado_id) {
            return false;
        }

        return true;
    }

    /**
     * Verifica idempotência: se a regra já foi disparada com sucesso para este evento.
     */
    protected function jaFoiEnviado(
        ReguaFollowUp $regra,
        Interessado $interessado,
        ?VisitaInteressado $visita,
        Carbon $dataReferencia
    ): bool {
        $query = ReguaFollowUpLog::where('regua_follow_up_id', $regra->id)
            ->where('interessado_id', $interessado->id)
            ->where('status_envio', 'sucesso');

        if ($visita) {
            $query->where('visita_interessado_id', $visita->id);

            return $query->exists();
        }

        if ($regra->gatilho === GatilhoReguaFollowUp::LeadCriado) {
            return $query->exists();
        }

        // Para gatilhos recorrentes (estagnado ou atrasado), evita disparar mais de uma vez a cada ciclo de offset
        $diasJanela = max(1, $regra->dias_offset);
        $dataCorte = $dataReferencia->copy()->subDays($diasJanela)->toDateString();

        return $query->where('data_envio', '>=', $dataCorte)->exists();
    }

    /**
     * Cria o registro no log de envio.
     */
    protected function registrarLog(
        ReguaFollowUp $regra,
        Interessado $interessado,
        ?VisitaInteressado $visita,
        string $canal,
        ?string $destinatario,
        array $interpolado,
        string $status,
        ?string $erro,
        Carbon $dataEnvio
    ): ReguaFollowUpLog {
        return ReguaFollowUpLog::create([
            'regua_follow_up_id' => $regra->id,
            'interessado_id' => $interessado->id,
            'visita_interessado_id' => $visita?->id,
            'canal' => $canal,
            'destinatario' => $destinatario,
            'assunto_enviado' => $interpolado['assunto'],
            'mensagem_enviada' => $interpolado['mensagem'],
            'status_envio' => $status,
            'erro' => $erro,
            'data_envio' => $dataEnvio->toDateString(),
        ]);
    }
}

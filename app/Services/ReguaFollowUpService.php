<?php

namespace App\Services;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Enums\StatusVisitaInteressado;
use App\Mail\MensagemGenericaMail;
use App\Models\HistoricoContato;
use App\Models\InstituicaoEnsino;
use App\Models\Interessado;
use App\Models\ReguaFollowUp;
use App\Models\ReguaFollowUpLog;
use App\Models\TipoContatoInteressado;
use App\Models\Unidade;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Support\DescadastroComunicacao;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Régua de follow-up do CRM (e-mails e avisos internos disparados por gatilhos de lead e de visita).
 *
 * Regras que importam para quem altera este serviço:
 *  - o e-mail é enviado na hora (`sendNow`), não enfileirado: o log só diz "sucesso" se o transporte aceitou;
 *  - gatilhos por data olham uma janela de `crm.regua.janela_recuperacao_dias` para trás, então um dia em que o
 *    agendador não rodou não perde a mensagem; a idempotência vem do log (`jaProcessada()`);
 *  - gatilhos de visita só valem para lead ainda em andamento;
 *  - cada lead recebe no máximo `crm.regua.max_emails_por_lead_dia` e-mails por dia (o excedente fica para o dia seguinte);
 *  - `horario_envio` é respeitado quando o chamador informa `$agora` (o comando agendado, que roda de hora em hora).
 */
class ReguaFollowUpService
{
    /** Nome da escola e tipo de contato "E-mail" não mudam durante uma execução: um valor por serviço, não por mensagem. */
    private ?string $nomeEscola = null;

    private ?int $tipoEmailId = null;

    /**
     * Processa a régua de follow-up do CRM para a data informada.
     *
     * @param  Carbon|null  $agora  se informado, regras com `horario_envio` posterior a este horário ficam para uma execução futura
     * @return array{
     *     data_execucao: string,
     *     dry_run: bool,
     *     total_regras: int,
     *     total_regras_aguardando_horario: int,
     *     total_candidatos_analisados: int,
     *     total_notificacoes_enviadas: int,
     *     total_adiadas_limite: int,
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
    public function processarReguaDiaria(Carbon $dataReferencia, bool $dryRun = false, ?Carbon $agora = null): array
    {
        $regras = ReguaFollowUp::where('is_ativo', true)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        $resultado = [
            'data_execucao' => $dataReferencia->format('d/m/Y'),
            'dry_run' => $dryRun,
            'total_regras' => $regras->count(),
            'total_regras_aguardando_horario' => 0,
            'total_candidatos_analisados' => 0,
            'total_notificacoes_enviadas' => 0,
            'total_adiadas_limite' => 0,
            'detalhes' => [],
        ];

        /** @var array<int, int> e-mails da régua já enviados (ou simulados) hoje, por lead */
        $emailsHoje = [];

        foreach ($regras as $regra) {
            if ($agora !== null && ! $this->horarioChegou($regra, $agora)) {
                $resultado['total_regras_aguardando_horario']++;

                continue;
            }

            $enviadosRegra = 0;
            $candidatos = $this->obterCandidatosParaRegra($regra, $dataReferencia);

            $resultado['total_candidatos_analisados'] += $candidatos->count();

            foreach ($candidatos as $candidato) {
                $interessado = $candidato['interessado'];
                $visita = $candidato['visita'] ?? null;

                if (! $this->atendeFiltrosOpcionais($regra, $interessado)) {
                    continue;
                }

                if ($this->jaProcessada($regra, $interessado, $visita, $dataReferencia)) {
                    continue;
                }

                $ehEmail = $regra->canal === CanalReguaFollowUp::Email;

                if ($ehEmail && $this->atingiuLimiteDiario($interessado, $dataReferencia, $emailsHoje)) {
                    $resultado['total_adiadas_limite']++;

                    continue;
                }

                if (! $dryRun) {
                    $resEnvio = $this->enviarNotificacao($regra, $interessado, $visita, $dataReferencia);

                    if (! $resEnvio['sucesso']) {
                        continue;
                    }
                }

                $enviadosRegra++;
                $resultado['total_notificacoes_enviadas']++;

                if ($ehEmail) {
                    $emailsHoje[$interessado->id] = ($emailsHoje[$interessado->id] ?? 0) + 1;
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
        $interpolado = $regra->interpolarMensagem($interessado, $visita, $this->nomeEscola());

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
                // `sendNow`: o mailable é ShouldQueue (serve à comunicação em massa), mas aqui o resultado do envio
                // precisa entrar no log. Enfileirado, `send()` voltava na hora e o log marcava "sucesso" mesmo
                // quando o SMTP recusava depois.
                Mail::to($destinatario)->sendNow(new MensagemGenericaMail(
                    $interpolado['assunto'],
                    $interpolado['mensagem'],
                    DescadastroComunicacao::url($pessoa),
                ));

                // Registra no histórico de contatos do lead
                HistoricoContato::create([
                    'interessado_id' => $interessado->id,
                    'tipo_contato_interessado_id' => $this->tipoContatoEmailId(),
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
                $admins = User::role(['super_admin', 'admin'])->ativos()->get();
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
     * A regra já pode sair? `horario_envio` ("HH:MM:SS") é comparado com a hora de `$agora`; sem horário, vale desde a primeira execução do dia.
     */
    protected function horarioChegou(ReguaFollowUp $regra, Carbon $agora): bool
    {
        $horario = $regra->horario_envio;

        if (blank($horario)) {
            return true;
        }

        return $agora->format('H:i:s') >= Carbon::parse($horario)->format('H:i:s');
    }

    /**
     * Localiza os registros candidatos para avaliação da regra com base no momento do gatilho e no offset.
     *
     * Os gatilhos por data pós-evento (cadastro, visita realizada/faltou, contato atrasado) olham de
     * `data-alvo - janela` até `data-alvo` (`crm.regua.janela_recuperacao_dias`): com `whereDate` exato, o dia em
     * que o agendador não rodou (deploy, servidor fora) perdia a mensagem para sempre. Repetir a avaliação nos dias
     * seguintes é seguro porque `jaProcessada()` consulta o log. O lembrete de visita fica de fora (ver abaixo).
     *
     * @return Collection<int, array{interessado: Interessado, visita: ?VisitaInteressado}>
     */
    protected function obterCandidatosParaRegra(ReguaFollowUp $regra, Carbon $dataReferencia): Collection
    {
        $janela = max(0, (int) config('crm.regua.janela_recuperacao_dias', 3));
        $offset = (int) $regra->dias_offset;
        $depois = $dataReferencia->copy()->subDays($offset); // eventos que aconteceram há `offset` dias

        return match ($regra->gatilho) {
            // Lembrete: só a data exata. Um "amanhã" enviado no dia da visita (ou depois dela) seria errado, então este
            // gatilho não tem janela de recuperação.
            GatilhoReguaFollowUp::VisitaLembrete => $this->candidatosDeVisitas(
                StatusVisitaInteressado::Agendada,
                $dataReferencia->copy()->addDays($offset),
                $dataReferencia->copy()->addDays($offset),
            ),
            GatilhoReguaFollowUp::VisitaRealizada => $this->candidatosDeVisitas(StatusVisitaInteressado::Realizada, $depois->copy()->subDays($janela), $depois),
            GatilhoReguaFollowUp::VisitaFaltou => $this->candidatosDeVisitas(StatusVisitaInteressado::Faltou, $depois->copy()->subDays($janela), $depois),
            GatilhoReguaFollowUp::LeadCriado => $this->candidatosDeLeads(
                Interessado::ativos()->whereDate('created_at', '>=', $depois->copy()->subDays($janela)->toDateString())->whereDate('created_at', '<=', $depois->toDateString())
            ),
            // Leads ativos sem interação há >= offset dias (usa o escopo nativo).
            GatilhoReguaFollowUp::LeadEstagnado => $this->candidatosDeLeads(Interessado::ativos()->estagnados($offset)),
            // Leads ativos cuja data_proximo_contato venceu há X dias (ou há até X + janela dias).
            GatilhoReguaFollowUp::ContatoAtrasado => $this->candidatosDeLeads(
                Interessado::ativos()->whereDate('data_proximo_contato', '>=', $depois->copy()->subDays($janela)->toDateString())->whereDate('data_proximo_contato', '<=', $depois->toDateString())
            ),
            default => collect(),
        };
    }

    /**
     * Visitas no status informado, entre as datas (inclusive), de leads que ainda estão em andamento: lembrete,
     * agradecimento ou reagendamento para quem já matriculou ou desistiu é ruído.
     *
     * @return Collection<int, array{interessado: Interessado, visita: ?VisitaInteressado}>
     */
    private function candidatosDeVisitas(StatusVisitaInteressado $status, Carbon $de, Carbon $ate): Collection
    {
        return VisitaInteressado::where('status', $status)
            ->whereDate('data_hora', '>=', $de->toDateString())
            ->whereDate('data_hora', '<=', $ate->toDateString())
            ->whereHas('interessado', fn ($query) => $query->ativos())
            ->with(['interessado.pessoa', 'interessado.dependentes.serie', 'interessado.usuario', 'dependente.serie'])
            ->get()
            ->filter(fn (VisitaInteressado $visita): bool => $visita->interessado !== null)
            ->map(fn (VisitaInteressado $visita): array => ['interessado' => $visita->interessado, 'visita' => $visita])
            ->values();
    }

    /**
     * @param  Builder<Interessado>  $consulta
     * @return Collection<int, array{interessado: Interessado, visita: ?VisitaInteressado}>
     */
    private function candidatosDeLeads($consulta): Collection
    {
        return $consulta
            ->with(['pessoa', 'dependentes.serie', 'usuario'])
            ->get()
            ->map(fn (Interessado $lead): array => ['interessado' => $lead, 'visita' => null])
            ->values();
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
     * Idempotência: esta regra já foi cumprida para este evento, já foi tentada hoje ou esgotou as tentativas?
     *
     * "Evento" é a visita, o cadastro do lead (uma vez só) ou, nos gatilhos recorrentes, o ciclo atual: o
     * vencimento do contato (`ContatoAtrasado`, que recomeça quando a data é reagendada) ou a janela de `dias_offset`
     * (`LeadEstagnado`). Falhas contam: uma tentativa por dia e no máximo `crm.regua.max_tentativas_falha` por
     * evento, para um e-mail inválido não ser reenviado a cada execução horária.
     */
    protected function jaProcessada(
        ReguaFollowUp $regra,
        Interessado $interessado,
        ?VisitaInteressado $visita,
        Carbon $dataReferencia
    ): bool {
        $logs = ReguaFollowUpLog::where('regua_follow_up_id', $regra->id)
            ->where('interessado_id', $interessado->id);

        if ($visita) {
            $logs->where('visita_interessado_id', $visita->id);
        } elseif ($regra->gatilho === GatilhoReguaFollowUp::ContatoAtrasado && $interessado->data_proximo_contato) {
            $logs->where('data_envio', '>=', Carbon::parse($interessado->data_proximo_contato)->toDateString());
        } elseif ($regra->gatilho !== GatilhoReguaFollowUp::LeadCriado) {
            $diasJanela = max(1, $regra->dias_offset);
            $logs->where('data_envio', '>=', $dataReferencia->copy()->subDays($diasJanela)->toDateString());
        }

        if ((clone $logs)->where('status_envio', 'sucesso')->exists()) {
            return true;
        }

        $falhas = (clone $logs)->where('status_envio', 'falha');

        if ((clone $falhas)->whereDate('data_envio', $dataReferencia->toDateString())->exists()) {
            return true;
        }

        return $falhas->count() >= max(1, (int) config('crm.regua.max_tentativas_falha', 3));
    }

    /**
     * O lead já recebeu o teto de e-mails da régua hoje? Evita três regras diferentes (cadastro, contato atrasado,
     * estagnado) caírem na mesma caixa de entrada no mesmo dia; o excedente sai nos dias seguintes, dentro da janela.
     *
     * @param  array<int, int>  $emailsHoje  contagem por lead, preenchida na primeira consulta e mantida pela execução
     */
    private function atingiuLimiteDiario(Interessado $interessado, Carbon $dataReferencia, array &$emailsHoje): bool
    {
        $limite = (int) config('crm.regua.max_emails_por_lead_dia', 2);

        if ($limite <= 0) {
            return false;
        }

        $emailsHoje[$interessado->id] ??= ReguaFollowUpLog::where('interessado_id', $interessado->id)
            ->where('canal', 'email')
            ->where('status_envio', 'sucesso')
            ->whereDate('data_envio', $dataReferencia->toDateString())
            ->count();

        return $emailsHoje[$interessado->id] >= $limite;
    }

    private function nomeEscola(): string
    {
        return $this->nomeEscola ??= Unidade::first()?->nome ?? InstituicaoEnsino::first()?->nome ?? 'Nossa Escola';
    }

    private function tipoContatoEmailId(): int
    {
        return $this->tipoEmailId ??= TipoContatoInteressado::porNome('E-mail')->id;
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

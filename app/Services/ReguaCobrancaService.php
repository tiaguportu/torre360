<?php

namespace App\Services;

use App\Enums\StatusFatura;
use App\Models\Fatura;
use App\Models\Pessoa;
use App\Models\ReguaCobranca;
use App\Models\ReguaCobrancaLog;
use App\Models\User;
use App\Notifications\CobrancaFaturaNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ReguaCobrancaService
{
    /**
     * Processa a régua de cobrança para todas as regras ativas na data de referência.
     */
    public function processarReguaDiaria(?Carbon $dataReferencia = null, bool $dryRun = false): array
    {
        $hoje = $dataReferencia ? $dataReferencia->copy()->startOfDay() : now()->startOfDay();

        // 1. Atualizar status de faturas que venceram antes de hoje para 'Atrasado'
        $this->atualizarStatusFaturasAtrasadas($hoje);

        $regras = ReguaCobranca::where('is_ativo', true)
            ->orderBy('ordem')
            ->orderBy('dias_offset')
            ->get();

        $estatisticas = [
            'data_execucao' => $hoje->toDateString(),
            'dry_run' => $dryRun,
            'total_regras' => $regras->count(),
            'total_faturas_analisadas' => 0,
            'total_notificacoes_enviadas' => 0,
            'detalhes' => [],
        ];

        foreach ($regras as $regra) {
            $dataVencimentoAlvo = $regra->calcularDataVencimentoAlvo($hoje)->toDateString();

            $faturas = Fatura::query()
                ->whereDate('vencimento', $dataVencimentoAlvo)
                ->whereIn('status', [StatusFatura::Pendente, StatusFatura::Atrasado, StatusFatura::Parcial])
                ->with([
                    'contrato.matricula.pessoa.responsaveis.responsavel',
                    'contrato.responsaveisFinanceiros.pessoa',
                    'itens',
                    'transacoes',
                ])
                ->get();

            $enviadasNestaRegra = 0;

            foreach ($faturas as $fatura) {
                // Se a fatura já tiver sido totalmente quitada, pula
                if ($fatura->valor_restante <= 0) {
                    continue;
                }

                $estatisticas['total_faturas_analisadas']++;

                $responsaveis = $this->obterResponsaveisFatura($fatura);

                if ($responsaveis->isEmpty()) {
                    continue;
                }

                $aluno = $fatura->contrato?->matricula?->pessoa;

                foreach ($responsaveis as $responsavel) {
                    // Evitar disparo duplicado na mesma data para a mesma fatura e regra
                    $jaEnviado = ReguaCobrancaLog::where('regua_cobranca_id', $regra->id)
                        ->where('fatura_id', $fatura->id)
                        ->where('pessoa_id', $responsavel->id)
                        ->whereDate('data_envio', $hoje->toDateString())
                        ->exists();

                    if ($jaEnviado) {
                        continue;
                    }

                    $assunto = $this->substituirMacros($regra->assunto, $fatura, $responsavel, $aluno, $hoje);
                    $mensagem = $this->substituirMacros($regra->mensagem, $fatura, $responsavel, $aluno, $hoje);

                    if (! $dryRun) {
                        $this->enviarNotificacao($fatura, $responsavel, $regra, $assunto, $mensagem, $hoje);
                    }

                    $enviadasNestaRegra++;
                    $estatisticas['total_notificacoes_enviadas']++;
                }
            }

            $estatisticas['detalhes'][] = [
                'regra_id' => $regra->id,
                'regra_nome' => $regra->nome,
                'dias_offset' => $regra->dias_offset,
                'data_alvo_vencimento' => $dataVencimentoAlvo,
                'notificacoes_enviadas' => $enviadasNestaRegra,
            ];
        }

        return $estatisticas;
    }

    /**
     * Dispara um lembrete/cobrança manual imediata para os responsáveis de uma fatura.
     */
    public function dispararLembreteManual(
        Fatura $fatura,
        ?ReguaCobranca $regra = null,
        ?string $mensagemCustomizada = null,
        ?string $assuntoCustomizado = null,
        string $canal = 'todos'
    ): array {
        $hoje = now()->startOfDay();
        $responsaveis = $this->obterResponsaveisFatura($fatura);
        $aluno = $fatura->contrato?->matricula?->pessoa;

        if ($responsaveis->isEmpty()) {
            throw new \InvalidArgumentException('Nenhum responsável financeiro ou familiar com contato encontrado para esta fatura.');
        }

        $assuntoTemplate = $assuntoCustomizado ?? ($regra?->assunto ?? 'Lembrete de Cobrança: Fatura #{{NUMERO_FATURA}}');
        $mensagemTemplate = $mensagemCustomizada ?? ($regra?->mensagem ?? 'Prezado(a) {{RESPONSAVEL_NOME}}, lembramos da fatura #{{NUMERO_FATURA}} de {{ALUNO_NOME}} com vencimento em {{DATA_VENCIMENTO}} no valor de R$ {{VALOR}}.');

        $enviados = 0;

        foreach ($responsaveis as $responsavel) {
            $assunto = $this->substituirMacros($assuntoTemplate, $fatura, $responsavel, $aluno, $hoje);
            $mensagem = $this->substituirMacros($mensagemTemplate, $fatura, $responsavel, $aluno, $hoje);

            $this->enviarNotificacao(
                $fatura,
                $responsavel,
                $regra,
                $assunto,
                $mensagem,
                $hoje,
                $canal
            );

            $enviados++;
        }

        return [
            'total_responsaveis' => $responsaveis->count(),
            'total_enviados' => $enviados,
        ];
    }

    /**
     * Envia a notificação multicanal e registra o log.
     */
    protected function enviarNotificacao(
        Fatura $fatura,
        Pessoa $responsavel,
        ?ReguaCobranca $regra,
        string $assunto,
        string $mensagem,
        Carbon $dataEnvio,
        ?string $canalSobrescrito = null
    ): void {
        $canal = $canalSobrescrito ?? ($regra?->canal ?? 'todos');
        $statusEnvio = 'sucesso';
        $erroMsg = null;

        try {
            $tipoAlerta = $regra?->getGatilhoBadgeColorAttribute() ?? 'warning';
            if ($fatura->status === StatusFatura::Atrasado || ($regra && $regra->isAtraso())) {
                $tipoAlerta = 'danger';
            }

            // Localiza se o responsável possui usuário vinculado no sistema
            $usuario = User::where('email', $responsavel->email)
                ->orWhereHas('pessoas', fn ($q) => $q->where('pessoa.id', $responsavel->id))
                ->first();

            $canaisDisparo = match ($canal) {
                'email' => ['mail'],
                'portal' => ['database'],
                'push' => ['push'],
                default => ['mail', 'database', 'push'],
            };

            $notificacao = new CobrancaFaturaNotification(
                $fatura,
                $responsavel,
                $assunto,
                $mensagem,
                $canaisDisparo,
                $tipoAlerta
            );

            if ($usuario) {
                $usuario->notify($notificacao);
            } elseif ($responsavel->email) {
                // Notifica anonimamente por e-mail se não tiver User no painel
                Notification::route('mail', $responsavel->email)->notify($notificacao);
            }
        } catch (\Throwable $e) {
            $statusEnvio = 'falha';
            $erroMsg = $e->getMessage();
            Log::error("Erro no envio da régua de cobrança para fatura #{$fatura->id}: {$erroMsg}");
        }

        if ($regra) {
            ReguaCobrancaLog::create([
                'regua_cobranca_id' => $regra->id,
                'fatura_id' => $fatura->id,
                'pessoa_id' => $responsavel->id,
                'canal' => $canal,
                'destinatario' => $responsavel->email ?? $responsavel->telefone ?? $responsavel->nome,
                'mensagem_enviada' => $mensagem,
                'status_envio' => $statusEnvio,
                'erro' => $erroMsg,
                'data_envio' => $dataEnvio->toDateString(),
            ]);
        }
    }

    /**
     * Obtém todas as pessoas responsáveis que devem ser notificadas da fatura.
     */
    public function obterResponsaveisFatura(Fatura $fatura): Collection
    {
        $responsaveis = collect();

        // 1. Responsáveis financeiros do contrato
        if ($fatura->contrato && $fatura->contrato->responsaveisFinanceiros->isNotEmpty()) {
            foreach ($fatura->contrato->responsaveisFinanceiros as $rf) {
                if ($rf->pessoa) {
                    $responsaveis->push($rf->pessoa);
                }
            }
        }

        // 2. Se não houver responsável financeiro definido, busca os responsáveis do aluno
        if ($responsaveis->isEmpty() && $fatura->contrato?->matricula?->pessoa) {
            $aluno = $fatura->contrato->matricula->pessoa;
            if ($aluno->responsaveis && $aluno->responsaveis->isNotEmpty()) {
                foreach ($aluno->responsaveis as $resp) {
                    if ($resp->responsavel) {
                        $responsaveis->push($resp->responsavel);
                    }
                }
            } else {
                $responsaveis->push($aluno);
            }
        }

        return $responsaveis->unique('id');
    }

    /**
     * Substitui as tags de interpolação pelos dados reais da fatura.
     */
    public function substituirMacros(
        string $texto,
        Fatura $fatura,
        Pessoa $responsavel,
        ?Pessoa $aluno,
        Carbon $dataReferencia
    ): string {
        $vencimento = $fatura->vencimento ? Carbon::parse($fatura->vencimento) : $dataReferencia;
        $diasAtraso = $vencimento->isPast() ? max(0, $vencimento->diffInDays($dataReferencia, false)) : 0;
        $valorRestante = number_format($fatura->valor_restante, 2, ',', '.');

        $tags = [
            '{{RESPONSAVEL_NOME}}' => $responsavel->nome ?? 'Responsável',
            '{{ALUNO_NOME}}' => $aluno?->nome ?? 'Estudante',
            '{{NUMERO_FATURA}}' => (string) $fatura->id,
            '{{VALOR}}' => $valorRestante,
            '{{DATA_VENCIMENTO}}' => $vencimento->format('d/m/Y'),
            '{{DIAS_ATRASO}}' => (string) $diasAtraso,
            '{{LINK_PAGAMENTO}}' => url("/admin/faturas/{$fatura->id}"),
            '{{PIX_COPIA_COLA}}' => '00020126360014BR.GOV.BCB.PIX0114+551199999999520400005303986540'.$valorRestante.'5802BR5925TORRE360 GESTAO ESCOLAR6009SAO PAULO62070503***6304',
        ];

        return str_replace(array_keys($tags), array_values($tags), $texto);
    }

    /**
     * Atualiza automaticamente para 'Atrasado' as faturas pendentes cujo vencimento já expirou.
     */
    public function atualizarStatusFaturasAtrasadas(?Carbon $dataReferencia = null): int
    {
        $hoje = $dataReferencia ? $dataReferencia->copy()->startOfDay() : now()->startOfDay();

        return Fatura::where('vencimento', '<', $hoje->toDateString())
            ->where('status', StatusFatura::Pendente)
            ->update(['status' => StatusFatura::Atrasado]);
    }
}

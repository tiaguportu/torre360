<?php

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusRematricula;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\PeriodoRematricula;
use App\Models\Rematricula;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RematriculaService
{
    public function __construct(
        private GeracaoFaturasContratoService $geracaoFaturasService,
        private AssinafyService $assinafyService,
    ) {}

    /**
     * Retorna o período de rematrícula aberto no momento (se houver).
     */
    public function obterPeriodoAtivo(): ?PeriodoRematricula
    {
        return PeriodoRematricula::query()
            ->where('is_ativo', true)
            ->whereDate('data_inicio', '<=', now())
            ->whereDate('data_fim', '>=', now())
            ->first();
    }

    /**
     * Retorna as matrículas dos alunos vinculados a um usuário que são elegíveis para o período ativo.
     */
    public function obterMatriculasElegiveis(User $user, PeriodoRematricula $periodo): Collection
    {
        $pessoasAcessiveisIds = $user->pessoasAcessiveis()->pluck('id');

        return Matricula::query()
            ->whereIn('pessoa_id', $pessoasAcessiveisIds)
            ->where('periodo_letivo_id', $periodo->periodo_letivo_origem_id)
            ->whereIn('situacao', [SituacaoMatricula::ATIVA, 'ativa'])
            ->with(['pessoa', 'turma.serie.curso', 'periodoLetivo'])
            ->get();
    }

    /**
     * Inicia ou recupera um processo de rematrícula para uma matrícula de origem.
     */
    public function iniciarOuObter(Matricula $matricula, PeriodoRematricula $periodo, User $user): Rematricula
    {
        return Rematricula::firstOrCreate(
            [
                'periodo_rematricula_id' => $periodo->id,
                'matricula_origem_id' => $matricula->id,
            ],
            [
                'solicitante_user_id' => $user->id,
                'status' => StatusRematricula::Iniciada,
            ]
        );
    }

    /**
     * Efetiva a rematrícula no sistema:
     * Cria a nova matrícula no período subsequente, gera o contrato se houver template e conclui o processo.
     *
     * Idempotente: se a rematrícula já foi efetivada, devolve a matrícula já criada sem gerar
     * matrícula, contrato, faturas nem documento no Assinafy em duplicidade.
     */
    public function efetivar(Rematricula $rematricula): Matricula
    {
        $contrato = null;

        // Tudo que é gravado fica numa transação: se algum passo falhar (ex.: entrada maior que o
        // valor do contrato) nada é mantido pela metade, e uma nova tentativa não duplica registros.
        $novaMatricula = DB::transaction(function () use ($rematricula, &$contrato): Matricula {
            // Trava a linha para que duas efetivações simultâneas (duas abas, Portal + admin)
            // não passem juntas pela checagem de "já efetivada".
            $atual = Rematricula::query()->lockForUpdate()->findOrFail($rematricula->getKey());

            if ($matriculaExistente = $atual->novaMatricula) {
                return $matriculaExistente;
            }

            $matriculaOrigem = $atual->matriculaOrigem;
            $periodo = $atual->periodoRematricula;

            // Determina a turma de destino
            $turmaDestinoId = $atual->turma_destino_id;

            if (! $turmaDestinoId && $atual->serie_destino_id) {
                // Tenta encontrar uma turma na série pretendida com o turno pretendido no período de destino
                $turmaQuery = Turma::query()
                    ->where('periodo_letivo_id', $periodo->periodo_letivo_destino_id)
                    ->where('serie_id', $atual->serie_destino_id);

                if ($atual->turno_pretendido_id) {
                    $turmaQuery->where('turno_id', $atual->turno_pretendido_id);
                }

                $turmaDestino = $turmaQuery->first();
                $turmaDestinoId = $turmaDestino?->id;
            }

            // Se ainda não tiver turma específica de destino, mantém a turma equivalente ou cria sem turma
            $novaMatricula = Matricula::create([
                'pessoa_id' => $matriculaOrigem->pessoa_id,
                'turma_id' => $turmaDestinoId,
                'periodo_letivo_id' => $periodo->periodo_letivo_destino_id,
                'situacao' => SituacaoMatricula::ATIVA,
                'data_ativacao' => now()->toDateString(),
            ]);

            // Se houver template de contrato configurado, cria o contrato do novo período letivo
            if ($periodo->template_contrato_id) {
                // Sem data_aceite: ela só passa a existir quando o contrato é assinado
                // (ver AssinafyService::aplicarStatus()).
                $contrato = Contrato::create([
                    'matricula_id' => $novaMatricula->id,
                    'template_contrato_id' => $periodo->template_contrato_id,
                    'valor_total' => $periodo->valor_taxa ?? 0,
                ]);

                // Copia responsáveis financeiros do contrato anterior se existirem
                $contratoAnterior = Contrato::where('matricula_id', $matriculaOrigem->id)->first();
                if ($contratoAnterior) {
                    foreach ($contratoAnterior->responsaveisFinanceiros as $rf) {
                        $contrato->responsaveisFinanceiros()->create([
                            'pessoa_id' => $rf->pessoa_id,
                            'percentual' => $rf->percentual,
                        ]);
                    }
                }

                $atual->contrato_id = $contrato->id;

                // Gera a cobrança automaticamente (entrada + parcelas configuradas na campanha de rematrícula).
                // Os vencimentos partem do dia da rematrícula, não da assinatura: as faturas já existem
                // (e podem estar à vista da família) quando o contrato ainda aguarda assinatura.
                if ((float) $contrato->valor_total > 0) {
                    $this->geracaoFaturasService->gerar(
                        $contrato,
                        $periodo->quantidade_parcelas_padrao,
                        (float) $periodo->valor_entrada_padrao,
                        today()
                    );
                }

                // Só vira AguardandoAssinatura depois que o contrato for de fato enviado (abaixo).
                $atual->status = StatusRematricula::DadosConfirmados;
            } else {
                $atual->status = StatusRematricula::Confirmada;
                $atual->data_confirmacao = now();
            }

            $atual->nova_matricula_id = $novaMatricula->id;
            $atual->save();

            return $novaMatricula;
        });

        // Envia o contrato para assinatura digital fora da transação (são várias chamadas HTTP e o
        // lock da linha não deve ficar preso nelas). A rematrícula só é dada como Confirmada quando o
        // webhook do Assinafy avisar que foi assinado (ver AssinafyService::handleWebhook()). Se o envio
        // falhar (ex.: Assinafy não configurado), ela fica em DadosConfirmados para a secretaria — ou a
        // própria família, em Documentos e Contratos — enviar depois, sem travar o processo.
        if ($contrato) {
            $envio = $this->assinafyService->enviarContrato($contrato);

            if ($envio['success'] ?? false) {
                // Condicional para não desfazer a confirmação caso o webhook da assinatura já tenha chegado.
                Rematricula::query()
                    ->whereKey($rematricula->getKey())
                    ->where('status', StatusRematricula::DadosConfirmados->value)
                    ->update(['status' => StatusRematricula::AguardandoAssinatura->value]);
            }
        }

        $rematricula->refresh();

        return $novaMatricula;
    }
}

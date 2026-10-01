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
     */
    public function efetivar(Rematricula $rematricula): Matricula
    {
        $matriculaOrigem = $rematricula->matriculaOrigem;
        $periodo = $rematricula->periodoRematricula;

        // Determina a turma de destino
        $turmaDestinoId = $rematricula->turma_destino_id;

        if (! $turmaDestinoId && $rematricula->serie_destino_id) {
            // Tenta encontrar uma turma na série pretendida com o turno pretendido no período de destino
            $turmaQuery = Turma::query()
                ->where('periodo_letivo_id', $periodo->periodo_letivo_destino_id)
                ->where('serie_id', $rematricula->serie_destino_id);

            if ($rematricula->turno_pretendido_id) {
                $turmaQuery->where('turno_id', $rematricula->turno_pretendido_id);
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
            $contrato = Contrato::create([
                'matricula_id' => $novaMatricula->id,
                'template_contrato_id' => $periodo->template_contrato_id,
                'valor_total' => $periodo->valor_taxa ?? 0,
                'data_aceite' => now()->toDateString(),
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

            $rematricula->contrato_id = $contrato->id;

            // Gera a cobrança automaticamente (entrada + parcelas configuradas na campanha de rematrícula)
            if ((float) $contrato->valor_total > 0) {
                $this->geracaoFaturasService->gerar(
                    $contrato,
                    $periodo->quantidade_parcelas_padrao,
                    (float) $periodo->valor_entrada_padrao
                );
            }

            // Envia o contrato para assinatura digital; a rematrícula só é dada como
            // Confirmada quando o webhook do Assinafy avisar que foi assinado
            // (ver AssinafyService::handleWebhook()). Se o envio falhar (ex.: Assinafy
            // não configurado), a rematrícula fica em DadosConfirmados para a secretaria
            // resolver manualmente — não trava o processo da família.
            $envio = $this->assinafyService->enviarContrato($contrato);

            $rematricula->status = ($envio['success'] ?? false)
                ? StatusRematricula::AguardandoAssinatura
                : StatusRematricula::DadosConfirmados;
        } else {
            $rematricula->status = StatusRematricula::Confirmada;
            $rematricula->data_confirmacao = now();
        }

        $rematricula->nova_matricula_id = $novaMatricula->id;
        $rematricula->save();

        return $novaMatricula;
    }
}

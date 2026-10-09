<?php

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusFatura;
use App\Enums\StatusRematricula;
use App\Exceptions\TurmaIndisponivelException;
use App\Filament\Resources\Rematriculas\RematriculaResource;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Matricula;
use App\Models\PeriodoRematricula;
use App\Models\Rematricula;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RematriculaService
{
    public function __construct(
        private GeracaoFaturasContratoService $geracaoFaturasService,
        private AssinafyService $assinafyService,
        private TurmaVagasService $vagasService,
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
            ->doPeriodo($periodo->periodo_letivo_origem_id)
            ->whereIn('situacao', [SituacaoMatricula::ATIVA, 'ativa'])
            ->with(['pessoa', 'turma.serie.curso', 'periodoLetivo'])
            ->get();
    }

    /**
     * Turmas que podem receber a rematrícula: do período letivo de destino da campanha, abertas para
     * matrícula (Planejada ou Ativa) e, quando a família informou a série pretendida, dessa série.
     *
     * @return Collection<int, Turma>
     */
    public function turmasParaEscolha(PeriodoRematricula $periodo, ?int $serieId = null): Collection
    {
        return Turma::query()
            ->abertasParaMatricula()
            ->where('periodo_letivo_id', $periodo->periodo_letivo_destino_id)
            ->when($serieId, fn ($query) => $query->where('serie_id', $serieId))
            ->with('turno')
            ->orderBy('nome')
            ->get();
    }

    /**
     * Opções para o Select de turma: rótulos com ocupação ("12/30 vagas") e a lista das lotadas
     * (para desabilitá-las na tela).
     *
     * @return array{opcoes: array<int, string>, lotadas: list<int>}
     */
    public function opcoesDeTurma(PeriodoRematricula $periodo, ?int $serieId = null): array
    {
        $opcoes = [];
        $lotadas = [];

        foreach ($this->turmasParaEscolha($periodo, $serieId) as $turma) {
            $opcoes[$turma->id] = $this->vagasService->rotulo($turma, $turma->turno?->nome);

            if ($this->vagasService->estaLotada($turma)) {
                $lotadas[] = $turma->id;
            }
        }

        return ['opcoes' => $opcoes, 'lotadas' => $lotadas];
    }

    /**
     * Turma sugerida para pré-selecionar: a já definida na rematrícula ou, se só existir uma turma
     * com vaga na série e no turno que a família pediu, essa.
     */
    public function turmaSugerida(Rematricula $rematricula, PeriodoRematricula $periodo): ?int
    {
        if ($rematricula->turma_destino_id) {
            return (int) $rematricula->turma_destino_id;
        }

        $candidatas = $this->turmasParaEscolha($periodo, $rematricula->serie_destino_id)
            ->when($rematricula->turno_pretendido_id, fn (Collection $turmas) => $turmas->where('turno_id', $rematricula->turno_pretendido_id))
            ->reject(fn (Turma $turma) => $this->vagasService->estaLotada($turma));

        return $candidatas->count() === 1 ? (int) $candidatas->first()->id : null;
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
     * A turma de destino é obrigatória: vem do argumento `$turmaId` (escolhida pela secretaria) ou,
     * se omitido, de `turma_destino_id` da própria rematrícula. Ela precisa ser do período letivo de
     * destino da campanha, da série pretendida (quando informada), estar aberta para matrículas
     * (Planejada ou Ativa) e ter vaga — a checagem de vaga trava a turma na transação.
     *
     * Idempotente: se a rematrícula já foi efetivada, devolve a matrícula já criada sem gerar
     * matrícula, contrato, faturas nem documento no Assinafy em duplicidade.
     *
     * @throws TurmaIndisponivelException turma de outro período/série, fechada ou lotada
     * @throws \DomainException turma de destino não informada
     */
    public function efetivar(Rematricula $rematricula, ?int $turmaId = null): Matricula
    {
        $contrato = null;

        // Tudo que é gravado fica numa transação: se algum passo falhar (ex.: entrada maior que o
        // valor do contrato) nada é mantido pela metade, e uma nova tentativa não duplica registros.
        $novaMatricula = DB::transaction(function () use ($rematricula, $turmaId, &$contrato): Matricula {
            // Trava a linha para que duas efetivações simultâneas (duas abas, Portal + admin)
            // não passem juntas pela checagem de "já efetivada".
            $atual = Rematricula::query()->lockForUpdate()->findOrFail($rematricula->getKey());

            if ($atual->estaCancelada()) {
                throw new \DomainException('Esta rematrícula foi cancelada e não pode ser efetivada. Exclua-a e inicie uma nova, se for o caso.');
            }

            if ($matriculaExistente = $atual->novaMatricula) {
                return $matriculaExistente;
            }

            $matriculaOrigem = $atual->matriculaOrigem;
            $periodo = $atual->periodoRematricula;

            $turmaDestinoId = $turmaId ?? $atual->turma_destino_id;

            if (! $turmaDestinoId) {
                throw new \DomainException('Escolha a turma de destino antes de efetivar a rematrícula.');
            }

            // Trava a turma (depois da rematrícula, sempre nessa ordem) e confere status e vaga.
            $turma = $this->vagasService->garantirVaga($turmaDestinoId);

            if ((int) $turma->periodo_letivo_id !== (int) $periodo->periodo_letivo_destino_id) {
                throw TurmaIndisponivelException::periodoDiferente($turma);
            }

            if ($atual->serie_destino_id && (int) $turma->serie_id !== (int) $atual->serie_destino_id) {
                throw TurmaIndisponivelException::serieDiferente($turma);
            }

            // A turma escolhida passa a ser a fonte da verdade da rematrícula.
            $atual->turma_destino_id = $turma->id;
            $atual->serie_destino_id = $turma->serie_id;
            $atual->turno_pretendido_id ??= $turma->turno_id;

            // Com contrato a assinar, a matrícula nasce Pendente: reserva a vaga, mas só vira Ativa quando o
            // contrato for assinado (Rematricula::confirmarPelaAssinatura()). Sem contrato, já nasce Ativa.
            $novaMatricula = Matricula::create([
                'pessoa_id' => $matriculaOrigem->pessoa_id,
                'turma_id' => $turma->id,
                'situacao' => $periodo->template_contrato_id ? SituacaoMatricula::PENDENTE : SituacaoMatricula::ATIVA,
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

    /**
     * Cancela a rematrícula (desistência, erro de lançamento etc.).
     *
     * Libera a vaga — a nova matrícula vira Cancelada — e cancela as faturas em aberto do contrato.
     * Faturas já pagas (total ou parcialmente) NÃO são mexidas: o estorno é manual, e o resumo avisa
     * quantas são. O contrato e o documento no Assinafy permanecem para histórico, mas uma assinatura
     * tardia não reativa a rematrícula (ver {@see Rematricula::confirmarPelaAssinatura()}).
     *
     * Idempotente: cancelar de novo uma rematrícula já cancelada não faz nada.
     *
     * @return array{ja_cancelada: bool, matricula_cancelada: bool, faturas_canceladas: int, faturas_pagas: int}
     */
    public function cancelar(Rematricula $rematricula, ?string $motivo = null): array
    {
        return DB::transaction(function () use ($rematricula, $motivo): array {
            $atual = Rematricula::query()->lockForUpdate()->findOrFail($rematricula->getKey());

            if ($atual->estaCancelada()) {
                return ['ja_cancelada' => true, 'matricula_cancelada' => false, 'faturas_canceladas' => 0, 'faturas_pagas' => 0];
            }

            $resumo = $this->aplicarCancelamento($atual);

            $observacao = trim('Cancelada em '.now()->format('d/m/Y H:i').($motivo ? ": {$motivo}" : '.'));

            // Quietly: os efeitos já foram aplicados acima e o evento `updated` os aplicaria de novo.
            $atual->forceFill([
                'status' => StatusRematricula::Cancelada,
                'observacoes' => trim(($atual->observacoes ? $atual->observacoes."\n" : '').$observacao),
            ])->saveQuietly();

            $rematricula->refresh();

            return ['ja_cancelada' => false] + $resumo;
        });
    }

    /**
     * Efeitos do cancelamento sobre a nova matrícula e o contrato. Idempotente (usado também pelo evento
     * `updated` do model quando o status vira Cancelada por qualquer outro caminho, como o formulário de edição).
     *
     * @return array{matricula_cancelada: bool, faturas_canceladas: int, faturas_pagas: int}
     */
    public function aplicarCancelamento(Rematricula $rematricula): array
    {
        $resumo = ['matricula_cancelada' => false, 'faturas_canceladas' => 0, 'faturas_pagas' => 0];

        DB::transaction(function () use ($rematricula, &$resumo): void {
            if ($rematricula->nova_matricula_id) {
                $matricula = Matricula::query()->lockForUpdate()->find($rematricula->nova_matricula_id);

                if ($matricula && in_array($matricula->situacao, TurmaVagasService::SITUACOES_QUE_OCUPAM_VAGA, true)) {
                    $matricula->update([
                        'situacao' => SituacaoMatricula::CANCELADA,
                        'data_desativacao' => today(),
                    ]);
                    $resumo['matricula_cancelada'] = true;
                }
            }

            if ($rematricula->contrato_id) {
                $resumo['faturas_canceladas'] = Fatura::query()
                    ->where('contrato_id', $rematricula->contrato_id)
                    ->whereIn('status', [StatusFatura::Pendente->value, StatusFatura::Atrasado->value])
                    ->update(['status' => StatusFatura::Cancelado->value]);

                $resumo['faturas_pagas'] = Fatura::query()
                    ->where('contrato_id', $rematricula->contrato_id)
                    ->whereIn('status', [StatusFatura::Pago->value, StatusFatura::Parcial->value])
                    ->count();
            }
        });

        return $resumo;
    }

    /**
     * Avisa a equipe (quem pode efetivar rematrículas) que uma família registrou a intenção e falta
     * escolher a turma. Aviso no sino do painel, com atalho para a lista de Rematrículas.
     */
    public function notificarEquipe(Rematricula $rematricula): void
    {
        $destinatarios = $this->equipeQuePodeEfetivar();

        if ($destinatarios->isEmpty()) {
            return;
        }

        $aluno = Matricula::query()->with('pessoa')->find($rematricula->matricula_origem_id)?->pessoa?->nome ?? 'Um aluno';
        $campanha = PeriodoRematricula::query()->find($rematricula->periodo_rematricula_id)?->nome ?? 'a campanha de rematrícula';
        $serie = Serie::query()->find($rematricula->serie_destino_id)?->nome;

        Notification::make()
            ->title('Rematrícula aguardando turma')
            ->body("{$aluno} registrou as preferências para {$campanha}".($serie ? " (série pretendida: {$serie})" : '').'. Escolha a turma e efetive a rematrícula.')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->iconColor('warning')
            ->actions([
                Action::make('abrir')
                    ->label('Abrir Rematrículas')
                    ->button()
                    // Quem registra é a família, no painel do Portal; a lista de Rematrículas fica no painel admin.
                    ->url(RematriculaResource::getUrl('index', panel: 'admin')),
            ])
            ->sendToDatabase($destinatarios);
    }

    /**
     * Usuários ativos com a permissão de efetivar rematrículas (diretamente ou por papel), mais os super admins.
     *
     * @return Collection<int, User>
     */
    private function equipeQuePodeEfetivar(): Collection
    {
        $usuarios = collect();

        if (Permission::where('name', 'Update:Rematricula')->where('guard_name', 'web')->exists()) {
            $usuarios = User::ativos()->permission('Update:Rematricula')->get();
        }

        if (Role::where('name', 'super_admin')->where('guard_name', 'web')->exists()) {
            $usuarios = $usuarios->merge(User::ativos()->role('super_admin')->get());
        }

        return $usuarios->unique('id')->values();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MotivoCancelamentoMatricula;
use App\Enums\OrigemCancelamentoMatricula;
use App\Enums\SituacaoMatricula;
use App\Enums\StatusCancelamentoMatricula;
use App\Enums\StatusFatura;
use App\Enums\StatusRematricula;
use App\Enums\StatusTransferencia;
use App\Enums\TipoTransferencia;
use App\Filament\Resources\CancelamentosMatricula\CancelamentoMatriculaResource;
use App\Models\CancelamentoMatricula;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\Rematricula;
use App\Models\TransferenciaEscolar;
use App\Models\User;
use App\Support\ArquivoPrivado;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cancelamento de matrícula e distrato (Onda 61).
 *
 * Fluxo: a família pede (ou a secretaria registra) → a secretaria confere a situação financeira e decide o destino de
 * cada fatura (manter, cancelar ou devolver) ao aprovar → imprime o distrato, colhe a assinatura e anexa a cópia → conclui,
 * e só aí a matrícula vira Cancelada e as faturas mudam. Nada disso toca na Declaração de Transferência: a lei não permite
 * reter documento de transferência por inadimplência (Lei nº 9.870/1999, art. 6º, § 2º).
 *
 * Regra da proposta de cada fatura: o mês da saída é cobrado inteiro (sem proporcional); o que vence depois dele e está
 * sem pagamento é cancelado, e o que já foi pago adiantado é devolvido; a entrada (matrícula) não se devolve.
 */
class CancelamentoMatriculaService
{
    public const MANTER = 'manter';

    public const CANCELAR = 'cancelar';

    public const DEVOLVER = 'devolver';

    /**
     * Situações que ainda admitem cancelamento. Cancelada, concluída e evasão já estão encerradas.
     *
     * @var list<SituacaoMatricula>
     */
    public const SITUACOES_CANCELAVEIS = [
        SituacaoMatricula::ATIVA,
        SituacaoMatricula::TRANCADA,
        SituacaoMatricula::PENDENTE,
        SituacaoMatricula::RESERVA,
    ];

    public function __construct(private readonly EncargosAtrasoService $encargos) {}

    // --- Quem pode pedir ---------------------------------------------------------------------------------------------

    /**
     * Responsável pelo aluno, responsável financeiro do contrato ou o próprio aluno maior de idade. O aluno menor não
     * pede o próprio cancelamento.
     */
    public function familiaPodeAgir(Matricula $matricula, User $user): bool
    {
        $pessoasDoUsuario = $user->pessoas()->pluck('pessoa.id');

        if ($pessoasDoUsuario->isEmpty()) {
            return false;
        }

        $ehResponsavelDoAluno = DB::table('aluno_responsavel')
            ->where('aluno_id', $matricula->pessoa_id)
            ->whereIn('responsavel_id', $pessoasDoUsuario)
            ->exists();

        if ($ehResponsavelDoAluno) {
            return true;
        }

        $contrato = $matricula->contrato;

        if ($contrato && $contrato->responsaveisFinanceiros()->whereIn('pessoa_id', $pessoasDoUsuario)->exists()) {
            return true;
        }

        if ($pessoasDoUsuario->contains($matricula->pessoa_id)) {
            $nascimento = $matricula->pessoa?->data_nascimento;

            return $nascimento !== null && Carbon::parse($nascimento)->age >= 18;
        }

        return false;
    }

    /**
     * Por que a matrícula não aceita um pedido agora, ou null se aceita.
     */
    public function motivoDeNaoPoderSolicitar(Matricula $matricula): ?string
    {
        if (! in_array($matricula->situacao, self::SITUACOES_CANCELAVEIS, true)) {
            return 'A matrícula está '.mb_strtolower($matricula->situacao?->getLabel() ?? 'encerrada').' e não pode mais ser cancelada.';
        }

        if (CancelamentoMatricula::query()->abertos()->where('matricula_id', $matricula->id)->exists()) {
            return 'Já existe um pedido de cancelamento em andamento para esta matrícula.';
        }

        return null;
    }

    // --- Pedido ------------------------------------------------------------------------------------------------------

    /**
     * Abre o pedido. A família escolhe a data de saída a partir de hoje (mais a antecedência da configuração); a secretaria
     * pode registrar qualquer data, inclusive passada, quando a família já saiu e o pedido chegou depois.
     *
     * @param  array{motivo: MotivoCancelamentoMatricula|string, motivo_detalhe?: ?string, data_efetiva: mixed, gerar_transferencia?: ?bool, escola_destino?: ?string, observacao?: ?string}  $dados
     *
     * @throws \DomainException
     */
    public function solicitar(Matricula $matricula, array $dados, ?User $por = null, OrigemCancelamentoMatricula $origem = OrigemCancelamentoMatricula::Familia): CancelamentoMatricula
    {
        $cancelamento = DB::transaction(function () use ($matricula, $dados, $por, $origem): CancelamentoMatricula {
            // Trava a matrícula: dois cliques ou duas pessoas pedindo ao mesmo tempo viram um pedido só.
            $matricula = Matricula::query()->lockForUpdate()->findOrFail($matricula->id);

            if ($motivoDeNao = $this->motivoDeNaoPoderSolicitar($matricula)) {
                throw new \DomainException($motivoDeNao);
            }

            $dataEfetiva = Carbon::parse($dados['data_efetiva'])->startOfDay();

            if ($origem === OrigemCancelamentoMatricula::Familia) {
                $minima = today()->addDays((int) config('cancelamento.antecedencia_minima_dias', 0));

                if ($dataEfetiva->lt($minima)) {
                    throw new \DomainException('A data de saída precisa ser a partir de '.$minima->format('d/m/Y').'.');
                }
            }

            $motivo = $dados['motivo'] instanceof MotivoCancelamentoMatricula
                ? $dados['motivo']
                : MotivoCancelamentoMatricula::from($dados['motivo']);

            $cancelamento = CancelamentoMatricula::create([
                'matricula_id' => $matricula->id,
                'origem' => $origem,
                'motivo' => $motivo,
                'motivo_detalhe' => filled($dados['motivo_detalhe'] ?? null) ? trim((string) $dados['motivo_detalhe']) : null,
                'data_efetiva' => $dataEfetiva,
                'status' => StatusCancelamentoMatricula::Solicitado,
                'gerar_transferencia' => (bool) ($dados['gerar_transferencia'] ?? ($motivo === MotivoCancelamentoMatricula::MudancaEscola)),
                'escola_destino' => filled($dados['escola_destino'] ?? null) ? trim((string) $dados['escola_destino']) : null,
                'observacao' => filled($dados['observacao'] ?? null) ? trim((string) $dados['observacao']) : null,
                'solicitado_por_user_id' => $por?->id,
            ]);

            $this->registrarLog($cancelamento, $por, 'Cancelamento de matrícula solicitado.', [
                'origem' => $origem->value,
                'motivo' => $motivo->value,
                'data_efetiva' => $dataEfetiva->toDateString(),
            ]);

            return $cancelamento;
        });

        if ($origem === OrigemCancelamentoMatricula::Familia) {
            try {
                $this->notificarEquipe($cancelamento);
            } catch (\Throwable $e) {
                Log::warning('Falha ao avisar a equipe sobre pedido de cancelamento de matrícula.', ['cancelamento_id' => $cancelamento->id, 'erro' => $e->getMessage()]);
            }
        }

        return $cancelamento;
    }

    // --- Situação financeira -----------------------------------------------------------------------------------------

    /**
     * Uma linha por fatura do contrato (menos as já canceladas) com o valor em aberto, a multa e os juros até hoje, a
     * decisão sugerida e as decisões que a fatura aceita.
     *
     * @return Collection<int, array{fatura_id: int, descricao: string, vencimento: CarbonInterface, status: StatusFatura, valor: float, valor_pago: float, saldo: float, encargos: float, total_em_aberto: float, decisao: string, sugestao: string, opcoes: list<string>}>
     */
    public function proposta(CancelamentoMatricula $cancelamento, ?CarbonInterface $em = null): Collection
    {
        $cancelamento->loadMissing('matricula.contrato');
        $contrato = $cancelamento->matricula->contrato;

        if (! $contrato) {
            return collect();
        }

        $limite = $cancelamento->data_efetiva->copy()->endOfMonth()->startOfDay();

        return $contrato->faturas()
            ->where('status', '!=', StatusFatura::Cancelado->value)
            ->with(['itens', 'transacoes', 'contrato'])
            ->reorder()
            ->orderBy('vencimento')
            ->orderBy('id')
            ->get()
            ->map(function (Fatura $fatura) use ($limite, $em): array {
                $calculo = $this->encargos->calcular($fatura, $em);
                $pago = round((float) $fatura->valor_pago, 2);
                [$decisao, $sugestao] = $this->sugerir($fatura, $limite, $pago);

                return [
                    'fatura_id' => $fatura->id,
                    'descricao' => $this->encargos->descricao($fatura),
                    'vencimento' => $fatura->vencimento,
                    'status' => $fatura->status,
                    'valor' => round((float) $fatura->valor, 2),
                    'valor_pago' => $pago,
                    'saldo' => $calculo['principal'],
                    'encargos' => round($calculo['multa'] + $calculo['juros'], 2),
                    'total_em_aberto' => $calculo['total'],
                    'decisao' => $decisao,
                    'sugestao' => $sugestao,
                    'opcoes' => $pago > 0 ? [self::MANTER, self::DEVOLVER] : [self::MANTER, self::CANCELAR],
                ];
            })
            ->values();
    }

    /**
     * @return array{0: string, 1: string} decisão e o porquê, para a secretaria conferir
     */
    private function sugerir(Fatura $fatura, CarbonInterface $limite, float $pago): array
    {
        if (str_starts_with(mb_strtolower(trim($this->encargos->descricao($fatura))), 'entrada')) {
            return [self::MANTER, 'Entrada: não se cancela nem se devolve.'];
        }

        $aberta = in_array($fatura->status, [StatusFatura::Pendente, StatusFatura::Atrasado, StatusFatura::Parcial], true);

        if ($fatura->vencimento->copy()->startOfDay()->lte($limite)) {
            return [self::MANTER, $aberta ? 'Vence até o mês da saída: continua devida.' : 'Já paga.'];
        }

        if ($pago > 0) {
            return [self::DEVOLVER, 'Vence depois do mês da saída e já tem pagamento: devolver o que foi pago.'];
        }

        return [self::CANCELAR, 'Vence depois do mês da saída e não foi paga: cancelar.'];
    }

    /**
     * Confere as decisões informadas (id da fatura => decisão) contra a proposta: o que não for informado fica com a
     * sugestão, e uma decisão que a fatura não aceita (cancelar fatura paga, devolver fatura sem pagamento) é recusada.
     *
     * @param  array<int|string, string>  $informadas
     * @return array<int, string>
     *
     * @throws \DomainException
     */
    public function normalizarDecisoes(CancelamentoMatricula $cancelamento, array $informadas, ?Collection $proposta = null): array
    {
        $proposta ??= $this->proposta($cancelamento);

        $desconhecidas = array_diff(array_map('intval', array_keys($informadas)), $proposta->pluck('fatura_id')->all());

        if ($desconhecidas !== []) {
            throw new \DomainException('Há faturas que não pertencem a esta matrícula (ou já foram canceladas).');
        }

        $decisoes = [];

        foreach ($proposta as $linha) {
            $decisao = $informadas[$linha['fatura_id']] ?? $linha['decisao'];

            if (! in_array($decisao, $linha['opcoes'], true)) {
                $porque = $linha['valor_pago'] > 0
                    ? 'ela já tem pagamento: mantenha ou devolva'
                    : 'ela não tem pagamento: mantenha ou cancele';

                throw new \DomainException("A fatura \"{$linha['descricao']}\" não pode ficar como \"{$decisao}\": {$porque}.");
            }

            $decisoes[$linha['fatura_id']] = $decisao;
        }

        return $decisoes;
    }

    /**
     * O que a família ainda deve (faturas mantidas, com multa e juros até hoje) e o que a escola devolve (o pago das
     * faturas marcadas para devolver).
     *
     * @param  Collection<int, array<string, mixed>>  $proposta
     * @param  array<int, string>  $decisoes
     * @return array{valor_devido: float, valor_a_devolver: float}
     */
    public function totais(Collection $proposta, array $decisoes): array
    {
        $devido = 0.0;
        $devolver = 0.0;

        foreach ($proposta as $linha) {
            $decisao = $decisoes[$linha['fatura_id']] ?? $linha['decisao'];

            if ($decisao === self::MANTER) {
                $devido += $linha['total_em_aberto'];
            } elseif ($decisao === self::DEVOLVER) {
                $devolver += $linha['valor_pago'];
            }
        }

        return ['valor_devido' => round($devido, 2), 'valor_a_devolver' => round($devolver, 2)];
    }

    // --- Decisão -----------------------------------------------------------------------------------------------------

    /**
     * Aprova o pedido: fixa as decisões por fatura, os valores do distrato e (se for o caso) a multa rescisória e a
     * transferência. A partir daqui o distrato pode ser impresso.
     *
     * @param  array{decisoes?: array<int|string, string>, multa_rescisoria?: float|int|string|null, gerar_transferencia?: ?bool, escola_destino?: ?string, observacao?: ?string, data_efetiva?: mixed}  $dados
     *
     * @throws \DomainException
     */
    public function aprovar(CancelamentoMatricula $cancelamento, User $por, array $dados = []): CancelamentoMatricula
    {
        return DB::transaction(function () use ($cancelamento, $por, $dados): CancelamentoMatricula {
            $atual = CancelamentoMatricula::query()->lockForUpdate()->findOrFail($cancelamento->id);

            if (! $atual->estaSolicitado()) {
                throw new \DomainException('Só dá para aprovar um pedido que ainda aguarda a secretaria.');
            }

            if (filled($dados['data_efetiva'] ?? null)) {
                $atual->data_efetiva = Carbon::parse($dados['data_efetiva'])->startOfDay();
            }

            $multa = round(max(0.0, (float) ($dados['multa_rescisoria'] ?? 0)), 2);
            $proposta = $this->proposta($atual);
            $decisoes = $this->normalizarDecisoes($atual, $dados['decisoes'] ?? [], $proposta);
            $totais = $this->totais($proposta, $decisoes);

            $atual->forceFill([
                'status' => StatusCancelamentoMatricula::Aprovado,
                'decisoes' => $decisoes,
                'valor_devido' => $totais['valor_devido'],
                'valor_a_devolver' => $totais['valor_a_devolver'],
                'multa_rescisoria' => $multa,
                'gerar_transferencia' => (bool) ($dados['gerar_transferencia'] ?? $atual->gerar_transferencia),
                'escola_destino' => array_key_exists('escola_destino', $dados) ? (filled($dados['escola_destino']) ? trim((string) $dados['escola_destino']) : null) : $atual->escola_destino,
                'observacao' => array_key_exists('observacao', $dados) ? (filled($dados['observacao']) ? trim((string) $dados['observacao']) : null) : $atual->observacao,
                'distrato_gerado_em' => now(),
                'decidido_por_user_id' => $por->id,
                'decidido_em' => now(),
            ])->save();

            $this->registrarLog($atual, $por, 'Cancelamento de matrícula aprovado.', [
                'faturas_a_cancelar' => count(array_keys($decisoes, self::CANCELAR, true)),
                'faturas_a_devolver' => count(array_keys($decisoes, self::DEVOLVER, true)),
                'com_multa' => $multa > 0,
            ]);

            return $atual->refresh();
        });
    }

    /**
     * @throws \DomainException
     */
    public function recusar(CancelamentoMatricula $cancelamento, User $por, string $motivo): CancelamentoMatricula
    {
        if (blank($motivo)) {
            throw new \DomainException('Informe o motivo da recusa: a família vai ler.');
        }

        return DB::transaction(function () use ($cancelamento, $por, $motivo): CancelamentoMatricula {
            $atual = CancelamentoMatricula::query()->lockForUpdate()->findOrFail($cancelamento->id);

            if (! $atual->estaSolicitado()) {
                throw new \DomainException('Só dá para recusar um pedido que ainda aguarda a secretaria.');
            }

            $atual->forceFill([
                'status' => StatusCancelamentoMatricula::Recusado,
                'motivo_recusa' => trim($motivo),
                'decidido_por_user_id' => $por->id,
                'decidido_em' => now(),
            ])->save();

            $this->registrarLog($atual, $por, 'Cancelamento de matrícula recusado.');

            return $atual->refresh();
        });
    }

    /**
     * A família desiste enquanto o pedido aguarda a secretaria; depois de aprovado, só a equipe cancela o processo.
     *
     * @throws \DomainException
     */
    public function retirar(CancelamentoMatricula $cancelamento, User $por): CancelamentoMatricula
    {
        return DB::transaction(function () use ($cancelamento, $por): CancelamentoMatricula {
            $atual = CancelamentoMatricula::query()->lockForUpdate()->findOrFail($cancelamento->id);

            if (! $atual->estaAberto()) {
                throw new \DomainException('Este pedido já foi encerrado.');
            }

            if ($atual->estaAprovado() && ! $por->can('Aprovar:CancelamentoMatricula')) {
                throw new \DomainException('Depois de aprovado, só a secretaria encerra o pedido. Fale com a escola.');
            }

            $atual->forceFill([
                'status' => StatusCancelamentoMatricula::Retirado,
                'decidido_por_user_id' => $atual->decidido_por_user_id ?? $por->id,
                'decidido_em' => $atual->decidido_em ?? now(),
            ])->save();

            $this->registrarLog($atual, $por, 'Pedido de cancelamento de matrícula retirado.');

            return $atual->refresh();
        });
    }

    // --- Distrato assinado -------------------------------------------------------------------------------------------

    /**
     * Guarda a cópia assinada do distrato (PDF ou foto) no disco privado. O tipo vem do conteúdo, não do nome.
     *
     * @throws \DomainException
     */
    public function anexarDistratoAssinado(CancelamentoMatricula $cancelamento, UploadedFile $arquivo, ?User $por = null): CancelamentoMatricula
    {
        if (! $cancelamento->estaAprovado()) {
            throw new \DomainException('O distrato assinado só é anexado depois da aprovação e antes da conclusão.');
        }

        $anterior = $cancelamento->distrato_assinado_path;
        $caminho = ArquivoPrivado::guardarDocumento($arquivo, "cancelamentos_matricula/{$cancelamento->id}", 'distrato-assinado-'.now()->format('YmdHis'));

        $cancelamento->update(['distrato_assinado_path' => $caminho]);

        if ($anterior && $anterior !== $caminho) {
            Storage::disk('local')->delete($anterior);
        }

        $this->registrarLog($cancelamento, $por, 'Distrato assinado anexado.');

        return $cancelamento;
    }

    // --- Conclusão ---------------------------------------------------------------------------------------------------

    /**
     * Aplica o cancelamento: matrícula Cancelada, faturas canceladas conforme as decisões, multa rescisória (se houver),
     * rematrícula do próximo ano ainda sem contrato cancelada e, se marcado, a Declaração de Transferência.
     *
     * Só a partir da data de saída e, se a configuração pede, com o distrato assinado anexado. Uma fatura marcada para
     * cancelar que recebeu pagamento depois da aprovação não é cancelada: vira aviso para a secretaria resolver.
     *
     * @return array{faturas_canceladas: int, multa_fatura_id: ?int, transferencia: ?string, avisos: list<string>}
     *
     * @throws \DomainException
     */
    public function concluir(CancelamentoMatricula $cancelamento, User $por): array
    {
        $resumo = ['faturas_canceladas' => 0, 'multa_fatura_id' => null, 'transferencia' => null, 'avisos' => []];

        DB::transaction(function () use ($cancelamento, $por, &$resumo): void {
            $atual = CancelamentoMatricula::query()->lockForUpdate()->findOrFail($cancelamento->id);

            if (! $atual->estaAprovado()) {
                throw new \DomainException('Só dá para concluir um pedido aprovado.');
            }

            if ($atual->data_efetiva->isFuture()) {
                throw new \DomainException('A saída está marcada para '.$atual->data_efetiva->format('d/m/Y').'. Conclua a partir dessa data.');
            }

            if (config('cancelamento.exige_distrato_assinado', true) && ! $atual->temDistratoAssinado()) {
                throw new \DomainException('Anexe o distrato assinado antes de concluir.');
            }

            $matricula = Matricula::query()->with(['pessoa', 'contrato'])->findOrFail($atual->matricula_id);

            $this->aplicarDecisoesNasFaturas($atual, $resumo);

            $matricula->update([
                'situacao' => SituacaoMatricula::CANCELADA,
                'data_desativacao' => $atual->data_efetiva,
            ]);

            if ((float) $atual->multa_rescisoria > 0 && $matricula->contrato) {
                $resumo['multa_fatura_id'] = $this->criarFaturaDaMulta($matricula, (float) $atual->multa_rescisoria)->id;
            }

            $this->cancelarRematriculasNaoEfetivadas($matricula, $resumo);

            if ($atual->gerar_transferencia) {
                [$transferencia, $estado, $aviso] = $this->abrirTransferencia($atual, $matricula, $por);
                $atual->transferencia_escolar_id = $transferencia->id;
                $resumo['transferencia'] = $estado;

                if ($aviso) {
                    $resumo['avisos'][] = 'Declaração de Transferência não emitida: '.$aviso.' O processo ficou em andamento em Transferências Escolares.';
                }
            }

            $atual->forceFill([
                'status' => StatusCancelamentoMatricula::Concluido,
                'concluido_por_user_id' => $por->id,
                'concluido_em' => now(),
            ])->save();

            $this->registrarLog($atual, $por, 'Cancelamento de matrícula concluído.', [
                'faturas_canceladas' => $resumo['faturas_canceladas'],
                'multa_cobrada' => $resumo['multa_fatura_id'] !== null,
                'transferencia' => $resumo['transferencia'],
            ]);
        });

        return $resumo;
    }

    /**
     * @param  array{faturas_canceladas: int, multa_fatura_id: ?int, transferencia: ?string, avisos: list<string>}  $resumo
     */
    private function aplicarDecisoesNasFaturas(CancelamentoMatricula $cancelamento, array &$resumo): void
    {
        $decisoes = $cancelamento->decisoes ?? [];
        $contratoId = Matricula::query()->with('contrato')->find($cancelamento->matricula_id)?->contrato?->id;

        if ($decisoes === [] || ! $contratoId) {
            return;
        }

        $faturas = Fatura::query()
            ->where('contrato_id', $contratoId)
            ->whereIn('id', array_keys($decisoes))
            ->with(['itens', 'transacoes'])
            ->get();

        foreach ($faturas as $fatura) {
            $decisao = $decisoes[$fatura->id] ?? self::MANTER;

            if ($decisao === self::MANTER || $fatura->status === StatusFatura::Cancelado) {
                continue;
            }

            if ($decisao === self::CANCELAR && (float) $fatura->valor_pago > 0) {
                $resumo['avisos'][] = 'A fatura "'.$this->encargos->descricao($fatura).'" recebeu pagamento depois da aprovação e não foi cancelada: decida se devolve.';

                continue;
            }

            $this->cancelarCobrancaNoGateway($fatura);
            $fatura->update(['status' => StatusFatura::Cancelado]);
            $resumo['faturas_canceladas']++;
        }
    }

    /**
     * A cobrança já emitida (PIX, boleto) precisa sair do gateway, ou a família pode pagar uma fatura cancelada. Falha
     * do gateway não impede o cancelamento: fica no log.
     */
    private function cancelarCobrancaNoGateway(Fatura $fatura): void
    {
        if (blank($fatura->gateway_id) || blank($fatura->gateway)) {
            return;
        }

        try {
            GatewayPagamentoManager::resolver($fatura->gateway)->cancelar($fatura->gateway_id);
        } catch (\Throwable $e) {
            Log::warning('Falha ao cancelar a cobrança no gateway ao cancelar a matrícula.', ['fatura_id' => $fatura->id, 'erro' => $e->getMessage()]);
        }
    }

    private function criarFaturaDaMulta(Matricula $matricula, float $valor): Fatura
    {
        $fatura = Fatura::create([
            'contrato_id' => $matricula->contrato->id,
            'vencimento' => today()->addDays((int) config('cancelamento.multa_vencimento_dias', 10)),
            'status' => StatusFatura::Pendente,
        ]);

        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Multa rescisória — cancelamento de matrícula',
            'quantidade' => 1,
            'valor_unitario' => $valor,
            'desconto' => 0,
            'tipo_desconto' => 'absoluto',
        ]);

        return $fatura;
    }

    /**
     * Rematrícula do próximo ano que ainda não virou matrícula nem contrato perde o sentido quando o aluno sai: cancela
     * junto. Já efetivada tem contrato e faturas próprios, e a decisão sobre elas é da família: só avisa.
     *
     * @param  array{faturas_canceladas: int, multa_fatura_id: ?int, transferencia: ?string, avisos: list<string>}  $resumo
     */
    private function cancelarRematriculasNaoEfetivadas(Matricula $matricula, array &$resumo): void
    {
        $rematriculas = Rematricula::query()
            ->where('matricula_origem_id', $matricula->id)
            ->where('status', '!=', StatusRematricula::Cancelada->value)
            ->get();

        foreach ($rematriculas as $rematricula) {
            if ($rematricula->nova_matricula_id || $rematricula->contrato_id) {
                $resumo['avisos'][] = 'Há rematrícula já efetivada para o próximo ano, com contrato próprio: cancele-a em Rematrículas se a família também desistiu dela.';

                continue;
            }

            app(RematriculaService::class)->cancelar($rematricula, 'Matrícula de origem cancelada.');
            $resumo['avisos'][] = 'A rematrícula do próximo ano, que ainda não tinha turma nem contrato, foi cancelada junto.';
        }
    }

    /**
     * Abre o processo de saída e tenta emitir a Declaração de Transferência. Sem modelo ativo cadastrado a emissão falha, e
     * o processo fica em andamento para a secretaria concluir depois; isso não desfaz o cancelamento.
     *
     * @return array{0: TransferenciaEscolar, 1: string, 2: ?string} transferência, estado (emitida|pendente) e aviso
     */
    private function abrirTransferencia(CancelamentoMatricula $cancelamento, Matricula $matricula, User $por): array
    {
        $transferencia = TransferenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'tipo' => TipoTransferencia::Saida,
            'escola_externa_nome' => $cancelamento->escola_destino ?: 'Não informada',
            'data' => $cancelamento->data_efetiva,
            'motivo' => 'Cancelamento de matrícula: '.$cancelamento->motivo->getLabel(),
            'status' => StatusTransferencia::EmAndamento,
            'criado_por_user_id' => $por->id,
        ]);

        $transferencia->setRelation('matricula', $matricula);
        $transferencia->setRelation('criadoPor', $por);

        try {
            $transferencia->concluirSaida();

            return [$transferencia, 'emitida', null];
        } catch (\DomainException $e) {
            return [$transferencia, 'pendente', $e->getMessage()];
        }
    }

    // --- Devolução ---------------------------------------------------------------------------------------------------

    /**
     * Marca que o dinheiro foi devolvido à família. O sistema só controla a data: a saída do dinheiro se lança em
     * Movimentação Bancária.
     *
     * @throws \DomainException
     */
    public function registrarDevolucao(CancelamentoMatricula $cancelamento, CarbonInterface $data, ?User $por = null): CancelamentoMatricula
    {
        if (! $cancelamento->devolucaoPendente()) {
            throw new \DomainException('Este pedido não tem devolução pendente.');
        }

        if ($data->isFuture()) {
            throw new \DomainException('A data da devolução não pode ser futura.');
        }

        $cancelamento->update(['devolucao_realizada_em' => $data->toDateString()]);

        $this->registrarLog($cancelamento, $por, 'Devolução de valores registrada.');

        return $cancelamento;
    }

    // --- Apoio -------------------------------------------------------------------------------------------------------

    /**
     * Log sem o texto livre do motivo (pode ter saúde ou situação da família) e sem valores.
     *
     * @param  array<string, mixed>  $propriedades
     */
    private function registrarLog(CancelamentoMatricula $cancelamento, ?User $por, string $mensagem, array $propriedades = []): void
    {
        $log = activity('secretaria')
            ->performedOn($cancelamento)
            ->withProperties(['matricula_id' => $cancelamento->matricula_id] + $propriedades);

        if ($por) {
            $log->causedBy($por);
        }

        $log->log($mensagem);
    }

    /**
     * Aviso no sino do painel para quem pode aprovar (e os super admins), com atalho para a lista. Só o nome do aluno e a
     * data: o motivo fica no pedido.
     */
    private function notificarEquipe(CancelamentoMatricula $cancelamento): void
    {
        $destinatarios = $this->equipeQueDecide();

        if ($destinatarios->isEmpty()) {
            return;
        }

        $aluno = Matricula::query()->with('pessoa')->find($cancelamento->matricula_id)?->pessoa?->nome ?? 'Um aluno';

        Notification::make()
            ->title('Pedido de cancelamento de matrícula')
            ->body("{$aluno}: a família pediu o cancelamento a partir de {$cancelamento->data_efetiva->format('d/m/Y')}. Confira a situação financeira e aprove ou recuse.")
            ->icon('heroicon-o-x-circle')
            ->iconColor('warning')
            ->actions([
                Action::make('abrir')
                    ->label('Abrir cancelamentos')
                    ->button()
                    ->url(CancelamentoMatriculaResource::getUrl('index', panel: 'admin')),
            ])
            ->sendToDatabase($destinatarios);
    }

    /**
     * @return Collection<int, User>
     */
    private function equipeQueDecide(): Collection
    {
        $usuarios = collect();

        if (Permission::where('name', 'Aprovar:CancelamentoMatricula')->where('guard_name', 'web')->exists()) {
            $usuarios = User::ativos()->permission('Aprovar:CancelamentoMatricula')->get();
        }

        if (Role::where('name', 'super_admin')->where('guard_name', 'web')->exists()) {
            $usuarios = $usuarios->merge(User::ativos()->role('super_admin')->get());
        }

        return $usuarios->unique('id')->values();
    }
}

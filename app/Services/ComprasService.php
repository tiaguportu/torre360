<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DestinoItemCompra;
use App\Enums\StatusContaPagar;
use App\Enums\StatusRequisicaoCompra;
use App\Models\BemPatrimonial;
use App\Models\ContaPagar;
use App\Models\Cotacao;
use App\Models\Fornecedor;
use App\Models\ItemEstoque;
use App\Models\ItemRequisicaoCompra;
use App\Models\PedidoCompra;
use App\Models\RecebimentoCompra;
use App\Models\RequisicaoCompra;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Compras e cotações (Onda 38): requisição do setor, cotações, escolha da vencedora (que vira o pedido), aprovação por alçada,
 * recebimento com a nota e a conta a pagar que nasce dele.
 *
 * O valor da onda é o controle interno, então as regras que o sustentam não têm atalho:
 *  - quem solicita não aprova a própria compra, e quem aprova não recebe (`compras.segregar_funcoes`);
 *  - a alçada pelo valor define quem aprova, e o saldo do orçamento aparece (ou bloqueia) na aprovação;
 *  - menos cotações que o mínimo da faixa, ou escolher a que não é a mais barata, exige justificativa registrada;
 *  - o recebimento dá entrada no estoque, cadastra o bem no patrimônio e gera a conta a pagar, tudo no mesmo ato.
 */
class ComprasService
{
    public function __construct(
        private readonly OrcamentoService $orcamento,
        private readonly EstoqueService $estoque,
    ) {}

    // --- Requisição --------------------------------------------------------------------------------------------------

    /**
     * @param  array{titulo?: ?string, justificativa?: ?string, urgencia?: ?string, centro_custo_id?: ?int, plano_conta_id?: ?int}  $dados
     * @param  list<array{descricao?: ?string, quantidade?: mixed, unidade_medida?: ?string, estimativa_unitaria?: mixed, destino?: mixed, item_estoque_id?: mixed}>  $itens
     *
     * @throws \DomainException
     */
    public function criarRequisicao(User $solicitante, array $dados, array $itens): RequisicaoCompra
    {
        $campos = $this->validarRequisicao($dados);
        $itensValidos = $this->validarItens($itens);

        return DB::transaction(function () use ($solicitante, $campos, $itensValidos): RequisicaoCompra {
            $requisicao = RequisicaoCompra::create($campos + [
                'numero' => $this->proximoNumero('REQ', RequisicaoCompra::class),
                'solicitante_user_id' => $solicitante->id,
                'status' => StatusRequisicaoCompra::Rascunho,
            ]);

            $this->gravarItens($requisicao, $itensValidos);

            return $requisicao->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     * @param  list<array<string, mixed>>  $itens
     *
     * @throws \DomainException
     */
    public function atualizarRequisicao(RequisicaoCompra $requisicao, array $dados, array $itens): RequisicaoCompra
    {
        if (! $requisicao->ehRascunho()) {
            throw new \DomainException('Só a requisição em rascunho pode ser editada.');
        }

        $campos = $this->validarRequisicao($dados);
        $itensValidos = $this->validarItens($itens);

        DB::transaction(function () use ($requisicao, $campos, $itensValidos): void {
            $requisicao->update($campos);
            $requisicao->itens()->delete();
            $this->gravarItens($requisicao, $itensValidos);
        });

        return $requisicao->refresh();
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{titulo: string, justificativa: string, urgencia: string, centro_custo_id: ?int, plano_conta_id: ?int}
     *
     * @throws \DomainException
     */
    private function validarRequisicao(array $dados): array
    {
        $titulo = mb_substr(trim((string) ($dados['titulo'] ?? '')), 0, 160);
        $justificativa = trim((string) ($dados['justificativa'] ?? ''));

        if ($titulo === '') {
            throw new \DomainException('Dê um título à requisição.');
        }

        if ($justificativa === '') {
            throw new \DomainException('Explique por que a compra é necessária.');
        }

        return [
            'titulo' => $titulo,
            'justificativa' => $justificativa,
            'urgencia' => ($dados['urgencia'] ?? 'normal') === 'urgente' ? 'urgente' : 'normal',
            'centro_custo_id' => filled($dados['centro_custo_id'] ?? null) ? (int) $dados['centro_custo_id'] : null,
            'plano_conta_id' => filled($dados['plano_conta_id'] ?? null) ? (int) $dados['plano_conta_id'] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return list<array{descricao: string, quantidade: float, unidade_medida: string, estimativa_unitaria: ?float, destino: DestinoItemCompra, item_estoque_id: ?int}>
     *
     * @throws \DomainException
     */
    private function validarItens(array $itens): array
    {
        $validos = [];

        foreach (array_values($itens) as $posicao => $item) {
            $numero = $posicao + 1;
            $descricao = mb_substr(trim((string) ($item['descricao'] ?? '')), 0, 200);
            $quantidade = round((float) ($item['quantidade'] ?? 0), 3);
            $destinoInformado = $item['destino'] ?? 'consumo';
            $destino = $destinoInformado instanceof DestinoItemCompra ? $destinoInformado : (DestinoItemCompra::tryFrom((string) $destinoInformado) ?? DestinoItemCompra::Consumo);
            $itemEstoqueId = filled($item['item_estoque_id'] ?? null) ? (int) $item['item_estoque_id'] : null;

            if ($descricao === '') {
                throw new \DomainException("Item {$numero}: descreva o que se quer comprar.");
            }

            if ($quantidade <= 0) {
                throw new \DomainException("Item {$numero}: a quantidade precisa ser maior que zero.");
            }

            if ($destino === DestinoItemCompra::Estoque && ($itemEstoqueId === null || ! ItemEstoque::query()->whereKey($itemEstoqueId)->exists())) {
                throw new \DomainException("Item {$numero}: escolha o item de estoque que receberá a compra.");
            }

            if ($destino === DestinoItemCompra::Patrimonio && floor($quantidade) !== $quantidade) {
                throw new \DomainException("Item {$numero}: bem durável é comprado em unidades inteiras.");
            }

            $validos[] = [
                'descricao' => $descricao,
                'quantidade' => $quantidade,
                'unidade_medida' => mb_substr(trim((string) ($item['unidade_medida'] ?? 'un')) ?: 'un', 0, 10),
                'estimativa_unitaria' => filled($item['estimativa_unitaria'] ?? null) ? round((float) $item['estimativa_unitaria'], 2) : null,
                'destino' => $destino,
                'item_estoque_id' => $destino === DestinoItemCompra::Estoque ? $itemEstoqueId : null,
            ];
        }

        if ($validos === []) {
            throw new \DomainException('Inclua ao menos um item na requisição.');
        }

        return $validos;
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    private function gravarItens(RequisicaoCompra $requisicao, array $itens): void
    {
        foreach ($itens as $ordem => $item) {
            $requisicao->itens()->create($item + ['ordem' => $ordem]);
        }
    }

    /**
     * @throws \DomainException
     */
    public function enviarParaCotacao(RequisicaoCompra $requisicao, User $por): RequisicaoCompra
    {
        if (! $requisicao->ehRascunho()) {
            throw new \DomainException('A requisição já saiu do rascunho.');
        }

        if (! $requisicao->itens()->exists()) {
            throw new \DomainException('A requisição está sem itens.');
        }

        $requisicao->update(['status' => StatusRequisicaoCompra::EmCotacao, 'enviada_em' => now()]);
        $this->registrarLog($requisicao, $por, 'Requisição enviada para cotação.');

        return $requisicao->refresh();
    }

    // --- Cotação -----------------------------------------------------------------------------------------------------

    /**
     * Registra (ou atualiza) a proposta de um fornecedor. Todo item precisa de preço: cotação incompleta não compara.
     *
     * @param  array<int, mixed>  $precos  id do item da requisição => preço unitário
     * @param  array{prazo_entrega_dias?: mixed, prazo_pagamento_dias?: mixed, condicoes?: ?string, validade_ate?: mixed, frete?: mixed}  $dados
     *
     * @throws \DomainException
     */
    public function registrarCotacao(RequisicaoCompra $requisicao, Fornecedor $fornecedor, array $precos, array $dados, ?User $por = null): Cotacao
    {
        if ($requisicao->status !== StatusRequisicaoCompra::EmCotacao) {
            throw new \DomainException('Só dá para cotar uma requisição em cotação.');
        }

        $itens = $requisicao->itens()->get();

        foreach ($itens as $item) {
            if (! isset($precos[$item->id]) || (float) $precos[$item->id] <= 0) {
                throw new \DomainException("Informe o preço do item \"{$item->descricao}\": a cotação precisa cobrir todos os itens.");
            }
        }

        $frete = round((float) ($dados['frete'] ?? 0), 2);

        if ($frete < 0) {
            throw new \DomainException('O frete não pode ser negativo.');
        }

        return DB::transaction(function () use ($requisicao, $fornecedor, $precos, $dados, $itens, $frete, $por): Cotacao {
            $cotacao = Cotacao::query()->updateOrCreate(
                ['requisicao_compra_id' => $requisicao->id, 'fornecedor_id' => $fornecedor->id],
                [
                    'prazo_entrega_dias' => filled($dados['prazo_entrega_dias'] ?? null) ? (int) $dados['prazo_entrega_dias'] : null,
                    'prazo_pagamento_dias' => max(0, (int) ($dados['prazo_pagamento_dias'] ?? 30)),
                    'condicoes' => filled($dados['condicoes'] ?? null) ? trim((string) $dados['condicoes']) : null,
                    'validade_ate' => filled($dados['validade_ate'] ?? null) ? $dados['validade_ate'] : null,
                    'frete' => $frete,
                    'registrada_por_user_id' => $por?->id,
                ],
            );

            $cotacao->itens()->delete();

            foreach ($itens as $item) {
                $cotacao->itens()->create(['item_requisicao_compra_id' => $item->id, 'preco_unitario' => round((float) $precos[$item->id], 4)]);
            }

            return $cotacao->refresh();
        });
    }

    /**
     * @throws \DomainException
     */
    public function removerCotacao(Cotacao $cotacao): void
    {
        $cotacao->loadMissing('requisicao');

        if ($cotacao->requisicao->status !== StatusRequisicaoCompra::EmCotacao) {
            throw new \DomainException('Só dá para retirar cotação enquanto a requisição está em cotação.');
        }

        $cotacao->delete();
    }

    /**
     * O que a tela compara: um preço por item e fornecedor, o total de cada cotação e a mais barata.
     *
     * @return array{itens: list<array<string, mixed>>, cotacoes: list<array<string, mixed>>, minimo: int, estimado: float}
     */
    public function mapaComparativo(RequisicaoCompra $requisicao): array
    {
        $itens = $requisicao->itens()->get();
        $cotacoes = $requisicao->cotacoes()->with(['fornecedor', 'itens'])->get();

        $totais = $cotacoes->mapWithKeys(function (Cotacao $cotacao) use ($itens): array {
            $precos = $cotacao->itens->pluck('preco_unitario', 'item_requisicao_compra_id');
            $total = $itens->sum(fn (ItemRequisicaoCompra $i): float => (float) ($precos[$i->id] ?? 0) * (float) $i->quantidade) + (float) $cotacao->frete;

            return [$cotacao->id => round($total, 2)];
        });

        $menor = $totais->isEmpty() ? null : $totais->min();

        return [
            'itens' => $itens->map(function (ItemRequisicaoCompra $item) use ($cotacoes): array {
                $precos = $cotacoes->mapWithKeys(fn (Cotacao $c): array => [$c->id => $c->itens->firstWhere('item_requisicao_compra_id', $item->id)?->preco_unitario]);
                $menorPreco = $precos->filter()->map(fn ($p) => (float) $p)->min();

                return [
                    'id' => $item->id,
                    'descricao' => $item->descricao,
                    'quantidade' => (float) $item->quantidade,
                    'unidade' => $item->unidade_medida,
                    'estimativa' => $item->estimativa_unitaria !== null ? (float) $item->estimativa_unitaria : null,
                    'precos' => $precos->map(fn ($p) => $p !== null ? (float) $p : null)->all(),
                    'menor' => $menorPreco,
                ];
            })->all(),
            'cotacoes' => $cotacoes->map(fn (Cotacao $c): array => [
                'id' => $c->id,
                'fornecedor' => $c->fornecedor?->razao_social ?? 'Fornecedor',
                'prazo_entrega' => $c->prazo_entrega_dias,
                'prazo_pagamento' => $c->prazo_pagamento_dias,
                'condicoes' => $c->condicoes,
                'frete' => (float) $c->frete,
                'validade' => $c->validade_ate,
                'vencida' => $c->vencida(),
                'total' => $totais[$c->id],
                'mais_barata' => $menor !== null && $totais[$c->id] === $menor,
                'escolhida' => $c->escolhida,
                'divergencias' => $this->fornecedorSinalizado($c->fornecedor_id),
            ])->all(),
            'minimo' => $this->minimoDeCotacoes((float) ($menor ?? $requisicao->valorEstimado())),
            'estimado' => $requisicao->valorEstimado(),
        ];
    }

    /**
     * Recebimentos com divergência (nota errada, item faltando) do fornecedor nos últimos meses: o sinal que a próxima cotação mostra.
     */
    public function fornecedorSinalizado(int $fornecedorId): int
    {
        return RecebimentoCompra::query()
            ->where('divergencia', true)
            ->where('created_at', '>=', now()->subMonths(max(1, (int) config('compras.meses_historico_fornecedor', 12))))
            ->whereHas('pedido', fn ($q) => $q->where('fornecedor_id', $fornecedorId))
            ->count();
    }

    public function minimoDeCotacoes(float $valor): int
    {
        foreach ((array) config('compras.cotacoes_minimas', []) as $faixa) {
            if ($faixa['ate'] === null || $valor <= (float) $faixa['ate']) {
                return (int) $faixa['minimo'];
            }
        }

        return 1;
    }

    /**
     * @return array{nivel: int, permissao: string, nome: string}
     */
    public function alcadaPara(float $valor): array
    {
        $faixas = (array) config('compras.alcadas', []);

        foreach ($faixas as $faixa) {
            if ($faixa['ate'] === null || $valor <= (float) $faixa['ate']) {
                return ['nivel' => (int) $faixa['nivel'], 'permissao' => (string) $faixa['permissao'], 'nome' => (string) $faixa['nome']];
            }
        }

        $ultima = end($faixas);

        return ['nivel' => (int) $ultima['nivel'], 'permissao' => (string) $ultima['permissao'], 'nome' => (string) $ultima['nome']];
    }

    // --- Escolha da cotação e pedido ---------------------------------------------------------------------------------

    /**
     * Escolhe a cotação vencedora e abre o pedido, que espera a aprovação da alçada.
     *
     * @throws \DomainException
     */
    public function escolherCotacao(RequisicaoCompra $requisicao, Cotacao $cotacao, ?string $motivoEscolha, ?string $justificativaDispensa, User $por): PedidoCompra
    {
        if ($requisicao->status !== StatusRequisicaoCompra::EmCotacao || $cotacao->requisicao_compra_id !== $requisicao->id) {
            throw new \DomainException('A cotação não é desta requisição ou a requisição não está em cotação.');
        }

        if ($cotacao->vencida()) {
            throw new \DomainException('A validade desta cotação já passou. Peça uma nova ao fornecedor.');
        }

        $mapa = $this->mapaComparativo($requisicao);
        $linha = collect($mapa['cotacoes'])->firstWhere('id', $cotacao->id);
        $total = (float) $linha['total'];
        $minimo = $this->minimoDeCotacoes($total);

        if (count($mapa['cotacoes']) < $minimo && blank(trim((string) $justificativaDispensa))) {
            throw new \DomainException("Esta faixa de valor pede {$minimo} cotações e há ".count($mapa['cotacoes']).'. Registre mais cotações ou justifique a dispensa.');
        }

        if (! $linha['mais_barata'] && blank(trim((string) $motivoEscolha))) {
            throw new \DomainException('A cotação escolhida não é a de menor valor total. Explique por que ela foi preferida.');
        }

        return DB::transaction(function () use ($requisicao, $cotacao, $total, $motivoEscolha, $justificativaDispensa, $por): PedidoCompra {
            $requisicao->cotacoes()->update(['escolhida' => false, 'motivo_escolha' => null]);
            $cotacao->update(['escolhida' => true, 'motivo_escolha' => filled(trim((string) $motivoEscolha)) ? trim((string) $motivoEscolha) : null]);

            $pedido = PedidoCompra::create([
                'numero' => $this->proximoNumero('PC', PedidoCompra::class),
                'requisicao_compra_id' => $requisicao->id,
                'cotacao_id' => $cotacao->id,
                'fornecedor_id' => $cotacao->fornecedor_id,
                'valor_total' => $total,
                'nivel_alcada' => $this->alcadaPara($total)['nivel'],
            ]);

            $requisicao->update([
                'status' => StatusRequisicaoCompra::AguardandoAprovacao,
                'cotacao_dispensada_justificativa' => filled(trim((string) $justificativaDispensa)) ? trim((string) $justificativaDispensa) : null,
            ]);

            $this->registrarLog($requisicao, $por, "Cotação escolhida; pedido {$pedido->numero} aguarda aprovação.", ['valor' => $total, 'nivel' => $pedido->nivel_alcada]);

            return $pedido;
        });
    }

    // --- Aprovação ---------------------------------------------------------------------------------------------------

    /**
     * Motivo pelo qual o usuário não pode aprovar o pedido, ou null quando pode.
     */
    public function impedimentoParaAprovar(PedidoCompra $pedido, User $usuario): ?string
    {
        $pedido->loadMissing('requisicao');
        $requisicao = $pedido->requisicao;

        if ($requisicao->status !== StatusRequisicaoCompra::AguardandoAprovacao) {
            return 'O pedido não está aguardando aprovação.';
        }

        if ($this->segregar() && $usuario->id === $requisicao->solicitante_user_id) {
            return 'Quem solicitou a compra não a aprova.';
        }

        if (! $this->temAlcada($usuario, $pedido->nivel_alcada)) {
            $alcada = $this->alcadaPara((float) $pedido->valor_total);

            return "Este valor exige a alçada de nível {$pedido->nivel_alcada} ({$alcada['nome']}).";
        }

        return null;
    }

    /**
     * O saldo do orçamento da conta da compra, para mostrar a quem aprova.
     *
     * @return array{orcamento_id: int, plano_orcado: string, previsto: float, realizado: float, comprometido: float, saldo: float, acima: bool}|null
     */
    public function saldoOrcamentario(PedidoCompra $pedido): ?array
    {
        $pedido->loadMissing('requisicao');
        $requisicao = $pedido->requisicao;
        $saldo = $this->orcamento->saldoDisponivel($requisicao->plano_conta_id, $requisicao->centro_custo_id, now(), $this->comprometidoPorPedidos($requisicao->plano_conta_id, $pedido->id));

        return $saldo === null ? null : $saldo + ['acima' => (float) $pedido->valor_total > $saldo['saldo']];
    }

    /**
     * @throws \DomainException
     */
    public function aprovar(PedidoCompra $pedido, User $aprovador, ?string $justificativaAcimaDoOrcamento = null): PedidoCompra
    {
        if ($motivo = $this->impedimentoParaAprovar($pedido, $aprovador)) {
            throw new \DomainException($motivo);
        }

        $saldo = $this->saldoOrcamentario($pedido);
        $justificativa = trim((string) $justificativaAcimaDoOrcamento);

        if ($saldo !== null && $saldo['acima']) {
            if (config('compras.bloquear_acima_do_orcamento')) {
                throw new \DomainException('A compra passa do saldo do orçamento ('.number_format($saldo['saldo'], 2, ',', '.').' em "'.$saldo['plano_orcado'].'"). Abra uma revisão do orçamento ou reduza o pedido.');
            }

            if ($justificativa === '') {
                throw new \DomainException('A compra passa do saldo do orçamento ('.number_format($saldo['saldo'], 2, ',', '.').' em "'.$saldo['plano_orcado'].'"). Justifique a aprovação.');
            }
        }

        DB::transaction(function () use ($pedido, $aprovador, $saldo, $justificativa): void {
            $pedido->update([
                'aprovado_por_user_id' => $aprovador->id,
                'aprovado_em' => now(),
                'justificativa_acima_do_orcamento' => $saldo !== null && $saldo['acima'] ? $justificativa : null,
                'saldo_orcamentario' => $saldo,
            ]);

            $pedido->requisicao->update(['status' => StatusRequisicaoCompra::Aprovada]);
        });

        $this->registrarLog($pedido->requisicao, $aprovador, "Pedido {$pedido->numero} aprovado.", ['valor' => (float) $pedido->valor_total, 'nivel' => $pedido->nivel_alcada]);

        return $pedido->refresh();
    }

    /**
     * Recusa o pedido: encerra a requisição, ou a devolve à cotação para o setor escolher outra proposta.
     *
     * @throws \DomainException
     */
    public function recusar(PedidoCompra $pedido, User $aprovador, string $motivo, bool $devolverParaCotacao = false): RequisicaoCompra
    {
        if ($impedimento = $this->impedimentoParaAprovar($pedido, $aprovador)) {
            throw new \DomainException($impedimento);
        }

        if (trim($motivo) === '') {
            throw new \DomainException('Informe o motivo da recusa.');
        }

        $requisicao = $pedido->requisicao;

        DB::transaction(function () use ($pedido, $requisicao, $motivo, $devolverParaCotacao): void {
            $requisicao->update(['motivo_recusa' => trim($motivo), 'status' => $devolverParaCotacao ? StatusRequisicaoCompra::EmCotacao : StatusRequisicaoCompra::Recusada]);

            if ($devolverParaCotacao) {
                $requisicao->cotacoes()->update(['escolhida' => false, 'motivo_escolha' => null]);
                $pedido->delete();
            }
        });

        $this->registrarLog($requisicao, $aprovador, $devolverParaCotacao ? 'Pedido devolvido para nova cotação.' : 'Pedido recusado.');

        return $requisicao->refresh();
    }

    private function temAlcada(User $usuario, int $nivelExigido): bool
    {
        foreach ((array) config('compras.alcadas', []) as $faixa) {
            if ((int) $faixa['nivel'] >= $nivelExigido && $usuario->can((string) $faixa['permissao'])) {
                return true;
            }
        }

        return false;
    }

    private function segregar(): bool
    {
        return (bool) config('compras.segregar_funcoes', true);
    }

    /**
     * Pedidos já aprovados na mesma conta do plano e ainda não recebidos por inteiro: dinheiro que o orçamento já considera gasto.
     */
    public function comprometidoPorPedidos(?int $planoContaId, ?int $excetoPedidoId = null): float
    {
        if (! $planoContaId) {
            return 0.0;
        }

        $pedidos = PedidoCompra::query()
            ->when($excetoPedidoId, fn ($q) => $q->whereKeyNot($excetoPedidoId))
            ->whereHas('requisicao', fn ($q) => $q->where('plano_conta_id', $planoContaId)->whereIn('status', [StatusRequisicaoCompra::Aprovada->value, StatusRequisicaoCompra::RecebidaParcial->value]))
            ->withSum('recebimentos as total_recebido', 'valor_recebido')
            ->get();

        return round((float) $pedidos->sum(fn (PedidoCompra $p): float => max(0.0, (float) $p->valor_total - (float) ($p->total_recebido ?? 0))), 2);
    }

    // --- Recebimento -------------------------------------------------------------------------------------------------

    /**
     * Motivo pelo qual o usuário não pode receber o pedido, ou null quando pode.
     */
    public function impedimentoParaReceber(PedidoCompra $pedido, User $usuario): ?string
    {
        $pedido->loadMissing('requisicao');

        if (! in_array($pedido->requisicao->status, [StatusRequisicaoCompra::Aprovada, StatusRequisicaoCompra::RecebidaParcial], true)) {
            return 'O pedido não está aguardando entrega.';
        }

        if ($this->segregar() && $usuario->id === $pedido->aprovado_por_user_id) {
            return 'Quem aprovou a compra não faz o recebimento.';
        }

        return null;
    }

    /**
     * Registra a entrega (total ou parcial): dá entrada no estoque, cadastra os bens no patrimônio e gera a conta a pagar.
     *
     * @param  array<int, mixed>  $quantidades  id do item da requisição => quantidade recebida agora
     * @param  array{conteudo: string, extensao: string}|null  $notaFiscal  o arquivo da nota
     *
     * @throws \DomainException
     */
    public function receber(PedidoCompra $pedido, User $recebedor, array $quantidades, CarbonInterface $data, ?string $numeroDaNota, ?array $notaFiscal = null, ?string $observacao = null, bool $divergencia = false): RecebimentoCompra
    {
        if ($motivo = $this->impedimentoParaReceber($pedido, $recebedor)) {
            throw new \DomainException($motivo);
        }

        if (blank(trim((string) $numeroDaNota))) {
            throw new \DomainException('Informe o número da nota fiscal (ou do documento que acompanhou a entrega).');
        }

        if ($divergencia && blank(trim((string) $observacao))) {
            throw new \DomainException('Descreva a divergência (nota diferente do pedido, item faltando...): ela fica no histórico do fornecedor.');
        }

        $requisicao = $pedido->requisicao()->with(['itens.itemEstoque'])->firstOrFail();
        $cotacao = $pedido->cotacao()->with('itens')->firstOrFail();
        $precos = $cotacao->itens->pluck('preco_unitario', 'item_requisicao_compra_id');
        $recebidos = [];

        foreach ($requisicao->itens as $item) {
            $quantidade = round((float) ($quantidades[$item->id] ?? 0), 3);

            if ($quantidade <= 0) {
                continue;
            }

            if ($quantidade > $item->quantidadePendente()) {
                throw new \DomainException("Item \"{$item->descricao}\": só faltam {$item->quantidadePendente()} {$item->unidade_medida} a receber.");
            }

            if ($item->destino === DestinoItemCompra::Patrimonio && floor($quantidade) !== $quantidade) {
                throw new \DomainException("Item \"{$item->descricao}\": bem durável é recebido em unidades inteiras.");
            }

            $recebidos[] = [$item, $quantidade, (float) $precos[$item->id]];
        }

        if ($recebidos === []) {
            throw new \DomainException('Informe a quantidade recebida de ao menos um item.');
        }

        $caminhoDaNota = $notaFiscal ? $this->guardarNota($pedido, $notaFiscal) : null;

        $recebimento = DB::transaction(function () use ($pedido, $recebedor, $recebidos, $data, $numeroDaNota, $caminhoDaNota, $observacao, $divergencia, $requisicao, $cotacao): RecebimentoCompra {
            $valor = round(array_sum(array_map(fn (array $r): float => $r[1] * $r[2], $recebidos)), 2);

            // O frete da cotação entra na primeira entrega.
            if (! $pedido->recebimentos()->exists()) {
                $valor = round($valor + (float) $cotacao->frete, 2);
            }

            $recebimento = RecebimentoCompra::create([
                'pedido_compra_id' => $pedido->id,
                'recebido_por_user_id' => $recebedor->id,
                'data_recebimento' => $data->toDateString(),
                'nota_fiscal_numero' => mb_substr(trim((string) $numeroDaNota), 0, 40),
                'nota_fiscal_path' => $caminhoDaNota,
                'observacao' => filled(trim((string) $observacao)) ? trim((string) $observacao) : null,
                'divergencia' => $divergencia,
                'valor_recebido' => $valor,
            ]);

            foreach ($recebidos as [$item, $quantidade, $preco]) {
                $recebimento->itens()->create(['item_requisicao_compra_id' => $item->id, 'quantidade_recebida' => $quantidade, 'preco_unitario' => $preco]);
                $this->darDestino($item, $quantidade, $preco, $pedido, $recebimento, $recebedor, $data);
            }

            ContaPagar::create([
                'descricao' => mb_substr("Compra {$pedido->numero} — {$requisicao->titulo} (NF {$recebimento->nota_fiscal_numero})", 0, 250),
                'valor' => $valor,
                'vencimento' => $data->copy()->addDays((int) $cotacao->prazo_pagamento_dias)->toDateString(),
                'status' => StatusContaPagar::Pendente,
                'fornecedor_id' => $pedido->fornecedor_id,
                'plano_conta_id' => $requisicao->plano_conta_id,
                'centro_custo_id' => $requisicao->centro_custo_id,
                'observacao' => "Gerada pelo recebimento #{$recebimento->id} do pedido {$pedido->numero}.",
                'pedido_compra_id' => $pedido->id,
                'recebimento_compra_id' => $recebimento->id,
            ]);

            $requisicao->update(['status' => $requisicao->itens()->get()->every(fn (ItemRequisicaoCompra $i): bool => $i->quantidadePendente() <= 0)
                ? StatusRequisicaoCompra::Recebida
                : StatusRequisicaoCompra::RecebidaParcial]);

            return $recebimento;
        });

        $this->registrarLog($requisicao, $recebedor, "Recebimento do pedido {$pedido->numero}.", ['valor' => (float) $recebimento->valor_recebido, 'divergencia' => $divergencia]);

        return $recebimento->refresh();
    }

    private function darDestino(ItemRequisicaoCompra $item, float $quantidade, float $preco, PedidoCompra $pedido, RecebimentoCompra $recebimento, User $recebedor, CarbonInterface $data): void
    {
        if ($item->destino === DestinoItemCompra::Estoque && $item->itemEstoque) {
            $this->estoque->entrada($item->itemEstoque, $quantidade, $preco, $recebimento, $recebedor, "Pedido {$pedido->numero} · NF {$recebimento->nota_fiscal_numero}");

            return;
        }

        if ($item->destino === DestinoItemCompra::Patrimonio) {
            $existentes = BemPatrimonial::query()->where('numero_patrimonio', 'like', "{$pedido->numero}-{$item->id}-%")->count();

            for ($n = 1; $n <= (int) $quantidade; $n++) {
                BemPatrimonial::create([
                    'descricao' => $item->descricao,
                    'numero_patrimonio' => sprintf('%s-%d-%02d', $pedido->numero, $item->id, $existentes + $n),
                    'categoria' => 'Compras',
                    'data_aquisicao' => $data->toDateString(),
                    'valor_aquisicao' => round($preco, 2),
                    'fornecedor_id' => $pedido->fornecedor_id,
                    'status' => 'em_uso',
                ]);
            }
        }
    }

    /**
     * A nota fica em área privada. Só PDF e imagem comum: o arquivo vem de um formulário.
     *
     * @param  array{conteudo: string, extensao: string}  $nota
     *
     * @throws \DomainException
     */
    private function guardarNota(PedidoCompra $pedido, array $nota): string
    {
        $extensao = mb_strtolower($nota['extensao']);

        if (! in_array($extensao, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            throw new \DomainException('A nota fiscal deve ser PDF, JPG ou PNG.');
        }

        if ($nota['conteudo'] === '' || strlen($nota['conteudo']) > 10 * 1024 * 1024) {
            throw new \DomainException('A nota fiscal deve ter até 10 MB.');
        }

        $caminho = "compras/notas/{$pedido->id}/".Str::uuid().".{$extensao}";
        Storage::disk('local')->put($caminho, $nota['conteudo']);

        return $caminho;
    }

    // --- Cancelar ----------------------------------------------------------------------------------------------------

    /**
     * @throws \DomainException
     */
    public function cancelar(RequisicaoCompra $requisicao, User $por, string $motivo): RequisicaoCompra
    {
        if ($requisicao->status->encerrada() || in_array($requisicao->status, [StatusRequisicaoCompra::RecebidaParcial], true)) {
            throw new \DomainException('Esta requisição já foi encerrada ou teve entrega: não dá para cancelar.');
        }

        if (trim($motivo) === '') {
            throw new \DomainException('Informe o motivo do cancelamento.');
        }

        $requisicao->update(['status' => StatusRequisicaoCompra::Cancelada, 'motivo_cancelamento' => trim($motivo)]);
        $this->registrarLog($requisicao, $por, 'Requisição cancelada.');

        return $requisicao->refresh();
    }

    // --- Apoio -------------------------------------------------------------------------------------------------------

    /**
     * @param  class-string<Model>  $modelo
     */
    private function proximoNumero(string $prefixo, string $modelo): string
    {
        $ano = now()->year;
        $ultimo = $modelo::query()->where('numero', 'like', "{$prefixo}-{$ano}-%")->orderByDesc('id')->value('numero');
        $sequencia = $ultimo ? ((int) substr((string) $ultimo, -4)) + 1 : 1;

        return sprintf('%s-%d-%04d', $prefixo, $ano, $sequencia);
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private function registrarLog(RequisicaoCompra $requisicao, ?User $por, string $mensagem, array $propriedades = []): void
    {
        $registro = activity('compras')->performedOn($requisicao)->withProperties(['status' => $requisicao->status->value] + $propriedades);

        if ($por) {
            $registro->causedBy($por);
        }

        $registro->log($mensagem);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Contabil;

use App\Enums\FormatoExportacaoContabil;
use App\Enums\RegimeContabil;
use App\Enums\StatusContaPagar;
use App\Enums\StatusFatura;
use App\Models\Banco;
use App\Models\ContaPagar;
use App\Models\Fatura;
use App\Models\LoteContabil;
use App\Models\ParametroContabil;
use App\Models\PlanoConta;
use App\Models\TransacaoBancaria;
use App\Models\User;
use App\Support\Contabil\LancamentoContabil;
use App\Support\CsvSanitizer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Exportação para a contabilidade (Onda 73): transforma o movimento financeiro da escola (transações bancárias, faturas e
 * contas a pagar) em partidas dobradas, no plano de contas do escritório, e entrega um arquivo CSV.
 *
 * O sistema não decide a contabilidade da escola: ele só traduz. A tradução depende de códigos que a escola preenche (a conta
 * do escritório em cada conta do plano e em cada banco, mais as contas padrão); enquanto faltar algum, a apuração lista a
 * pendência e o arquivo não sai, em vez de mandar ao escritório uma conta em branco.
 *
 * O arquivo leva só o necessário: valores, datas, contas e a referência do documento. Não leva nome de aluno nem de
 * responsável (LGPD, art. 6º, III): o escritório reconcilia pelo número da fatura.
 */
class ExportacaoContabilService
{
    /** Período máximo de uma exportação; um ano contábil cabe, e o arquivo de um período maior é difícil de conferir. */
    private const DIAS_MAXIMOS = 366;

    /** Quantos exemplos de cada pendência a conferência mostra. */
    private const EXEMPLOS_POR_PENDENCIA = 5;

    /** @var array<string, array{mensagem: string, quantidade: int, exemplos: list<string>}> */
    private array $pendencias = [];

    /** @var array<string, array{mensagem: string, quantidade: int, exemplos: list<string>}> */
    private array $avisos = [];

    private ParametroContabil $parametros;

    /**
     * Monta os lançamentos do período e aponta o que impede o arquivo de sair.
     *
     * @return array{lancamentos: list<LancamentoContabil>, pendencias: list<array{chave: string, mensagem: string, quantidade: int, exemplos: list<string>}>, avisos: list<array{chave: string, mensagem: string, quantidade: int, exemplos: list<string>}>, resumo: array{quantidade: int, total: float, por_origem: array<string, int>}}
     */
    public function apurar(CarbonInterface $inicio, CarbonInterface $fim, RegimeContabil $regime): array
    {
        $this->pendencias = [];
        $this->avisos = [];
        $this->parametros = ParametroContabil::atual();

        $lancamentos = [...$this->doMovimentoBancario($inicio, $fim, $regime)];

        if ($regime === RegimeContabil::Competencia) {
            $lancamentos = [...$lancamentos, ...$this->dasFaturas($inicio, $fim), ...$this->dasContasAPagar($inicio, $fim)];
        }

        usort($lancamentos, fn (LancamentoContabil $a, LancamentoContabil $b): int => [$a->data->toDateString(), $a->origem, $a->documento] <=> [$b->data->toDateString(), $b->origem, $b->documento]);

        $porOrigem = [];

        foreach ($lancamentos as $lancamento) {
            $porOrigem[$lancamento->origem] = ($porOrigem[$lancamento->origem] ?? 0) + 1;
        }

        ksort($porOrigem);

        return [
            'lancamentos' => $lancamentos,
            'pendencias' => $this->listar($this->pendencias),
            'avisos' => $this->listar($this->avisos),
            'resumo' => [
                'quantidade' => count($lancamentos),
                'total' => round(array_sum(array_map(fn (LancamentoContabil $l): float => $l->valor, $lancamentos)), 2),
                'por_origem' => $porOrigem,
            ],
        ];
    }

    /**
     * Já existe arquivo gerado para um período que se sobrepõe a este, no mesmo regime?
     *
     * @return list<LoteContabil>
     */
    public function lotesQueSeSobrepoem(CarbonInterface $inicio, CarbonInterface $fim, RegimeContabil $regime): array
    {
        return LoteContabil::query()
            ->where('regime', $regime->value)
            ->whereDate('periodo_inicio', '<=', $fim->toDateString())
            ->whereDate('periodo_fim', '>=', $inicio->toDateString())
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /**
     * Gera o arquivo, guarda-o e registra o lote.
     *
     * @throws \DomainException
     */
    public function exportar(CarbonInterface $inicio, CarbonInterface $fim, RegimeContabil $regime, FormatoExportacaoContabil $formato, ?User $por = null): LoteContabil
    {
        $inicio = Carbon::parse($inicio)->startOfDay();
        $fim = Carbon::parse($fim)->startOfDay();

        if ($fim->lt($inicio)) {
            throw new \DomainException('A data final não pode ser anterior à inicial.');
        }

        if ($inicio->diffInDays($fim) > self::DIAS_MAXIMOS) {
            throw new \DomainException('O período passa de um ano. Gere por ano contábil, no máximo.');
        }

        $apuracao = $this->apurar($inicio, $fim, $regime);

        if ($apuracao['pendencias'] !== []) {
            $quantidade = array_sum(array_column($apuracao['pendencias'], 'quantidade'));

            throw new \DomainException("Há {$quantidade} pendência(s) de classificação. Corrija o plano de contas, os bancos e os parâmetros contábeis e confira de novo.");
        }

        if ($apuracao['lancamentos'] === []) {
            throw new \DomainException('Não há lançamentos neste período para exportar.');
        }

        $conteudo = $this->gerarArquivo($apuracao['lancamentos'], $formato);
        $caminho = 'exportacoes_contabeis/'.Str::uuid().'.'.$formato->extensao();
        Storage::disk('local')->put($caminho, $conteudo);

        $lote = LoteContabil::create([
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fim' => $fim->toDateString(),
            'regime' => $regime,
            'formato' => $formato,
            'lancamentos' => $apuracao['resumo']['quantidade'],
            'total' => $apuracao['resumo']['total'],
            'arquivo_path' => $caminho,
            'arquivo_hash' => hash('sha256', $conteudo),
            'gerado_por_user_id' => $por?->id,
        ]);

        $registro = activity('contabilidade')->performedOn($lote)->withProperties([
            'periodo' => [$inicio->toDateString(), $fim->toDateString()],
            'regime' => $regime->value,
            'formato' => $formato->value,
            'lancamentos' => $lote->lancamentos,
        ]);

        if ($por) {
            $registro->causedBy($por);
        }

        $registro->log('Arquivo da contabilidade gerado.');

        return $lote;
    }

    // --- Arquivos ----------------------------------------------------------------------------------------------------

    /**
     * @param  list<LancamentoContabil>  $lancamentos
     */
    public function gerarArquivo(array $lancamentos, FormatoExportacaoContabil $formato): string
    {
        $saida = fopen('php://temp', 'w+');
        // BOM para o Excel abrir o UTF-8 com acentos; ponto e vírgula, o separador do Excel em português.
        fwrite($saida, "\xEF\xBB\xBF");

        match ($formato) {
            FormatoExportacaoContabil::CsvLancamentos => $this->csvLancamentos($saida, $lancamentos),
            FormatoExportacaoContabil::CsvBalancete => $this->csvBalancete($saida, $lancamentos),
        };

        rewind($saida);
        $conteudo = (string) stream_get_contents($saida);
        fclose($saida);

        return $conteudo;
    }

    /**
     * @param  resource  $saida
     * @param  list<LancamentoContabil>  $lancamentos
     */
    private function csvLancamentos($saida, array $lancamentos): void
    {
        CsvSanitizer::fputcsv($saida, ['Data', 'Conta débito', 'Conta crédito', 'Valor', 'Histórico', 'Documento', 'Centro de custo', 'Origem'], ';');

        foreach ($lancamentos as $l) {
            CsvSanitizer::fputcsv($saida, [
                $l->data->format('d/m/Y'),
                $l->contaDebito,
                $l->contaCredito,
                $this->numero($l->valor),
                $l->historico,
                $l->documento,
                $l->centroCusto,
                $l->origem,
            ], ';');
        }
    }

    /**
     * Total a débito e a crédito por conta, com o saldo na coluna do lado que sobra (sem sinal negativo, que a planilha leria
     * como texto).
     *
     * @param  resource  $saida
     * @param  list<LancamentoContabil>  $lancamentos
     */
    private function csvBalancete($saida, array $lancamentos): void
    {
        $contas = [];

        foreach ($lancamentos as $l) {
            $contas[$l->contaDebito] = ($contas[$l->contaDebito] ?? ['d' => 0.0, 'c' => 0.0]);
            $contas[$l->contaDebito]['d'] += $l->valor;
            $contas[$l->contaCredito] = ($contas[$l->contaCredito] ?? ['d' => 0.0, 'c' => 0.0]);
            $contas[$l->contaCredito]['c'] += $l->valor;
        }

        ksort($contas, SORT_NATURAL);

        CsvSanitizer::fputcsv($saida, ['Conta', 'Total a débito', 'Total a crédito', 'Saldo devedor', 'Saldo credor'], ';');

        foreach ($contas as $conta => $totais) {
            $saldo = round($totais['d'] - $totais['c'], 2);

            CsvSanitizer::fputcsv($saida, [
                (string) $conta,
                $this->numero($totais['d']),
                $this->numero($totais['c']),
                $this->numero(max($saldo, 0.0)),
                $this->numero(max(-$saldo, 0.0)),
            ], ';');
        }
    }

    private function numero(float $valor): string
    {
        return number_format($valor, 2, ',', '');
    }

    // --- Lançamentos -------------------------------------------------------------------------------------------------

    /**
     * @return list<LancamentoContabil>
     */
    private function doMovimentoBancario(CarbonInterface $inicio, CarbonInterface $fim, RegimeContabil $regime): array
    {
        $transacoes = TransacaoBancaria::query()
            ->with(['banco', 'planoConta', 'centroCusto'])
            ->whereDate('data_transacao', '>=', $inicio->toDateString())
            ->whereDate('data_transacao', '<=', $fim->toDateString())
            ->orderBy('data_transacao')
            ->orderBy('id')
            ->get();

        $comContaAPagar = $regime === RegimeContabil::Competencia
            ? ContaPagar::query()->whereIn('transacao_bancaria_id', $transacoes->pluck('id'))->pluck('id', 'transacao_bancaria_id')
            : collect();

        $lancamentos = [];

        foreach ($transacoes as $transacao) {
            $valor = round((float) $transacao->valor, 2);

            if ($valor <= 0) {
                continue;
            }

            $banco = $this->contaDoBanco($transacao->banco, $transacao->id);
            $entrada = $transacao->tipo === 'entrada';
            $referencia = "movimento bancário #{$transacao->id}";

            if ($entrada) {
                // Competência: o recebimento de uma fatura baixa o que a escola tinha a receber, não é receita nova.
                $contraPartida = $regime === RegimeContabil::Competencia && $transacao->fatura_id
                    ? $this->contaDeClientes($referencia)
                    : $this->contaDoPlano($transacao->planoConta, 'receita', $referencia);
            } else {
                $contraPartida = $regime === RegimeContabil::Competencia && $comContaAPagar->has($transacao->id)
                    ? $this->contaDeFornecedores($referencia)
                    : $this->contaDoPlano($transacao->planoConta, 'despesa', $referencia);
            }

            $lancamentos[] = new LancamentoContabil(
                data: Carbon::parse($transacao->data_transacao),
                contaDebito: $entrada ? $banco : $contraPartida,
                contaCredito: $entrada ? $contraPartida : $banco,
                valor: $valor,
                historico: Str::limit((string) ($transacao->descricao ?: ($entrada ? 'Recebimento' : 'Pagamento')), 120, '…'),
                documento: $transacao->fatura_id ? "FAT-{$transacao->fatura_id}" : ($transacao->external_id ?: "MOV-{$transacao->id}"),
                centroCusto: $transacao->centroCusto?->nome,
                origem: 'banco',
            );
        }

        return $lancamentos;
    }

    /**
     * Faturas com vencimento no período: a escola passa a ter a receber. O valor é o da fatura (itens com desconto); a receita
     * é a conta padrão de receita, porque a fatura não tem conta do plano.
     *
     * @return list<LancamentoContabil>
     */
    private function dasFaturas(CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $faturas = Fatura::query()
            ->with('itens')
            ->where('status', '!=', StatusFatura::Cancelado->value)
            ->whereDate('vencimento', '>=', $inicio->toDateString())
            ->whereDate('vencimento', '<=', $fim->toDateString())
            ->orderBy('vencimento')
            ->orderBy('id')
            ->get();

        $lancamentos = [];

        foreach ($faturas as $fatura) {
            $valor = round((float) $fatura->valor, 2);

            if ($valor <= 0) {
                continue;
            }

            $referencia = "fatura #{$fatura->id}";

            $lancamentos[] = new LancamentoContabil(
                data: $fatura->vencimento,
                contaDebito: $this->contaDeClientes($referencia),
                contaCredito: $this->contaDoPlano(null, 'receita', $referencia),
                valor: $valor,
                historico: "Fatura {$fatura->id} com vencimento em {$fatura->vencimento->format('d/m/Y')}",
                documento: "FAT-{$fatura->id}",
                centroCusto: null,
                origem: 'fatura',
            );
        }

        return $lancamentos;
    }

    /**
     * Contas a pagar com vencimento no período: a escola passa a dever ao fornecedor.
     *
     * @return list<LancamentoContabil>
     */
    private function dasContasAPagar(CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $contas = ContaPagar::query()
            ->with(['planoConta', 'centroCusto', 'fornecedor'])
            ->where('status', '!=', StatusContaPagar::Cancelado->value)
            ->whereDate('vencimento', '>=', $inicio->toDateString())
            ->whereDate('vencimento', '<=', $fim->toDateString())
            ->orderBy('vencimento')
            ->orderBy('id')
            ->get();

        $lancamentos = [];

        foreach ($contas as $conta) {
            $valor = round((float) $conta->valor, 2);

            if ($valor <= 0) {
                continue;
            }

            $referencia = "conta a pagar #{$conta->id}";
            $fornecedor = $conta->fornecedor?->razao_social;

            $lancamentos[] = new LancamentoContabil(
                data: $conta->vencimento,
                contaDebito: $this->contaDoPlano($conta->planoConta, 'despesa', $referencia),
                contaCredito: $this->contaDeFornecedores($referencia),
                valor: $valor,
                historico: Str::limit($conta->descricao.($fornecedor ? " — {$fornecedor}" : ''), 120, '…'),
                documento: "CP-{$conta->id}",
                centroCusto: $conta->centroCusto?->nome,
                origem: 'conta_a_pagar',
            );
        }

        return $lancamentos;
    }

    // --- Contas ------------------------------------------------------------------------------------------------------

    private function contaDoBanco(?Banco $banco, int $transacaoId): ?string
    {
        if (! $banco) {
            $this->apontar($this->pendencias, 'transacao_sem_banco', 'Movimento sem banco cadastrado.', "movimento bancário #{$transacaoId}");

            return null;
        }

        if (blank($banco->conta_contabil)) {
            $this->apontar($this->pendencias, "banco_{$banco->id}", 'Banco sem a conta contábil do escritório. Preencha em Bancos.', $banco->nome);

            return null;
        }

        return trim((string) $banco->conta_contabil);
    }

    /**
     * Conta do escritório para a conta do plano da escola. Sem classificação, vale a conta padrão da natureza (receita ou
     * despesa); conta do plano sem código do escritório é pendência, nunca cai na padrão em silêncio.
     */
    private function contaDoPlano(?PlanoConta $plano, string $natureza, string $referencia): ?string
    {
        if ($plano) {
            if ($plano->tipo !== $natureza && in_array($plano->tipo, ['receita', 'despesa'], true)) {
                $this->apontar($this->avisos, "plano_tipo_{$plano->id}_{$natureza}", "Conta de {$plano->tipo} usada em movimento de {$natureza}. Confira se a classificação está certa.", "{$plano->codigo} - {$plano->nome} ({$referencia})");
            }

            return $this->codigoDoPlano($plano);
        }

        $padrao = $natureza === 'receita' ? $this->parametros->planoContaReceita : $this->parametros->planoContaDespesa;

        if (! $padrao) {
            $this->apontar($this->pendencias, "sem_padrao_{$natureza}", "Há movimento sem conta do plano e a conta padrão de {$natureza} não está definida. Defina em Parâmetros contábeis ou classifique o movimento.", $referencia);

            return null;
        }

        return $this->codigoDoPlano($padrao);
    }

    private function codigoDoPlano(PlanoConta $plano): ?string
    {
        if (blank($plano->codigo_contabil)) {
            $this->apontar($this->pendencias, "plano_{$plano->id}", 'Conta do plano sem o código do escritório. Preencha em Plano de Contas.', "{$plano->codigo} - {$plano->nome}");

            return null;
        }

        return trim((string) $plano->codigo_contabil);
    }

    private function contaDeClientes(string $referencia): ?string
    {
        if (blank($this->parametros->conta_clientes)) {
            $this->apontar($this->pendencias, 'sem_conta_clientes', 'A conta de clientes (a receber) não está definida. Defina em Parâmetros contábeis.', $referencia);

            return null;
        }

        return trim((string) $this->parametros->conta_clientes);
    }

    private function contaDeFornecedores(string $referencia): ?string
    {
        if (blank($this->parametros->conta_fornecedores)) {
            $this->apontar($this->pendencias, 'sem_conta_fornecedores', 'A conta de fornecedores (a pagar) não está definida. Defina em Parâmetros contábeis.', $referencia);

            return null;
        }

        return trim((string) $this->parametros->conta_fornecedores);
    }

    // --- Pendências --------------------------------------------------------------------------------------------------

    /**
     * @param  array<string, array{mensagem: string, quantidade: int, exemplos: list<string>}>  $destino
     */
    private function apontar(array &$destino, string $chave, string $mensagem, string $exemplo): void
    {
        $destino[$chave] ??= ['mensagem' => $mensagem, 'quantidade' => 0, 'exemplos' => []];
        $destino[$chave]['quantidade']++;

        if (count($destino[$chave]['exemplos']) < self::EXEMPLOS_POR_PENDENCIA && ! in_array($exemplo, $destino[$chave]['exemplos'], true)) {
            $destino[$chave]['exemplos'][] = $exemplo;
        }
    }

    /**
     * @param  array<string, array{mensagem: string, quantidade: int, exemplos: list<string>}>  $itens
     * @return list<array{chave: string, mensagem: string, quantidade: int, exemplos: list<string>}>
     */
    private function listar(array $itens): array
    {
        $lista = [];

        foreach ($itens as $chave => $item) {
            $lista[] = ['chave' => (string) $chave] + $item;
        }

        return $lista;
    }
}

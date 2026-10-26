<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusContaPagar;
use App\Enums\StatusOrcamento;
use App\Models\CentroCusto;
use App\Models\ContaPagar;
use App\Models\Orcamento;
use App\Models\OrcamentoLinha;
use App\Models\PlanoConta;
use App\Models\TransacaoBancaria;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orçamento anual × realizado (Onda 43).
 *
 * O previsto fica em `orcamento_linhas` (conta do plano, centro de custo opcional e mês). O realizado vem do próprio sistema, com a
 * regra do DRE: soma do `valor` das transações bancárias classificadas na conta (e nas suas subcontas) no período. Não há uma
 * segunda verdade: o que o DRE mostra para a conta é o que aqui se compara com o previsto.
 *
 * O orçamento aprovado não muda. A revisão cria uma nova versão, a antiga fica "substituída" quando a nova é aprovada.
 */
class OrcamentoService
{
    /** Folga, em % do previsto acumulado, em que a despesa fica amarela antes de estourar (config `orcamento.alerta_percentual`). */
    public function percentualDeAlerta(): int
    {
        return max(1, min(100, (int) config('orcamento.alerta_percentual', 90)));
    }

    // --- Criar e preencher --------------------------------------------------------------------------------------------

    /**
     * Cria o rascunho de um ano, em branco ou copiando as linhas de outro orçamento com um reajuste percentual.
     */
    public function criar(int $ano, string $nome, ?Orcamento $baseadoEm = null, float $reajustePercentual = 0.0, ?User $por = null): Orcamento
    {
        return DB::transaction(function () use ($ano, $nome, $baseadoEm, $reajustePercentual, $por): Orcamento {
            $orcamento = Orcamento::create([
                'ano' => $ano,
                'nome' => mb_substr(trim($nome), 0, 120) ?: "Orçamento {$ano}",
                'versao' => ((int) Orcamento::query()->where('ano', $ano)->max('versao')) + 1,
                'status' => StatusOrcamento::Rascunho,
                'origem_orcamento_id' => $baseadoEm?->id,
                'criado_por_user_id' => $por?->id,
            ]);

            if ($baseadoEm) {
                $fator = 1 + ($reajustePercentual / 100);

                foreach ($baseadoEm->linhas()->get() as $linha) {
                    $orcamento->linhas()->create([
                        'plano_conta_id' => $linha->plano_conta_id,
                        'centro_custo_id' => $linha->centro_custo_id,
                        'mes' => $linha->mes,
                        'valor_previsto' => round((float) $linha->valor_previsto * $fator, 2),
                    ]);
                }
            }

            return $orcamento;
        });
    }

    /**
     * Grava (ou apaga, se o valor é zero) o previsto de uma conta em um mês.
     *
     * @throws \DomainException
     */
    public function definirValor(Orcamento $orcamento, int $planoContaId, ?int $centroCustoId, int $mes, float $valor): void
    {
        $this->exigirRascunho($orcamento);

        if ($mes < 1 || $mes > 12) {
            throw new \DomainException('O mês do orçamento vai de 1 a 12.');
        }

        if ($valor < 0) {
            throw new \DomainException('O valor previsto não pode ser negativo.');
        }

        $plano = PlanoConta::query()->find($planoContaId);

        if (! $plano || ! in_array($plano->tipo, ['receita', 'despesa'], true)) {
            throw new \DomainException('O orçamento só leva contas de receita e de despesa.');
        }

        $filtro = ['orcamento_id' => $orcamento->id, 'plano_conta_id' => $planoContaId, 'centro_custo_id' => $centroCustoId, 'mes' => $mes];
        $existente = OrcamentoLinha::query()->where($filtro)->first();

        if (round($valor, 2) <= 0) {
            $existente?->delete();

            return;
        }

        if ($existente) {
            $existente->update(['valor_previsto' => round($valor, 2)]);

            return;
        }

        OrcamentoLinha::create($filtro + ['valor_previsto' => round($valor, 2)]);
    }

    /**
     * Importa uma planilha CSV (separador `;` ou `,`): `plano;centro;jan;...;dez`, com o código do plano de contas da escola e o
     * nome do centro de custo (opcional). Linhas inválidas são puladas e voltam na lista de erros.
     *
     * @return array{linhas: int, erros: list<string>}
     *
     * @throws \DomainException
     */
    public function importarCsv(Orcamento $orcamento, string $conteudo): array
    {
        $this->exigirRascunho($orcamento);

        $linhas = preg_split('/\r\n|\r|\n/', trim(ltrim($conteudo, "\xEF\xBB\xBF"))) ?: [];
        $planos = PlanoConta::query()->get()->keyBy(fn (PlanoConta $p): string => mb_strtolower(trim((string) $p->codigo)));
        $centros = CentroCusto::query()->get()->keyBy(fn (CentroCusto $c): string => mb_strtolower(trim($c->nome)));
        $importadas = 0;
        $erros = [];

        DB::transaction(function () use ($orcamento, $linhas, $planos, $centros, &$importadas, &$erros): void {
            foreach ($linhas as $posicao => $texto) {
                if (trim($texto) === '') {
                    continue;
                }

                $separador = substr_count($texto, ';') >= substr_count($texto, ',') ? ';' : ',';
                $colunas = array_map('trim', str_getcsv($texto, $separador));

                // Cabeçalho: a primeira coluna não é um código de plano.
                if ($posicao === 0 && ! $planos->has(mb_strtolower($colunas[0] ?? ''))) {
                    continue;
                }

                $numero = $posicao + 1;
                $plano = $planos->get(mb_strtolower($colunas[0] ?? ''));

                if (! $plano) {
                    $erros[] = "Linha {$numero}: não existe conta do plano com o código \"".($colunas[0] ?? '').'".';

                    continue;
                }

                $centro = null;

                if (($colunas[1] ?? '') !== '') {
                    $centro = $centros->get(mb_strtolower($colunas[1]));

                    if (! $centro) {
                        $erros[] = "Linha {$numero}: não existe centro de custo \"{$colunas[1]}\".";

                        continue;
                    }
                }

                try {
                    for ($mes = 1; $mes <= 12; $mes++) {
                        $this->definirValor($orcamento, $plano->id, $centro?->id, $mes, $this->lerValor($colunas[$mes + 1] ?? ''));
                    }
                } catch (\DomainException $e) {
                    $erros[] = "Linha {$numero}: ".$e->getMessage();

                    continue;
                }

                $importadas++;
            }
        });

        return ['linhas' => $importadas, 'erros' => $erros];
    }

    public function lerValor(string $texto): float
    {
        $texto = trim(str_replace(['R$', ' '], '', $texto));

        if ($texto === '') {
            return 0.0;
        }

        // 1.234,56 (padrão brasileiro), 1.234 (milhar brasileiro) ou 1234.56 (ponto decimal).
        if (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', str_replace('.', '', $texto));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto) === 1) {
            $texto = str_replace('.', '', $texto);
        }

        return is_numeric($texto) ? (float) $texto : throw new \DomainException("Valor inválido: \"{$texto}\".");
    }

    // --- Aprovar e revisar --------------------------------------------------------------------------------------------

    /**
     * @throws \DomainException
     */
    public function aprovar(Orcamento $orcamento, User $por): Orcamento
    {
        $this->exigirRascunho($orcamento);

        if (! $orcamento->linhas()->where('valor_previsto', '>', 0)->exists()) {
            throw new \DomainException('O orçamento está em branco. Preencha ao menos uma conta antes de aprovar.');
        }

        DB::transaction(function () use ($orcamento, $por): void {
            Orcamento::query()
                ->where('ano', $orcamento->ano)
                ->where('status', StatusOrcamento::Aprovado->value)
                ->update(['status' => StatusOrcamento::Substituido->value]);

            $orcamento->update(['status' => StatusOrcamento::Aprovado, 'aprovado_por_user_id' => $por->id, 'aprovado_em' => now()]);
        });

        activity('orcamento')->performedOn($orcamento)->causedBy($por)->withProperties(['ano' => $orcamento->ano, 'versao' => $orcamento->versao])->log('Orçamento aprovado.');

        return $orcamento->refresh();
    }

    /**
     * Abre uma nova versão (rascunho) a partir de um orçamento aprovado; o original continua valendo até a nova ser aprovada.
     *
     * @throws \DomainException
     */
    public function revisar(Orcamento $orcamento, User $por): Orcamento
    {
        if (! $orcamento->estaAprovado()) {
            throw new \DomainException('Só um orçamento aprovado precisa de revisão; o rascunho se edita direto.');
        }

        if (Orcamento::query()->where('ano', $orcamento->ano)->where('status', StatusOrcamento::Rascunho->value)->exists()) {
            throw new \DomainException('Já existe um rascunho para este ano. Aprove ou exclua o rascunho antes de abrir outra revisão.');
        }

        $nova = $this->criar($orcamento->ano, $orcamento->nome, $orcamento, 0.0, $por);

        activity('orcamento')->performedOn($nova)->causedBy($por)->withProperties(['origem' => $orcamento->id])->log('Revisão do orçamento aberta.');

        return $nova;
    }

    /**
     * @throws \DomainException
     */
    public function excluir(Orcamento $orcamento): void
    {
        $this->exigirRascunho($orcamento);

        $orcamento->delete();
    }

    /**
     * @throws \DomainException
     */
    private function exigirRascunho(Orcamento $orcamento): void
    {
        if (! $orcamento->ehRascunho()) {
            throw new \DomainException('O orçamento aprovado não muda. Abra uma revisão para ajustar valores.');
        }
    }

    // --- Planilha ----------------------------------------------------------------------------------------------------

    /**
     * A grade do orçamento: uma entrada por conta (e centro), com os 12 meses.
     *
     * @return Collection<string, array{plano: PlanoConta, centro: ?CentroCusto, meses: array<int, float>, total: float}>
     */
    public function grade(Orcamento $orcamento): Collection
    {
        $linhas = $orcamento->linhas()->with(['planoConta', 'centroCusto'])->get();

        return $linhas->groupBy(fn (OrcamentoLinha $l): string => $this->chave($l->plano_conta_id, $l->centro_custo_id))
            ->map(function (Collection $grupo): array {
                $meses = array_fill(1, 12, 0.0);

                foreach ($grupo as $linha) {
                    $meses[$linha->mes] = (float) $linha->valor_previsto;
                }

                /** @var OrcamentoLinha $primeira */
                $primeira = $grupo->first();

                return ['plano' => $primeira->planoConta, 'centro' => $primeira->centroCusto, 'meses' => $meses, 'total' => round(array_sum($meses), 2)];
            })
            ->sortBy(fn (array $linha): string => (string) $linha['plano']->codigo)
            ->values()
            ->keyBy(fn (array $linha): string => $this->chave($linha['plano']->id, $linha['centro']?->id));
    }

    /**
     * Total previsto do ano em receitas e em despesas.
     *
     * @return array{receita: float, despesa: float, resultado: float}
     */
    public function totais(Orcamento $orcamento): array
    {
        $linhas = $orcamento->linhas()->with('planoConta:id,tipo')->get();
        $receita = round((float) $linhas->filter(fn (OrcamentoLinha $l): bool => $l->planoConta?->tipo === 'receita')->sum(fn (OrcamentoLinha $l): float => (float) $l->valor_previsto), 2);
        $despesa = round((float) $linhas->filter(fn (OrcamentoLinha $l): bool => $l->planoConta?->tipo === 'despesa')->sum(fn (OrcamentoLinha $l): float => (float) $l->valor_previsto), 2);

        return ['receita' => $receita, 'despesa' => $despesa, 'resultado' => round($receita - $despesa, 2)];
    }

    public function chave(int $planoId, ?int $centroId): string
    {
        return $planoId.':'.($centroId ?? 0);
    }

    // --- Orçado × realizado -------------------------------------------------------------------------------------------

    /**
     * Previsto acumulado até o mês × realizado no mesmo período, por conta (e centro).
     *
     * @return list<array{plano: PlanoConta, centro: ?CentroCusto, tipo: string, previsto: float, realizado: float, variacao: float, percentual: ?float, situacao: string}>
     */
    public function comparativo(Orcamento $orcamento, int $ateMes): array
    {
        $ateMes = max(1, min(12, $ateMes));
        $inicio = Carbon::create($orcamento->ano, 1, 1)->startOfDay();
        $fim = Carbon::create($orcamento->ano, $ateMes, 1)->endOfMonth();
        $arvore = $this->arvoreDePlanos();
        $resultado = [];

        foreach ($this->grade($orcamento) as $linha) {
            $previsto = round(array_sum(array_slice($linha['meses'], 0, $ateMes)), 2);
            $realizado = $this->realizado($this->subarvore($arvore, $linha['plano']->id), $linha['centro']?->id, $inicio, $fim);
            $percentual = $previsto > 0 ? round(($realizado / $previsto) * 100, 1) : null;

            $resultado[] = [
                'plano' => $linha['plano'],
                'centro' => $linha['centro'],
                'tipo' => (string) $linha['plano']->tipo,
                'previsto' => $previsto,
                'realizado' => $realizado,
                'variacao' => round($realizado - $previsto, 2),
                'percentual' => $percentual,
                'situacao' => $this->situacao((string) $linha['plano']->tipo, $previsto, $realizado),
            ];
        }

        return $resultado;
    }

    /**
     * `ok`, `atencao` ou `estourado`. Despesa: perto do previsto é atenção, acima é estouro. Receita: abaixo do previsto é que preocupa.
     */
    public function situacao(string $tipo, float $previsto, float $realizado): string
    {
        if ($previsto <= 0) {
            return $realizado > 0 && $tipo === 'despesa' ? 'estourado' : 'ok';
        }

        $razao = ($realizado / $previsto) * 100;
        $alerta = $this->percentualDeAlerta();

        if ($tipo === 'despesa') {
            return $razao > 100 ? 'estourado' : ($razao >= $alerta ? 'atencao' : 'ok');
        }

        return $razao >= 100 ? 'ok' : ($razao >= $alerta ? 'atencao' : 'estourado');
    }

    /**
     * Movimento do período que ficou sem conta do plano: o realizado fica incompleto por causa dele.
     *
     * @return array{quantidade: int, valor: float}
     */
    public function semClassificacao(int $ano, int $ateMes): array
    {
        $consulta = TransacaoBancaria::query()
            ->whereNull('plano_conta_id')
            ->whereBetween('data_transacao', [Carbon::create($ano, 1, 1)->toDateString(), Carbon::create($ano, max(1, min(12, $ateMes)), 1)->endOfMonth()->toDateString()]);

        return ['quantidade' => (clone $consulta)->count(), 'valor' => round((float) $consulta->sum('valor'), 2)];
    }

    /**
     * Quanto sobra do orçamento do ano para uma conta (e centro), descontado o que já foi realizado e o que está comprometido (contas a
     * pagar em aberto mais o que o chamador informar, como pedidos aprovados ainda não recebidos). Usa a linha do orçamento da própria
     * conta ou, na falta, a da conta-mãe mais próxima. Sem orçamento aprovado ou sem linha, devolve null.
     *
     * @return array{orcamento_id: int, plano_orcado: string, previsto: float, realizado: float, comprometido: float, saldo: float}|null
     */
    public function saldoDisponivel(?int $planoContaId, ?int $centroCustoId, CarbonInterface $data, float $comprometidoExtra = 0.0): ?array
    {
        if (! $planoContaId) {
            return null;
        }

        $orcamento = Orcamento::vigenteDoAno($data->year);

        if (! $orcamento) {
            return null;
        }

        $arvore = $this->arvoreDePlanos();
        $linhas = $this->grade($orcamento);

        foreach ($this->ancestrais($arvore, $planoContaId) as $candidato) {
            $linha = $linhas->first(fn (array $l): bool => $l['plano']->id === $candidato && ($l['centro'] === null || $l['centro']->id === $centroCustoId));

            if (! $linha) {
                continue;
            }

            $ids = $this->subarvore($arvore, $candidato);
            $inicio = Carbon::create($data->year, 1, 1)->startOfDay();
            $fim = Carbon::create($data->year, 12, 31)->endOfDay();
            $realizado = $this->realizado($ids, $linha['centro']?->id, $inicio, $fim);
            $comprometido = round($this->comprometido($ids, $linha['centro']?->id, $inicio, $fim) + $comprometidoExtra, 2);

            return [
                'orcamento_id' => $orcamento->id,
                'plano_orcado' => "{$linha['plano']->codigo} - {$linha['plano']->nome}",
                'previsto' => $linha['total'],
                'realizado' => $realizado,
                'comprometido' => $comprometido,
                'saldo' => round($linha['total'] - $realizado - $comprometido, 2),
            ];
        }

        return null;
    }

    /**
     * Despesas com o realizado acima do previsto acumulado (ou do percentual de alerta) até o mês: a fila da Mesa.
     *
     * @return list<array{plano: PlanoConta, centro: ?CentroCusto, previsto: float, realizado: float, percentual: ?float, situacao: string}>
     */
    public function despesasEmAlerta(Orcamento $orcamento, int $ateMes): array
    {
        return array_values(array_filter(
            array_map(fn (array $l): array => $l, $this->comparativo($orcamento, $ateMes)),
            fn (array $l): bool => $l['tipo'] === 'despesa' && in_array($l['situacao'], ['atencao', 'estourado'], true),
        ));
    }

    // --- Apoio -------------------------------------------------------------------------------------------------------

    /**
     * @param  list<int>  $planoIds
     */
    private function realizado(array $planoIds, ?int $centroCustoId, CarbonInterface $inicio, CarbonInterface $fim): float
    {
        return round((float) TransacaoBancaria::query()
            ->whereIn('plano_conta_id', $planoIds)
            ->when($centroCustoId, fn ($q) => $q->where('centro_custo_id', $centroCustoId))
            ->whereBetween('data_transacao', [$inicio->toDateString(), $fim->toDateString()])
            ->sum('valor'), 2);
    }

    /**
     * Contas a pagar ainda em aberto na conta e no ano: compromisso já assumido que ainda não virou realizado.
     *
     * @param  list<int>  $planoIds
     */
    private function comprometido(array $planoIds, ?int $centroCustoId, CarbonInterface $inicio, CarbonInterface $fim): float
    {
        return round((float) ContaPagar::query()
            ->whereIn('plano_conta_id', $planoIds)
            ->when($centroCustoId, fn ($q) => $q->where('centro_custo_id', $centroCustoId))
            ->whereIn('status', [StatusContaPagar::Pendente->value, StatusContaPagar::Atrasado->value])
            ->whereBetween('vencimento', [$inicio->toDateString(), $fim->toDateString()])
            ->sum('valor'), 2);
    }

    /**
     * @return array<int, ?int> id => pai_id
     */
    private function arvoreDePlanos(): array
    {
        return PlanoConta::query()->pluck('pai_id', 'id')->map(fn ($pai) => $pai === null ? null : (int) $pai)->all();
    }

    /**
     * A conta e todas as suas descendentes.
     *
     * @param  array<int, ?int>  $arvore
     * @return list<int>
     */
    private function subarvore(array $arvore, int $planoId): array
    {
        $ids = [$planoId];

        foreach ($arvore as $id => $pai) {
            $cursor = $pai;
            $guarda = 0;

            while ($cursor !== null && $guarda++ < 20) {
                if ($cursor === $planoId) {
                    $ids[] = (int) $id;

                    break;
                }

                $cursor = $arvore[$cursor] ?? null;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * A conta e as suas ascendentes, da mais próxima para a mais distante.
     *
     * @param  array<int, ?int>  $arvore
     * @return list<int>
     */
    private function ancestrais(array $arvore, int $planoId): array
    {
        $ids = [];
        $cursor = $planoId;

        while ($cursor !== null && ! in_array($cursor, $ids, true) && count($ids) < 20) {
            $ids[] = $cursor;
            $cursor = $arvore[$cursor] ?? null;
        }

        return $ids;
    }
}

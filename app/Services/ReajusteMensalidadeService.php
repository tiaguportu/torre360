<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ArredondamentoReajuste;
use App\Enums\SituacaoMatricula;
use App\Enums\StatusCenarioReajuste;
use App\Enums\StatusPlanilhaLei;
use App\Exceptions\ReajusteMensalidadeException;
use App\Models\BolsaConcedida;
use App\Models\CenarioReajuste;
use App\Models\CenarioReajusteItem;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\PeriodoRematricula;
use App\Models\PeriodoRematriculaPreco;
use App\Models\PlanilhaLeiMensalidade;
use App\Models\Rematricula;
use App\Models\User;
use App\Support\CsvSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reajuste de mensalidades para a campanha de rematrícula.
 *
 * O ponto de partida de cada série é o valor médio dos contratos das matrículas ativas do período letivo de ORIGEM da
 * campanha (editável). Sobre ele se aplica o percentual (padrão do cenário ou próprio da série) e o arredondamento.
 * Aprovado e aplicado, o preço de cada série passa a valer nas rematrículas efetivadas depois, no lugar do valor único
 * da campanha. Contratos que já existem NÃO são alterados: o reajuste vale para o próximo período.
 */
class ReajusteMensalidadeService
{
    /** Divulgação mínima antes da data final de matrícula (Lei nº 9.870/1999, art. 2º). */
    public const PRAZO_DIVULGACAO_DIAS = 45;

    public const PERCENTUAL_MINIMO = -50.0;

    public const PERCENTUAL_MAXIMO = 100.0;

    /**
     * Cria o cenário já com um item por série que tem contratos com valor no período de origem.
     *
     * @throws ReajusteMensalidadeException
     */
    public function simular(PeriodoRematricula $periodo, string $nome, float $percentual, ArredondamentoReajuste $arredondamento, ?PlanilhaLeiMensalidade $planilha, User $por): CenarioReajuste
    {
        $this->validarPercentual($percentual);

        $base = $this->baseDoPeriodoDeOrigem($periodo);

        if ($base->isEmpty()) {
            throw new ReajusteMensalidadeException('Não há contratos com valor nas matrículas ativas do período de origem desta campanha. Sem eles não há valor atual para reajustar.');
        }

        return DB::transaction(function () use ($periodo, $nome, $percentual, $arredondamento, $planilha, $por, $base): CenarioReajuste {
            $cenario = CenarioReajuste::create([
                'periodo_rematricula_id' => $periodo->id,
                'planilha_lei_id' => $planilha?->id,
                'nome' => $nome,
                'percentual_padrao' => $percentual,
                'arredondamento' => $arredondamento,
                'status' => StatusCenarioReajuste::Simulacao,
                'criado_por_user_id' => $por->id,
            ]);

            foreach ($base as $serieId => $linha) {
                CenarioReajusteItem::create([
                    'cenario_id' => $cenario->id,
                    'serie_id' => $serieId,
                    'valor_atual' => $linha['valor_atual'],
                    'valor_novo' => $this->calcularValorNovo($linha['valor_atual'], $percentual, $arredondamento),
                    'alunos' => $linha['alunos'],
                    'bolsa_media' => $linha['bolsa_media'],
                ]);
            }

            return $cenario;
        });
    }

    /**
     * Altera o percentual padrão e o arredondamento e recalcula todos os itens.
     *
     * @throws ReajusteMensalidadeException
     */
    public function atualizarCenario(CenarioReajuste $cenario, float $percentual, ArredondamentoReajuste $arredondamento): CenarioReajuste
    {
        $this->exigirSimulacao($cenario);
        $this->validarPercentual($percentual);

        return DB::transaction(function () use ($cenario, $percentual, $arredondamento): CenarioReajuste {
            $cenario->update(['percentual_padrao' => $percentual, 'arredondamento' => $arredondamento]);

            foreach ($cenario->itens()->get() as $item) {
                $this->recalcularItem($item->setRelation('cenario', $cenario));
            }

            return $cenario->refresh();
        });
    }

    /**
     * Ajusta uma série: percentual próprio (nulo volta ao padrão) e/ou valor atual informado à mão.
     *
     * @throws ReajusteMensalidadeException
     */
    public function atualizarItem(CenarioReajusteItem $item, ?float $percentual, ?float $valorAtual = null): CenarioReajusteItem
    {
        $item->loadMissing('cenario');
        $this->exigirSimulacao($item->cenario);

        if ($percentual !== null) {
            $this->validarPercentual($percentual);
        }

        if ($valorAtual !== null && $valorAtual < 0) {
            throw new ReajusteMensalidadeException('O valor atual não pode ser negativo.');
        }

        $item->percentual = $percentual;

        if ($valorAtual !== null) {
            $item->valor_atual = $valorAtual;
        }

        return $this->recalcularItem($item);
    }

    /**
     * @throws ReajusteMensalidadeException
     */
    public function aprovar(CenarioReajuste $cenario, User $por): CenarioReajuste
    {
        return DB::transaction(function () use ($cenario, $por): CenarioReajuste {
            $cenario = CenarioReajuste::query()->lockForUpdate()->with('itens')->findOrFail($cenario->id);
            $this->exigirSimulacao($cenario);

            if ($cenario->itens->isEmpty()) {
                throw new ReajusteMensalidadeException('O cenário não tem nenhuma série.');
            }

            $semValor = $cenario->itens->filter(fn (CenarioReajusteItem $i): bool => (float) $i->valor_novo <= 0);
            if ($semValor->isNotEmpty()) {
                throw new ReajusteMensalidadeException('Há série com valor novo zerado. Informe o valor atual ou remova o cenário.');
            }

            return $this->registrar($cenario, 'Cenário de reajuste aprovado.', $por, [
                'status' => StatusCenarioReajuste::Aprovado,
                'aprovado_por_user_id' => $por->id,
                'aprovado_em' => now(),
            ]);
        });
    }

    /**
     * Grava o preço de cada série na campanha. Só vale para as próximas rematrículas efetivadas.
     *
     * @throws ReajusteMensalidadeException
     */
    public function aplicar(CenarioReajuste $cenario, User $por): CenarioReajuste
    {
        return DB::transaction(function () use ($cenario, $por): CenarioReajuste {
            $cenario = CenarioReajuste::query()->lockForUpdate()->with('itens')->findOrFail($cenario->id);

            if ($cenario->status !== StatusCenarioReajuste::Aprovado) {
                throw new ReajusteMensalidadeException('Só um cenário aprovado pode ser aplicado.');
            }

            $this->exigirCampanhaSemRematriculaEfetivada($cenario->periodo_rematricula_id, 'aplicar');

            $outro = CenarioReajuste::query()
                ->where('periodo_rematricula_id', $cenario->periodo_rematricula_id)
                ->where('status', StatusCenarioReajuste::Aplicado->value)
                ->where('id', '!=', $cenario->id)
                ->first();

            if ($outro) {
                throw new ReajusteMensalidadeException("O cenário “{$outro->nome}” já está aplicado nesta campanha. Desfaça-o antes de aplicar outro.");
            }

            foreach ($cenario->itens as $item) {
                PeriodoRematriculaPreco::updateOrCreate(
                    ['periodo_rematricula_id' => $cenario->periodo_rematricula_id, 'serie_id' => $item->serie_id],
                    ['valor_total' => $item->valor_novo, 'cenario_reajuste_item_id' => $item->id],
                );
            }

            return $this->registrar($cenario, 'Cenário de reajuste aplicado na campanha.', $por, [
                'status' => StatusCenarioReajuste::Aplicado,
                'aplicado_em' => now(),
            ]);
        });
    }

    /**
     * Tira os preços da campanha e devolve o cenário a "aprovado". Só antes de qualquer rematrícula efetivada.
     *
     * @throws ReajusteMensalidadeException
     */
    public function desfazer(CenarioReajuste $cenario, User $por): CenarioReajuste
    {
        return DB::transaction(function () use ($cenario, $por): CenarioReajuste {
            $cenario = CenarioReajuste::query()->lockForUpdate()->with('itens')->findOrFail($cenario->id);

            if (! $cenario->estaAplicado()) {
                throw new ReajusteMensalidadeException('Só um cenário aplicado pode ser desfeito.');
            }

            $this->exigirCampanhaSemRematriculaEfetivada($cenario->periodo_rematricula_id, 'desfazer');

            PeriodoRematriculaPreco::query()
                ->where('periodo_rematricula_id', $cenario->periodo_rematricula_id)
                ->whereIn('cenario_reajuste_item_id', $cenario->itens->pluck('id'))
                ->delete();

            return $this->registrar($cenario, 'Aplicação do cenário de reajuste desfeita.', $por, [
                'status' => StatusCenarioReajuste::Aprovado,
                'aplicado_em' => null,
            ]);
        });
    }

    /**
     * Registra que as famílias foram comunicadas do novo valor.
     *
     * @throws ReajusteMensalidadeException
     */
    public function registrarComunicado(CenarioReajuste $cenario, User $por): CenarioReajuste
    {
        if ($cenario->status === StatusCenarioReajuste::Simulacao) {
            throw new ReajusteMensalidadeException('Aprove o cenário antes de registrar o comunicado às famílias.');
        }

        return $this->registrar($cenario, 'Comunicado do reajuste às famílias registrado.', $por, ['comunicado_em' => now()]);
    }

    /**
     * Projeção de receita anual do cenário: o que as turmas atuais pagariam hoje e depois do reajuste.
     *
     * @return array{receita_atual: float, receita_nova: float, diferenca: float, variacao_percentual: float, bolsas_estimadas: float, receita_liquida_nova: float, alunos: int}
     */
    public function projecao(CenarioReajuste $cenario): array
    {
        $cenario->loadMissing('itens');

        $atual = $nova = $bolsas = 0.0;
        $alunos = 0;

        foreach ($cenario->itens as $item) {
            $atual += $item->alunos * (float) $item->valor_atual;
            $nova += $item->alunos * (float) $item->valor_novo;
            $bolsas += $item->alunos * (float) $item->valor_novo * ((float) $item->bolsa_media / 100);
            $alunos += $item->alunos;
        }

        return [
            'receita_atual' => round($atual, 2),
            'receita_nova' => round($nova, 2),
            'diferenca' => round($nova - $atual, 2),
            'variacao_percentual' => $atual > 0 ? round(($nova - $atual) / $atual * 100, 2) : 0.0,
            'bolsas_estimadas' => round($bolsas, 2),
            'receita_liquida_nova' => round($nova - $bolsas, 2),
            'alunos' => $alunos,
        ];
    }

    /**
     * Pontos de atenção que não impedem o cenário, mas a direção precisa ver antes de decidir.
     *
     * @return list<string>
     */
    public function alertas(CenarioReajuste $cenario): array
    {
        $cenario->loadMissing(['itens', 'periodoRematricula', 'planilhaLei']);
        $alertas = [];
        $planilha = $cenario->planilhaLei;

        if (! $planilha) {
            $alertas[] = 'Nenhuma planilha de custos da Lei das Mensalidades está vinculada ao cenário. O reajuste precisa se apoiar na planilha divulgada às famílias.';
        } else {
            if (! in_array($planilha->status, [StatusPlanilhaLei::Homologada, StatusPlanilhaLei::Publicada], true)) {
                $alertas[] = "A planilha “{$planilha->titulo}” ainda não foi homologada ({$planilha->status->getLabel()}).";
            }

            $referencia = (float) ($planilha->percentual_reajuste_adotado ?? $planilha->percentual_reajuste_sugerido ?? 0);
            if ($referencia > 0 && (float) $cenario->percentual_padrao > $referencia) {
                $alertas[] = sprintf('O percentual do cenário (%s%%) é maior que o da planilha (%s%%).', $this->numero((float) $cenario->percentual_padrao), $this->numero($referencia));
            }
        }

        $dataFim = $cenario->periodoRematricula->data_fim;
        $limite = $dataFim->copy()->subDays(self::PRAZO_DIVULGACAO_DIAS)->startOfDay();
        $divulgacao = $cenario->comunicado_em?->copy()->startOfDay() ?? today();

        if ($divulgacao->gt($limite)) {
            $alertas[] = sprintf(
                'A divulgação %s menos de %d dias antes do fim da campanha (%s). A Lei nº 9.870/1999 (art. 2º) pede ao menos %d dias antes da data final de matrícula; confira com o jurídico.',
                $cenario->comunicado_em ? 'foi feita com' : 'ficaria com',
                self::PRAZO_DIVULGACAO_DIAS,
                $dataFim->format('d/m/Y'),
                self::PRAZO_DIVULGACAO_DIAS,
            );
        }

        if ($cenario->itens->contains(fn (CenarioReajusteItem $i): bool => $i->alunos === 0)) {
            $alertas[] = 'Há série sem aluno com contrato no período de origem; o valor atual foi informado à mão.';
        }

        if (Rematricula::query()->where('periodo_rematricula_id', $cenario->periodo_rematricula_id)->whereNotNull('nova_matricula_id')->exists()) {
            $alertas[] = 'Já existem rematrículas efetivadas nesta campanha. Elas mantêm o valor antigo e o cenário não poderá ser aplicado.';
        }

        return $alertas;
    }

    /**
     * Quem deve ser avisado: um item por responsável financeiro e aluno das séries do cenário.
     *
     * @return Collection<int, array{responsavel: string, email: ?string, telefone: ?string, aluno: string, serie: string, valor_atual: float, valor_novo: float, variacao_percentual: float}>
     */
    public function destinatarios(CenarioReajuste $cenario): Collection
    {
        $cenario->loadMissing(['itens.serie', 'periodoRematricula']);
        $itens = $cenario->itens->keyBy('serie_id');

        $matriculas = $this->matriculasAtivasDaOrigem($cenario->periodoRematricula)
            ->filter(fn (Matricula $m): bool => $itens->has($m->turma->serie_id))
            ->load('pessoa');

        $contratos = $this->contratoMaisRecenteDe($matriculas)->load('responsaveisFinanceiros.pessoa');
        $linhas = collect();

        foreach ($matriculas as $matricula) {
            $contrato = $contratos->get($matricula->id);

            if (! $contrato) {
                continue;
            }

            $item = $itens->get($matricula->turma->serie_id);

            foreach ($contrato->responsaveisFinanceiros as $responsavel) {
                $linhas->push([
                    'responsavel' => (string) $responsavel->pessoa?->nome,
                    'email' => $responsavel->pessoa?->email,
                    'telefone' => $responsavel->pessoa?->telefone,
                    'aluno' => (string) $matricula->pessoa?->nome,
                    'serie' => (string) $item->serie?->nome,
                    'valor_atual' => (float) $item->valor_atual,
                    'valor_novo' => (float) $item->valor_novo,
                    'variacao_percentual' => (float) $item->valor_atual > 0 ? round(((float) $item->valor_novo - (float) $item->valor_atual) / (float) $item->valor_atual * 100, 2) : 0.0,
                ]);
            }
        }

        return $linhas->sortBy(['responsavel', 'aluno'])->values();
    }

    /**
     * Lista de comunicação em CSV (separador ponto e vírgula, BOM para o Excel), com proteção contra injeção de fórmulas.
     */
    public function csvDeComunicacao(CenarioReajuste $cenario): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        CsvSanitizer::fputcsv($handle, ['Responsável', 'E-mail', 'Telefone', 'Aluno', 'Série', 'Valor atual', 'Valor novo', 'Variação (%)']);

        foreach ($this->destinatarios($cenario) as $linha) {
            CsvSanitizer::fputcsv($handle, [
                $linha['responsavel'],
                $linha['email'],
                $linha['telefone'],
                $linha['aluno'],
                $linha['serie'],
                $this->numero($linha['valor_atual']),
                $this->numero($linha['valor_novo']),
                $this->numero($linha['variacao_percentual']),
            ]);
        }

        rewind($handle);
        $conteudo = (string) stream_get_contents($handle);
        fclose($handle);

        return $conteudo;
    }

    public function calcularValorNovo(float $valorAtual, float $percentual, ArredondamentoReajuste $arredondamento): float
    {
        $centavos = (int) round($valorAtual * 100);
        $fator = 10000 + (int) round($percentual * 100);

        return $arredondamento->aplicar(round(($centavos * $fator) / 1_000_000, 2));
    }

    /**
     * Valor atual médio, alunos e bolsa média por série, a partir dos contratos do período de origem.
     *
     * @return Collection<int, array{valor_atual: float, alunos: int, bolsa_media: float}>
     */
    public function baseDoPeriodoDeOrigem(PeriodoRematricula $periodo): Collection
    {
        $matriculas = $this->matriculasAtivasDaOrigem($periodo);
        $contratos = $this->contratoMaisRecenteDe($matriculas);
        $bolsas = BolsaConcedida::query()->whereIn('matricula_id', $matriculas->pluck('id'))->get()->groupBy('matricula_id');

        $porSerie = [];

        foreach ($matriculas as $matricula) {
            $contrato = $contratos->get($matricula->id);

            if (! $contrato || (float) $contrato->valor_total <= 0) {
                continue;
            }

            $percentualBolsa = min(100, (int) ($bolsas->get($matricula->id)?->filter(fn (BolsaConcedida $b): bool => $b->isAtiva())->sum('percentual') ?? 0));

            $porSerie[$matricula->turma->serie_id]['valores'][] = (float) $contrato->valor_total;
            $porSerie[$matricula->turma->serie_id]['bolsas'][] = $percentualBolsa;
        }

        return collect($porSerie)->map(fn (array $dados): array => [
            'valor_atual' => round(array_sum($dados['valores']) / count($dados['valores']), 2),
            'alunos' => count($dados['valores']),
            'bolsa_media' => round(array_sum($dados['bolsas']) / count($dados['bolsas']), 2),
        ]);
    }

    /**
     * @return Collection<int, Matricula>
     */
    private function matriculasAtivasDaOrigem(PeriodoRematricula $periodo): Collection
    {
        return Matricula::query()
            ->with('turma')
            ->where('situacao', SituacaoMatricula::ATIVA->value)
            ->whereHas('turma', fn ($turma) => $turma->where('periodo_letivo_id', $periodo->periodo_letivo_origem_id)->whereNotNull('serie_id'))
            ->get();
    }

    /**
     * O contrato mais recente de cada matrícula, indexado pelo id da matrícula.
     *
     * @param  Collection<int, Matricula>  $matriculas
     * @return Collection<int, Contrato>
     */
    private function contratoMaisRecenteDe(Collection $matriculas): Collection
    {
        return Contrato::query()
            ->whereIn('matricula_id', $matriculas->pluck('id'))
            ->orderBy('id')
            ->get()
            ->keyBy('matricula_id'); // o último da ordenação por id substitui os anteriores
    }

    private function recalcularItem(CenarioReajusteItem $item): CenarioReajusteItem
    {
        $item->loadMissing('cenario');

        $item->valor_novo = $this->calcularValorNovo((float) $item->valor_atual, $item->percentualEfetivo(), $item->cenario->arredondamento);
        $item->save();

        return $item;
    }

    /**
     * Atualiza o cenário sem o log automático (que não diria quem agiu) e registra o evento com o autor.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function registrar(CenarioReajuste $cenario, string $descricao, User $por, array $atributos): CenarioReajuste
    {
        $cenario->disableLogging();
        $cenario->update($atributos);
        $cenario->enableLogging();

        activity('reajuste_mensalidade')
            ->performedOn($cenario)
            ->causedBy($por)
            ->withProperties(['status' => $cenario->status->value])
            ->log($descricao);

        return $cenario->refresh();
    }

    private function exigirSimulacao(CenarioReajuste $cenario): void
    {
        if ($cenario->status !== StatusCenarioReajuste::Simulacao) {
            throw new ReajusteMensalidadeException('O cenário já foi aprovado e não pode mais ser alterado. Crie outra simulação.');
        }
    }

    private function exigirCampanhaSemRematriculaEfetivada(int $periodoId, string $acao): void
    {
        $efetivadas = Rematricula::query()->where('periodo_rematricula_id', $periodoId)->whereNotNull('nova_matricula_id')->count();

        if ($efetivadas > 0) {
            throw new ReajusteMensalidadeException("Não é possível {$acao} o reajuste: {$efetivadas} rematrícula(s) desta campanha já foram efetivadas, e as famílias já têm o valor antigo no contrato.");
        }
    }

    private function validarPercentual(float $percentual): void
    {
        if ($percentual < self::PERCENTUAL_MINIMO || $percentual > self::PERCENTUAL_MAXIMO) {
            throw new ReajusteMensalidadeException(sprintf('O percentual precisa estar entre %s%% e %s%%.', $this->numero(self::PERCENTUAL_MINIMO), $this->numero(self::PERCENTUAL_MAXIMO)));
        }
    }

    private function numero(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusContaPagar;
use App\Models\ContaPagar;
use App\Models\ContaRecorrente;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Contas a pagar recorrentes (Onda 78). O modelo guarda a regra (periodicidade, dia, valor, vigência); uma rotina diária gera a
 * conta de cada vencimento com a antecedência escolhida. A mesma conta nunca é gerada duas vezes: a competência (mês do
 * vencimento) é única por modelo, no banco e na rotina.
 *
 * Valor estimado (energia, água) gera a conta marcada como "a revisar": quem paga confere o valor da fatura antes de dar a baixa.
 */
class ContaRecorrenteService
{
    /**
     * Os vencimentos do modelo entre as duas datas (inclusive), na ordem. O primeiro é o primeiro dia de vencimento a partir do
     * início da vigência; o dia 31 em mês curto vence no último dia do mês.
     *
     * @return list<CarbonImmutable>
     */
    public function vencimentos(ContaRecorrente $modelo, CarbonInterface $de, CarbonInterface $ate): array
    {
        $inicio = CarbonImmutable::parse($modelo->vigencia_inicio)->startOfDay();
        $fim = $modelo->vigencia_fim ? CarbonImmutable::parse($modelo->vigencia_fim)->startOfDay() : null;
        $de = CarbonImmutable::parse($de)->startOfDay();
        $ate = CarbonImmutable::parse($ate)->startOfDay();
        $intervalo = $modelo->periodicidade->meses();
        $dia = max(1, min(31, (int) $modelo->dia_vencimento));
        $mes = $inicio->startOfMonth();
        $lista = [];

        for ($i = 0; $i < 600; $i++) {
            $vencimento = $mes->addMonthsNoOverflow($i * $intervalo);
            $vencimento = $vencimento->setDay(min($dia, $vencimento->daysInMonth));

            if ($vencimento->gt($ate) || ($fim !== null && $vencimento->gt($fim))) {
                break;
            }

            if ($vencimento->lt($inicio) || $vencimento->lt($de)) {
                continue;
            }

            $lista[] = $vencimento;
        }

        return $lista;
    }

    /**
     * Gera as contas a pagar dos vencimentos que já entraram na janela de antecedência e ainda não têm conta.
     *
     * @return array{geradas: int, ja_existiam: int}
     */
    public function gerar(?CarbonInterface $hoje = null): array
    {
        $hoje = CarbonImmutable::parse($hoje ?? today())->startOfDay();
        $retroativo = max(0, (int) config('financeiro.recorrentes.dias_retroativos', 31));
        $geradas = 0;
        $jaExistiam = 0;

        foreach (ContaRecorrente::query()->ativas()->orderBy('id')->get() as $modelo) {
            $limite = $hoje->addDays((int) $modelo->antecedencia_dias);

            foreach ($this->vencimentos($modelo, $hoje->subDays($retroativo), $limite) as $vencimento) {
                if ($this->gerarConta($modelo, $vencimento, $hoje)) {
                    $geradas++;
                } else {
                    $jaExistiam++;
                }
            }
        }

        return ['geradas' => $geradas, 'ja_existiam' => $jaExistiam];
    }

    private function gerarConta(ContaRecorrente $modelo, CarbonImmutable $vencimento, CarbonImmutable $hoje): bool
    {
        $competencia = $vencimento->startOfMonth();

        if (ContaPagar::query()->where('conta_recorrente_id', $modelo->id)->whereDate('competencia', $competencia->toDateString())->exists()) {
            return false;
        }

        try {
            ContaPagar::create([
                'descricao' => $modelo->descricao.' — '.$competencia->format('m/Y'),
                'valor' => $modelo->valor,
                'vencimento' => $vencimento->toDateString(),
                'status' => $vencimento->lt($hoje) ? StatusContaPagar::Atrasado : StatusContaPagar::Pendente,
                'fornecedor_id' => $modelo->fornecedor_id,
                'plano_conta_id' => $modelo->plano_conta_id,
                'centro_custo_id' => $modelo->centro_custo_id,
                'conta_recorrente_id' => $modelo->id,
                'competencia' => $competencia->toDateString(),
                'valor_revisado' => ! $modelo->valor_estimado,
                'observacao' => $modelo->valor_estimado ? 'Gerada de uma conta recorrente de valor estimado: confira o valor da fatura antes de pagar.' : 'Gerada de uma conta recorrente.',
            ]);
        } catch (QueryException $e) {
            // Duas rotinas ao mesmo tempo: a segunda perde a corrida e a conta já existe.
            if (ContaPagar::query()->where('conta_recorrente_id', $modelo->id)->whereDate('competencia', $competencia->toDateString())->exists()) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * Vencimentos dos modelos ativos que ainda não viraram conta, para a projeção do caixa.
     *
     * @return Collection<int, array{modelo: ContaRecorrente, vencimento: CarbonImmutable, valor: float}>
     */
    public function proximasSemConta(CarbonInterface $de, CarbonInterface $ate): Collection
    {
        $modelos = ContaRecorrente::query()->ativas()->get();
        $existentes = ContaPagar::query()
            ->whereNotNull('conta_recorrente_id')
            ->whereDate('competencia', '>=', CarbonImmutable::parse($de)->startOfMonth()->toDateString())
            ->whereDate('competencia', '<=', CarbonImmutable::parse($ate)->toDateString())
            ->get(['conta_recorrente_id', 'competencia'])
            ->map(fn (ContaPagar $c): string => $c->conta_recorrente_id.'|'.$c->competencia->format('Y-m'))
            ->flip();

        $lista = collect();

        foreach ($modelos as $modelo) {
            foreach ($this->vencimentos($modelo, $de, $ate) as $vencimento) {
                if (! $existentes->has($modelo->id.'|'.$vencimento->format('Y-m'))) {
                    $lista->push(['modelo' => $modelo, 'vencimento' => $vencimento, 'valor' => (float) $modelo->valor]);
                }
            }
        }

        return $lista;
    }

    /**
     * Confirma (ou corrige) o valor de uma conta gerada de valor estimado, na hora de pagar.
     *
     * @throws \DomainException
     */
    public function revisarValor(ContaPagar $conta, float $valor, bool $aplicarAoModelo = false, ?User $por = null): ContaPagar
    {
        if (in_array($conta->status, [StatusContaPagar::Pago, StatusContaPagar::Cancelado], true)) {
            throw new \DomainException('Conta paga ou cancelada não tem o valor revisado.');
        }

        if ($valor <= 0) {
            throw new \DomainException('Informe o valor da fatura.');
        }

        $conta->update(['valor' => round($valor, 2), 'valor_revisado' => true]);

        if ($aplicarAoModelo && $conta->conta_recorrente_id) {
            $conta->loadMissing('contaRecorrente');
            $conta->contaRecorrente?->update(['valor' => round($valor, 2)]);
        }

        $registro = activity('conta_recorrente')->performedOn($conta)->withProperties(['valor' => round($valor, 2), 'aplicado_ao_modelo' => $aplicarAoModelo]);

        if ($por) {
            $registro->causedBy($por);
        }

        $registro->log('Valor da conta recorrente revisado.');

        return $conta->refresh();
    }

    /**
     * Registra o reajuste do contrato: muda o valor do modelo, marca o próximo reajuste (em um ano, se não informado) e atualiza as
     * contas ainda abertas, de hoje em diante, que continuam no valor antigo (a que alguém editou à mão fica como está).
     *
     * @return int quantas contas abertas foram atualizadas
     *
     * @throws \DomainException
     */
    public function registrarReajuste(ContaRecorrente $modelo, float $novoValor, ?CarbonInterface $proximoReajuste = null, ?User $por = null): int
    {
        if ($novoValor <= 0) {
            throw new \DomainException('Informe o novo valor do contrato.');
        }

        $antigo = (float) $modelo->valor;
        $base = $modelo->proximo_reajuste_em ? CarbonImmutable::parse($modelo->proximo_reajuste_em) : CarbonImmutable::today();
        $proximo = $proximoReajuste ? CarbonImmutable::parse($proximoReajuste)->startOfDay() : $base->addYear();

        if ($proximo->lte(CarbonImmutable::today())) {
            throw new \DomainException('A data do próximo reajuste precisa ser futura.');
        }

        $atualizadas = ContaPagar::query()
            ->where('conta_recorrente_id', $modelo->id)
            ->whereIn('status', [StatusContaPagar::Pendente->value, StatusContaPagar::Atrasado->value])
            ->whereDate('vencimento', '>=', today()->toDateString())
            ->where('valor', round($antigo, 2))
            ->update(['valor' => round($novoValor, 2)]);

        $modelo->update(['valor' => round($novoValor, 2), 'proximo_reajuste_em' => $proximo->toDateString()]);

        $registro = activity('conta_recorrente')->performedOn($modelo)->withProperties(['valor_antigo' => $antigo, 'valor_novo' => round($novoValor, 2), 'contas_atualizadas' => $atualizadas]);

        if ($por) {
            $registro->causedBy($por);
        }

        $registro->log('Reajuste do contrato registrado.');

        return $atualizadas;
    }
}

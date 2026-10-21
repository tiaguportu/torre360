<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RegimeContratacao;
use App\Enums\SituacaoConcessaoFerias;
use App\Models\Funcionario;
use App\Models\PeriodoFerias;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Acompanha o prazo de concessão das férias dos empregados CLT.
 *
 * Regra (CLT): o empregado adquire o direito após 12 meses de trabalho (período aquisitivo) e o empregador deve
 * conceder as férias nos 12 meses seguintes (art. 134). Se concede depois, paga a remuneração em dobro (art. 137;
 * TST, Súmula 81, para os dias gozados fora do prazo). Esta classe só calcula e avisa: não altera o status gravado
 * nem decide pagamento.
 *
 * Limitações: considera só empregados CLT ativos; o fracionamento em até três períodos (art. 134, § 1º) e a redução
 * do direito por faltas (art. 130) não são modelados, então o saldo é o que o RH informou em `dias_direito` e
 * `dias_gozados`.
 */
class FeriasVencimentoService
{
    /** Quantos dias antes do fim do prazo de concessão o período entra em "prazo acabando". */
    public const DIAS_ALERTA = 90;

    /** Períodos esperados sem registro só são cobrados se o prazo de concessão acabou há menos de um ano. */
    public const JANELA_SEM_REGISTRO_DIAS = 365;

    /**
     * @return array{situacao: SituacaoConcessaoFerias, limite: CarbonInterface, dias_para_limite: int, saldo: int, dias_fora_do_prazo: int, dobro_dias: int}
     */
    public function avaliar(PeriodoFerias $periodo, ?CarbonInterface $hoje = null): array
    {
        $hoje = ($hoje ?? today())->copy()->startOfDay();
        $limite = $periodo->limiteDeConcessao();
        $saldo = max(0, (int) $periodo->dias_direito - (int) $periodo->dias_gozados);
        $diasParaLimite = (int) round($hoje->diffInDays($limite, false));
        $foraDoPrazo = 0;
        $dobro = 0;

        if ($saldo === 0) {
            $fimDoGozo = $periodo->data_fim_gozo?->copy()->startOfDay();

            if ($fimDoGozo && $fimDoGozo->gt($limite)) {
                $situacao = SituacaoConcessaoFerias::QuitadaForaDoPrazo;
                $foraDoPrazo = min((int) $periodo->dias_direito, (int) round($limite->diffInDays($fimDoGozo)));
            } else {
                $situacao = SituacaoConcessaoFerias::Quitada;
            }
        } elseif ($hoje->lte($periodo->periodo_aquisitivo_fim)) {
            $situacao = SituacaoConcessaoFerias::EmAquisicao;
        } elseif ($diasParaLimite < 0) {
            $situacao = SituacaoConcessaoFerias::Vencida;
            $dobro = $saldo;
        } elseif ($diasParaLimite <= self::DIAS_ALERTA) {
            $situacao = SituacaoConcessaoFerias::AVencer;
        } else {
            $situacao = SituacaoConcessaoFerias::AConceder;
        }

        return [
            'situacao' => $situacao,
            'limite' => $limite,
            'dias_para_limite' => $diasParaLimite,
            'saldo' => $saldo,
            'dias_fora_do_prazo' => $foraDoPrazo,
            'dobro_dias' => $dobro,
        ];
    }

    /**
     * Períodos aquisitivos que já deveriam existir pela data de admissão e não têm registro. É o risco silencioso:
     * ninguém cadastrou o período, então nenhum alerta dispararia.
     *
     * @return list<array{inicio: CarbonInterface, fim: CarbonInterface, limite: CarbonInterface}>
     */
    public function periodosSemRegistro(Funcionario $funcionario, ?CarbonInterface $hoje = null): array
    {
        $hoje = ($hoje ?? today())->copy()->startOfDay();
        $funcionario->loadMissing('periodosFerias');
        $admissao = $funcionario->data_admissao->copy()->startOfDay();
        $esperados = [];

        for ($n = 0; $n < 60; $n++) {
            $inicio = $admissao->copy()->addYearsNoOverflow($n);
            $fim = $inicio->copy()->addYearNoOverflow()->subDay();

            if ($fim->gte($hoje)) {
                break; // o período aquisitivo ainda não se completou
            }

            $limite = $fim->copy()->addYearNoOverflow();

            if ($limite->lt($hoje->copy()->subDays(self::JANELA_SEM_REGISTRO_DIAS))) {
                continue; // histórico antigo: não há como saber se foi gozado
            }

            $registrado = $funcionario->periodosFerias->contains(
                fn (PeriodoFerias $p): bool => $p->periodo_aquisitivo_inicio->lte($fim) && $p->periodo_aquisitivo_fim->gte($inicio),
            );

            if (! $registrado) {
                $esperados[] = ['inicio' => $inicio, 'fim' => $fim, 'limite' => $limite];
            }
        }

        return $esperados;
    }

    /**
     * Linhas do painel de férias: um item por período (registrado ou esperado) dos empregados CLT ativos, do mais grave
     * para o menos grave.
     *
     * @return Collection<int, array{funcionario: Funcionario, periodo: ?PeriodoFerias, inicio: CarbonInterface, fim: CarbonInterface, situacao: SituacaoConcessaoFerias, limite: CarbonInterface, dias_para_limite: int, saldo: int, dias_fora_do_prazo: int, dobro_dias: int}>
     */
    public function painel(?CarbonInterface $hoje = null): Collection
    {
        $hoje = ($hoje ?? today())->copy()->startOfDay();
        $linhas = collect();

        $funcionarios = Funcionario::query()
            ->with(['pessoa', 'periodosFerias'])
            ->where('regime', RegimeContratacao::CLT->value)
            ->whereNull('data_desligamento')
            ->get();

        foreach ($funcionarios as $funcionario) {
            foreach ($funcionario->periodosFerias as $periodo) {
                $linhas->push([
                    'funcionario' => $funcionario,
                    'periodo' => $periodo,
                    'inicio' => $periodo->periodo_aquisitivo_inicio,
                    'fim' => $periodo->periodo_aquisitivo_fim,
                ] + $this->avaliar($periodo, $hoje));
            }

            foreach ($this->periodosSemRegistro($funcionario, $hoje) as $esperado) {
                $linhas->push([
                    'funcionario' => $funcionario,
                    'periodo' => null,
                    'inicio' => $esperado['inicio'],
                    'fim' => $esperado['fim'],
                    'situacao' => SituacaoConcessaoFerias::SemRegistro,
                    'limite' => $esperado['limite'],
                    'dias_para_limite' => (int) round($hoje->diffInDays($esperado['limite'], false)),
                    'saldo' => 30,
                    'dias_fora_do_prazo' => 0,
                    'dobro_dias' => 0,
                ]);
            }
        }

        return $linhas
            ->sortBy([
                fn (array $a, array $b): int => $b['situacao']->gravidade() <=> $a['situacao']->gravidade(),
                fn (array $a, array $b): int => $a['dias_para_limite'] <=> $b['dias_para_limite'],
            ])
            ->values();
    }

    /**
     * Quantidade de linhas por situação, só das que exigem atenção.
     *
     * @return array<string, int>
     */
    public function resumo(?CarbonInterface $hoje = null): array
    {
        $contagem = $this->painel($hoje)->countBy(fn (array $l): string => $l['situacao']->value);

        return [
            SituacaoConcessaoFerias::Vencida->value => (int) ($contagem[SituacaoConcessaoFerias::Vencida->value] ?? 0),
            SituacaoConcessaoFerias::AVencer->value => (int) ($contagem[SituacaoConcessaoFerias::AVencer->value] ?? 0),
            SituacaoConcessaoFerias::SemRegistro->value => (int) ($contagem[SituacaoConcessaoFerias::SemRegistro->value] ?? 0),
        ];
    }
}

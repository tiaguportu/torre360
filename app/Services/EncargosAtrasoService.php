<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusFatura;
use App\Models\Contrato;
use App\Models\Fatura;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Multa e juros de mora das mensalidades em atraso (Onda 62).
 *
 * - **Multa:** uma vez, sobre o saldo devedor da fatura, a partir do primeiro dia de atraso. Nunca passa de 2%: é o teto
 *   do CDC para a multa de mora (art. 52, § 1º, na redação da Lei nº 9.298/1996), mesmo que o contrato diga mais.
 * - **Juros:** simples, proporcionais aos dias de atraso (mês de 30 dias), sobre o mesmo saldo.
 * - **Vencimento que cai em fim de semana ou feriado** (lista em `config/financeiro.php`) vale no próximo dia útil: o
 *   atraso só começa depois dele.
 * - **Carência:** dias em que ainda não se cobra nada (padrão 0).
 *
 * Os percentuais vêm do contrato (o que a família assinou) e, se o contrato não define, do padrão da escola. Este
 * serviço calcula o que a escola pode cobrar e mostrar à família; o boleto emitido pelo banco ou pelo gateway precisa
 * ser configurado com os mesmos percentuais.
 */
class EncargosAtrasoService
{
    /** Teto legal da multa de mora (CDC, art. 52, § 1º). */
    public const MULTA_MAXIMA_LEGAL = 2.0;

    /** Limite só para barrar erro de digitação nos juros; o contrato e o jurídico definem o valor. */
    public const JUROS_MENSAL_MAXIMO_CONFIGURAVEL = 12.0;

    /**
     * @return array{multa_percentual: float, juros_mensal_percentual: float, carencia_dias: int}
     */
    public function parametros(?Contrato $contrato = null): array
    {
        $padrao = (array) config('financeiro.encargos', []);

        $multa = $contrato?->multa_percentual ?? ($padrao['multa_percentual'] ?? self::MULTA_MAXIMA_LEGAL);
        $juros = $contrato?->juros_mensal_percentual ?? ($padrao['juros_mensal_percentual'] ?? 1.0);

        return [
            'multa_percentual' => min(self::MULTA_MAXIMA_LEGAL, max(0.0, (float) $multa)),
            'juros_mensal_percentual' => min(self::JUROS_MENSAL_MAXIMO_CONFIGURAVEL, max(0.0, (float) $juros)),
            'carencia_dias' => max(0, (int) ($padrao['carencia_dias'] ?? 0)),
        ];
    }

    /**
     * Primeiro dia útil a partir do vencimento (o próprio dia, se for útil). Fim de semana e feriados configurados
     * adiam o vencimento.
     */
    public function vencimentoEfetivo(CarbonInterface $vencimento): CarbonImmutable
    {
        $feriados = array_flip(array_map(fn (mixed $data): string => (string) $data, (array) config('financeiro.encargos.feriados', [])));
        $dia = CarbonImmutable::parse($vencimento)->startOfDay();

        for ($i = 0; $i < 31 && ($dia->isWeekend() || isset($feriados[$dia->toDateString()])); $i++) {
            $dia = $dia->addDay();
        }

        return $dia;
    }

    /**
     * Encargos de uma fatura numa data (hoje, se não informada).
     *
     * @return array{fatura_id: int, status: StatusFatura, vencimento: CarbonImmutable, vencimento_efetivo: CarbonImmutable, dias_atraso: int, em_atraso: bool, cobra_encargos: bool, principal: float, multa: float, juros: float, total: float, multa_percentual: float, juros_mensal_percentual: float}
     */
    public function calcular(Fatura $fatura, ?CarbonInterface $em = null): array
    {
        $fatura->loadMissing(['itens', 'transacoes', 'contrato']);

        $em = CarbonImmutable::parse($em ?? today())->startOfDay();
        $parametros = $this->parametros($fatura->contrato);
        $vencimento = CarbonImmutable::parse($fatura->vencimento)->startOfDay();
        $efetivo = $this->vencimentoEfetivo($vencimento);

        $aberta = ! in_array($fatura->status, [StatusFatura::Pago, StatusFatura::Cancelado], true);
        $principal = $aberta ? max(0.0, round((float) $fatura->valor_restante, 2)) : 0.0;
        $dias = $principal > 0 ? max(0, (int) round($efetivo->diffInDays($em, false))) : 0;
        $cobra = $dias > $parametros['carencia_dias'];

        $multa = $cobra ? round($principal * $parametros['multa_percentual'] / 100, 2) : 0.0;
        $juros = $cobra ? round($principal * ($parametros['juros_mensal_percentual'] / 100) * ($dias / 30), 2) : 0.0;

        return [
            'fatura_id' => (int) $fatura->id,
            'status' => $fatura->status,
            'vencimento' => $vencimento,
            'vencimento_efetivo' => $efetivo,
            'dias_atraso' => $dias,
            'em_atraso' => $dias > 0,
            'cobra_encargos' => $cobra,
            'principal' => $principal,
            'multa' => $multa,
            'juros' => $juros,
            'total' => round($principal + $multa + $juros, 2),
            'multa_percentual' => $parametros['multa_percentual'],
            'juros_mensal_percentual' => $parametros['juros_mensal_percentual'],
        ];
    }

    /**
     * Linhas do demonstrativo de um contrato: as faturas em aberto com os encargos de cada uma. Por padrão só as que
     * estão em atraso.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function linhasDoContrato(Contrato $contrato, ?CarbonInterface $em = null, bool $apenasEmAtraso = true): Collection
    {
        return $contrato->faturas()
            ->whereNotIn('status', [StatusFatura::Pago->value, StatusFatura::Cancelado->value])
            ->with(['itens', 'transacoes', 'contrato'])
            ->orderBy('vencimento')
            ->get()
            ->map(fn (Fatura $fatura): array => $this->calcular($fatura, $em) + ['descricao' => $this->descricao($fatura)])
            ->when($apenasEmAtraso, fn (Collection $linhas): Collection => $linhas->filter(fn (array $linha): bool => $linha['em_atraso']))
            ->values();
    }

    /**
     * Somas de um conjunto de linhas de {@see calcular()}.
     *
     * @param  iterable<int, array<string, mixed>>  $linhas
     * @return array{faturas: int, principal: float, multa: float, juros: float, encargos: float, total: float}
     */
    public function totais(iterable $linhas): array
    {
        $linhas = collect($linhas);
        $principal = round((float) $linhas->sum('principal'), 2);
        $multa = round((float) $linhas->sum('multa'), 2);
        $juros = round((float) $linhas->sum('juros'), 2);

        return [
            'faturas' => $linhas->count(),
            'principal' => $principal,
            'multa' => $multa,
            'juros' => $juros,
            'encargos' => round($multa + $juros, 2),
            'total' => round($principal + $multa + $juros, 2),
        ];
    }

    public function descricao(Fatura $fatura): string
    {
        $fatura->loadMissing('itens');

        return $fatura->itens->pluck('descricao')->filter()->implode(' + ') ?: "Fatura #{$fatura->id}";
    }
}

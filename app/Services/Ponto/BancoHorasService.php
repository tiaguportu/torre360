<?php

declare(strict_types=1);

namespace App\Services\Ponto;

use App\Enums\OrigemLancamentoBancoHoras;
use App\Exceptions\PontoException;
use App\Models\Funcionario;
use App\Models\PontoAcordoBancoHoras;
use App\Models\PontoBancoHorasLancamento;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Banco de horas como razão de lançamentos (créditos e débitos em minutos, só inserção). O saldo é a soma das linhas.
 *
 * Regras (CLT, art. 59):
 * - Só entra no banco o saldo de dias cobertos por um acordo vigente; sem acordo, a hora excedente é hora extra a pagar.
 * - Cada crédito tem prazo de compensação conforme o tipo do acordo (6 meses individual, 12 coletivo, fim do mês).
 * - Débitos abatem primeiro o crédito que vence antes. O que vence sem ser compensado vira "horas a pagar" (§ 3º).
 * - Só dias "ok" são lançados. Falta e dia incompleto dependem de tratamento (abono, inclusão) ou de decisão do RH.
 */
class BancoHorasService
{
    public function __construct(
        private readonly PontoApuracaoService $apuracao,
    ) {}

    public function acordoVigente(Funcionario $funcionario, CarbonInterface $data): ?PontoAcordoBancoHoras
    {
        return PontoAcordoBancoHoras::query()
            ->where('funcionario_id', $funcionario->id)
            ->whereDate('vigencia_inicio', '<=', $data->toDateString())
            ->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>=', $data->toDateString()))
            ->orderByDesc('vigencia_inicio')
            ->first();
    }

    /**
     * Apura o dia e lança no banco a diferença entre o saldo atual do dia e o que já foi lançado antes (um ajuste aprovado
     * depois gera um lançamento corretivo, nunca altera o anterior). Idempotente.
     */
    public function lancarApuracao(Funcionario $funcionario, CarbonInterface $dia): ?PontoBancoHorasLancamento
    {
        $dia = $dia->copy()->startOfDay();

        return DB::transaction(function () use ($funcionario, $dia): ?PontoBancoHorasLancamento {
            // Serializa o lançamento por funcionário: duas execuções simultâneas não duplicam a diferença.
            Funcionario::query()->whereKey($funcionario->id)->lockForUpdate()->first();

            $apuracao = $this->apuracao->apurarDia($funcionario, $dia);

            if ($apuracao['situacao'] !== 'ok') {
                return null;
            }

            $acordo = $this->acordoVigente($funcionario, $dia);

            if (! $acordo) {
                return null;
            }

            $jaLancado = (int) PontoBancoHorasLancamento::query()
                ->where('funcionario_id', $funcionario->id)
                ->whereDate('data', $dia->toDateString())
                ->where('origem', OrigemLancamentoBancoHoras::ApuracaoDia->value)
                ->sum('minutos');

            $diferenca = (int) $apuracao['saldo'] - $jaLancado;

            if ($diferenca === 0) {
                return null;
            }

            return PontoBancoHorasLancamento::create([
                'funcionario_id' => $funcionario->id,
                'data' => $dia->toDateString(),
                'minutos' => $diferenca,
                'origem' => OrigemLancamentoBancoHoras::ApuracaoDia->value,
                'vence_em' => $diferenca > 0 ? $acordo->vencimentoDoCredito($dia) : null,
                'observacao' => 'Apuração de '.$dia->format('d/m/Y'),
            ]);
        });
    }

    /**
     * Folga que consome crédito do banco.
     *
     * @throws PontoException
     */
    public function compensar(Funcionario $funcionario, CarbonInterface $data, int $minutos, User $por, ?string $observacao = null): PontoBancoHorasLancamento
    {
        if ($minutos <= 0) {
            throw new PontoException('Informe quantos minutos serão compensados.');
        }

        if (! $this->acordoVigente($funcionario, $data)) {
            throw new PontoException('Não há acordo de banco de horas vigente para esta data.');
        }

        return DB::transaction(function () use ($funcionario, $data, $minutos, $por, $observacao): PontoBancoHorasLancamento {
            Funcionario::query()->whereKey($funcionario->id)->lockForUpdate()->first();

            if ($this->posicao($funcionario, $data)['utilizavel'] < $minutos) {
                throw new PontoException('Saldo insuficiente no banco de horas para esta compensação.');
            }

            return PontoBancoHorasLancamento::create([
                'funcionario_id' => $funcionario->id,
                'data' => $data->toDateString(),
                'minutos' => -$minutos,
                'origem' => OrigemLancamentoBancoHoras::Compensacao->value,
                'observacao' => $observacao,
                'criado_por_user_id' => $por->id,
            ]);
        });
    }

    /**
     * Correção manual (com sinal). Exige motivo e fica registrada com quem fez.
     *
     * @throws PontoException
     */
    public function ajustarManualmente(Funcionario $funcionario, CarbonInterface $data, int $minutos, string $motivo, User $por): PontoBancoHorasLancamento
    {
        if ($minutos === 0) {
            throw new PontoException('Informe os minutos do ajuste (positivo ou negativo).');
        }

        if (mb_strlen(trim($motivo)) < 10) {
            throw new PontoException('Explique o motivo do ajuste (pelo menos 10 caracteres).');
        }

        $vencimento = null;

        if ($minutos > 0) {
            $acordo = $this->acordoVigente($funcionario, $data);

            if (! $acordo) {
                throw new PontoException('Crédito no banco de horas exige acordo vigente para a data do ajuste.');
            }

            $vencimento = $acordo->vencimentoDoCredito($data);
        }

        return PontoBancoHorasLancamento::create([
            'funcionario_id' => $funcionario->id,
            'data' => $data->toDateString(),
            'minutos' => $minutos,
            'origem' => OrigemLancamentoBancoHoras::AjusteManual->value,
            'vence_em' => $vencimento,
            'observacao' => trim($motivo),
            'criado_por_user_id' => $por->id,
        ]);
    }

    public function saldo(Funcionario $funcionario, ?CarbonInterface $ate = null): int
    {
        return (int) PontoBancoHorasLancamento::query()
            ->where('funcionario_id', $funcionario->id)
            ->when($ate, fn ($q) => $q->whereDate('data', '<=', $ate->toDateString()))
            ->sum('minutos');
    }

    /**
     * Reconstrói, a partir do razão, quanto resta de cada crédito e se há saldo devedor.
     *
     * Os lançamentos são repassados em ordem cronológica. Crédito que chega com saldo devedor primeiro o quita. Débito
     * comum abate os créditos ainda válidos na data dele, do que vence antes para o que vence depois; débito de expiração
     * zera exatamente o crédito a que se refere.
     *
     * @return array{creditos: list<array{lancamento: PontoBancoHorasLancamento, restante: int}>, devedor: int, utilizavel: int}
     */
    public function posicao(Funcionario $funcionario, ?CarbonInterface $ate = null): array
    {
        $lancamentos = PontoBancoHorasLancamento::query()
            ->where('funcionario_id', $funcionario->id)
            ->when($ate, fn ($q) => $q->whereDate('data', '<=', $ate->toDateString()))
            ->orderBy('data')
            ->orderBy('id')
            ->get();

        /** @var array<int, array{lancamento: PontoBancoHorasLancamento, restante: int}> $creditos */
        $creditos = [];
        $devedor = 0;

        foreach ($lancamentos as $lancamento) {
            if ($lancamento->minutos > 0) {
                $quitado = min($devedor, $lancamento->minutos);
                $devedor -= $quitado;
                $creditos[$lancamento->id] = ['lancamento' => $lancamento, 'restante' => $lancamento->minutos - $quitado];

                continue;
            }

            $debito = -$lancamento->minutos;

            if ($lancamento->origem === OrigemLancamentoBancoHoras::Expiracao && $lancamento->credito_origem_id !== null) {
                if (isset($creditos[$lancamento->credito_origem_id])) {
                    $creditos[$lancamento->credito_origem_id]['restante'] -= min($debito, $creditos[$lancamento->credito_origem_id]['restante']);
                }

                continue;
            }

            $data = $lancamento->data->toDateString();
            $candidatos = collect($creditos)
                ->filter(fn (array $c): bool => $c['restante'] > 0 && ($c['lancamento']->vence_em === null || $c['lancamento']->vence_em->toDateString() >= $data))
                ->sortBy(fn (array $c): string => ($c['lancamento']->vence_em?->toDateString() ?? '9999-12-31').sprintf('%012d', $c['lancamento']->id));

            foreach ($candidatos as $id => $candidato) {
                $retira = min($debito, $creditos[$id]['restante']);
                $creditos[$id]['restante'] -= $retira;
                $debito -= $retira;

                if ($debito === 0) {
                    break;
                }
            }

            $devedor += $debito;
        }

        $hoje = ($ate ?? today())->toDateString();
        $utilizavel = collect($creditos)
            ->filter(fn (array $c): bool => $c['lancamento']->vence_em === null || $c['lancamento']->vence_em->toDateString() >= $hoje)
            ->sum('restante');

        return [
            'creditos' => array_values(array_filter($creditos, fn (array $c): bool => $c['restante'] > 0)),
            'devedor' => $devedor,
            'utilizavel' => max(0, (int) $utilizavel - $devedor),
        ];
    }

    /**
     * Converte em "horas a pagar" os créditos cujo prazo de compensação já passou. Idempotente: depois de expirado, o
     * crédito não tem mais restante. O lançamento leva a data seguinte ao vencimento.
     *
     * @return int quantidade de créditos expirados
     */
    public function expirarVencidos(?CarbonInterface $hoje = null): int
    {
        $hoje ??= today();
        $expirados = 0;

        $ids = PontoBancoHorasLancamento::query()
            ->where('minutos', '>', 0)
            ->whereDate('vence_em', '<', $hoje->toDateString())
            ->distinct()
            ->pluck('funcionario_id');

        foreach (Funcionario::query()->whereIn('id', $ids)->get() as $funcionario) {
            DB::transaction(function () use ($funcionario, $hoje, &$expirados): void {
                Funcionario::query()->whereKey($funcionario->id)->lockForUpdate()->first();

                foreach ($this->posicao($funcionario, $hoje)['creditos'] as $credito) {
                    $vencimento = $credito['lancamento']->vence_em;

                    if ($vencimento === null || $vencimento->toDateString() >= $hoje->toDateString()) {
                        continue;
                    }

                    PontoBancoHorasLancamento::create([
                        'funcionario_id' => $funcionario->id,
                        'data' => $vencimento->copy()->addDay()->toDateString(),
                        'minutos' => -$credito['restante'],
                        'origem' => OrigemLancamentoBancoHoras::Expiracao->value,
                        'credito_origem_id' => $credito['lancamento']->id,
                        'observacao' => 'Prazo de compensação vencido em '.$vencimento->format('d/m/Y').': horas a pagar como extras (CLT, art. 59, § 3º).',
                    ]);

                    $expirados++;
                }
            });
        }

        return $expirados;
    }

    /**
     * Minutos expirados no período, que o RH precisa pagar como hora extra.
     */
    public function horasAPagar(Funcionario $funcionario, CarbonInterface $de, CarbonInterface $ate): int
    {
        return -(int) PontoBancoHorasLancamento::query()
            ->where('funcionario_id', $funcionario->id)
            ->where('origem', OrigemLancamentoBancoHoras::Expiracao->value)
            ->whereDate('data', '>=', $de->toDateString())
            ->whereDate('data', '<=', $ate->toDateString())
            ->sum('minutos');
    }
}

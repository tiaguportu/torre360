<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoProcessoFuncionario;
use App\Models\ContratoTrabalho;
use App\Models\Funcionario;
use App\Models\PeriodoFerias;
use App\Models\PontoRegistro;
use App\Models\ProcessoFuncionario;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Concentra as regras de vínculo empregatício e contratos de trabalho de funcionários (Invariantes I1-I6).
 */
class VinculoFuncionarioService
{
    /**
     * Cria a primeira linha de contrato de trabalho com início na data de admissão.
     *
     * @throws \DomainException
     */
    public function abrirContratoInicial(
        Funcionario $funcionario,
        string|int|float $salario,
        ?User $por = null,
    ): ContratoTrabalho {
        $salarioValor = $this->converterSalario($salario);

        if ($salarioValor <= 0) {
            throw new \DomainException('O salário inicial deve ser maior que zero.');
        }

        return DB::transaction(function () use ($funcionario, $salarioValor, $por): ContratoTrabalho {
            $funcionario = Funcionario::query()->lockForUpdate()->findOrFail($funcionario->id);

            if (! $funcionario->isAtivo()) {
                throw new \DomainException('Não é possível abrir contrato inicial para funcionário desligado.');
            }

            if ($funcionario->contratosTrabalho()->exists()) {
                throw new \DomainException('Este funcionário já possui contrato de trabalho cadastrado.');
            }

            $contrato = $funcionario->contratosTrabalho()->create([
                'vigencia_inicio' => $funcionario->data_admissao,
                'vigencia_fim' => null,
                'salario' => number_format($salarioValor, 2, '.', ''),
            ]);

            activity('rh')
                ->performedOn($contrato)
                ->causedBy($por)
                ->withProperties([
                    'funcionario_id' => $funcionario->id,
                    'salario' => number_format($salarioValor, 2, '.', ''),
                    'vigencia_inicio' => $funcionario->data_admissao?->toDateString(),
                ])
                ->log('Contrato inicial registrado.');

            return $contrato;
        });
    }

    /**
     * Encerra vigências abertas ou posteriores à data do desligamento.
     *
     * @return array{encerrados: int, avisos: list<string>}
     */
    public function encerrarContratos(Funcionario $funcionario, CarbonInterface $data): array
    {
        return DB::transaction(function () use ($funcionario, $data): array {
            $dataInicioDia = Carbon::instance($data)->startOfDay();
            $dataStr = $dataInicioDia->toDateString();

            $contratos = $funcionario->contratosTrabalho()->lockForUpdate()->get();

            $encerrados = 0;
            $avisos = [];
            $posteriores = 0;

            foreach ($contratos as $contrato) {
                $inicio = $contrato->vigencia_inicio?->startOfDay();
                $fim = $contrato->vigencia_fim?->startOfDay();

                if ($inicio && $inicio->greaterThan($dataInicioDia)) {
                    $posteriores++;

                    continue;
                }

                if ($fim === null || $fim->greaterThan($dataInicioDia)) {
                    $contrato->update(['vigencia_fim' => $dataStr]);
                    $encerrados++;
                }
            }

            if ($posteriores > 0) {
                $avisos[] = "Há {$posteriores} vigência(s) de contrato com início depois do desligamento: revise.";
            }

            return [
                'encerrados' => $encerrados,
                'avisos' => $avisos,
            ];
        });
    }

    /**
     * Corrige a data de admissão e alinha a primeira vigência de contrato com auditoria no Activitylog.
     *
     * @return array{avisos: list<string>}
     *
     * @throws \DomainException
     */
    public function corrigirDataAdmissao(
        Funcionario $funcionario,
        CarbonInterface|string $nova,
        string $justificativa,
        User $por,
    ): array {
        $justificativaLimpa = trim($justificativa);

        if (mb_strlen($justificativaLimpa) < 10) {
            throw new \DomainException('Informe uma justificativa com pelo menos 10 caracteres.');
        }

        $novaData = Carbon::parse($nova)->startOfDay();

        return DB::transaction(function () use ($funcionario, $novaData, $justificativaLimpa, $por): array {
            $funcionario = Funcionario::query()->lockForUpdate()->findOrFail($funcionario->id);

            if (! $funcionario->isAtivo()) {
                throw new \DomainException('Não é possível corrigir a data de admissão de um funcionário desligado.');
            }

            if ($funcionario->data_admissao && $novaData->isSameDay($funcionario->data_admissao)) {
                throw new \DomainException('A nova data de admissão é igual à data atual.');
            }

            $temAdmissaoEmAndamento = ProcessoFuncionario::query()
                ->where('funcionario_id', $funcionario->id)
                ->where('tipo', TipoProcessoFuncionario::Admissao)
                ->emAndamento()
                ->exists();

            if ($temAdmissaoEmAndamento) {
                throw new \DomainException('Há um processo de admissão em andamento para este funcionário. Cancele ou conclua a admissão antes de alterar a data.');
            }

            $contratos = $funcionario->contratosTrabalho()
                ->reorder()
                ->orderBy('vigencia_inicio', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $primeiraVigencia = $contratos->first();

            if ($primeiraVigencia) {
                if ($primeiraVigencia->vigencia_fim !== null && $novaData->greaterThan($primeiraVigencia->vigencia_fim->startOfDay())) {
                    throw new \DomainException("A nova data de admissão não pode ser posterior ao término da primeira vigência ({$primeiraVigencia->vigencia_fim->format('d/m/Y')}).");
                }

                if ($contratos->count() > 1) {
                    $segundaVigencia = $contratos->get(1);
                    if ($segundaVigencia->vigencia_inicio !== null && $novaData->greaterThanOrEqualTo($segundaVigencia->vigencia_inicio->startOfDay())) {
                        throw new \DomainException("A nova data de admissão não pode ser posterior ou igual ao início da vigência seguinte ({$segundaVigencia->vigencia_inicio->format('d/m/Y')}).");
                    }
                }
            }

            $dataAnterior = $funcionario->data_admissao;
            $funcionario->update(['data_admissao' => $novaData->toDateString()]);

            if ($primeiraVigencia) {
                $primeiraVigencia->update(['vigencia_inicio' => $novaData->toDateString()]);
            }

            activity('rh')
                ->performedOn($funcionario)
                ->causedBy($por)
                ->withProperties([
                    'data_anterior' => $dataAnterior?->toDateString(),
                    'data_nova' => $novaData->toDateString(),
                    'justificativa' => $justificativaLimpa,
                ])
                ->log('Data de admissão corrigida.');

            $avisos = [];

            $temFeriasAnteriores = PeriodoFerias::query()
                ->where('funcionario_id', $funcionario->id)
                ->where('periodo_aquisitivo_inicio', '<', $novaData->toDateString())
                ->exists();

            if ($temFeriasAnteriores) {
                $avisos[] = 'Há períodos de férias cadastrados com início anterior à nova data de admissão: revise o histórico de férias.';
            }

            $temPontoAnterior = PontoRegistro::query()
                ->where('funcionario_id', $funcionario->id)
                ->whereDate('momento', '<', $novaData->toDateString())
                ->exists();

            if ($temPontoAnterior) {
                $avisos[] = 'Há registros de ponto eletrônico com data anterior à nova data de admissão: revise o espelho de ponto.';
            }

            return [
                'avisos' => $avisos,
            ];
        });
    }

    /**
     * Remove a vigência mais recente de funcionário ativo e reabre a vigência imediatamente anterior (se houver).
     *
     * @throws \DomainException
     */
    public function removerUltimaVigencia(ContratoTrabalho $contrato): void
    {
        DB::transaction(function () use ($contrato): void {
            $contrato = ContratoTrabalho::query()->lockForUpdate()->findOrFail($contrato->id);
            $funcionario = Funcionario::query()->lockForUpdate()->findOrFail($contrato->funcionario_id);

            if (! $funcionario->isAtivo()) {
                throw new \DomainException('Não é possível remover contrato de funcionário desligado.');
            }

            $maisRecente = $funcionario->contratosTrabalho()
                ->reorder()
                ->orderByDesc('vigencia_inicio')
                ->orderByDesc('id')
                ->first();

            if ($maisRecente?->id !== $contrato->id) {
                throw new \DomainException('Apenas a vigência contratual mais recente pode ser removida.');
            }

            $anterior = $funcionario->contratosTrabalho()
                ->where('id', '!=', $contrato->id)
                ->reorder()
                ->orderByDesc('vigencia_inicio')
                ->orderByDesc('id')
                ->first();

            if ($anterior) {
                $anterior->update(['vigencia_fim' => null]);
            }

            $contrato->delete();
        });
    }

    /**
     * Converte valor monetário ou numérico para float seguro.
     */
    private function converterSalario(string|int|float $salario): float
    {
        if (is_numeric($salario)) {
            return (float) $salario;
        }

        $limpo = trim(str_replace(['R$', ' '], '', (string) $salario));

        if (str_contains($limpo, ',') && str_contains($limpo, '.')) {
            $limpo = str_replace('.', '', $limpo);
            $limpo = str_replace(',', '.', $limpo);
        } elseif (str_contains($limpo, ',')) {
            $limpo = str_replace(',', '.', $limpo);
        }

        return (float) $limpo;
    }
}

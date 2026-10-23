<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CronogramaAula;
use App\Models\DiaNaoLetivo;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Aula que não aconteceu e a sua reposição (Onda 49). A aula não realizada sai da carga letiva; a reposição agendada
 * entra no cronograma como aula marcada como reposição, conta no realizado quando acontece e aparece no calendário da
 * turma como qualquer outra.
 */
class ReposicaoAulaService
{
    /**
     * @throws \DomainException
     */
    public function marcarNaoRealizada(CronogramaAula $aula, string $motivo, ?User $por = null): CronogramaAula
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new \DomainException('Informe o motivo da aula não realizada (falta do professor, evento, clima...).');
        }

        $aula = CronogramaAula::query()->findOrFail($aula->id);

        if ($aula->naoFoiRealizada()) {
            throw new \DomainException('Esta aula já está marcada como não realizada.');
        }

        // Chamada lançada é a prova de que a aula aconteceu: para marcar como não realizada, a chamada precisa sair antes.
        if ($aula->frequencias()->whereNotNull('situacao')->exists()) {
            throw new \DomainException('Esta aula tem chamada lançada, então aconteceu. Corrija a chamada antes de marcá-la como não realizada.');
        }

        $aula->update(['nao_realizada_em' => now(), 'motivo_nao_realizacao' => mb_substr($motivo, 0, 160)]);

        $this->registrarLog($aula, $por, 'Aula marcada como não realizada.', ['motivo' => $motivo]);

        return $aula->refresh();
    }

    /**
     * Volta a aula para "prevista". Só enquanto ela não tem reposição ativa.
     *
     * @throws \DomainException
     */
    public function desfazerNaoRealizada(CronogramaAula $aula, ?User $por = null): CronogramaAula
    {
        $aula = CronogramaAula::query()->findOrFail($aula->id);

        if (! $aula->naoFoiRealizada()) {
            throw new \DomainException('Esta aula não está marcada como não realizada.');
        }

        if ($this->reposicaoAtiva($aula)) {
            throw new \DomainException('Já há uma reposição agendada para esta aula. Cancele a reposição antes.');
        }

        $aula->update(['nao_realizada_em' => null, 'motivo_nao_realizacao' => null]);

        $this->registrarLog($aula, $por, 'Aula voltou a ser prevista.');

        return $aula->refresh();
    }

    /**
     * Agenda a aula de reposição. Mesma turma e disciplina da original; o professor é o da original, salvo se outro for
     * informado.
     *
     * @throws \DomainException
     */
    public function agendarReposicao(CronogramaAula $original, CarbonInterface $data, string $horaInicio, string $horaFim, ?int $professorId = null, ?User $por = null): CronogramaAula
    {
        $original = CronogramaAula::query()->findOrFail($original->id);

        if (! $original->naoFoiRealizada()) {
            throw new \DomainException('Só se repõe aula que não aconteceu: marque a aula como não realizada antes.');
        }

        if ($this->reposicaoAtiva($original)) {
            throw new \DomainException('Esta aula já tem uma reposição agendada.');
        }

        $dia = Carbon::parse($data->toDateString());
        $horaInicio = $this->normalizarHora($horaInicio);
        $horaFim = $this->normalizarHora($horaFim);

        if ($horaFim <= $horaInicio) {
            throw new \DomainException('O horário final precisa ser depois do inicial.');
        }

        if ($dia->lt($original->data)) {
            throw new \DomainException('A reposição não pode ser antes da aula que ela repõe ('.$original->data->format('d/m/Y').').');
        }

        $turma = Turma::query()->findOrFail($original->turma_id);
        $periodo = $turma->periodo_letivo_id ? PeriodoLetivo::query()->find($turma->periodo_letivo_id) : null;

        if ($periodo && ($dia->lt($periodo->data_inicio) || $dia->gt($periodo->data_fim))) {
            throw new \DomainException('A reposição precisa cair dentro do período letivo ('.$periodo->data_inicio->format('d/m/Y').' a '.$periodo->data_fim->format('d/m/Y').').');
        }

        if ($periodo && DiaNaoLetivo::query()->where('periodo_letivo_id', $periodo->id)->where('flag_ativo', true)->whereDate('data', $dia->toDateString())->exists()) {
            throw new \DomainException('Esse dia é não letivo no calendário.');
        }

        $professor = $professorId ?? $original->pessoa_id;

        $this->conferirConflito($turma->id, $professor, $dia, $horaInicio, $horaFim);

        return DB::transaction(function () use ($original, $dia, $horaInicio, $horaFim, $professor, $por): CronogramaAula {
            $reposicao = CronogramaAula::create([
                'turma_id' => $original->turma_id,
                'disciplina_id' => $original->disciplina_id,
                'pessoa_id' => $professor,
                'data' => $dia->toDateString(),
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'reposicao_de_id' => $original->id,
            ]);

            $this->registrarLog($original, $por, 'Reposição de aula agendada.', ['reposicao_id' => $reposicao->id, 'data' => $dia->toDateString()]);

            return $reposicao;
        });
    }

    /**
     * Hora no formato do banco (HH:MM:SS): comparar texto de tamanhos diferentes erra nas aulas coladas (09:00:00 > 09:00).
     */
    private function normalizarHora(string $hora): string
    {
        $hora = trim($hora);

        return strlen($hora) === 5 ? $hora.':00' : $hora;
    }

    public function reposicaoAtiva(CronogramaAula $aula): ?CronogramaAula
    {
        return CronogramaAula::query()
            ->where('reposicao_de_id', $aula->id)
            ->whereNull('nao_realizada_em')
            ->first();
    }

    /**
     * Turma ou professor com outra aula no mesmo horário.
     *
     * @throws \DomainException
     */
    private function conferirConflito(int $turmaId, ?int $professorId, CarbonInterface $dia, string $inicio, string $fim): void
    {
        $sobrepostas = fn () => CronogramaAula::query()
            ->whereDate('data', $dia->toDateString())
            ->whereNull('nao_realizada_em')
            ->where('hora_inicio', '<', $fim)
            ->where('hora_fim', '>', $inicio);

        if ($sobrepostas()->where('turma_id', $turmaId)->exists()) {
            throw new \DomainException('A turma já tem aula nesse horário.');
        }

        if ($professorId && $sobrepostas()->where('pessoa_id', $professorId)->exists()) {
            throw new \DomainException('O professor já tem aula nesse horário.');
        }
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private function registrarLog(CronogramaAula $aula, ?User $por, string $mensagem, array $propriedades = []): void
    {
        $registro = activity('frequencia')
            ->performedOn($aula)
            ->withProperties(['turma_id' => $aula->turma_id] + $propriedades);

        if ($por) {
            $registro->causedBy($por);
        }

        $registro->log($mensagem);
    }
}

<?php

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Exceptions\TurmaIndisponivelException;
use App\Models\Matricula;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Regra única de vagas da turma.
 *
 * Ocupa vaga a matrícula Ativa, Pendente ou Reserva que ainda não foi desativada (mesma regra do
 * Termômetro de Vagas). `vagas_maximas` nulo ou 0 significa turma sem limite.
 */
class TurmaVagasService
{
    /**
     * @var list<SituacaoMatricula>
     */
    public const SITUACOES_QUE_OCUPAM_VAGA = [
        SituacaoMatricula::ATIVA,
        SituacaoMatricula::PENDENTE,
        SituacaoMatricula::RESERVA,
    ];

    /**
     * Matrículas que ocupam vaga numa turma.
     *
     * @return Builder<Matricula>
     */
    public function queryOcupantes(Turma|int $turma): Builder
    {
        return $this->queryOcupantesDe([$turma instanceof Turma ? $turma->getKey() : $turma]);
    }

    /**
     * Matrículas que ocupam vaga em qualquer uma das turmas informadas.
     *
     * @param  list<int>  $turmaIds
     * @return Builder<Matricula>
     */
    public function queryOcupantesDe(array $turmaIds): Builder
    {
        return Matricula::query()
            ->whereIn('turma_id', $turmaIds)
            ->whereIn('situacao', array_map(fn (SituacaoMatricula $situacao) => $situacao->value, self::SITUACOES_QUE_OCUPAM_VAGA))
            ->where(function (Builder $query) {
                $query->whereNull('data_desativacao')->orWhereDate('data_desativacao', '>', now());
            });
    }

    /**
     * Mesma regra de {@see queryOcupantes()} aplicada a uma matrícula já carregada (sem consultar o banco).
     */
    public function ocupaVaga(Matricula $matricula): bool
    {
        if (! in_array($matricula->situacao, self::SITUACOES_QUE_OCUPAM_VAGA, true)) {
            return false;
        }

        return $matricula->data_desativacao === null
            || $matricula->data_desativacao->toDateString() > now()->toDateString();
    }

    public function ocupadas(Turma|int $turma): int
    {
        return $this->queryOcupantes($turma)->count();
    }

    /**
     * Limite de vagas da turma, ou null quando não há limite.
     */
    public function limite(Turma $turma): ?int
    {
        $vagas = (int) $turma->vagas_maximas;

        return $vagas > 0 ? $vagas : null;
    }

    /**
     * Vagas livres (null = sem limite).
     */
    public function disponiveis(Turma $turma): ?int
    {
        $limite = $this->limite($turma);

        return $limite === null ? null : max(0, $limite - $this->ocupadas($turma));
    }

    public function estaLotada(Turma $turma): bool
    {
        return $this->disponiveis($turma) === 0;
    }

    /**
     * Texto para listas de escolha: "1º Ano A — Manhã (12/30 vagas)" ou "... — LOTADA".
     */
    public function rotulo(Turma $turma, ?string $turno = null): string
    {
        $limite = $this->limite($turma);
        $ocupadas = $this->ocupadas($turma);
        $nome = $turno ? "{$turma->nome} — {$turno}" : (string) $turma->nome;

        if ($limite === null) {
            return "{$nome} ({$ocupadas} matriculados)";
        }

        return $ocupadas >= $limite
            ? "{$nome} ({$ocupadas}/{$limite}) — LOTADA"
            : "{$nome} ({$ocupadas}/{$limite} vagas)";
    }

    /**
     * Garante uma vaga para uma nova matrícula na turma.
     *
     * Deve ser chamada DENTRO de uma transação e antes de criar a matrícula: a linha da turma é
     * travada (`lockForUpdate`) até o fim da transação, então dois pedidos simultâneos para a mesma
     * turma passam um de cada vez e o segundo enxerga a vaga já ocupada. (No SQLite dos testes o
     * lock não tem efeito.)
     *
     * `$quantidade`: quantas matrículas serão criadas de uma vez (ex.: irmãos no mesmo wizard).
     *
     * @throws TurmaIndisponivelException turma fechada para matrículas ou sem vagas suficientes
     */
    public function garantirVaga(Turma|int $turma, int $quantidade = 1): Turma
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('garantirVaga() deve rodar dentro de DB::transaction() para travar a turma.');
        }

        $turmaId = $turma instanceof Turma ? $turma->getKey() : $turma;
        $travada = Turma::query()->lockForUpdate()->findOrFail($turmaId);

        if (! $travada->status->abertaParaMatricula()) {
            throw TurmaIndisponivelException::fechada($travada);
        }

        $limite = $this->limite($travada);

        if ($limite !== null) {
            $disponiveis = max(0, $limite - $this->ocupadas($travada));

            if ($disponiveis < $quantidade) {
                throw $quantidade === 1 || $disponiveis === 0
                    ? TurmaIndisponivelException::lotada($travada, $limite)
                    : TurmaIndisponivelException::semVagasSuficientes($travada, $disponiveis, $quantidade);
            }
        }

        return $travada;
    }
}

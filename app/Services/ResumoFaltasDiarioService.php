<?php

namespace App\Services;

use App\Models\FrequenciaEscolar;
use App\Models\User;
use App\Notifications\FrequenciaResumoDiarioNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Junta as faltas pendentes de aviso (`FrequenciaFaltaObserver`) em um único aviso por responsável.
 *
 * Os pendentes são relidos na hora do envio: falta corrigida para presente, apagada ou justificada depois de lançada não
 * entra no resumo. Como a chave é "pendente" e não "aulas de hoje", a falta de uma aula antiga lançada hoje também sai,
 * com a data da aula. Uma falta de aluno sem nenhum responsável com usuário é descartada (e não fica na fila), para
 * não despejar um resumo antigo em quem for cadastrado meses depois.
 */
class ResumoFaltasDiarioService
{
    private const LOTE_IDS = 500;

    /**
     * @param  bool  $simular  Só monta o resultado: não reserva as faltas nem envia nada.
     * @return array{faltas: int, alunos: int, responsaveis: int, descartadas: int, falhas: int, destinatarios: list<array{user: User, alunos: list<array{nome: string, faltas: list<array<string, mixed>>}>}>}
     */
    public function enviar(bool $simular = false): array
    {
        if ($simular) {
            return $this->resultado($this->montar($this->pendentes()), 0);
        }

        // Reserva antes de enviar: uma segunda rodada simultânea já não encontra o que esta levou.
        $faltas = DB::transaction(function (): Collection {
            $faltas = $this->pendentes(travar: true);
            $this->atualizarMarca($faltas->modelKeys(), pendente: false);

            return $faltas;
        });

        $montagem = $this->montar($faltas);
        $dia = now()->toDateString();
        $enviadas = [];
        $idsComFalha = [];
        $responsaveisComFalha = 0;

        foreach ($montagem['destinatarios'] as $destinatario) {
            $ids = $this->idsDasFaltas($destinatario['alunos']);

            try {
                $destinatario['user']->notify(new FrequenciaResumoDiarioNotification([
                    'dia' => $dia,
                    'alunos' => $destinatario['alunos'],
                ]));
                $enviadas = array_merge($enviadas, $ids);
            } catch (Throwable $e) {
                Log::error("Resumo diário de faltas: falha ao avisar o usuário {$destinatario['user']->id}: {$e->getMessage()}");
                $idsComFalha = array_merge($idsComFalha, $ids);
                $responsaveisComFalha++;
            }
        }

        // Uma falta de dois responsáveis só volta para a fila se nenhum deles foi avisado (senão o outro receberia em dobro).
        $this->atualizarMarca(array_values(array_diff($idsComFalha, $enviadas)), pendente: true);

        return $this->resultado($montagem, $responsaveisComFalha);
    }

    /**
     * @return Collection<int, FrequenciaEscolar>
     */
    public function pendentes(bool $travar = false): Collection
    {
        $consulta = FrequenciaEscolar::query()
            ->where('aviso_resumo_pendente', true)
            ->where('situacao', 'ausente')
            ->where('justificada', false)
            ->with(['matricula.pessoa.responsaveis.users', 'cronogramaAula.disciplina', 'cronogramaAula.professor']);

        if ($travar) {
            $consulta->lockForUpdate();
        }

        return $consulta->get();
    }

    /**
     * Agrupa por usuário responsável, depois por aluno, com as aulas em ordem cronológica.
     *
     * @param  Collection<int, FrequenciaEscolar>  $faltas
     * @return array{destinatarios: list<array{user: User, alunos: list<array{nome: string, faltas: list<array<string, mixed>>}>}>, descartadas: list<int>}
     */
    private function montar(Collection $faltas): array
    {
        $porUsuario = [];
        $descartadas = [];

        foreach ($faltas as $falta) {
            $aluno = $falta->matricula?->pessoa;
            $aula = $falta->cronogramaAula;
            $usuarios = $aluno?->responsaveis->flatMap(fn ($responsavel) => $responsavel->users)->unique('id');

            if (! $aluno || ! $aula || $usuarios === null || $usuarios->isEmpty()) {
                $descartadas[] = $falta->id;

                continue;
            }

            $item = [
                'frequencia_id' => $falta->id,
                'data' => $aula->data?->toDateString(),
                'inicio' => $this->hora($aula->hora_inicio),
                'fim' => $this->hora($aula->hora_fim),
                'disciplina' => $aula->disciplina?->nome ?? 'Aula',
                'professor' => $aula->professor?->nome,
            ];

            foreach ($usuarios as $usuario) {
                $porUsuario[$usuario->id]['user'] = $usuario;
                $porUsuario[$usuario->id]['alunos'][$aluno->id]['nome'] = $aluno->nome ?? 'Aluno';
                $porUsuario[$usuario->id]['alunos'][$aluno->id]['faltas'][] = $item;
            }
        }

        $destinatarios = [];

        foreach ($porUsuario as $linha) {
            $alunos = [];

            foreach ($linha['alunos'] as $aluno) {
                usort($aluno['faltas'], fn (array $a, array $b) => [$a['data'], $a['inicio']] <=> [$b['data'], $b['inicio']]);
                $alunos[] = $aluno;
            }

            usort($alunos, fn (array $a, array $b) => strcmp($a['nome'], $b['nome']));
            $destinatarios[] = ['user' => $linha['user'], 'alunos' => $alunos];
        }

        return ['destinatarios' => $destinatarios, 'descartadas' => $descartadas];
    }

    /**
     * @param  array{destinatarios: list<array{user: User, alunos: list<array{nome: string, faltas: list<array<string, mixed>>}>}>, descartadas: list<int>}  $montagem
     */
    private function resultado(array $montagem, int $falhas): array
    {
        $ids = [];
        $alunos = [];

        foreach ($montagem['destinatarios'] as $destinatario) {
            $ids = array_merge($ids, $this->idsDasFaltas($destinatario['alunos']));

            foreach ($destinatario['alunos'] as $aluno) {
                $alunos[$aluno['nome']] = true;
            }
        }

        return [
            'faltas' => count(array_unique($ids)),
            'alunos' => count($alunos),
            'responsaveis' => count($montagem['destinatarios']),
            'descartadas' => count($montagem['descartadas']),
            'falhas' => $falhas,
            'destinatarios' => $montagem['destinatarios'],
        ];
    }

    /**
     * @param  list<array{nome: string, faltas: list<array<string, mixed>>}>  $alunos
     * @return list<int>
     */
    private function idsDasFaltas(array $alunos): array
    {
        $ids = [];

        foreach ($alunos as $aluno) {
            foreach ($aluno['faltas'] as $falta) {
                $ids[] = $falta['frequencia_id'];
            }
        }

        return $ids;
    }

    /**
     * @param  list<int>  $ids
     */
    private function atualizarMarca(array $ids, bool $pendente): void
    {
        foreach (array_chunk($ids, self::LOTE_IDS) as $lote) {
            FrequenciaEscolar::whereKey($lote)->update([
                'aviso_resumo_pendente' => $pendente,
                'aviso_resumo_enviado_em' => $pendente ? null : now(),
            ]);
        }
    }

    private function hora(mixed $hora): ?string
    {
        return $hora ? substr((string) $hora, 0, 5) : null;
    }
}

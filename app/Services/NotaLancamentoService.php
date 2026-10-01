<?php

namespace App\Services;

use App\Enums\SituacaoNota;
use App\Models\Avaliacao;
use App\Models\Nota;

class NotaLancamentoService
{
    /**
     * Monta o estado inicial da grade de lançamento: uma linha por aluno matriculado na turma da avaliação.
     *
     * @return array<int, array{matricula_id: int, aluno_nome: string, valor: string|null}>
     */
    public function estadoNotasParaGrade(Avaliacao $avaliacao): array
    {
        $turma = $avaliacao->turma;

        if (! $turma) {
            return [];
        }

        $matriculas = $turma->matriculas()
            ->join('pessoa', 'matricula.pessoa_id', '=', 'pessoa.id')
            ->select('matricula.*', 'pessoa.nome as aluno_nome')
            ->orderBy('pessoa.nome')
            ->get();

        $notasExistentes = $avaliacao->notas()->get()->keyBy('matricula_id');

        return $matriculas->map(fn ($matricula) => [
            'matricula_id' => $matricula->id,
            'aluno_nome' => $matricula->aluno_nome ?? 'Sem Nome',
            'valor' => $notasExistentes[$matricula->id]?->valor,
            'situacao' => $notasExistentes[$matricula->id]?->situacao?->value,
        ])->all();
    }

    /**
     * Persiste as notas da grade: cria/atualiza quando há valor ou justificativa (faltou / não se aplica),
     * remove quando ambos foram esvaziados.
     *
     * @param  array<int, array{matricula_id?: int, aluno_nome?: string, valor?: string|null}>  $notasAlunos
     *
     * @throws \InvalidArgumentException quando uma nota excede o valor máximo permitido pela avaliação
     */
    public function salvarNotas(Avaliacao $avaliacao, array $notasAlunos): void
    {
        foreach ($notasAlunos as $item) {
            if (! isset($item['matricula_id'])) {
                continue;
            }

            $situacao = SituacaoNota::tryFrom((string) ($item['situacao'] ?? ''));

            if ($situacao) {
                // Justificativa (faltou / não se aplica) prevalece e descarta qualquer valor digitado.
                Nota::updateOrCreate(
                    [
                        'avaliacao_id' => $avaliacao->id,
                        'matricula_id' => $item['matricula_id'],
                    ],
                    [
                        'valor' => null,
                        'situacao' => $situacao,
                    ]
                );
            } elseif (isset($item['valor']) && $item['valor'] !== '' && $item['valor'] !== null) {
                $valor = (float) str_replace(',', '.', (string) $item['valor']);

                if ($valor > (float) $avaliacao->nota_maxima) {
                    $alunoNome = $item['aluno_nome'] ?? 'aluno';

                    throw new \InvalidArgumentException(
                        "A nota ({$valor}) de {$alunoNome} não pode ser maior que a nota máxima da avaliação ({$avaliacao->nota_maxima})."
                    );
                }

                Nota::updateOrCreate(
                    [
                        'avaliacao_id' => $avaliacao->id,
                        'matricula_id' => $item['matricula_id'],
                    ],
                    [
                        'valor' => $valor,
                        'situacao' => null,
                    ]
                );
            } else {
                Nota::where([
                    'avaliacao_id' => $avaliacao->id,
                    'matricula_id' => $item['matricula_id'],
                ])->delete();
            }
        }
    }
}

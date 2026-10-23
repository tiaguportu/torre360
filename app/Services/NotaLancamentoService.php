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
            'valor' => $notasExistentes->get($matricula->id)?->valor,
            'situacao' => $notasExistentes->get($matricula->id)?->situacao?->value,
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

    /**
     * Retorna estatísticas consolidadas de lançamento para a avaliação informada.
     *
     * @return array{
     *     total_alunos: int,
     *     total_resolvidas: int,
     *     total_pendentes: int,
     *     com_nota: int,
     *     justificadas: int,
     *     taxa_participacao: float,
     *     media: float|null,
     *     maior_nota: float|null,
     *     menor_nota: float|null,
     * }
     */
    public function calcularEstatisticas(Avaliacao $avaliacao): array
    {
        $turma = $avaliacao->turma;
        $totalAlunos = $turma ? $turma->matriculas()->where('situacao', 'ativa')->count() : 0;

        $notas = $avaliacao->notas()->get();

        $notasComValor = $notas->whereNotNull('valor')->pluck('valor')->map(fn ($v) => (float) $v);
        $justificadas = $notas->whereNotNull('situacao')->count();

        $comNota = $notasComValor->count();
        $totalResolvidas = $comNota + $justificadas;
        $totalPendentes = max(0, $totalAlunos - $totalResolvidas);
        $taxaParticipacao = $totalAlunos > 0 ? round(($totalResolvidas / $totalAlunos) * 100, 1) : 0.0;

        $media = $comNota > 0 ? round($notasComValor->avg(), 2) : null;
        $maiorNota = $comNota > 0 ? round($notasComValor->max(), 2) : null;
        $menorNota = $comNota > 0 ? round($notasComValor->min(), 2) : null;

        return [
            'total_alunos' => $totalAlunos,
            'total_resolvidas' => $totalResolvidas,
            'total_pendentes' => $totalPendentes,
            'com_nota' => $comNota,
            'justificadas' => $justificadas,
            'taxa_participacao' => $taxaParticipacao,
            'media' => $media,
            'maior_nota' => $maiorNota,
            'menor_nota' => $menorNota,
        ];
    }

    /**
     * Lê e faz o parsing de um arquivo CSV retornando lista associativa de linhas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lerLinhasCsv(string $caminhoArquivo): array
    {
        if (! file_exists($caminhoArquivo) || ! is_readable($caminhoArquivo)) {
            throw new \InvalidArgumentException('Arquivo CSV não encontrado ou inacessível.');
        }

        $handle = fopen($caminhoArquivo, 'r');
        if (! $handle) {
            throw new \InvalidArgumentException('Falha ao abrir arquivo CSV.');
        }

        // Lê primeira linha para identificar separador (; ou ,)
        $primeiraLinha = fgets($handle);
        if ($primeiraLinha === false) {
            fclose($handle);

            return [];
        }

        // Remove UTF-8 BOM se existir
        $primeiraLinha = preg_replace('/^\xEF\xBB\xBF/', '', $primeiraLinha);

        $delimitador = str_contains($primeiraLinha, ';') ? ';' : ',';

        $cabecalhos = str_getcsv(trim($primeiraLinha), $delimitador);
        $cabecalhosNormalizados = array_map(fn ($c) => strtolower(trim((string) $c)), $cabecalhos);

        $linhas = [];
        while (($dados = fgetcsv($handle, 0, $delimitador)) !== false) {
            if (empty(array_filter($dados, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            $linhaAssociativa = [];
            foreach ($cabecalhosNormalizados as $idx => $campo) {
                $linhaAssociativa[$campo] = $dados[$idx] ?? null;
            }

            $linhas[] = $linhaAssociativa;
        }

        fclose($handle);

        return $linhas;
    }

    /**
     * Processa linhas importadas de um CSV e persiste as notas.
     *
     * @param  array<int, array<string, mixed>>  $linhasCsv
     * @return int Quantidade de registros persistidos
     *
     * @throws \InvalidArgumentException
     */
    public function importarNotasCsv(Avaliacao $avaliacao, array $linhasCsv): int
    {
        $turma = $avaliacao->turma;
        $matriculasValidas = $turma ? $turma->matriculas()->pluck('id')->all() : [];

        $payload = [];

        foreach ($linhasCsv as $linha) {
            $matriculaIdRaw = $linha['matricula_id'] ?? null;
            if ($matriculaIdRaw === null || trim((string) $matriculaIdRaw) === '') {
                continue;
            }

            $matriculaId = (int) $matriculaIdRaw;
            if (! in_array($matriculaId, $matriculasValidas, true)) {
                $aluno = $linha['aluno_nome'] ?? "ID {$matriculaId}";

                throw new \InvalidArgumentException("A matrícula #{$matriculaId} ({$aluno}) não pertence à turma desta avaliação.");
            }

            $situacaoRaw = trim((string) ($linha['situacao'] ?? ''));
            $situacao = null;
            if ($situacaoRaw !== '') {
                $situacao = SituacaoNota::tryFrom(strtolower($situacaoRaw));
                if (! $situacao) {
                    throw new \InvalidArgumentException("A situação \"{$situacaoRaw}\" informada para o aluno é inválida. Valores aceitos: faltou, nao_se_aplica.");
                }
            }

            $valorRaw = $linha['nota'] ?? $linha['valor'] ?? null;
            $valorFormatado = null;

            if ($situacao) {
                $valorFormatado = null;
            } elseif ($valorRaw !== null && trim((string) $valorRaw) !== '') {
                $valorLimpo = str_replace(',', '.', trim((string) $valorRaw));
                if (! is_numeric($valorLimpo)) {
                    $aluno = $linha['aluno_nome'] ?? "Matrícula #{$matriculaId}";

                    throw new \InvalidArgumentException("O valor \"{$valorRaw}\" informado para {$aluno} não é uma nota numérica válida.");
                }

                $valorFloat = (float) $valorLimpo;
                if ($valorFloat < 0) {
                    throw new \InvalidArgumentException("A nota ({$valorFloat}) não pode ser negativa.");
                }

                if ($valorFloat > (float) $avaliacao->nota_maxima) {
                    $aluno = $linha['aluno_nome'] ?? "Matrícula #{$matriculaId}";

                    throw new \InvalidArgumentException("A nota ({$valorFloat}) de {$aluno} excede a nota máxima ({$avaliacao->nota_maxima}) permitida.");
                }

                $valorFormatado = $valorFloat;
            }

            $payload[] = [
                'matricula_id' => $matriculaId,
                'aluno_nome' => $linha['aluno_nome'] ?? null,
                'valor' => $valorFormatado,
                'situacao' => $situacao?->value,
            ];
        }

        $this->salvarNotas($avaliacao, $payload);

        return count($payload);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Educacenso;

use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\Unidade;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lê os arquivos que o Educacenso devolve e grava os códigos do Inep no sistema:
 *
 * - **Exportação da matrícula inicial** (leiaute da 1ª etapa): o registro 20 traz o código Inep da turma, o 30 o ID Inep
 *   da pessoa e o 60 o código da matrícula, sempre ao lado do código próprio que o nosso exportador enviou (turma:
 *   `codigo` ou id; pessoa: id).
 * - **Resultado da identificação** (RESULTADO_CERTO, 9 campos): o código do aluno na escola (nosso id) e o ID Inep.
 *
 * Só são lidas as linhas de escolas cadastradas aqui (pelo código Inep da unidade ou da instituição). Um código já
 * gravado e diferente do recebido NÃO é trocado: vira divergência para a secretaria conferir.
 */
class EducacensoRetornoImporter
{
    /** @var array<string, list<int>> código Inep da escola => ids de unidade */
    private array $unidadesPorInep = [];

    /** @var array<string, ?Turma> */
    private array $turmas = [];

    /**
     * @return array{linhas: int, turmas: int, pessoas: int, matriculas: int, divergencias: list<string>, nao_encontrados: list<string>, escolas_desconhecidas: list<string>}
     */
    public function importarMatriculaInicial(string $conteudo): array
    {
        $linhas = FormatoInep::linhasDoArquivo($conteudo);
        $relevantes = array_values(array_filter($linhas, fn (string $l): bool => in_array(substr($l, 0, 3), ['20|', '30|', '60|'], true)));

        if ($relevantes === []) {
            throw new InvalidArgumentException('O arquivo não tem registros 20, 30 ou 60 da matrícula inicial. Confira se é a exportação do Educacenso.');
        }

        $this->carregarUnidades();
        $resumo = $this->resumoVazio(count($linhas));

        DB::transaction(function () use ($relevantes, &$resumo): void {
            foreach ($relevantes as $linha) {
                $campos = explode('|', $linha);
                $escola = FormatoInep::digitos($campos[1] ?? '');

                if (! isset($this->unidadesPorInep[$escola])) {
                    $resumo['escolas_desconhecidas'][$escola] = $escola;

                    continue;
                }

                match ($campos[0]) {
                    '20' => $this->registro20($escola, $campos, $resumo),
                    '30' => $this->registro30($campos, $resumo),
                    '60' => $this->registro60($escola, $campos, $resumo),
                };
            }
        });

        $resumo['escolas_desconhecidas'] = array_values(array_filter($resumo['escolas_desconhecidas']));
        // O mesmo desencontro aparece nos registros 30 e 60 (ou 20 e 60): conta uma vez só.
        $resumo['divergencias'] = array_values(array_unique($resumo['divergencias']));
        $resumo['nao_encontrados'] = array_values(array_unique($resumo['nao_encontrados']));
        $this->registrar('Importou o retorno da matrícula inicial do Educacenso', $resumo);

        return $resumo;
    }

    /**
     * @return array{linhas: int, turmas: int, pessoas: int, matriculas: int, divergencias: list<string>, nao_encontrados: list<string>, escolas_desconhecidas: list<string>}
     */
    public function importarIdentificacao(string $conteudo): array
    {
        $linhas = FormatoInep::linhasDoArquivo($conteudo);
        $validas = array_values(array_filter($linhas, fn (string $l): bool => count(explode('|', $l)) === 9));

        if ($validas === []) {
            throw new InvalidArgumentException('O arquivo não está no leiaute de identificação (9 campos por linha). Importe o RESULTADO_CERTO devolvido pelo Educacenso.');
        }

        $resumo = $this->resumoVazio(count($linhas));

        DB::transaction(function () use ($validas, &$resumo): void {
            foreach ($validas as $linha) {
                $campos = explode('|', $linha);
                $pessoa = ctype_digit($campos[0]) ? Pessoa::query()->find((int) $campos[0]) : null;

                if (! $pessoa) {
                    $resumo['nao_encontrados'][] = "Aluno de código {$campos[0]} não encontrado.";

                    continue;
                }

                $this->gravarIdInep($pessoa, FormatoInep::digitos($campos[8]), $resumo);
            }
        });

        $this->registrar('Importou o resultado da identificação de alunos do Educacenso', $resumo);

        return $resumo;
    }

    /**
     * @param  list<string>  $campos
     * @param  array<string, mixed>  $resumo
     */
    private function registro20(string $escola, array $campos, array &$resumo): void
    {
        $turma = $this->turma($escola, $campos[2] ?? '');

        if (! $turma) {
            $resumo['nao_encontrados'][] = 'Turma de código '.($campos[2] ?? '').' não encontrada na escola '.$escola.'.';

            return;
        }

        $this->gravarCodigoTurma($turma, FormatoInep::digitos($campos[3] ?? ''), $resumo);
    }

    /**
     * @param  list<string>  $campos
     * @param  array<string, mixed>  $resumo
     */
    private function registro30(array $campos, array &$resumo): void
    {
        $codigo = $campos[2] ?? '';
        $pessoa = ctype_digit($codigo) ? Pessoa::query()->find((int) $codigo) : null;

        if (! $pessoa) {
            $resumo['nao_encontrados'][] = "Pessoa de código {$codigo} não encontrada.";

            return;
        }

        $this->gravarIdInep($pessoa, FormatoInep::digitos($campos[3] ?? ''), $resumo);
    }

    /**
     * @param  list<string>  $campos
     * @param  array<string, mixed>  $resumo
     */
    private function registro60(string $escola, array $campos, array &$resumo): void
    {
        $codigoPessoa = $campos[2] ?? '';
        $turma = $this->turma($escola, $campos[4] ?? '');

        if (! $turma || ! ctype_digit($codigoPessoa)) {
            $resumo['nao_encontrados'][] = "Vínculo do aluno {$codigoPessoa} com a turma ".($campos[4] ?? '').' não encontrado.';

            return;
        }

        $matricula = Matricula::query()->without('turma')->where('pessoa_id', (int) $codigoPessoa)->where('turma_id', $turma->id)->first();

        if (! $matricula) {
            $resumo['nao_encontrados'][] = "Matrícula do aluno {$codigoPessoa} na turma {$turma->nome} não encontrada.";

            return;
        }

        $codigoMatricula = FormatoInep::digitos($campos[6] ?? '');

        if ($codigoMatricula !== '' && strlen($codigoMatricula) <= 12) {
            $atual = (string) ($matricula->codigo_matricula_inep ?? '');

            if ($atual === '') {
                $matricula->codigo_matricula_inep = $codigoMatricula;
                $matricula->saveQuietly();
                $resumo['matriculas']++;
            } elseif ($atual !== $codigoMatricula) {
                $resumo['divergencias'][] = "Matrícula {$matricula->id}: código {$atual} no sistema e {$codigoMatricula} no arquivo.";
            }
        }

        if ($pessoa = Pessoa::query()->find((int) $codigoPessoa)) {
            $this->gravarIdInep($pessoa, FormatoInep::digitos($campos[3] ?? ''), $resumo);
        }

        $this->gravarCodigoTurma($turma, FormatoInep::digitos($campos[5] ?? ''), $resumo);
    }

    /**
     * @param  array<string, mixed>  $resumo
     */
    private function gravarIdInep(Pessoa $pessoa, string $id, array &$resumo): void
    {
        if (strlen($id) !== 12) {
            return;
        }

        $atual = (string) ($pessoa->codigo_inep ?? '');

        if ($atual === '') {
            if (Pessoa::query()->where('codigo_inep', $id)->whereKeyNot($pessoa->id)->exists()) {
                $resumo['divergencias'][] = "{$pessoa->nome}: o ID Inep {$id} já está cadastrado para outro aluno.";

                return;
            }

            $pessoa->codigo_inep = $id;
            $pessoa->saveQuietly();
            $resumo['pessoas']++;
        } elseif ($atual !== $id) {
            $resumo['divergencias'][] = "{$pessoa->nome}: ID Inep {$atual} no sistema e {$id} no arquivo.";
        }
    }

    /**
     * @param  array<string, mixed>  $resumo
     */
    private function gravarCodigoTurma(Turma $turma, string $codigo, array &$resumo): void
    {
        if ($codigo === '' || strlen($codigo) > 10) {
            return;
        }

        $atual = (string) ($turma->codigo_inep ?? '');

        if ($atual === '') {
            $turma->codigo_inep = $codigo;
            $turma->saveQuietly();
            $resumo['turmas']++;
        } elseif ($atual !== $codigo) {
            $resumo['divergencias'][] = "Turma {$turma->nome}: código Inep {$atual} no sistema e {$codigo} no arquivo.";
        }
    }

    /**
     * Turma da escola pelo código próprio enviado na matrícula inicial (`codigo` ou, sem ele, o id).
     */
    private function turma(string $escola, string $codigoLocal): ?Turma
    {
        $chave = $escola.'|'.$codigoLocal;

        if (array_key_exists($chave, $this->turmas)) {
            return $this->turmas[$chave];
        }

        $daEscola = Turma::query()->whereHas('serie.curso', fn ($q) => $q->whereIn('unidade_id', $this->unidadesPorInep[$escola] ?? []));

        $turma = (clone $daEscola)->where('codigo', $codigoLocal)->first();

        if (! $turma && ctype_digit($codigoLocal)) {
            $turma = (clone $daEscola)->whereKey((int) $codigoLocal)->where(fn ($q) => $q->whereNull('codigo')->orWhere('codigo', ''))->first();
        }

        return $this->turmas[$chave] = $turma;
    }

    private function carregarUnidades(): void
    {
        $this->unidadesPorInep = [];

        foreach (Unidade::query()->with('instituicaoEnsino')->get() as $unidade) {
            $inep = FormatoInep::digitos($unidade->codigo_inep ?: $unidade->instituicaoEnsino?->codigo_inep);

            if (strlen($inep) === 8) {
                $this->unidadesPorInep[$inep][] = $unidade->id;
            }
        }
    }

    /**
     * @return array{linhas: int, turmas: int, pessoas: int, matriculas: int, divergencias: list<string>, nao_encontrados: list<string>, escolas_desconhecidas: array<string, string>}
     */
    private function resumoVazio(int $linhas): array
    {
        return ['linhas' => $linhas, 'turmas' => 0, 'pessoas' => 0, 'matriculas' => 0, 'divergencias' => [], 'nao_encontrados' => [], 'escolas_desconhecidas' => []];
    }

    /**
     * @param  array<string, mixed>  $resumo
     */
    private function registrar(string $descricao, array $resumo): void
    {
        activity('censo_escolar')
            ->causedBy(auth()->user())
            ->withProperties([
                'linhas' => $resumo['linhas'],
                'turmas' => $resumo['turmas'],
                'pessoas' => $resumo['pessoas'],
                'matriculas' => $resumo['matriculas'],
                'divergencias' => count($resumo['divergencias']),
                'nao_encontrados' => count($resumo['nao_encontrados']),
            ])
            ->log($descricao);
    }
}

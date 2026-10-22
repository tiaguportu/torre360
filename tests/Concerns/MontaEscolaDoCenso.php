<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\EtapaEnsino;
use App\Models\EtapaEnsinoAgregada;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\SituacaoFinalDisciplina;
use App\Models\Turma;
use App\Models\Unidade;

/**
 * Escola de referência dos testes do Censo Escolar: unidade "Sede" (Inep 12345678) com gestora, período 2026 (data de
 * referência do Censo: 27/05/2026) e helpers para turmas, alunos e resultados finais.
 *
 * Chame `montarEscolaDoCenso()` no `setUp()`, depois de fixar a data do teste.
 */
trait MontaEscolaDoCenso
{
    private PeriodoLetivo $periodo;

    private Unidade $unidade;

    private Serie $serie;

    private int $sequencia = 0;

    private function montarEscolaDoCenso(): void
    {
        $this->periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->unidade = Unidade::create(['nome' => 'Sede', 'codigo_inep' => '12345678']);
        $gestora = Pessoa::create(['nome' => 'Maria José Gestora', 'cpf' => '52998224725']);
        $this->unidade->representantesLegais()->attach($gestora->id, ['cargo' => 'Diretora']);
        $curso = Curso::create(['unidade_id' => $this->unidade->id, 'nome_externo' => 'Fundamental', 'nome_interno' => 'EF']);
        $this->serie = Serie::create(['nome' => '1º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
    }

    private function etapa(string $codigo): EtapaEnsino
    {
        $agregada = EtapaEnsinoAgregada::firstOrCreate(['codigo' => '302'], ['nome' => 'Ensino Fundamental']);

        return EtapaEnsino::firstOrCreate(['codigo' => $codigo], ['etapa_ensino_agregada_id' => $agregada->id, 'nome' => "Etapa {$codigo}"]);
    }

    private function turma(string $nome = 'Turma 101', string $etapa = '14', ?string $codigoInep = '9001', string $codigo = 'T101'): Turma
    {
        return Turma::create([
            'nome' => $nome,
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodo->id,
            'etapa_ensino_id' => $this->etapa($etapa)->id,
            'codigo' => $codigo,
            'codigo_inep' => $codigoInep,
        ]);
    }

    /**
     * Aluno com ID Inep e matrícula com código do Inep, ativa desde 01/02/2026 (antes da data de referência).
     *
     * @param  array<string, mixed>  $pessoa
     * @param  array<string, mixed>  $matricula
     */
    private function aluno(Turma $turma, string $nome = 'Ana Lima', array $pessoa = [], array $matricula = []): Matricula
    {
        $this->sequencia++;
        $criada = Pessoa::create(array_merge(['nome' => $nome, 'codigo_inep' => str_pad((string) $this->sequencia, 12, '1', STR_PAD_LEFT)], $pessoa));

        return Matricula::create(array_merge([
            'pessoa_id' => $criada->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
            'data_ativacao' => '2026-02-01',
            'codigo_matricula_inep' => str_pad((string) (200 + $this->sequencia), 12, '2', STR_PAD_LEFT),
        ], $matricula));
    }

    private function resultado(Matricula $matricula, string $situacao, ?string $posExame = null): void
    {
        SituacaoFinalDisciplina::create([
            'matricula_id' => $matricula->id,
            'disciplina_id' => Disciplina::factory()->create()->id,
            'periodo_letivo_id' => $this->periodo->id,
            'media_final' => 7,
            'situacao' => $situacao,
            'situacao_final_pos_exame' => $posExame,
        ]);
    }
}

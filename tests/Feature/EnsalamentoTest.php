<?php

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Filament\Pages\EnsalamentoTurmas;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\EnsalamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EnsalamentoTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $periodoLetivo;

    private Curso $curso;

    private Serie $serie;

    private EnsalamentoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EnsalamentoService::class);

        $this->periodoLetivo = PeriodoLetivo::create([
            'nome' => 'Ano Letivo 2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $unidade = Unidade::create([
            'nome' => 'Unidade Central',
            'cnpj' => '12.345.678/0001-90',
        ]);

        $this->curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'EF',
        ]);

        $this->serie = Serie::create([
            'nome' => '1º Ano',
            'curso_id' => $this->curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
    }

    private function criarPessoa(string $nome, string $sexo = 'masculino'): Pessoa
    {
        return Pessoa::create([
            'nome' => $nome,
            'cpf' => (string) rand(10000000000, 99999999999),
            'sexo' => $sexo,
            'data_nascimento' => '2018-05-10',
        ]);
    }

    private function criarMatricula(Pessoa $pessoa, ?Turma $turma = null): Matricula
    {
        return Matricula::create([
            'pessoa_id' => $pessoa->id,
            'turma_id' => $turma?->id,
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);
    }

    public function test_obter_turmas_cenario_calcula_ocupacao_e_generos_corretamente(): void
    {
        $turma = Turma::create([
            'nome' => 'Turma 1A',
            'codigo' => '1A',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'vagas_maximas' => 20,
        ]);

        $menina1 = $this->criarPessoa('Mariana Silva', 'feminino');
        $menina2 = $this->criarPessoa('Beatriz Souza', 'feminino');
        $menino1 = $this->criarPessoa('Carlos Eduardo', 'masculino');

        $this->criarMatricula($menina1, $turma);
        $this->criarMatricula($menina2, $turma);
        $this->criarMatricula($menino1, $turma);

        $cenario = $this->service->obterTurmasCenario($this->periodoLetivo->id, $this->serie->id);

        $this->assertCount(1, $cenario);
        $dadosTurma = $cenario->first();

        $this->assertEquals(3, $dadosTurma['total_alunos']);
        $this->assertEquals(3, $dadosTurma['ocupados']);
        $this->assertEquals(17, $dadosTurma['vagas_restantes']);
        $this->assertEquals(2, $dadosTurma['meninas']);
        $this->assertEquals(1, $dadosTurma['meninos']);
        $this->assertEquals(15.0, $dadosTurma['percentual_ocupacao']);
    }

    public function test_alocar_alunos_em_turma_respeita_capacidade_maxima(): void
    {
        $turma = Turma::create([
            'nome' => 'Turma Pequena',
            'codigo' => 'TP',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'vagas_maximas' => 2,
        ]);

        $p1 = $this->criarPessoa('Aluno Um');
        $p2 = $this->criarPessoa('Aluno Dois');
        $p3 = $this->criarPessoa('Aluno Tres');

        $m1 = $this->criarMatricula($p1);
        $m2 = $this->criarMatricula($p2);
        $m3 = $this->criarMatricula($p3);

        // Tentar alocar 3 alunos em turma de 2 vagas deve lançar InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->service->alocarAlunosEmTurma([$m1->id, $m2->id, $m3->id], $turma->id);
    }

    public function test_alocar_alunos_em_turma_com_sucesso(): void
    {
        $turma = Turma::create([
            'nome' => 'Turma A',
            'codigo' => 'TA',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'vagas_maximas' => 10,
        ]);

        $p1 = $this->criarPessoa('Aluno Um');
        $p2 = $this->criarPessoa('Aluno Dois');

        $m1 = $this->criarMatricula($p1);
        $m2 = $this->criarMatricula($p2);

        $this->service->alocarAlunosEmTurma([$m1->id, $m2->id], $turma->id);

        $this->assertEquals($turma->id, $m1->fresh()->turma_id);
        $this->assertEquals($turma->id, $m2->fresh()->turma_id);
    }

    public function test_distribuicao_automatica_equilibrio_genero(): void
    {
        $turmaA = Turma::create([
            'nome' => 'Turma A',
            'codigo' => 'TA',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'vagas_maximas' => 10,
        ]);

        $turmaB = Turma::create([
            'nome' => 'Turma B',
            'codigo' => 'TB',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'vagas_maximas' => 10,
        ]);

        // Criar 4 meninas e 4 meninos não ensalados
        for ($i = 1; $i <= 4; $i++) {
            $menina = $this->criarPessoa("Menina {$i}", 'feminino');
            $this->criarMatricula($menina);

            $menino = $this->criarPessoa("Menino {$i}", 'masculino');
            $this->criarMatricula($menino);
        }

        $resultado = $this->service->distribuirAutomaticamente(
            $this->serie->id,
            $this->periodoLetivo->id,
            [$turmaA->id, $turmaB->id],
            'equilibrio_genero'
        );

        $this->assertEquals(8, $resultado['total_distribuidos']);

        $cenario = $this->service->obterTurmasCenario($this->periodoLetivo->id, $this->serie->id);
        $turmaADados = $cenario->firstWhere('id', $turmaA->id);
        $turmaBDados = $cenario->firstWhere('id', $turmaB->id);

        // Ambas devem ter exatamente 4 alunos, sendo 2 meninas e 2 meninos
        $this->assertEquals(4, $turmaADados['total_alunos']);
        $this->assertEquals(2, $turmaADados['meninas']);
        $this->assertEquals(2, $turmaADados['meninos']);

        $this->assertEquals(4, $turmaBDados['total_alunos']);
        $this->assertEquals(2, $turmaBDados['meninas']);
        $this->assertEquals(2, $turmaBDados['meninos']);
    }

    public function test_distribuicao_automatica_ordem_alfabetica(): void
    {
        $turmaA = Turma::create([
            'nome' => 'Turma A',
            'codigo' => 'TA',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'vagas_maximas' => 10,
        ]);

        $turmaB = Turma::create([
            'nome' => 'Turma B',
            'codigo' => 'TB',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'vagas_maximas' => 10,
        ]);

        $nomes = ['Amanda', 'Bruno', 'Camila', 'Daniel'];
        foreach ($nomes as $nome) {
            $p = $this->criarPessoa($nome);
            $this->criarMatricula($p);
        }

        $this->service->distribuirAutomaticamente(
            $this->serie->id,
            $this->periodoLetivo->id,
            [$turmaA->id, $turmaB->id],
            'ordem_alfabetica'
        );

        $alunosTurmaA = $turmaA->matriculas()->with('pessoa')->get()->pluck('pessoa.nome')->toArray();
        $alunosTurmaB = $turmaB->matriculas()->with('pessoa')->get()->pluck('pessoa.nome')->toArray();

        $this->assertCount(2, $alunosTurmaA);
        $this->assertCount(2, $alunosTurmaB);
        $this->assertContains('Amanda', $alunosTurmaA);
        $this->assertContains('Bruno', $alunosTurmaB);
    }

    public function test_desensalar_remove_estudante_da_turma(): void
    {
        $turma = Turma::create([
            'nome' => 'Turma A',
            'codigo' => 'TA',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'vagas_maximas' => 10,
        ]);

        $p = $this->criarPessoa('Aluno Teste');
        $m = $this->criarMatricula($p, $turma);

        $this->assertEquals($turma->id, $m->fresh()->turma_id);

        $this->service->removerDeTurma([$m->id]);

        $this->assertNull($m->fresh()->turma_id);
    }

    public function test_pagina_livewire_ensalamento_carrega_e_funciona(): void
    {
        Permission::findOrCreate('View:Ensalamento', 'web');
        Permission::findOrCreate('Manage:Ensalamento', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo(['View:Ensalamento', 'Manage:Ensalamento']);

        $turma = Turma::create([
            'nome' => 'Turma 1A',
            'codigo' => '1A',
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => $this->periodoLetivo->id,
            'vagas_maximas' => 15,
        ]);

        $p = $this->criarPessoa('Aluno Não Ensalado');
        $m = $this->criarMatricula($p);

        Livewire::actingAs($user)
            ->test(EnsalamentoTurmas::class)
            ->set('periodoLetivoId', $this->periodoLetivo->id)
            ->set('serieId', $this->serie->id)
            ->assertSee('Turma 1A')
            ->assertSee('Aluno Não Ensalado')
            ->set('selecionados', [$m->id])
            ->set('turmaDestinoManualId', $turma->id)
            ->call('alocarSelecionados')
            ->assertHasNoErrors();

        $this->assertEquals($turma->id, $m->fresh()->turma_id);
    }
}

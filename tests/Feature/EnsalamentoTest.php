<?php

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusTurma;
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

/**
 * Remanejamento de alunos entre turmas (antigo "Ensalamento"). Toda matrícula já nasce numa turma, então
 * os testes partem de alunos já alocados e só movem/redistribuem.
 */
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

    private function criarTurma(string $nome, int $vagas = 10, ?PeriodoLetivo $periodo = null, StatusTurma $status = StatusTurma::Ativa): Turma
    {
        return Turma::create([
            'nome' => $nome,
            'codigo' => strtoupper(substr($nome, -2)),
            'serie_id' => $this->serie->id,
            'periodo_letivo_id' => ($periodo ?? $this->periodoLetivo)->id,
            'status' => $status,
            'vagas_maximas' => $vagas,
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

    private function criarMatricula(Pessoa $pessoa, Turma $turma, SituacaoMatricula $situacao = SituacaoMatricula::ATIVA): Matricula
    {
        return Matricula::create([
            'pessoa_id' => $pessoa->id,
            'turma_id' => $turma->id,
            'situacao' => $situacao,
        ]);
    }

    public function test_obter_turmas_cenario_calcula_ocupacao_e_generos_corretamente(): void
    {
        $turma = $this->criarTurma('Turma 1A', 20);

        $this->criarMatricula($this->criarPessoa('Mariana Silva', 'feminino'), $turma);
        $this->criarMatricula($this->criarPessoa('Beatriz Souza', 'feminino'), $turma);
        $this->criarMatricula($this->criarPessoa('Carlos Eduardo', 'masculino'), $turma);

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

    public function test_cenario_conta_so_quem_ocupa_vaga_e_mostra_so_turmas_abertas_do_periodo(): void
    {
        $turma = $this->criarTurma('Turma 1A', 20);
        $this->criarMatricula($this->criarPessoa('Ativa'), $turma, SituacaoMatricula::ATIVA);
        $this->criarMatricula($this->criarPessoa('Pendente'), $turma, SituacaoMatricula::PENDENTE);
        $this->criarMatricula($this->criarPessoa('Reserva'), $turma, SituacaoMatricula::RESERVA);
        $this->criarMatricula($this->criarPessoa('Cancelada'), $turma, SituacaoMatricula::CANCELADA);
        $this->criarMatricula($this->criarPessoa('Trancada'), $turma, SituacaoMatricula::TRANCADA);

        $planejada = $this->criarTurma('Turma 1B', 20, status: StatusTurma::Planejada);
        $concluida = $this->criarTurma('Turma 1C', 20, status: StatusTurma::Concluida);
        $cancelada = $this->criarTurma('Turma 1D', 20, status: StatusTurma::Cancelada);
        $outroPeriodo = $this->criarTurma('Turma 1E', 20, PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']));

        $cenario = $this->service->obterTurmasCenario($this->periodoLetivo->id, $this->serie->id);

        $this->assertEqualsCanonicalizing([$turma->id, $planejada->id], $cenario->pluck('id')->all());
        $this->assertNotContains($concluida->id, $cenario->pluck('id')->all());
        $this->assertNotContains($cancelada->id, $cenario->pluck('id')->all());
        $this->assertNotContains($outroPeriodo->id, $cenario->pluck('id')->all());
        $this->assertEquals(3, $cenario->firstWhere('id', $turma->id)['total_alunos'], 'Só Ativa, Pendente e Reserva ocupam vaga.');
    }

    public function test_mover_alunos_para_turma_cheia_e_recusado(): void
    {
        $origem = $this->criarTurma('Origem', 10);
        $destino = $this->criarTurma('Turma Pequena', 2);

        $m1 = $this->criarMatricula($this->criarPessoa('Aluno Um'), $origem);
        $m2 = $this->criarMatricula($this->criarPessoa('Aluno Dois'), $origem);
        $m3 = $this->criarMatricula($this->criarPessoa('Aluno Tres'), $origem);

        // 3 alunos numa turma de 2 vagas lança InvalidArgumentException e não move ninguém
        try {
            $this->service->alocarAlunosEmTurma([$m1->id, $m2->id, $m3->id], $destino->id);
            $this->fail('Deveria recusar: a turma só tem 2 vagas.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('possui apenas 2 vaga(s) disponível(is) e você tentou matricular 3 aluno(s)', $e->getMessage());
        }

        $this->assertSame($origem->id, $m1->fresh()->turma_id);
        $this->assertSame($origem->id, $m3->fresh()->turma_id);
    }

    public function test_mover_alunos_para_outra_turma_do_mesmo_periodo(): void
    {
        $origem = $this->criarTurma('Origem', 10);
        $destino = $this->criarTurma('Turma A', 10);

        $m1 = $this->criarMatricula($this->criarPessoa('Aluno Um'), $origem);
        $m2 = $this->criarMatricula($this->criarPessoa('Aluno Dois'), $origem);

        $this->service->alocarAlunosEmTurma([$m1->id, $m2->id], $destino->id);

        $this->assertEquals($destino->id, $m1->fresh()->turma_id);
        $this->assertEquals($destino->id, $m2->fresh()->turma_id);
        // O período e a série da matrícula acompanham a turma
        $this->assertSame($this->periodoLetivo->id, $m1->fresh()->periodo_letivo_id);
        $this->assertSame($this->serie->id, $m1->fresh()->serie_id);
    }

    public function test_mover_para_a_mesma_turma_nao_conta_vaga_em_dobro(): void
    {
        $turma = $this->criarTurma('Turma Lotada', 2);
        $m1 = $this->criarMatricula($this->criarPessoa('Aluno Um'), $turma);
        $this->criarMatricula($this->criarPessoa('Aluno Dois'), $turma);

        // Turma 2/2: "mover" quem já está nela não pode ser tratado como uma matrícula nova
        $this->service->alocarAlunosEmTurma([$m1->id], $turma->id);

        $this->assertSame($turma->id, $m1->fresh()->turma_id);
    }

    public function test_mover_para_turma_de_outro_periodo_e_recusado(): void
    {
        $origem = $this->criarTurma('Origem 2026', 10);
        $periodo2027 = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);
        $destino2027 = $this->criarTurma('Turma 2027', 10, $periodo2027, StatusTurma::Planejada);
        $m = $this->criarMatricula($this->criarPessoa('Aluno Um'), $origem);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('só pode ser feito entre turmas do mesmo período letivo');

        $this->service->alocarAlunosEmTurma([$m->id], $destino2027->id);
    }

    public function test_mover_para_turma_concluida_e_recusado(): void
    {
        $origem = $this->criarTurma('Origem', 10);
        $concluida = $this->criarTurma('Turma Concluída', 10, status: StatusTurma::Concluida);
        $m = $this->criarMatricula($this->criarPessoa('Aluno Um'), $origem);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não está aberta para matrículas');

        $this->service->alocarAlunosEmTurma([$m->id], $concluida->id);
    }

    public function test_redistribuicao_automatica_equilibrio_genero(): void
    {
        $turmaA = $this->criarTurma('Turma A');
        $turmaB = $this->criarTurma('Turma B');

        // 4 meninas e 4 meninos, todos hoje na turma A
        for ($i = 1; $i <= 4; $i++) {
            $this->criarMatricula($this->criarPessoa("Menina {$i}", 'feminino'), $turmaA);
            $this->criarMatricula($this->criarPessoa("Menino {$i}", 'masculino'), $turmaA);
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

    public function test_redistribuicao_automatica_ordem_alfabetica(): void
    {
        $turmaA = $this->criarTurma('Turma A');
        $turmaB = $this->criarTurma('Turma B');

        foreach (['Daniel', 'Camila', 'Bruno', 'Amanda'] as $nome) {
            $this->criarMatricula($this->criarPessoa($nome), $turmaA);
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

    public function test_redistribuicao_nao_mexe_em_matricula_cancelada_nem_em_turma_nao_selecionada(): void
    {
        $turmaA = $this->criarTurma('Turma A');
        $turmaB = $this->criarTurma('Turma B');
        $turmaC = $this->criarTurma('Turma C');

        $cancelada = $this->criarMatricula($this->criarPessoa('Cancelada'), $turmaA, SituacaoMatricula::CANCELADA);
        $deC = $this->criarMatricula($this->criarPessoa('Aluno da C'), $turmaC);
        foreach (['Ana', 'Bia', 'Caio', 'Davi'] as $nome) {
            $this->criarMatricula($this->criarPessoa($nome), $turmaA);
        }

        $resultado = $this->service->distribuirAutomaticamente($this->serie->id, $this->periodoLetivo->id, [$turmaA->id, $turmaB->id]);

        $this->assertEquals(4, $resultado['total_distribuidos']);
        $this->assertSame($turmaA->id, $cancelada->fresh()->turma_id, 'Matrícula cancelada fica onde está.');
        $this->assertSame($turmaC->id, $deC->fresh()->turma_id, 'Turma fora da seleção não é tocada.');
    }

    public function test_redistribuicao_sem_alunos_lanca_erro_claro(): void
    {
        $turmaA = $this->criarTurma('Turma A');
        $turmaB = $this->criarTurma('Turma B');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Não há alunos nas turmas selecionadas');

        $this->service->distribuirAutomaticamente($this->serie->id, $this->periodoLetivo->id, [$turmaA->id, $turmaB->id]);
    }

    private function usuarioComPermissoes(array $permissoes): User
    {
        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissoes);

        return $user;
    }

    public function test_pagina_livewire_mostra_turmas_e_move_aluno(): void
    {
        $user = $this->usuarioComPermissoes(['View:Ensalamento', 'Manage:Ensalamento']);
        $origem = $this->criarTurma('Turma 1A', 15);
        $destino = $this->criarTurma('Turma 1B', 15);
        $m = $this->criarMatricula($this->criarPessoa('Aluno Remanejado'), $origem);

        Livewire::actingAs($user)
            ->test(EnsalamentoTurmas::class)
            ->set('periodoLetivoId', $this->periodoLetivo->id)
            ->set('serieId', $this->serie->id)
            ->assertSee('Turma 1A')
            ->assertSee('Turma 1B')
            ->assertSee('Remanejamento')
            ->assertDontSee('Aguardando Turma')
            ->assertDontSee('Desensalar')
            ->call('abrirModalMover', $m->id, 'Aluno Remanejado')
            ->set('novaTurmaId', $destino->id)
            ->call('confirmarMover')
            ->assertHasNoErrors()
            ->assertSet('showModalMover', false);

        $this->assertEquals($destino->id, $m->fresh()->turma_id);
    }

    public function test_quem_so_visualiza_nao_consegue_mover_nem_redistribuir_chamando_o_livewire_direto(): void
    {
        $user = $this->usuarioComPermissoes(['View:Ensalamento']);
        $origem = $this->criarTurma('Turma 1A');
        $destino = $this->criarTurma('Turma 1B');
        $m = $this->criarMatricula($this->criarPessoa('Aluno'), $origem);

        // Uma instância nova por chamada: depois de um 403 o snapshot do componente deixa de ser reutilizável.
        $abrir = fn () => Livewire::actingAs($user)
            ->test(EnsalamentoTurmas::class)
            ->set('periodoLetivoId', $this->periodoLetivo->id)
            ->set('serieId', $this->serie->id);

        $abrir()->assertSee('Turma 1A');

        $abrir()->call('abrirModalMover', $m->id, 'Aluno')->assertForbidden();
        $abrir()->set('matriculaMoverId', $m->id)->set('novaTurmaId', $destino->id)->call('confirmarMover')->assertForbidden();
        $abrir()->set('turmasSelecionadasDistribuicao', [$origem->id, $destino->id])->call('executarDistribuicaoAutomatica')->assertForbidden();
        $abrir()->call('abrirModalDistribuicao')->assertForbidden();

        $this->assertSame($origem->id, $m->fresh()->turma_id);
    }

    public function test_pagina_abre_no_periodo_em_vigor_e_nao_no_mais_recente(): void
    {
        $user = $this->usuarioComPermissoes(['View:Ensalamento']);

        $emVigor = PeriodoLetivo::create(['nome' => 'Em vigor', 'data_inicio' => today()->subMonths(2)->toDateString(), 'data_fim' => today()->addMonths(2)->toDateString()]);
        PeriodoLetivo::create(['nome' => 'Futuro', 'data_inicio' => today()->addYear()->toDateString(), 'data_fim' => today()->addYear()->addMonths(10)->toDateString()]);

        Livewire::actingAs($user)
            ->test(EnsalamentoTurmas::class)
            ->assertSet('periodoLetivoId', $emVigor->id);
    }
}

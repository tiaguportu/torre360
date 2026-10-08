<?php

namespace Tests\Feature;

use App\Filament\Pages\FechamentoCicloLetivo;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\SituacaoFinalDisciplina;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FechamentoCicloLetivoPageTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarSuperAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('super_admin');
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        return $user;
    }

    /**
     * @return array{periodo: PeriodoLetivo, turma: Turma, disciplina: Disciplina, matricula: Matricula}
     */
    private function criarCenarioComSituacaoRecuperacao(): array
    {
        $periodo = PeriodoLetivo::create([
            'nome' => 'Ano Letivo Teste Página',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
            'nota_aprovacao' => 7,
            'nota_recuperacao_minima' => 5,
            'exame_final_habilitado' => true,
            'nota_aprovacao_pos_exame' => 5,
        ]);

        $turma = Turma::create([
            'nome' => 'Turma Teste Página',
            'periodo_letivo_id' => $periodo->id,
            'tipo_avaliacao' => 'notas',
        ]);

        $etapa = EtapaAvaliativa::create([
            'nome' => '1ª Etapa',
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-04-30',
        ]);

        $disciplina = Disciplina::create(['nome' => 'Disciplina Teste Página']);
        $turma->disciplinas()->attach($disciplina->id);

        $aluno = Pessoa::create(['nome' => 'Aluno Teste Página']);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $categoria = CategoriaAvaliacao::create(['nome' => 'Prova', 'eh_recuperacao' => false]);

        $avaliacao = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'etapa_avaliativa_id' => $etapa->id,
            'categoria_avaliacao_id' => $categoria->id,
            'data_prevista' => $etapa->data_inicio,
            'data_ocorrencia' => $etapa->data_inicio,
            'data_limite_lancamento' => $etapa->data_fim,
            'nota_maxima' => 10,
        ]);

        Nota::create([
            'matricula_id' => $matricula->id,
            'avaliacao_id' => $avaliacao->id,
            'valor' => 6.0, // >= recuperação (5) e < aprovação (7) => Recuperação
        ]);

        return compact('periodo', 'turma', 'disciplina', 'matricula');
    }

    public function test_calcular_situacao_final_grava_resultados(): void
    {
        $this->autenticarSuperAdmin();

        ['periodo' => $periodo] = $this->criarCenarioComSituacaoRecuperacao();

        Livewire::test(FechamentoCicloLetivo::class)
            ->set('data.periodo_letivo_id', $periodo->id)
            ->call('calcularSituacaoFinal')
            ->assertSuccessful();

        $this->assertDatabaseHas('situacao_final_disciplina', [
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'recuperacao',
        ]);
    }

    public function test_lancar_exame_final_a_partir_da_pagina_atualiza_situacao_definitiva(): void
    {
        $this->autenticarSuperAdmin();

        ['periodo' => $periodo, 'matricula' => $matricula, 'disciplina' => $disciplina] = $this->criarCenarioComSituacaoRecuperacao();

        $testable = Livewire::test(FechamentoCicloLetivo::class)
            ->set('data.periodo_letivo_id', $periodo->id)
            ->call('calcularSituacaoFinal')
            ->assertSuccessful();

        $registro = SituacaoFinalDisciplina::where([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodo->id,
        ])->firstOrFail();

        $this->assertSame('recuperacao', $registro->situacao->value);

        $testable
            ->callAction('lancarExameFinal', data: ['nota_exame_final' => 8.0], arguments: ['registro_id' => $registro->id])
            ->assertSuccessful()
            ->assertHasNoActionErrors();

        $registroAtualizado = $registro->fresh();

        // média final pós exame = (6.0 + 8.0) / 2 = 7.0 >= nota_aprovacao_pos_exame (5.0) => Aprovado
        $this->assertEquals(7.0, (float) $registroAtualizado->media_final_pos_exame);
        $this->assertSame('aprovado', $registroAtualizado->situacao_final_pos_exame->value);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ConceitoHabilidade;
use App\Filament\Resources\AvaliacaoHabilidades\Pages\LancarNotaHabilidade;
use App\Models\AvaliacaoHabilidade;
use App\Models\CampoExperiencia;
use App\Models\EtapaAvaliativa;
use App\Models\Habilidade;
use App\Models\Matricula;
use App\Models\NotaHabilidade;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BNCCEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function criarAvaliacaoComHabilidade(): array
    {
        $campoExperiencia = CampoExperiencia::create(['nome' => 'O eu, o outro e o nós']);

        $habilidade = Habilidade::factory()->create([
            'codigo' => 'EI01EO01',
            'nome' => 'Perceber que suas ações têm efeitos nas outras pessoas.',
            'campo_experiencia_id' => $campoExperiencia->id,
            'tipo' => 'BNCC',
        ]);

        $periodo = PeriodoLetivo::factory()->create();
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
        $turma->habilidades()->attach($habilidade->id);

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno BNCC Teste']);
        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        $etapa = EtapaAvaliativa::create([
            'nome' => '1º Trimestre',
            'periodo_letivo_id' => $periodo->id,
            'turma_id' => $turma->id,
            'data_inicio' => now()->subMonth()->toDateString(),
            'data_fim' => now()->addMonth()->toDateString(),
        ]);

        $avaliacao = AvaliacaoHabilidade::factory()->create([
            'turma_id' => $turma->id,
            'etapa_avaliativa_id' => $etapa->id,
        ]);

        $admin = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $admin->assignRole($role);
        session(['active_role' => 'super_admin']);

        return compact('campoExperiencia', 'habilidade', 'turma', 'aluno', 'matricula', 'etapa', 'avaliacao', 'admin');
    }

    public function test_habilidade_pertence_a_um_campo_de_experiencia_da_bncc(): void
    {
        $campoExperiencia = CampoExperiencia::create(['nome' => 'Corpo, gestos e movimentos']);

        $habilidade = Habilidade::factory()->create([
            'codigo' => 'EI01CG01',
            'nome' => 'Explorar o próprio corpo.',
            'campo_experiencia_id' => $campoExperiencia->id,
            'tipo' => 'BNCC',
        ]);

        $this->assertEquals('Corpo, gestos e movimentos', $habilidade->campoExperiencia->nome);
        $this->assertTrue($campoExperiencia->habilidades->contains($habilidade));
    }

    public function test_turma_pode_ter_habilidades_bncc_vinculadas(): void
    {
        $turma = Turma::factory()->create();
        $habilidade = Habilidade::factory()->create(['nome' => 'Reconhecer sons do ambiente.']);

        $turma->habilidades()->attach($habilidade->id);

        $this->assertTrue($turma->fresh()->habilidades->contains($habilidade));
    }

    public function test_lanca_conceito_de_habilidade_para_aluno_da_turma(): void
    {
        $dados = $this->criarAvaliacaoComHabilidade();

        Livewire::actingAs($dados['admin'])
            ->test(LancarNotaHabilidade::class, ['record' => $dados['avaliacao']->getKey()])
            ->set("data.notas.{$dados['matricula']->id}.{$dados['habilidade']->id}.conceito", ConceitoHabilidade::REALIZA_BEM->value)
            ->set("data.notas.{$dados['matricula']->id}.{$dados['habilidade']->id}.observacao", 'Demonstrou empatia com os colegas.')
            ->call('saveNotas', false);

        $this->assertDatabaseHas('nota_habilidades', [
            'avaliacao_habilidade_id' => $dados['avaliacao']->id,
            'matricula_id' => $dados['matricula']->id,
            'habilidade_id' => $dados['habilidade']->id,
            'conceito' => ConceitoHabilidade::REALIZA_BEM->value,
            'observacao' => 'Demonstrou empatia com os colegas.',
        ]);

        $nota = NotaHabilidade::where('avaliacao_habilidade_id', $dados['avaliacao']->id)->firstOrFail();
        $this->assertInstanceOf(ConceitoHabilidade::class, $nota->conceito);
        $this->assertEquals(ConceitoHabilidade::REALIZA_BEM, $nota->conceito);
    }

    public function test_relancar_conceito_atualiza_a_nota_existente_em_vez_de_duplicar(): void
    {
        $dados = $this->criarAvaliacaoComHabilidade();

        NotaHabilidade::create([
            'avaliacao_habilidade_id' => $dados['avaliacao']->id,
            'matricula_id' => $dados['matricula']->id,
            'habilidade_id' => $dados['habilidade']->id,
            'conceito' => ConceitoHabilidade::NAO_OBSERVADO->value,
        ]);

        Livewire::actingAs($dados['admin'])
            ->test(LancarNotaHabilidade::class, ['record' => $dados['avaliacao']->getKey()])
            ->set("data.notas.{$dados['matricula']->id}.{$dados['habilidade']->id}.conceito", ConceitoHabilidade::EM_DESENVOLVIMENTO->value)
            ->call('saveNotas', false);

        $this->assertDatabaseCount('nota_habilidades', 1);
        $this->assertDatabaseHas('nota_habilidades', [
            'avaliacao_habilidade_id' => $dados['avaliacao']->id,
            'matricula_id' => $dados['matricula']->id,
            'habilidade_id' => $dados['habilidade']->id,
            'conceito' => ConceitoHabilidade::EM_DESENVOLVIMENTO->value,
        ]);
    }

    public function test_limpar_conceito_remove_a_nota_de_habilidade(): void
    {
        $dados = $this->criarAvaliacaoComHabilidade();

        NotaHabilidade::create([
            'avaliacao_habilidade_id' => $dados['avaliacao']->id,
            'matricula_id' => $dados['matricula']->id,
            'habilidade_id' => $dados['habilidade']->id,
            'conceito' => ConceitoHabilidade::REALIZA_BEM->value,
        ]);

        Livewire::actingAs($dados['admin'])
            ->test(LancarNotaHabilidade::class, ['record' => $dados['avaliacao']->getKey()])
            ->set("data.notas.{$dados['matricula']->id}.{$dados['habilidade']->id}.conceito", null)
            ->call('saveNotas', false);

        $this->assertDatabaseMissing('nota_habilidades', [
            'avaliacao_habilidade_id' => $dados['avaliacao']->id,
            'matricula_id' => $dados['matricula']->id,
            'habilidade_id' => $dados['habilidade']->id,
        ]);
    }

    public function test_usuario_sem_permissao_nao_acessa_lancamento_de_notas_de_habilidade(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'Update:AvaliacaoHabilidade', 'guard_name' => 'web']);

        $dados = $this->criarAvaliacaoComHabilidade();

        // A criação do cenário base ativa a sessão como super_admin; removemos
        // para que a checagem de autorização não seja ignorada (bypass global).
        session()->forget('active_role');

        $userSemPermissao = User::factory()->create();

        Livewire::actingAs($userSemPermissao)
            ->test(LancarNotaHabilidade::class, ['record' => $dados['avaliacao']->getKey()])
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\QuantidadeRefeicao;
use App\Filament\Portal\Pages\AgendaDiaria;
use App\Filament\Resources\RegistroRotinaDiarias\Pages\CreateRegistroRotinaDiaria;
use App\Filament\Resources\RegistroRotinaDiarias\Pages\ListRegistroRotinaDiarias;
use App\Models\Matricula;
use App\Models\RegistroRotinaDiaria;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistroRotinaDiariaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:RegistroRotinaDiaria", 'guard_name' => 'web']);
            $role->givePermissionTo($permissao);
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_pagina_de_listagem_admin_carrega(): void
    {
        $this->autenticarComoAdmin();
        RegistroRotinaDiaria::factory()->count(2)->create();

        Livewire::test(ListRegistroRotinaDiarias::class)
            ->assertSuccessful();
    }

    public function test_criar_registro_com_refeicoes_via_formulario(): void
    {
        $this->autenticarComoAdmin();
        $turma = Turma::factory()->create();
        $matricula = Matricula::factory()->create(['turma_id' => $turma->id]);

        Livewire::test(CreateRegistroRotinaDiaria::class)
            ->fillForm([
                'matricula_id' => $matricula->id,
                'turma_id' => $turma->id,
                'data' => now()->toDateString(),
                'humor' => 'feliz',
                'hora_inicio_soneca' => '13:00',
                'hora_fim_soneca' => '14:30',
                'refeicoes' => [
                    ['nome' => 'Café da Manhã', 'quantidade' => QuantidadeRefeicao::ComeuTudo->value],
                    ['nome' => 'Almoço', 'quantidade' => QuantidadeRefeicao::ComeuParcialmente->value, 'observacao' => 'Recusou o feijão'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $registro = RegistroRotinaDiaria::where('matricula_id', $matricula->id)->first();
        $this->assertNotNull($registro);
        $this->assertSame($turma->id, $registro->turma_id);
        $this->assertTrue($registro->temSoneca());
        $this->assertCount(2, $registro->refeicoes);
        $this->assertSame('Recusou o feijão', $registro->refeicoes->last()->observacao);
    }

    public function test_nao_permite_dois_registros_para_o_mesmo_aluno_na_mesma_data(): void
    {
        $this->autenticarComoAdmin();
        $matricula = Matricula::factory()->create();
        RegistroRotinaDiaria::factory()->create([
            'matricula_id' => $matricula->id,
            'data' => '2026-10-08',
        ]);

        Livewire::test(CreateRegistroRotinaDiaria::class)
            ->fillForm([
                'matricula_id' => $matricula->id,
                'turma_id' => $matricula->turma_id,
                'data' => '2026-10-08',
            ])
            ->call('create')
            ->assertHasFormErrors(['data']);
    }

    public function test_permite_mesma_data_para_alunos_diferentes(): void
    {
        $this->autenticarComoAdmin();
        RegistroRotinaDiaria::factory()->create(['data' => '2026-10-08']);
        $outraMatricula = Matricula::factory()->create();

        Livewire::test(CreateRegistroRotinaDiaria::class)
            ->fillForm([
                'matricula_id' => $outraMatricula->id,
                'turma_id' => $outraMatricula->turma_id,
                'data' => '2026-10-08',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_tem_soneca_retorna_falso_sem_horario_registrado(): void
    {
        $registro = RegistroRotinaDiaria::factory()->create([
            'hora_inicio_soneca' => null,
            'hora_fim_soneca' => null,
        ]);

        $this->assertFalse($registro->temSoneca());
    }

    private function criarMatriculaComUsuario(): array
    {
        $turma = Turma::factory()->create();
        $matricula = Matricula::factory()->create(['turma_id' => $turma->id, 'situacao' => 'ativa']);

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->pessoas()->attach($matricula->pessoa_id);

        return ['user' => $user, 'matricula' => $matricula];
    }

    public function test_portal_lista_apenas_registros_do_aluno_selecionado(): void
    {
        $dados = $this->criarMatriculaComUsuario();

        $registroDoAluno = RegistroRotinaDiaria::factory()->create([
            'matricula_id' => $dados['matricula']->id,
            'turma_id' => $dados['matricula']->turma_id,
            'data' => '2026-10-08',
        ]);
        RegistroRotinaDiaria::factory()->create(['data' => '2026-10-08']);

        Livewire::actingAs($dados['user'])
            ->test(AgendaDiaria::class)
            ->assertCanSeeTableRecords([$registroDoAluno]);
    }
}

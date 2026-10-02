<?php

namespace Tests\Feature;

use App\Enums\StatusBolsa;
use App\Filament\Resources\BolsaConcedidas\Pages\CreateBolsaConcedida;
use App\Filament\Resources\BolsaConcedidas\Pages\ListBolsaConcedidas;
use App\Models\BolsaConcedida;
use App\Models\Matricula;
use App\Models\TipoBolsa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BolsaConcedidaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['TipoBolsa', 'BolsaConcedida'] as $modelo) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
                $permissao = Permission::firstOrCreate(['name' => "{$acao}:{$modelo}", 'guard_name' => 'web']);
                $role->givePermissionTo($permissao);
            }
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_pagina_de_listagem_carrega(): void
    {
        $this->autenticarComoAdmin();
        BolsaConcedida::factory()->count(2)->create();

        Livewire::test(ListBolsaConcedidas::class)
            ->assertSuccessful();
    }

    public function test_is_ativa_true_para_aprovada_dentro_da_vigencia(): void
    {
        $bolsa = BolsaConcedida::factory()->create([
            'status' => 'aprovada',
            'data_inicio' => now()->subMonth(),
            'data_fim' => null,
        ]);

        $this->assertTrue($bolsa->isAtiva());
    }

    public function test_is_ativa_false_para_solicitada(): void
    {
        $bolsa = BolsaConcedida::factory()->create(['status' => 'solicitada']);

        $this->assertFalse($bolsa->isAtiva());
    }

    public function test_is_ativa_false_apos_data_fim(): void
    {
        $bolsa = BolsaConcedida::factory()->create([
            'status' => 'aprovada',
            'data_inicio' => now()->subMonths(2),
            'data_fim' => now()->subDay(),
        ]);

        $this->assertFalse($bolsa->isAtiva());
    }

    public function test_percentual_ativo_para_soma_bolsas_ativas_e_limita_a_100(): void
    {
        $matricula = Matricula::factory()->create();
        BolsaConcedida::factory()->create(['matricula_id' => $matricula->id, 'status' => 'aprovada', 'percentual' => 60, 'data_inicio' => now()->subMonth()]);
        BolsaConcedida::factory()->create(['matricula_id' => $matricula->id, 'status' => 'aprovada', 'percentual' => 70, 'data_inicio' => now()->subMonth()]);
        BolsaConcedida::factory()->create(['matricula_id' => $matricula->id, 'status' => 'solicitada', 'percentual' => 50]);

        $this->assertSame(100, BolsaConcedida::percentualAtivoPara($matricula));
    }

    public function test_percentual_ativo_zero_sem_bolsa_aprovada(): void
    {
        $matricula = Matricula::factory()->create();

        $this->assertSame(0, BolsaConcedida::percentualAtivoPara($matricula));
    }

    public function test_criar_bolsa_com_tipo_que_exige_aprovacao_fica_solicitada(): void
    {
        $this->autenticarComoAdmin();
        $matricula = Matricula::factory()->create();
        $tipo = TipoBolsa::factory()->create(['exige_aprovacao' => true]);

        Livewire::test(CreateBolsaConcedida::class)
            ->fillForm([
                'matricula_id' => $matricula->id,
                'tipo_bolsa_id' => $tipo->id,
                'percentual' => 20,
                'data_inicio' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bolsa_concedidas', [
            'matricula_id' => $matricula->id,
            'status' => 'solicitada',
        ]);
    }

    public function test_criar_bolsa_com_tipo_sem_aprovacao_ja_nasce_aprovada(): void
    {
        $this->autenticarComoAdmin();
        $matricula = Matricula::factory()->create();
        $tipo = TipoBolsa::factory()->create(['exige_aprovacao' => false]);

        Livewire::test(CreateBolsaConcedida::class)
            ->fillForm([
                'matricula_id' => $matricula->id,
                'tipo_bolsa_id' => $tipo->id,
                'percentual' => 15,
                'data_inicio' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bolsa_concedidas', [
            'matricula_id' => $matricula->id,
            'status' => 'aprovada',
        ]);
    }

    public function test_acao_aprovar_muda_status_e_registra_aprovador(): void
    {
        $admin = $this->autenticarComoAdmin();
        $bolsa = BolsaConcedida::factory()->create(['status' => 'solicitada']);

        Livewire::test(ListBolsaConcedidas::class)
            ->callTableAction('aprovar', $bolsa)
            ->assertSuccessful();

        $bolsa = $bolsa->fresh();
        $this->assertSame(StatusBolsa::Aprovada, $bolsa->status);
        $this->assertSame($admin->id, $bolsa->aprovado_por_user_id);
    }

    public function test_acao_recusar_muda_status(): void
    {
        $this->autenticarComoAdmin();
        $bolsa = BolsaConcedida::factory()->create(['status' => 'solicitada']);

        Livewire::test(ListBolsaConcedidas::class)
            ->callTableAction('recusar', $bolsa, data: ['motivo' => 'Documentação incompleta'])
            ->assertSuccessful();

        $this->assertSame(StatusBolsa::Recusada, $bolsa->fresh()->status);
    }
}

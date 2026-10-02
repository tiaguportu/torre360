<?php

namespace Tests\Feature;

use App\Enums\StatusBemPatrimonial;
use App\Filament\Resources\BemPatrimonials\Pages\EditBemPatrimonial;
use App\Filament\Resources\BemPatrimonials\Pages\ListBemPatrimonials;
use App\Filament\Resources\BemPatrimonials\RelationManagers\MovimentacoesRelationManager;
use App\Models\BemPatrimonial;
use App\Models\InstituicaoEnsino;
use App\Models\Sala;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BemPatrimonialTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:BemPatrimonial", 'guard_name' => 'web']);
            $role->givePermissionTo($permissao);
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    private function criarUnidade(): Unidade
    {
        $instituicao = InstituicaoEnsino::factory()->create(['nome' => 'Instituição Teste '.uniqid()]);

        return Unidade::create(['instituicao_ensino_id' => $instituicao->id, 'nome' => 'Unidade Teste '.uniqid()]);
    }

    public function test_pagina_de_listagem_carrega(): void
    {
        $this->autenticarComoAdmin();
        BemPatrimonial::factory()->count(3)->create();

        Livewire::test(ListBemPatrimonials::class)
            ->assertSuccessful();
    }

    public function test_transferir_atualiza_localizacao_e_registra_movimentacao(): void
    {
        $unidadeOrigem = $this->criarUnidade();
        $unidadeDestino = $this->criarUnidade();
        $bem = BemPatrimonial::factory()->create(['unidade_id' => $unidadeOrigem->id]);

        $bem->transferir($unidadeDestino->id, null, 'Mudança de sala');

        $bem = $bem->fresh();
        $this->assertSame($unidadeDestino->id, $bem->unidade_id);
        $this->assertDatabaseHas('movimentacoes_patrimonio', [
            'bem_patrimonial_id' => $bem->id,
            'tipo' => 'transferencia',
            'unidade_anterior_id' => $unidadeOrigem->id,
            'unidade_nova_id' => $unidadeDestino->id,
        ]);
    }

    public function test_mudar_status_atualiza_e_registra_movimentacao(): void
    {
        $bem = BemPatrimonial::factory()->create(['status' => 'em_uso']);

        $bem->mudarStatus(StatusBemPatrimonial::EmManutencao, 'Tela quebrada');

        $bem = $bem->fresh();
        $this->assertSame(StatusBemPatrimonial::EmManutencao, $bem->status);
        $this->assertDatabaseHas('movimentacoes_patrimonio', [
            'bem_patrimonial_id' => $bem->id,
            'tipo' => 'mudanca_status',
            'status_anterior' => 'em_uso',
            'status_novo' => 'em_manutencao',
        ]);
    }

    public function test_acao_transferir_via_filament(): void
    {
        $this->autenticarComoAdmin();
        $unidade = $this->criarUnidade();
        $sala = Sala::factory()->create(['unidade_id' => $unidade->id]);
        $bem = BemPatrimonial::factory()->create();

        Livewire::test(ListBemPatrimonials::class)
            ->callTableAction('transferir', $bem, data: [
                'unidade_id' => $unidade->id,
                'sala_id' => $sala->id,
            ])
            ->assertSuccessful();

        $bem = $bem->fresh();
        $this->assertSame($unidade->id, $bem->unidade_id);
        $this->assertSame($sala->id, $bem->sala_id);
    }

    public function test_acao_mudar_status_via_filament(): void
    {
        $this->autenticarComoAdmin();
        $bem = BemPatrimonial::factory()->create(['status' => 'em_uso']);

        Livewire::test(ListBemPatrimonials::class)
            ->callTableAction('mudar_status', $bem, data: ['status' => 'baixado', 'observacao' => 'Equipamento obsoleto'])
            ->assertSuccessful();

        $this->assertSame(StatusBemPatrimonial::Baixado, $bem->fresh()->status);
    }

    public function test_relation_manager_de_movimentacoes_lista_o_historico(): void
    {
        $this->autenticarComoAdmin();
        $bem = BemPatrimonial::factory()->create();
        $bem->mudarStatus(StatusBemPatrimonial::EmManutencao, 'Revisão programada');

        Livewire::test(MovimentacoesRelationManager::class, [
            'ownerRecord' => $bem,
            'pageClass' => EditBemPatrimonial::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($bem->movimentacoes);
    }
}

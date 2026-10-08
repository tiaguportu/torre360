<?php

namespace Tests\Feature;

use App\Enums\StatusConsentimento;
use App\Filament\Portal\Pages\Consentimentos;
use App\Filament\Resources\ConsentimentoMatriculas\Pages\CreateConsentimentoMatricula;
use App\Filament\Resources\ConsentimentoMatriculas\Pages\ListConsentimentoMatriculas;
use App\Filament\Resources\TipoConsentimentos\Pages\CreateTipoConsentimento;
use App\Filament\Resources\TipoConsentimentos\Pages\ListTipoConsentimentos;
use App\Models\ConsentimentoMatricula;
use App\Models\Matricula;
use App\Models\TipoConsentimento;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsentimentoMatriculaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            foreach (['TipoConsentimento', 'ConsentimentoMatricula'] as $modelName) {
                $permissao = Permission::firstOrCreate(['name' => "{$acao}:{$modelName}", 'guard_name' => 'web']);
                $role->givePermissionTo($permissao);
            }
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_pagina_de_listagem_tipo_consentimento_carrega(): void
    {
        $this->autenticarComoAdmin();
        TipoConsentimento::factory()->count(2)->create();

        Livewire::test(ListTipoConsentimentos::class)
            ->assertSuccessful();
    }

    public function test_criar_tipo_consentimento_via_formulario(): void
    {
        $this->autenticarComoAdmin();

        Livewire::test(CreateTipoConsentimento::class)
            ->fillForm([
                'nome' => 'Uso de Imagem — Material Impresso',
                'texto_padrao' => 'Autorizo o uso da imagem em material impresso.',
                'exige_renovacao_periodica' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tipo_consentimentos', [
            'nome' => 'Uso de Imagem — Material Impresso',
        ]);
    }

    public function test_pagina_de_listagem_consentimento_matricula_carrega(): void
    {
        $this->autenticarComoAdmin();
        ConsentimentoMatricula::factory()->count(2)->create();

        Livewire::test(ListConsentimentoMatriculas::class)
            ->assertSuccessful();
    }

    public function test_registrar_consentimento_manualmente_via_formulario(): void
    {
        $this->autenticarComoAdmin();
        $matricula = Matricula::factory()->create();
        $tipo = TipoConsentimento::factory()->create(['exige_renovacao_periodica' => false]);

        Livewire::test(CreateConsentimentoMatricula::class)
            ->fillForm([
                'matricula_id' => $matricula->id,
                'tipo_consentimento_id' => $tipo->id,
                'status' => StatusConsentimento::Autorizado->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $registro = ConsentimentoMatricula::where('matricula_id', $matricula->id)->first();
        $this->assertSame(StatusConsentimento::Autorizado, $registro->status);
        $this->assertNotNull($registro->respondido_em);
    }

    public function test_responder_autorizado_com_renovacao_periodica_calcula_vigencia(): void
    {
        $matricula = Matricula::factory()->create();
        $tipo = TipoConsentimento::factory()->create(['exige_renovacao_periodica' => true, 'periodicidade_meses' => 12]);
        $consentimento = ConsentimentoMatricula::factory()->create([
            'matricula_id' => $matricula->id,
            'tipo_consentimento_id' => $tipo->id,
        ]);

        $usuario = User::factory()->create();
        $consentimento->responder(StatusConsentimento::Autorizado, $usuario, '127.0.0.1');

        $consentimento->refresh();
        $this->assertSame(StatusConsentimento::Autorizado, $consentimento->status);
        $this->assertNotNull($consentimento->respondido_em);
        $this->assertSame($usuario->id, $consentimento->respondido_por_user_id);
        $this->assertSame('127.0.0.1', $consentimento->ip_resposta);
        $this->assertNotNull($consentimento->vigencia_fim);
        $this->assertTrue($consentimento->vigencia_fim->isAfter(now()->addMonths(11)));
    }

    public function test_status_efetivo_volta_a_pendente_quando_vigencia_vence(): void
    {
        $consentimento = ConsentimentoMatricula::factory()->create([
            'status' => StatusConsentimento::Autorizado,
            'vigencia_inicio' => now()->subMonths(13),
            'vigencia_fim' => now()->subMonth(),
        ]);

        $this->assertSame(StatusConsentimento::Pendente, $consentimento->statusEfetivo());
        $this->assertTrue($consentimento->precisaResposta());
    }

    public function test_status_efetivo_mantem_nao_autorizado_sem_vigencia(): void
    {
        $consentimento = ConsentimentoMatricula::factory()->create([
            'status' => StatusConsentimento::NaoAutorizado,
        ]);

        $this->assertSame(StatusConsentimento::NaoAutorizado, $consentimento->statusEfetivo());
        $this->assertFalse($consentimento->precisaResposta());
    }

    public function test_localizar_ou_pendente_nao_duplica_registro_existente(): void
    {
        $matricula = Matricula::factory()->create();
        $tipo = TipoConsentimento::factory()->create();
        $existente = ConsentimentoMatricula::factory()->create([
            'matricula_id' => $matricula->id,
            'tipo_consentimento_id' => $tipo->id,
            'status' => StatusConsentimento::Autorizado,
        ]);

        $resultado = ConsentimentoMatricula::localizarOuPendente($matricula, $tipo);

        $this->assertSame($existente->id, $resultado->id);
        $this->assertSame(1, ConsentimentoMatricula::count());
    }

    private function criarMatriculaComUsuario(): array
    {
        $turma = Turma::factory()->create();
        $matricula = Matricula::factory()->create(['turma_id' => $turma->id, 'situacao' => 'ativa']);

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->pessoas()->attach($matricula->pessoa_id);

        return ['user' => $user, 'matricula' => $matricula];
    }

    public function test_portal_lista_tipos_ativos_e_cria_pendente_automaticamente(): void
    {
        $dados = $this->criarMatriculaComUsuario();
        TipoConsentimento::factory()->create(['nome' => 'Uso de Imagem', 'is_ativo' => true]);
        TipoConsentimento::factory()->create(['nome' => 'Inativo', 'is_ativo' => false]);

        Livewire::actingAs($dados['user'])
            ->test(Consentimentos::class)
            ->assertSee('Uso de Imagem')
            ->assertDontSee('Inativo');

        $this->assertDatabaseHas('consentimento_matriculas', [
            'matricula_id' => $dados['matricula']->id,
        ]);
    }

    public function test_portal_responder_autoriza_consentimento(): void
    {
        $dados = $this->criarMatriculaComUsuario();
        $tipo = TipoConsentimento::factory()->create(['exige_renovacao_periodica' => false]);
        $consentimento = ConsentimentoMatricula::factory()->create([
            'matricula_id' => $dados['matricula']->id,
            'tipo_consentimento_id' => $tipo->id,
        ]);

        Livewire::actingAs($dados['user'])
            ->test(Consentimentos::class)
            ->call('responder', $consentimento->id, StatusConsentimento::Autorizado->value);

        $this->assertSame(StatusConsentimento::Autorizado, $consentimento->fresh()->status);
    }

    public function test_portal_nao_permite_responder_consentimento_de_outra_matricula(): void
    {
        $dados = $this->criarMatriculaComUsuario();
        $outraMatricula = Matricula::factory()->create();
        $tipo = TipoConsentimento::factory()->create();
        $consentimento = ConsentimentoMatricula::factory()->create([
            'matricula_id' => $outraMatricula->id,
            'tipo_consentimento_id' => $tipo->id,
        ]);

        Livewire::actingAs($dados['user'])
            ->test(Consentimentos::class)
            ->call('responder', $consentimento->id, StatusConsentimento::Autorizado->value);

        $this->assertSame(StatusConsentimento::Pendente, $consentimento->fresh()->status);
    }
}

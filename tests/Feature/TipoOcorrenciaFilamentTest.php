<?php

namespace Tests\Feature;

use App\Filament\Resources\TipoOcorrenciaResource\Pages\CreateTipoOcorrencia;
use App\Filament\Resources\TipoOcorrenciaResource\Pages\EditTipoOcorrencia;
use App\Filament\Resources\TipoOcorrenciaResource\Pages\ListTipoOcorrencias;
use App\Models\TipoOcorrencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TipoOcorrenciaFilamentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $usuarioSemPermissao;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleSuper = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $roleSemAcesso = Role::firstOrCreate(['name' => 'sem_acesso', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole($roleSuper);

        $this->usuarioSemPermissao = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->usuarioSemPermissao->assignRole($roleSemAcesso);
    }

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_tipos_de_ocorrencia(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/tipo-ocorrencias')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_tipos_de_ocorrencia(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $tipo = TipoOcorrencia::create([
            'nome' => 'Atraso na Chegada',
            'categoria' => 'operacional',
            'gravidade' => 'leve',
            'notificar_responsaveis_padrao' => true,
        ]);

        Livewire::test(ListTipoOcorrencias::class)
            ->assertCanSeeTableRecords([$tipo])
            ->assertSee('Atraso na Chegada');
    }

    public function test_admin_pode_criar_tipo_de_ocorrencia(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateTipoOcorrencia::class)
            ->fillForm([
                'nome' => 'Elogio por Atitude Solidária',
                'categoria' => 'pedagogico',
                'gravidade' => 'positiva',
                'notificar_responsaveis_padrao' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tipo_ocorrencias', [
            'nome' => 'Elogio por Atitude Solidária',
            'categoria' => 'pedagogico',
            'gravidade' => 'positiva',
        ]);
    }

    public function test_validacao_rejeita_tipo_de_ocorrencia_sem_campos_obrigatorios(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateTipoOcorrencia::class)
            ->fillForm([
                'nome' => '',
                'categoria' => null,
                'gravidade' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'nome' => 'required',
                'categoria' => 'required',
                'gravidade' => 'required',
            ]);
    }

    public function test_admin_pode_editar_tipo_de_ocorrencia(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $tipo = TipoOcorrencia::create([
            'nome' => 'Uniforme Despadronizado',
            'categoria' => 'operacional',
            'gravidade' => 'leve',
            'notificar_responsaveis_padrao' => false,
        ]);

        Livewire::test(EditTipoOcorrencia::class, ['record' => $tipo->id])
            ->fillForm([
                'nome' => 'Uniforme Despadronizado (Reincidência)',
                'gravidade' => 'media',
                'notificar_responsaveis_padrao' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tipo->refresh();

        $this->assertEquals('Uniforme Despadronizado (Reincidência)', $tipo->nome);
        $this->assertEquals('media', $tipo->gravidade);
        $this->assertTrue((bool) $tipo->notificar_responsaveis_padrao);
    }
}

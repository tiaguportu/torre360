<?php

namespace Tests\Feature;

use App\Filament\Resources\VideoTutorials\Pages\CreateVideoTutorial;
use App\Filament\Resources\VideoTutorials\Pages\EditVideoTutorial;
use App\Filament\Resources\VideoTutorials\Pages\ListVideoTutorials;
use App\Models\User;
use App\Models\VideoTutorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VideoTutorialFilamentTest extends TestCase
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

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_videos_tutoriais(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/video-tutorials')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_videos_tutoriais(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $tutorial = VideoTutorial::create([
            'titulo' => 'Como Emitir Boletim Escolar',
            'descricao' => 'Passo a passo da emissão de boletins com QR Code',
            'categoria' => 'Secretaria',
            'chave_pagina' => 'matriculas',
            'ordem' => 1,
            'ativo' => true,
        ]);

        Livewire::test(ListVideoTutorials::class)
            ->assertCanSeeTableRecords([$tutorial])
            ->assertSee('Como Emitir Boletim Escolar');
    }

    public function test_admin_pode_criar_video_tutorial(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateVideoTutorial::class)
            ->fillForm([
                'titulo' => 'Lançamento Rápido de Frequência',
                'descricao' => 'Guia prático para professores realizarem a chamada.',
                'categoria' => 'Acadêmico',
                'chave_pagina' => 'cronograma-aula-lancar-frequencia',
                'ordem' => 2,
                'ativo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('video_tutorial', [
            'titulo' => 'Lançamento Rápido de Frequência',
            'chave_pagina' => 'cronograma-aula-lancar-frequencia',
        ]);
    }

    public function test_validacao_rejeita_video_tutorial_sem_titulo(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateVideoTutorial::class)
            ->fillForm([
                'titulo' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'titulo' => 'required',
            ]);
    }

    public function test_admin_pode_editar_video_tutorial(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $tutorial = VideoTutorial::create([
            'titulo' => 'Tutorial Básico',
            'descricao' => 'Descrição inicial',
            'ativo' => true,
        ]);

        Livewire::test(EditVideoTutorial::class, ['record' => $tutorial->id])
            ->fillForm([
                'titulo' => 'Tutorial Avançado de Configurações',
                'ativo' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tutorial->refresh();

        $this->assertEquals('Tutorial Avançado de Configurações', $tutorial->titulo);
        $this->assertFalse((bool) $tutorial->ativo);
    }
}

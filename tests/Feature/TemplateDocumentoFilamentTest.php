<?php

namespace Tests\Feature;

use App\Enums\TipoTemplateDocumento;
use App\Filament\Resources\TemplateDocumentos\Pages\CreateTemplateDocumento;
use App\Filament\Resources\TemplateDocumentos\Pages\EditTemplateDocumento;
use App\Filament\Resources\TemplateDocumentos\Pages\ListTemplateDocumentos;
use App\Models\TemplateDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TemplateDocumentoFilamentTest extends TestCase
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

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_modelos_de_documentos(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/template-documentos')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_modelos_de_documentos(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $template = TemplateDocumento::create([
            'nome' => 'Declaração de Matrícula e Frequência Regular',
            'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
            'conteudo' => '<p>Declaramos que o aluno {{ALUNO_NOME}} está regularmente matriculado.</p>',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);

        Livewire::test(ListTemplateDocumentos::class)
            ->assertCanSeeTableRecords([$template])
            ->assertSee('Declaração de Matrícula e Frequência Regular');
    }

    public function test_admin_pode_criar_modelo_de_documento(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateTemplateDocumento::class)
            ->fillForm([
                'nome' => 'Declaração de Transferência Escolar',
                'tipo' => TipoTemplateDocumento::DeclaracaoTransferencia->value,
                'validade_dias' => 60,
                'is_ativo' => true,
                'conteudo' => '<p>Certificamos para os devidos fins a transferência do aluno {{ALUNO_NOME}}.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('template_documentos', [
            'nome' => 'Declaração de Transferência Escolar',
            'tipo' => TipoTemplateDocumento::DeclaracaoTransferencia->value,
            'validade_dias' => 60,
        ]);
    }

    public function test_validacao_rejeita_modelo_sem_campos_obrigatorios(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateTemplateDocumento::class)
            ->fillForm([
                'nome' => '',
                'conteudo' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'nome' => 'required',
                'conteudo' => 'required',
            ]);
    }

    public function test_admin_pode_editar_modelo_de_documento(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $template = TemplateDocumento::create([
            'nome' => 'Atestado de Conclusão Inicial',
            'tipo' => TipoTemplateDocumento::DeclaracaoConclusao,
            'conteudo' => '<p>Concluiu com êxito o ano letivo.</p>',
            'validade_dias' => 90,
            'is_ativo' => true,
        ]);

        Livewire::test(EditTemplateDocumento::class, ['record' => $template->id])
            ->fillForm([
                'nome' => 'Atestado de Conclusão Oficial',
                'validade_dias' => 120,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $template->refresh();

        $this->assertEquals('Atestado de Conclusão Oficial', $template->nome);
        $this->assertEquals(120, $template->validade_dias);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoriaExigenciaDocumento;
use App\Filament\Resources\TipoDocumentos\Pages\ListTipoDocumentos;
use App\Models\Curso;
use App\Models\TipoDocumento;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use Database\Factories\PeriodoLetivoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TipoDocumentoBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->adminUser->assignRole($role);
        session(['active_role' => 'super_admin']);
    }

    public function test_usuario_com_permissao_pode_editar_categoria_exigencia_em_lote(): void
    {
        $this->actingAs($this->adminUser);

        $tipo1 = TipoDocumento::create([
            'nome' => 'Certidão de Nascimento',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OPCIONAL,
            'flag_obrigatorio' => false,
        ]);

        $tipo2 = TipoDocumento::create([
            'nome' => 'RG do Aluno',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OPCIONAL,
            'flag_obrigatorio' => false,
        ]);

        $tipo3 = TipoDocumento::create([
            'nome' => 'Carteira de Vacinação',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OPCIONAL,
            'flag_obrigatorio' => false,
        ]);

        Livewire::test(ListTipoDocumentos::class)
            ->assertStatus(200)
            ->callTableBulkAction('editar_lote', [$tipo1, $tipo2], data: [
                'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO->value,
                'modo_cursos' => 'manter',
                'modo_turmas' => 'manter',
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        $this->assertEquals(CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO, $tipo1->fresh()->categoria_exigencia);
        $this->assertTrue($tipo1->fresh()->flag_obrigatorio);

        $this->assertEquals(CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO, $tipo2->fresh()->categoria_exigencia);
        $this->assertTrue($tipo2->fresh()->flag_obrigatorio);

        // Registro não selecionado permanece inalterado
        $this->assertEquals(CategoriaExigenciaDocumento::OPCIONAL, $tipo3->fresh()->categoria_exigencia);
        $this->assertFalse($tipo3->fresh()->flag_obrigatorio);
    }

    public function test_editar_cursos_em_lote_substituir_e_limpar(): void
    {
        $this->actingAs($this->adminUser);

        $unidade = Unidade::create(['nome' => 'Unidade Principal']);
        $curso1 = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $curso2 = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Médio', 'nome_interno' => 'EM']);

        $tipo1 = TipoDocumento::create([
            'nome' => 'Histórico Escolar',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_HISTORICO,
        ]);
        $tipo2 = TipoDocumento::create([
            'nome' => 'Certificado de Conclusão',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_HISTORICO,
        ]);

        // 1. Substituir cursos em lote
        Livewire::test(ListTipoDocumentos::class)
            ->assertStatus(200)
            ->callTableBulkAction('editar_lote', [$tipo1, $tipo2], data: [
                'modo_cursos' => 'substituir',
                'cursos' => [$curso1->id, $curso2->id],
                'modo_turmas' => 'manter',
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        $this->assertEqualsCanonicalizing([$curso1->id, $curso2->id], $tipo1->fresh()->cursos->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$curso1->id, $curso2->id], $tipo2->fresh()->cursos->pluck('id')->all());

        // 2. Limpar cursos em lote (tornando disponível para todos os cursos)
        Livewire::test(ListTipoDocumentos::class)
            ->assertStatus(200)
            ->callTableBulkAction('editar_lote', [$tipo1, $tipo2], data: [
                'modo_cursos' => 'limpar',
                'modo_turmas' => 'manter',
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        $this->assertEmpty($tipo1->fresh()->cursos);
        $this->assertEmpty($tipo2->fresh()->cursos);
    }

    public function test_editar_turmas_em_lote_substituir_e_limpar(): void
    {
        $this->actingAs($this->adminUser);

        $turma = Turma::create(['nome' => '1º Ano A', 'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao()]);

        $tipo = TipoDocumento::create([
            'nome' => 'Ficha Individual',
            'categoria_exigencia' => CategoriaExigenciaDocumento::INTERNO,
        ]);

        // 1. Vincular turma em lote
        Livewire::test(ListTipoDocumentos::class)
            ->assertStatus(200)
            ->callTableBulkAction('editar_lote', [$tipo], data: [
                'modo_cursos' => 'manter',
                'modo_turmas' => 'substituir',
                'turmas' => [$turma->id],
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        $this->assertEquals([$turma->id], $tipo->fresh()->turmas->pluck('id')->all());

        // 2. Limpar turmas em lote
        Livewire::test(ListTipoDocumentos::class)
            ->assertStatus(200)
            ->callTableBulkAction('editar_lote', [$tipo], data: [
                'modo_cursos' => 'manter',
                'modo_turmas' => 'limpar',
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        $this->assertEmpty($tipo->fresh()->turmas);
    }

    public function test_editar_modelo_link_em_lote(): void
    {
        $this->actingAs($this->adminUser);

        $tipo1 = TipoDocumento::create(['nome' => 'Contrato Assinado']);
        $tipo2 = TipoDocumento::create(['nome' => 'Termo de Adesão']);

        Livewire::test(ListTipoDocumentos::class)
            ->assertStatus(200)
            ->callTableBulkAction('editar_lote', [$tipo1, $tipo2], data: [
                'modelo_link' => 'https://exemplo.com/modelo-instrucoes',
                'modo_cursos' => 'manter',
                'modo_turmas' => 'manter',
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        $this->assertEquals('https://exemplo.com/modelo-instrucoes', $tipo1->fresh()->modelo_link);
        $this->assertEquals('https://exemplo.com/modelo-instrucoes', $tipo2->fresh()->modelo_link);
    }

    public function test_bulk_action_fica_oculta_sem_permissao_update(): void
    {
        $tipo = TipoDocumento::create(['nome' => 'Documento Teste']);

        $roleComum = Role::firstOrCreate(['name' => 'secretaria', 'guard_name' => 'web']);
        $userSemUpdate = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $userSemUpdate->assignRole($roleComum);

        Permission::firstOrCreate(['name' => 'ViewAny:TipoDocumento', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:TipoDocumento', 'guard_name' => 'web']);
        $roleComum->givePermissionTo(['ViewAny:TipoDocumento', 'View:TipoDocumento']);

        session(['active_role' => 'secretaria']);

        Livewire::actingAs($userSemUpdate)
            ->test(ListTipoDocumentos::class)
            ->assertStatus(200)
            ->assertTableBulkActionHidden('editar_lote');
    }

    public function test_aviso_quando_nenhuma_alteracao_e_fornecida(): void
    {
        $this->actingAs($this->adminUser);

        $tipo = TipoDocumento::create([
            'nome' => 'Documento Original',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OPCIONAL,
        ]);

        Livewire::test(ListTipoDocumentos::class)
            ->assertStatus(200)
            ->callTableBulkAction('editar_lote', [$tipo], data: [
                'categoria_exigencia' => null,
                'modelo_link' => null,
                'modo_cursos' => 'manter',
                'modo_turmas' => 'manter',
            ])
            ->assertNotified();

        $this->assertEquals(CategoriaExigenciaDocumento::OPCIONAL, $tipo->fresh()->categoria_exigencia);
    }
}

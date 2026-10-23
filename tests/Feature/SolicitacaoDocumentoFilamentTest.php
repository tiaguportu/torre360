<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusSolicitacaoDocumento;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Resources\SolicitacaoDocumentos\Pages\ListSolicitacaoDocumentos;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SolicitacaoDocumentoFilamentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $usuarioSemPermissao;

    private Matricula $matricula;

    private TemplateDocumento $template;

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

        $unidade = Unidade::create(['nome' => 'Colégio Torre']);
        $curso = Curso::create(['nome_externo' => 'Ensino Médio', 'nome_interno' => 'EM', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '1ª Série EM', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:15:00', 'hora_fim' => '12:45:00']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        $turma = Turma::create([
            'nome' => '1º Ano A',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'periodo_letivo_id' => $periodo->id,
        ]);

        $aluno = Pessoa::create([
            'nome' => 'Beatriz Mendes',
            'cpf' => '12345678901',
            'data_nascimento' => '2010-06-15',
        ]);

        $this->matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $this->template = TemplateDocumento::create([
            'nome' => 'Declaração de Matrícula Oficial',
            'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
            'conteudo' => '<p>Declaramos que {{ALUNO_NOME}} está matriculado sob protocolo {{PROTOCOLO}}.</p>',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);
    }

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_solicitacoes_documentos(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/solicitacao-documentos')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_solicitacoes_de_documentos(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $solicitacao = SolicitacaoDocumento::create([
            'protocolo' => 'DOC-2026-000123',
            'codigo_verificacao' => 'TR36-VAL-1234-ABCD',
            'matricula_id' => $this->matricula->id,
            'template_documento_id' => $this->template->id,
            'solicitado_por_user_id' => $this->admin->id,
            'status' => StatusSolicitacaoDocumento::Disponivel,
            'data_solicitacao' => now(),
            'data_emissao' => now(),
            'data_validade' => now()->addDays(30),
        ]);

        Livewire::test(ListSolicitacaoDocumentos::class)
            ->assertCanSeeTableRecords([$solicitacao])
            ->assertSee('DOC-2026-000123')
            ->assertSee('Beatriz Mendes')
            ->assertSee('Declaração de Matrícula Oficial');
    }

    public function test_acao_recusar_solicitacao_atualiza_status_justificativa_e_atendente(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $solicitacao = SolicitacaoDocumento::create([
            'protocolo' => 'DOC-2026-000456',
            'codigo_verificacao' => 'TR36-REC-5678-WXYZ',
            'matricula_id' => $this->matricula->id,
            'template_documento_id' => $this->template->id,
            'solicitado_por_user_id' => $this->admin->id,
            'status' => StatusSolicitacaoDocumento::Solicitado,
            'data_solicitacao' => now(),
        ]);

        Livewire::test(ListSolicitacaoDocumentos::class)
            ->callTableAction('recusar', $solicitacao, data: [
                'justificativa_recusa' => 'Documentação cadastral incompleta.',
            ])
            ->assertHasNoTableActionErrors();

        $solicitacao->refresh();

        $this->assertEquals(StatusSolicitacaoDocumento::Rejeitado, $solicitacao->status);
        $this->assertEquals('Documentação cadastral incompleta.', $solicitacao->justificativa_recusa);
        $this->assertEquals($this->admin->id, $solicitacao->atendido_por_user_id);
    }

    public function test_acao_gerar_pdf_emite_arquivo_e_marca_como_disponivel(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $solicitacao = SolicitacaoDocumento::create([
            'protocolo' => 'DOC-2026-000789',
            'codigo_verificacao' => 'TR36-PDF-9999-EFGH',
            'matricula_id' => $this->matricula->id,
            'template_documento_id' => $this->template->id,
            'solicitado_por_user_id' => $this->admin->id,
            'status' => StatusSolicitacaoDocumento::Solicitado,
            'data_solicitacao' => now(),
        ]);

        Livewire::test(ListSolicitacaoDocumentos::class)
            ->callTableAction('gerar_pdf', $solicitacao)
            ->assertHasNoTableActionErrors();

        $solicitacao->refresh();

        $this->assertEquals(StatusSolicitacaoDocumento::Disponivel, $solicitacao->status);
        $this->assertNotEmpty($solicitacao->arquivo_path);
        Storage::disk('local')->assertExists($solicitacao->arquivo_path);
    }

    public function test_filtro_por_status_funciona_na_listagem(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $solicitacaoPendente = SolicitacaoDocumento::create([
            'protocolo' => 'DOC-PENDENTE-01',
            'codigo_verificacao' => 'TR36-PEN-1111',
            'matricula_id' => $this->matricula->id,
            'template_documento_id' => $this->template->id,
            'solicitado_por_user_id' => $this->admin->id,
            'status' => StatusSolicitacaoDocumento::Solicitado,
            'data_solicitacao' => now(),
        ]);

        $solicitacaoDisponivel = SolicitacaoDocumento::create([
            'protocolo' => 'DOC-DISPONIVEL-02',
            'codigo_verificacao' => 'TR36-DIS-2222',
            'matricula_id' => $this->matricula->id,
            'template_documento_id' => $this->template->id,
            'solicitado_por_user_id' => $this->admin->id,
            'status' => StatusSolicitacaoDocumento::Disponivel,
            'data_solicitacao' => now(),
        ]);

        Livewire::test(ListSolicitacaoDocumentos::class)
            ->filterTable('status', StatusSolicitacaoDocumento::Solicitado->value)
            ->assertCanSeeTableRecords([$solicitacaoPendente])
            ->assertCanNotSeeTableRecords([$solicitacaoDisponivel]);
    }
}

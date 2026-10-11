<?php

namespace Tests\Feature;

use App\Enums\PrioridadeChamado;
use App\Enums\StatusChamado;
use App\Filament\Resources\AtendimentoChamados\Pages\CreateAtendimentoChamado;
use App\Filament\Resources\AtendimentoChamados\Pages\ListAtendimentoChamados;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoMensagem;
use App\Models\AtendimentoSetor;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AtendimentoChamadosCicloTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $usuarioSemPermissao;

    private AtendimentoSetor $setorSecretaria;

    private Pessoa $solicitante;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->usuarioSemPermissao = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->usuarioSemPermissao->assignRole('aluno');

        $this->setorSecretaria = AtendimentoSetor::create([
            'nome' => 'Secretaria Acadêmica',
            'descricao' => 'Atendimento para declarações e documentos',
            'email_notificacao' => 'secretaria@escola.com',
            'ativo' => true,
            'ordem' => 1,
        ]);

        $this->solicitante = Pessoa::factory()->create([
            'nome' => 'Carlos Responsavel',
        ]);
    }

    public function test_usuario_sem_permissao_nao_acessa_atendimento_chamados(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'aluno']);

        $this->get('/admin/atendimento-chamados')->assertForbidden();

        Livewire::test(ListAtendimentoChamados::class)->assertForbidden();
    }

    public function test_criacao_de_chamado_com_protocolo_gerado_automaticamente(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $this->get('/admin/atendimento-chamados')->assertSuccessful();

        Livewire::test(CreateAtendimentoChamado::class)
            ->fillForm([
                'setor_id' => $this->setorSecretaria->id,
                'prioridade' => PrioridadeChamado::Urgente,
                'solicitante_id' => $this->solicitante->id,
                'assunto' => 'Solicitação de 2a via de histórico escolar',
                'status' => StatusChamado::Aberto,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $chamado = AtendimentoChamado::where('assunto', 'Solicitação de 2a via de histórico escolar')->first();

        $this->assertNotNull($chamado);
        $this->assertNotNull($chamado->protocolo);
        $this->assertStringStartsWith('ATD-', $chamado->protocolo);
        $this->assertEquals(StatusChamado::Aberto, $chamado->status);
        $this->assertEquals(PrioridadeChamado::Urgente, $chamado->prioridade);
    }

    public function test_atendente_responde_chamado_e_atualiza_status_via_action(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $chamado = AtendimentoChamado::create([
            'setor_id' => $this->setorSecretaria->id,
            'solicitante_id' => $this->solicitante->id,
            'assunto' => 'Dúvida sobre transporte escolar',
            'prioridade' => PrioridadeChamado::Normal,
            'status' => StatusChamado::Aberto,
        ]);

        Livewire::test(ListAtendimentoChamados::class)
            ->callTableAction('responder', $chamado, [
                'novo_status' => StatusChamado::EmAndamento,
                'mensagem' => 'Olá! Seu chamado está sendo analisado pelo setor de transporte.',
            ])
            ->assertHasNoTableActionErrors();

        $mensagem = AtendimentoMensagem::where('chamado_id', $chamado->id)->first();

        $this->assertNotNull($mensagem);
        $this->assertEquals($this->superAdmin->id, $mensagem->user_id);
        $this->assertEquals('Olá! Seu chamado está sendo analisado pelo setor de transporte.', $mensagem->mensagem);

        $chamadoAtualizado = $chamado->fresh();
        $this->assertEquals(StatusChamado::EmAndamento, $chamadoAtualizado->status);
        $this->assertEquals($this->superAdmin->id, $chamadoAtualizado->responsavel_atendimento_id);
    }

    public function test_setores_de_atendimento_filtra_setores_ativos(): void
    {
        $setorInativo = AtendimentoSetor::create([
            'nome' => 'Setor Desativado',
            'ativo' => false,
            'ordem' => 99,
        ]);

        $setoresAtivos = AtendimentoSetor::where('ativo', true)->pluck('id');

        $this->assertTrue($setoresAtivos->contains($this->setorSecretaria->id));
        $this->assertFalse($setoresAtivos->contains($setorInativo->id));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusTransferencia;
use App\Enums\TipoTemplateDocumento;
use App\Enums\TipoTransferencia;
use App\Filament\Resources\TransferenciasEscolares\Pages\CreateTransferenciaEscolar;
use App\Filament\Resources\TransferenciasEscolares\Pages\ListTransferenciasEscolares;
use App\Models\HistoricoEscolar;
use App\Models\HistoricoEscolarAno;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\TemplateDocumento;
use App\Models\TransferenciaEscolar;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TransferenciaEscolarTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:TransferenciaEscolar", 'guard_name' => 'web']);
            $role->givePermissionTo($permissao);
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    private function criarMatriculaAtiva(): Matricula
    {
        $turma = Turma::factory()->create();
        $periodo = PeriodoLetivo::factory()->create();
        $aluno = Pessoa::factory()->create();

        return Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);
    }

    private function criarTemplateDeclaracaoTransferencia(): TemplateDocumento
    {
        return TemplateDocumento::create([
            'nome' => 'Declaração de Transferência',
            'tipo' => TipoTemplateDocumento::DeclaracaoTransferencia,
            'conteudo' => '<p>Certificamos a transferência de {{ALUNO_NOME}}.</p>',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);
    }

    public function test_pagina_de_listagem_admin_carrega(): void
    {
        $this->autenticarComoAdmin();
        TransferenciaEscolar::factory()->count(2)->create();

        Livewire::test(ListTransferenciasEscolares::class)
            ->assertSuccessful();
    }

    public function test_criar_entrada_via_formulario(): void
    {
        $this->autenticarComoAdmin();
        $matricula = $this->criarMatriculaAtiva();

        Livewire::test(CreateTransferenciaEscolar::class)
            ->fillForm([
                'matricula_id' => $matricula->id,
                'tipo' => TipoTransferencia::Entrada->value,
                'escola_externa_nome' => 'Colégio Anterior',
                'data' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('transferencias_escolares', [
            'matricula_id' => $matricula->id,
            'tipo' => TipoTransferencia::Entrada->value,
            'escola_externa_nome' => 'Colégio Anterior',
        ]);
    }

    public function test_concluir_saida_emite_declaracao_e_cancela_matricula(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $matricula = $this->criarMatriculaAtiva();
        $this->criarTemplateDeclaracaoTransferencia();

        $transferencia = TransferenciaEscolar::factory()->create([
            'matricula_id' => $matricula->id,
            'tipo' => TipoTransferencia::Saida,
            'status' => StatusTransferencia::EmAndamento,
        ]);

        $transferencia->concluirSaida();

        $transferencia->refresh();
        $this->assertSame(StatusTransferencia::Concluida, $transferencia->status);
        $this->assertNotNull($transferencia->solicitacao_documento_id);

        $solicitacao = $transferencia->solicitacaoDocumento;
        $this->assertNotNull($solicitacao->arquivo_path);
        Storage::disk('local')->assertExists($solicitacao->arquivo_path);

        $this->assertSame(SituacaoMatricula::CANCELADA, $matricula->fresh()->situacao);
    }

    public function test_concluir_saida_sem_template_ativo_lanca_excecao(): void
    {
        $matricula = $this->criarMatriculaAtiva();

        $transferencia = TransferenciaEscolar::factory()->create([
            'matricula_id' => $matricula->id,
            'tipo' => TipoTransferencia::Saida,
            'status' => StatusTransferencia::EmAndamento,
        ]);

        $this->expectException(\DomainException::class);
        $transferencia->concluirSaida();
    }

    public function test_acao_concluir_saida_na_tabela(): void
    {
        Storage::fake('local');
        $this->autenticarComoAdmin();

        $matricula = $this->criarMatriculaAtiva();
        $this->criarTemplateDeclaracaoTransferencia();

        $transferencia = TransferenciaEscolar::factory()->create([
            'matricula_id' => $matricula->id,
            'tipo' => TipoTransferencia::Saida,
            'status' => StatusTransferencia::EmAndamento,
        ]);

        Livewire::test(ListTransferenciasEscolares::class)
            ->callTableAction('concluir_saida', $transferencia);

        $this->assertSame(StatusTransferencia::Concluida, $transferencia->fresh()->status);
        $this->assertSame(SituacaoMatricula::CANCELADA, $matricula->fresh()->situacao);
    }

    public function test_marcar_historico_recebido_para_entrada(): void
    {
        $matricula = $this->criarMatriculaAtiva();

        $historico = HistoricoEscolar::create([
            'pessoa_id' => $matricula->pessoa_id,
            'codigo_autenticidade' => HistoricoEscolar::gerarCodigoAutenticidade(),
            'situacao' => 'em_andamento',
            'data_emissao' => now(),
        ]);

        $ano = HistoricoEscolarAno::create([
            'historico_escolar_id' => $historico->id,
            'ano_letivo' => 2025,
            'serie_nome' => '8º Ano',
            'ordem' => 1,
            'tipo' => 'externo',
            'escola_nome' => 'Colégio Anterior',
            'situacao_ano' => 'Aprovado',
        ]);

        $transferencia = TransferenciaEscolar::factory()->create([
            'matricula_id' => $matricula->id,
            'tipo' => TipoTransferencia::Entrada,
            'status' => StatusTransferencia::EmAndamento,
            'historico_recebido' => false,
        ]);

        $transferencia->marcarHistoricoRecebido($ano);

        $transferencia->refresh();
        $this->assertTrue($transferencia->historico_recebido);
        $this->assertSame(StatusTransferencia::Concluida, $transferencia->status);
        $this->assertSame($ano->id, $transferencia->historico_escolar_ano_id);
    }
}

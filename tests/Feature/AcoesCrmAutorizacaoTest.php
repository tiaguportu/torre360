<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Enums\StatusVisitaInteressado;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Filament\Resources\Interessados\RelationManagers\DocumentosCandidatoRelationManager;
use App\Filament\Resources\Interessados\RelationManagers\VisitasRelationManager;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Support\PermissaoAcao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ações personalizadas do Filament NÃO herdam a policy do recurso (o padrão é "liberado para todos"). Quem só
 * visualiza leads não pode registrar contato, gerar o link do portal da família, acionar IA paga, aprovar
 * documentos nem alterar visitas.
 */
class AcoesCrmAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    private Interessado $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $status = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        TipoContatoInteressado::firstOrCreate(['nome' => 'Presencial']);

        $this->lead = Interessado::create([
            'pessoa_id' => Pessoa::factory()->create(['telefone' => '11988887777'])->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
        ]);

        InteressadoDependente::create([
            'interessado_id' => $this->lead->id,
            'nome_crianca' => 'Lucas Silva',
        ]);
    }

    /**
     * @param  list<string>  $permissoes
     */
    private function usuarioCom(array $permissoes): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);

        foreach ($permissoes as $nome) {
            $usuario->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        return $usuario;
    }

    private function leitor(): User
    {
        return $this->usuarioCom(['ViewAny:Interessado', 'View:Interessado']);
    }

    private function operador(): User
    {
        return $this->usuarioCom(['ViewAny:Interessado', 'View:Interessado', 'Update:Interessado']);
    }

    private function documento(): DocumentoInserido
    {
        $tipo = TipoDocumento::create(['nome' => 'RG', 'flag_obrigatorio' => true, 'status' => 'ativo']);

        return DocumentoInserido::create([
            'interessado_id' => $this->lead->id,
            'tipo_documento_id' => $tipo->id,
            'arquivo_path' => 'documentos_candidatos/'.$this->lead->id.'/rg.pdf',
            'nome_arquivo_original' => 'rg.pdf',
            'hash_arquivo' => 'abc',
            'status' => SituacaoDocumento::EM_ANALISE,
        ]);
    }

    // ------------------------------------------------------------------ helper

    public function test_permissao_acao_exige_ao_menos_uma_das_permissoes(): void
    {
        $autorizado = PermissaoAcao::qualquer('Update:Interessado', 'Create:HistoricoContato');

        $this->actingAs($this->usuarioCom(['Create:HistoricoContato']));
        $this->assertTrue($autorizado());

        $this->actingAs($this->leitor());
        $this->assertFalse($autorizado());
    }

    public function test_permissao_acao_nega_sem_usuario_logado(): void
    {
        $this->assertFalse(PermissaoAcao::qualquer('Update:Interessado')());
    }

    // ------------------------------------------------------------------ tabela de interessados

    /**
     * @return list<string>
     */
    private static function acoesDaTabelaComEfeito(): array
    {
        return ['registrarAtendimento', 'enviarWhatsapp', 'gerarConvite', 'agendarVisita', 'dossieIa', 'copilotoIa', 'resumoConversaIa'];
    }

    public function test_quem_so_visualiza_leads_nao_ve_acoes_com_efeito_colateral_na_tabela(): void
    {
        $componente = Livewire::actingAs($this->leitor())->test(ListInteressados::class)->assertSuccessful();

        foreach (self::acoesDaTabelaComEfeito() as $acao) {
            $componente->assertTableActionHidden($acao, $this->lead);
        }
    }

    public function test_quem_pode_editar_leads_ve_as_acoes_da_tabela(): void
    {
        $componente = Livewire::actingAs($this->operador())->test(ListInteressados::class)->assertSuccessful();

        foreach (self::acoesDaTabelaComEfeito() as $acao) {
            $componente->assertTableActionVisible($acao, $this->lead);
        }
    }

    public function test_quem_so_registra_contatos_ve_o_atendimento_mas_nao_o_link_do_portal(): void
    {
        $usuario = $this->usuarioCom(['ViewAny:Interessado', 'View:Interessado', 'Create:HistoricoContato']);

        Livewire::actingAs($usuario)
            ->test(ListInteressados::class)
            ->assertTableActionVisible('registrarAtendimento', $this->lead)
            ->assertTableActionVisible('enviarWhatsapp', $this->lead)
            ->assertTableActionVisible('copilotoIa', $this->lead)
            ->assertTableActionHidden('gerarConvite', $this->lead)
            ->assertTableActionHidden('dossieIa', $this->lead)
            ->assertTableActionHidden('resumoConversaIa', $this->lead);
    }

    public function test_agendar_visita_aceita_a_permissao_especifica_de_visita(): void
    {
        $usuario = $this->usuarioCom(['ViewAny:Interessado', 'View:Interessado', 'Create:VisitaInteressado']);

        Livewire::actingAs($usuario)
            ->test(ListInteressados::class)
            ->assertTableActionVisible('agendarVisita', $this->lead)
            ->assertTableActionHidden('gerarConvite', $this->lead);
    }

    public function test_registrar_atendimento_grava_para_quem_tem_permissao(): void
    {
        $tipo = TipoContatoInteressado::where('nome', 'Presencial')->firstOrFail();

        Livewire::actingAs($this->operador())
            ->test(ListInteressados::class)
            ->callTableAction('registrarAtendimento', $this->lead, data: [
                'tipo_contato_interessado_id' => $tipo->id,
                'data_contato' => now()->subMinute()->format('Y-m-d H:i:s'),
                'relato' => 'Conversa com a mãe.',
            ])
            ->assertNotified('Atendimento registrado com sucesso!');

        $this->assertSame(1, HistoricoContato::where('interessado_id', $this->lead->id)->count());
    }

    public function test_importar_lead_com_ia_exige_permissao_de_criar_lead(): void
    {
        Livewire::actingAs($this->leitor())
            ->test(ListInteressados::class)
            ->assertActionHidden('importarComIA');

        Livewire::actingAs($this->usuarioCom(['ViewAny:Interessado', 'View:Interessado', 'Create:Interessado']))
            ->test(ListInteressados::class)
            ->assertActionVisible('importarComIA');
    }

    public function test_abrir_o_modal_do_link_do_portal_so_gera_token_para_quem_pode_editar(): void
    {
        Livewire::actingAs($this->leitor())
            ->test(ListInteressados::class)
            ->assertTableActionHidden('gerarConvite', $this->lead);

        $this->assertNull($this->lead->fresh()->token_documentos);
        $this->assertNull($this->lead->fresh()->token_convite);
    }

    // ------------------------------------------------------------------ documentos do candidato

    private function documentosRm(User $usuario): Testable
    {
        return Livewire::actingAs($usuario)->test(DocumentosCandidatoRelationManager::class, [
            'ownerRecord' => $this->lead,
            'pageClass' => EditInteressado::class,
        ]);
    }

    public function test_quem_so_visualiza_nao_aprova_nem_rejeita_nem_gera_link_do_portal(): void
    {
        $documento = $this->documento();

        $this->documentosRm($this->leitor())
            ->assertTableActionHidden('aprovar', $documento)
            ->assertTableActionHidden('rejeitar', $documento)
            ->assertTableActionHidden('linkPortal')
            ->assertTableActionHidden('enviarWhatsapp');

        $this->assertSame(SituacaoDocumento::EM_ANALISE, $documento->fresh()->status);
        $this->assertNull($this->lead->fresh()->token_documentos);
    }

    public function test_quem_pode_editar_o_lead_ve_as_acoes_de_documentos(): void
    {
        $documento = $this->documento();

        $this->documentosRm($this->operador())
            ->assertTableActionVisible('aprovar', $documento)
            ->assertTableActionVisible('rejeitar', $documento)
            ->assertTableActionVisible('linkPortal');
    }

    // ------------------------------------------------------------------ visitas

    private function visitaAgendada(): VisitaInteressado
    {
        return VisitaInteressado::create([
            'interessado_id' => $this->lead->id,
            'data_hora' => now()->addDay(),
            'status' => StatusVisitaInteressado::Agendada,
        ]);
    }

    private function visitasRm(User $usuario): Testable
    {
        return Livewire::actingAs($usuario)->test(VisitasRelationManager::class, [
            'ownerRecord' => $this->lead,
            'pageClass' => EditInteressado::class,
        ]);
    }

    public function test_quem_so_visualiza_nao_altera_o_status_da_visita(): void
    {
        $visita = $this->visitaAgendada();
        $leitor = $this->usuarioCom(['ViewAny:VisitaInteressado', 'View:VisitaInteressado']);

        $this->visitasRm($leitor)
            ->assertTableActionHidden('realizada', $visita)
            ->assertTableActionHidden('faltou', $visita);

        $this->assertSame(StatusVisitaInteressado::Agendada, $visita->fresh()->status);
    }

    public function test_quem_pode_editar_visitas_marca_como_realizada(): void
    {
        $visita = $this->visitaAgendada();
        $editor = $this->usuarioCom(['ViewAny:VisitaInteressado', 'View:VisitaInteressado', 'Update:VisitaInteressado']);

        $this->visitasRm($editor)
            ->assertTableActionVisible('realizada', $visita)
            ->callTableAction('realizada', $visita);

        $this->assertSame(StatusVisitaInteressado::Realizada, $visita->fresh()->status);
    }

    public function test_pesquisa_de_satisfacao_nao_e_criada_ao_apenas_visualizar_a_tabela_de_visitas(): void
    {
        $visita = $this->visitaAgendada();
        $visita->update(['status' => StatusVisitaInteressado::Realizada]);
        $leitor = $this->usuarioCom(['ViewAny:VisitaInteressado', 'View:VisitaInteressado']);

        $this->visitasRm($leitor)->assertSuccessful();

        $this->assertNull($visita->fresh()->pesquisa);
    }
}

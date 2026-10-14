<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Filament\Resources\IndicacaoInteressados\IndicacaoInteressadoResource;
use App\Filament\Resources\PropostaComercials\Pages\CreatePropostaComercial;
use App\Filament\Resources\ReguaFollowUps\Pages\ListReguaFollowUps;
use App\Models\ComunicacaoEmMassa;
use App\Models\IndicacaoInteressado;
use App\Models\Interessado;
use App\Models\Pessoa;
use App\Models\ReguaFollowUp;
use App\Models\User;
use App\Services\ComunicacaoEmMassaService;
use App\Services\CrmIaVendasService;
use App\Services\ReguaFollowUpService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Lote K: fecha as pendências do inventário do Lote J (J5) — superfícies do CRM que ainda
 * ignoravam o escopo de carteira do D7 (consultor vê os próprios leads e os sem dono).
 */
class CrmEscopoSuperficiesRestantesLoteKTest extends TestCase
{
    use RefreshDatabase;

    private User $consultorA;

    private User $consultorB;

    private Interessado $leadA;

    private Interessado $leadB;

    private Interessado $leadSemDono;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consultorA = $this->criarUsuario('Consultor A');
        $this->consultorB = $this->criarUsuario('Consultor B');

        $this->leadA = $this->criarLead($this->consultorA, 'Familia Alfa');
        $this->leadB = $this->criarLead($this->consultorB, 'Familia Beta');
        $this->leadSemDono = $this->criarLead(null, 'Familia Sem Dono');
    }

    private function criarUsuario(string $nome, array $permissoes = []): User
    {
        $usuario = User::factory()->create(['name' => $nome, 'activated_at' => now()]);

        $padrao = [
            'ViewAny:Interessado', 'View:Interessado', 'Update:Interessado', 'Create:Interessado',
            'ViewAny:PropostaComercial', 'View:PropostaComercial', 'Create:PropostaComercial',
            'ViewAny:IndicacaoInteressado', 'View:IndicacaoInteressado', 'Update:IndicacaoInteressado', 'Create:IndicacaoInteressado',
            'ViewAny:ReguaFollowUp', 'View:ReguaFollowUp', 'Execute:ReguaFollowUp',
        ];

        foreach (array_merge($padrao, $permissoes) as $permissao) {
            $usuario->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        return $usuario;
    }

    private function criarSuperAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Super Admin', 'activated_at' => now()]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function criarLead(?User $consultor, string $nomePessoa): Interessado
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => $nomePessoa,
            'email' => str_replace(' ', '.', strtolower($nomePessoa)).'@exemplo.com',
            'aceita_comunicacao' => true,
        ]);

        return Interessado::factory()->emAtendimento()->create([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => $consultor?->id,
        ]);
    }

    // ───────────────────────── K1: Proposta comercial ─────────────────────────

    public function test_proposta_nao_pre_preenche_com_lead_de_outro_consultor_via_url(): void
    {
        $componente = Livewire::withQueryParams(['interessado_id' => $this->leadB->id])
            ->actingAs($this->consultorA)
            ->test(CreatePropostaComercial::class);

        $this->assertNull($componente->get('data.interessado_id'));
        $this->assertNotSame('Familia Beta', $componente->get('data.responsavel_nome'));
    }

    public function test_proposta_pre_preenche_com_lead_proprio_e_sem_dono(): void
    {
        $proprio = Livewire::withQueryParams(['interessado_id' => $this->leadA->id])
            ->actingAs($this->consultorA)
            ->test(CreatePropostaComercial::class);

        $this->assertSame('Familia Alfa', $proprio->get('data.responsavel_nome'));

        $semDono = Livewire::withQueryParams(['interessado_id' => $this->leadSemDono->id])
            ->actingAs($this->consultorA)
            ->test(CreatePropostaComercial::class);

        $this->assertSame('Familia Sem Dono', $semDono->get('data.responsavel_nome'));
    }

    public function test_proposta_super_admin_pre_preenche_com_qualquer_lead(): void
    {
        $componente = Livewire::withQueryParams(['interessado_id' => $this->leadB->id])
            ->actingAs($this->criarSuperAdmin())
            ->test(CreatePropostaComercial::class);

        $this->assertSame('Familia Beta', $componente->get('data.responsavel_nome'));
    }

    public function test_proposta_recusa_no_servidor_interessado_id_de_lead_alheio(): void
    {
        $componente = Livewire::actingAs($this->consultorA)
            ->test(CreatePropostaComercial::class)
            ->fillForm(['interessado_id' => $this->leadB->id])
            ->call('create');

        $componente->assertHasFormErrors(['interessado_id']);
    }

    // ───────────────────────── K2: Indicações (MGM) ─────────────────────────

    private function criarIndicacao(Interessado $lead): IndicacaoInteressado
    {
        return IndicacaoInteressado::create([
            'indicador_pessoa_id' => Pessoa::factory()->create()->id,
            'interessado_id' => $lead->id,
            'codigo_indicacao' => 'FAM-'.$lead->id,
            'status' => IndicacaoInteressado::STATUS_PENDENTE,
        ]);
    }

    public function test_indicacoes_listam_apenas_leads_visiveis_para_o_consultor(): void
    {
        $indA = $this->criarIndicacao($this->leadA);
        $indB = $this->criarIndicacao($this->leadB);
        $indSemDono = $this->criarIndicacao($this->leadSemDono);

        $this->actingAs($this->consultorA);
        $ids = IndicacaoInteressadoResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($indA->id));
        $this->assertTrue($ids->contains($indSemDono->id));
        $this->assertFalse($ids->contains($indB->id));
    }

    public function test_indicacoes_super_admin_e_view_all_veem_todas(): void
    {
        $indA = $this->criarIndicacao($this->leadA);
        $indB = $this->criarIndicacao($this->leadB);

        $this->actingAs($this->criarSuperAdmin());
        $this->assertEqualsCanonicalizing(
            [$indA->id, $indB->id],
            IndicacaoInteressadoResource::getEloquentQuery()->pluck('id')->all()
        );

        $gestor = $this->criarUsuario('Gestor', ['ViewAll:Interessado']);
        $this->actingAs($gestor);
        $this->assertCount(2, IndicacaoInteressadoResource::getEloquentQuery()->get());
    }

    // ───────────────────────── K3: Dossiê PDF ─────────────────────────

    private function mockDossie(): void
    {
        $pdf = Mockery::mock(PDF::class);
        $pdf->shouldReceive('download')->andReturn(response('pdf-ok'));
        $pdf->shouldReceive('stream')->andReturn(response('pdf-ok'));

        $this->mock(CrmIaVendasService::class, function ($mock) use ($pdf): void {
            $mock->shouldReceive('gerarPdfDossie')->andReturn($pdf);
        });
    }

    public function test_dossie_pdf_recusa_lead_de_outro_consultor(): void
    {
        $this->mockDossie();

        $this->actingAs($this->consultorA)
            ->get(route('crm.interessados.dossie-pdf', $this->leadB))
            ->assertForbidden();

        $this->actingAs($this->consultorA)
            ->get(route('crm.interessados.dossie-pdf.stream', $this->leadB))
            ->assertForbidden();
    }

    public function test_dossie_pdf_permite_lead_proprio_sem_dono_e_view_all(): void
    {
        $this->mockDossie();

        $this->actingAs($this->consultorA)
            ->get(route('crm.interessados.dossie-pdf', $this->leadA))
            ->assertOk();

        $this->actingAs($this->consultorA)
            ->get(route('crm.interessados.dossie-pdf', $this->leadSemDono))
            ->assertOk();

        $this->actingAs($this->criarSuperAdmin())
            ->get(route('crm.interessados.dossie-pdf', $this->leadB))
            ->assertOk();
    }

    // ───────────────────────── K4: Comunicação em massa ─────────────────────────

    private function comunicacao(array $filtros, ?User $criador): ComunicacaoEmMassa
    {
        return ComunicacaoEmMassa::factory()->paraInteressados($filtros)->create([
            'enviado_por_user_id' => $criador?->id,
        ]);
    }

    /**
     * @return list<string>
     */
    private function nomesDestinatarios(ComunicacaoEmMassa $comunicacao): array
    {
        return app(ComunicacaoEmMassaService::class)->destinatarios($comunicacao)
            ->pluck('nome')->sort()->values()->all();
    }

    public function test_comunicacao_em_massa_de_consultor_atinge_so_a_carteira_dele(): void
    {
        $statusId = $this->leadA->status_interessado_id;
        $this->leadB->update(['status_interessado_id' => $statusId]);
        $this->leadSemDono->update(['status_interessado_id' => $statusId]);

        $comunicacao = $this->comunicacao(['status_interessado_ids' => [$statusId]], $this->consultorA);

        $this->assertSame(['Familia Alfa', 'Familia Sem Dono'], $this->nomesDestinatarios($comunicacao));
    }

    public function test_comunicacao_em_massa_com_ids_explicitos_ignora_lead_alheio(): void
    {
        $comunicacao = $this->comunicacao(
            ['interessado_ids' => [$this->leadA->id, $this->leadB->id]],
            $this->consultorA
        );

        $this->assertSame(['Familia Alfa'], $this->nomesDestinatarios($comunicacao));
    }

    public function test_comunicacao_em_massa_de_super_admin_e_de_view_all_atinge_todos(): void
    {
        $statusId = $this->leadA->status_interessado_id;
        $this->leadB->update(['status_interessado_id' => $statusId]);

        $porAdmin = $this->comunicacao(['status_interessado_ids' => [$statusId]], $this->criarSuperAdmin());
        $this->assertSame(['Familia Alfa', 'Familia Beta'], $this->nomesDestinatarios($porAdmin));

        $gestor = $this->criarUsuario('Gestor', ['ViewAll:Interessado']);
        $porGestor = $this->comunicacao(['status_interessado_ids' => [$statusId]], $gestor);
        $this->assertSame(['Familia Alfa', 'Familia Beta'], $this->nomesDestinatarios($porGestor));
    }

    public function test_comunicacao_legada_sem_criador_nao_e_restringida(): void
    {
        $statusId = $this->leadA->status_interessado_id;
        $this->leadB->update(['status_interessado_id' => $statusId]);

        $comunicacao = $this->comunicacao(['status_interessado_ids' => [$statusId]], null);

        $this->assertSame(['Familia Alfa', 'Familia Beta'], $this->nomesDestinatarios($comunicacao));
    }

    // ───────────────────────── K5: Teste de disparo da régua ─────────────────────────

    private function criarRegua(): ReguaFollowUp
    {
        return ReguaFollowUp::create([
            'nome' => 'Regua de teste K',
            'gatilho' => GatilhoReguaFollowUp::ContatoAtrasado,
            'dias_offset' => 1,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Oi',
            'mensagem' => 'Oi',
            'is_ativo' => true,
        ]);
    }

    public function test_testar_disparo_recusa_lead_de_outro_consultor(): void
    {
        $regua = $this->criarRegua();

        $servico = Mockery::mock(ReguaFollowUpService::class)->makePartial();
        $servico->shouldNotReceive('enviarNotificacao');
        $this->app->instance(ReguaFollowUpService::class, $servico);

        Livewire::actingAs($this->consultorA)
            ->test(ListReguaFollowUps::class)
            ->callTableAction('testar', $regua, data: ['interessado_id' => $this->leadB->id]);

        $this->addToAssertionCount(1);
    }

    public function test_testar_disparo_funciona_com_lead_proprio(): void
    {
        $regua = $this->criarRegua();

        $servico = Mockery::mock(ReguaFollowUpService::class)->makePartial();
        $servico->shouldReceive('enviarNotificacao')->once()->andReturn(['sucesso' => true, 'mensagem' => 'ok']);
        $this->app->instance(ReguaFollowUpService::class, $servico);

        Livewire::actingAs($this->consultorA)
            ->test(ListReguaFollowUps::class)
            ->callTableAction('testar', $regua, data: ['interessado_id' => $this->leadA->id]);

        $this->addToAssertionCount(1);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\RelationManagers\HistoricosRelationManager;
use App\Filament\Resources\Interessados\RelationManagers\TimelineRelationManager;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\LeadSlaService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * O SLA de 1ª resposta só encerrava pela ação "Atendimento" da tabela. Contatos registrados na Linha do Tempo
 * 360° (interação rápida) ou na aba "Histórico de Contatos" gravavam o histórico mas deixavam
 * `data_primeiro_contato` vazia: o lead seguia "sem resposta" e podia gerar alerta de SLA estourado.
 */
class CrmSlaPrimeiroContatoPelaFichaTest extends TestCase
{
    use RefreshDatabase;

    private Interessado $lead;

    private TipoContatoInteressado $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $usuario = User::factory()->create(['activated_at' => now()]);
        $usuario->assignRole('super_admin');
        $this->actingAs($usuario);

        $status = StatusInteressado::create(['nome' => 'Novo', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);

        // A factory já preenche `data_primeiro_contato`; aqui o lead ainda não teve resposta da escola.
        $this->lead = Interessado::factory()->create([
            'usuario_id' => $usuario->id,
            'status_interessado_id' => $status->id,
            'data_primeiro_contato' => null,
        ]);

        $this->whatsapp = TipoContatoInteressado::create(['nome' => 'WhatsApp']);
    }

    private function timeline(): Testable
    {
        return Livewire::test(TimelineRelationManager::class, [
            'ownerRecord' => $this->lead,
            'pageClass' => EditInteressado::class,
        ]);
    }

    private function historicos(): Testable
    {
        return Livewire::test(HistoricosRelationManager::class, [
            'ownerRecord' => $this->lead,
            'pageClass' => EditInteressado::class,
        ]);
    }

    public function test_interacao_rapida_da_linha_do_tempo_encerra_o_sla_e_a_segunda_nao_sobrescreve(): void
    {
        $this->assertNull($this->lead->data_primeiro_contato);

        $this->timeline()
            ->set('novoTipoContatoId', $this->whatsapp->id)
            ->set('novoRelato', 'Falei com a mãe pelo WhatsApp.')
            ->call('registrarContatoRapido')
            ->assertHasNoErrors();

        $lead = Interessado::with('status')->findOrFail($this->lead->id);
        $this->assertNotNull($lead->data_primeiro_contato);
        $this->assertTrue(app(LeadSlaService::class)->resumoSla($lead)['respondido']);
        $this->assertFalse(app(LeadSlaService::class)->slaEstourado($lead, now()->addYear()));

        $primeiro = $lead->data_primeiro_contato->timestamp;

        $this->travel(2)->hours();

        $this->timeline()
            ->set('novoTipoContatoId', $this->whatsapp->id)
            ->set('novoRelato', 'Retornei com os valores.')
            ->call('registrarContatoRapido')
            ->assertHasNoErrors();

        $this->assertSame(
            $primeiro,
            Interessado::findOrFail($this->lead->id)->data_primeiro_contato->timestamp,
            'Só o primeiro contato define a data; os seguintes não a sobrescrevem.'
        );
    }

    public function test_interacao_rapida_com_proximo_contato_reagenda_o_lead_e_sem_data_mantem_o_agendamento(): void
    {
        $agendado = now()->addDays(5)->startOfMinute();
        $this->lead->forceFill(['data_proximo_contato' => $agendado])->saveQuietly();

        $this->timeline()
            ->set('novoTipoContatoId', $this->whatsapp->id)
            ->set('novoRelato', 'Contato sem nova data combinada.')
            ->call('registrarContatoRapido')
            ->assertHasNoErrors();

        $this->assertEquals($agendado->timestamp, Interessado::findOrFail($this->lead->id)->data_proximo_contato->timestamp);

        $novaData = now()->addDays(2)->startOfMinute();

        $this->timeline()
            ->set('novoTipoContatoId', $this->whatsapp->id)
            ->set('novoRelato', 'Combinamos um novo retorno.')
            ->set('novaDataProximoContato', $novaData->format('Y-m-d\TH:i'))
            ->call('registrarContatoRapido')
            ->assertHasNoErrors();

        $this->assertEquals($novaData->timestamp, Interessado::findOrFail($this->lead->id)->data_proximo_contato->timestamp);
    }

    public function test_registro_administrativo_do_funil_na_linha_do_tempo_nao_conta_como_primeiro_contato(): void
    {
        $funil = TipoContatoInteressado::porNome(TipoContatoInteressado::FUNIL);

        $this->timeline()
            ->set('novoTipoContatoId', $funil->id)
            ->set('novoRelato', 'Ajuste de etapa feito a mão.')
            ->call('registrarContatoRapido')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('historico_contato', ['interessado_id' => $this->lead->id, 'tipo_contato_interessado_id' => $funil->id]);
        $this->assertNull(Interessado::findOrFail($this->lead->id)->data_primeiro_contato);
    }

    public function test_registrar_contato_do_historico_encerra_o_sla_com_a_data_do_contato(): void
    {
        $quando = now()->subDay()->startOfMinute();

        $this->historicos()
            ->callAction(TestAction::make('create')->table(), data: [
                'tipo_contato_interessado_id' => $this->whatsapp->id,
                'data_contato' => $quando->format('Y-m-d H:i:s'),
                'resultado' => 'retornar',
                'relato' => 'Ligação de apresentação da escola.',
            ])
            ->assertHasNoFormErrors();

        $lead = Interessado::with('status')->findOrFail($this->lead->id);
        $this->assertNotNull($lead->data_primeiro_contato);
        $this->assertEquals($quando->timestamp, $lead->data_primeiro_contato->timestamp);
        $this->assertTrue(app(LeadSlaService::class)->resumoSla($lead)['respondido']);
        $this->assertNotNull($lead->lead_score_atualizado_em);
    }

    public function test_registro_administrativo_no_historico_nao_conta_como_primeiro_contato(): void
    {
        $formulario = TipoContatoInteressado::porNome(TipoContatoInteressado::FORMULARIO_SITE);

        $this->historicos()
            ->callAction(TestAction::make('create')->table(), data: [
                'tipo_contato_interessado_id' => $formulario->id,
                'data_contato' => now()->format('Y-m-d H:i:s'),
                'relato' => 'Família reenviou o formulário.',
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('historico_contato', ['interessado_id' => $this->lead->id, 'tipo_contato_interessado_id' => $formulario->id]);
        $this->assertNull(Interessado::findOrFail($this->lead->id)->data_primeiro_contato);
    }
}

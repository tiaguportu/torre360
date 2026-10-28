<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\RelationManagers\HistoricosRelationManager;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * `data_primeiro_contato` acompanha o histórico: editar a data/tipo/autoria do contato que a definia, ou excluí-lo,
 * reavalia a data (e a limpa quando não sobra nenhum contato da escola). Leads antigos, com a data vinda de
 * outra fonte, não podem ter a data limpa por mexer num registro qualquer.
 */
class PrimeiroContatoReavaliacaoTest extends TestCase
{
    use RefreshDatabase;

    private Interessado $lead;

    private User $usuario;

    private TipoContatoInteressado $ligacao;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->usuario = User::factory()->create(['activated_at' => now()]);
        $this->usuario->assignRole('super_admin');
        $this->actingAs($this->usuario);

        $status = StatusInteressado::create(['nome' => 'Novo', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);

        $this->lead = Interessado::factory()->create([
            'usuario_id' => $this->usuario->id,
            'status_interessado_id' => $status->id,
            'data_primeiro_contato' => null,
        ]);

        $this->ligacao = TipoContatoInteressado::create(['nome' => 'Ligação']);
    }

    private function contato(string $quando, array $extra = []): HistoricoContato
    {
        return $this->lead->historicos()->create($extra + [
            'tipo_contato_interessado_id' => $this->ligacao->id,
            'usuario_id' => $this->usuario->id,
            'relato' => 'Contato de teste.',
            'data_contato' => $quando,
        ]);
    }

    private function definirPrimeiroContato(string $quando): void
    {
        $this->lead->forceFill(['data_primeiro_contato' => $quando])->saveQuietly();
    }

    private function dataDoLead(): ?string
    {
        return Interessado::query()->whereKey($this->lead->id)->toBase()->value('data_primeiro_contato');
    }

    private function historicos(): Testable
    {
        return Livewire::test(HistoricosRelationManager::class, [
            'ownerRecord' => $this->lead,
            'pageClass' => EditInteressado::class,
        ]);
    }

    // ─── Regras (modelo) ───────────────────────────────────────

    public function test_mudar_a_data_do_contato_que_definia_o_primeiro_contato_passa_a_valer_o_mais_antigo_que_restou(): void
    {
        $a = $this->contato('2026-10-01 09:00:00');
        $this->contato('2026-10-03 10:00:00');
        $this->definirPrimeiroContato('2026-10-01 09:00:00');

        $a->update(['data_contato' => '2026-10-05 08:00:00']);

        $this->assertSame('2026-10-03 10:00:00', $this->dataDoLead());
    }

    public function test_antecipar_um_contato_para_antes_do_primeiro_antecipa_a_data(): void
    {
        $this->contato('2026-10-03 09:00:00');
        $b = $this->contato('2026-10-05 10:00:00');
        $this->definirPrimeiroContato('2026-10-03 09:00:00');

        $b->update(['data_contato' => '2026-10-01 07:30:00']);

        $this->assertSame('2026-10-01 07:30:00', $this->dataDoLead());
    }

    public function test_excluir_o_contato_que_definia_a_data_usa_o_seguinte_e_sem_nenhum_limpa_a_data(): void
    {
        $a = $this->contato('2026-10-01 09:00:00');
        $b = $this->contato('2026-10-03 10:00:00');
        $this->definirPrimeiroContato('2026-10-01 09:00:00');

        $a->delete();
        $this->assertSame('2026-10-03 10:00:00', $this->dataDoLead());

        $b->delete();
        $this->assertNull($this->dataDoLead(), 'Sem contato da escola, o lead volta a ficar sem primeiro contato.');
    }

    public function test_excluir_contato_que_nao_definia_a_data_nao_altera_nada(): void
    {
        $this->contato('2026-10-01 09:00:00');
        $b = $this->contato('2026-10-03 10:00:00');
        $this->definirPrimeiroContato('2026-10-01 09:00:00');

        $b->delete();

        $this->assertSame('2026-10-01 09:00:00', $this->dataDoLead());
    }

    public function test_lead_antigo_com_data_de_outra_fonte_nao_tem_a_data_limpa_ao_mexer_no_historico(): void
    {
        // Data legada (gravada no cadastro, sem contato humano correspondente) + registro administrativo no mesmo instante.
        $this->definirPrimeiroContato('2026-09-01 08:00:00');
        $formulario = $this->contato('2026-09-01 08:00:00', [
            'tipo_contato_interessado_id' => TipoContatoInteressado::porNome(TipoContatoInteressado::FORMULARIO_SITE)->id,
            'usuario_id' => null,
        ]);
        $humano = $this->contato('2026-10-03 10:00:00');

        $formulario->update(['data_contato' => '2026-09-02 08:00:00']);
        $this->assertSame('2026-09-01 08:00:00', $this->dataDoLead(), 'Registro administrativo não define o primeiro contato.');

        $formulario->delete();
        $this->assertSame('2026-09-01 08:00:00', $this->dataDoLead());

        $humano->delete();
        $this->assertSame('2026-09-01 08:00:00', $this->dataDoLead(), 'A data legada não vem desse contato: não se limpa.');
    }

    public function test_tirar_o_contato_da_regra_por_tipo_administrativo_ou_automatico_reavalia_a_data(): void
    {
        $a = $this->contato('2026-10-01 09:00:00');
        $this->definirPrimeiroContato('2026-10-01 09:00:00');

        $a->update(['tipo_contato_interessado_id' => TipoContatoInteressado::porNome(TipoContatoInteressado::FUNIL)->id]);
        $this->assertNull($this->dataDoLead(), 'Virou movimentação de funil e não restou contato da escola.');

        $b = $this->contato('2026-10-04 09:00:00');
        $this->definirPrimeiroContato('2026-10-04 09:00:00');

        $b->update(['automatico' => true]);
        $this->assertNull($this->dataDoLead(), 'Registro automático não é contato da escola.');
    }

    public function test_virar_contato_da_escola_preenche_a_data_de_um_lead_sem_ela(): void
    {
        $registro = $this->contato('2026-10-02 09:00:00', [
            'tipo_contato_interessado_id' => TipoContatoInteressado::porNome(TipoContatoInteressado::FUNIL)->id,
        ]);
        $this->assertNull($this->dataDoLead());

        $registro->update(['tipo_contato_interessado_id' => $this->ligacao->id]);

        $this->assertSame('2026-10-02 09:00:00', $this->dataDoLead());
    }

    public function test_editar_so_o_relato_nao_mexe_na_data(): void
    {
        $a = $this->contato('2026-10-01 09:00:00');
        $this->definirPrimeiroContato('2026-10-01 09:00:00');

        $a->update(['relato' => 'Texto corrigido.']);

        $this->assertSame('2026-10-01 09:00:00', $this->dataDoLead());
    }

    // ─── Pela aba "Histórico de Contatos" ──────────────────────

    public function test_editar_pela_aba_historico_reavalia_a_data(): void
    {
        $a = $this->contato('2026-10-01 09:00:00');
        $this->contato('2026-10-03 10:00:00');
        $this->definirPrimeiroContato('2026-10-01 09:00:00');

        $this->historicos()
            ->callAction(TestAction::make('edit')->table($a), data: [
                'tipo_contato_interessado_id' => $this->ligacao->id,
                'data_contato' => '2026-10-06 11:00:00',
                'relato' => 'Data corrigida.',
            ])
            ->assertHasNoFormErrors();

        $this->assertSame('2026-10-03 10:00:00', $this->dataDoLead());
    }

    public function test_excluir_pela_aba_historico_reavalia_a_data(): void
    {
        $a = $this->contato('2026-10-01 09:00:00');
        $this->contato('2026-10-03 10:00:00');
        $this->definirPrimeiroContato('2026-10-01 09:00:00');

        $this->historicos()->callAction(TestAction::make('delete')->table($a));

        $this->assertSame('2026-10-03 10:00:00', $this->dataDoLead());
    }

    public function test_excluir_em_lote_pela_aba_historico_limpa_a_data_quando_nao_resta_contato(): void
    {
        $a = $this->contato('2026-10-01 09:00:00');
        $b = $this->contato('2026-10-03 10:00:00');
        $this->definirPrimeiroContato('2026-10-01 09:00:00');

        $this->historicos()
            ->selectTableRecords([$a->id, $b->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertSame(0, HistoricoContato::query()->where('interessado_id', $this->lead->id)->count());
        $this->assertNull($this->dataDoLead());
    }
}

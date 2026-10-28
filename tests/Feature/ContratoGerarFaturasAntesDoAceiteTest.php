<?php

namespace Tests\Feature;

use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TemplateContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Gerar Faturas Automaticamente" na edição do contrato: as faturas são geradas ANTES da
 * assinatura (elas constam no contrato enviado para assinar), então a falta de data de
 * aceite não pode impedir a geração.
 */
class ContratoGerarFaturasAntesDoAceiteTest extends TestCase
{
    use RefreshDatabase;

    private Matricula $matricula;

    private TemplateContrato $template;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny:Contrato', 'View:Contrato', 'Update:Contrato'] as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $admin = User::factory()->create(['activated_at' => now()->subDay(), 'deactivated_at' => null]);
        $admin->assignRole($role);
        $this->actingAs($admin);

        $this->matricula = Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create()->id]);
        $this->template = TemplateContrato::create(['nome' => 'Template', 'conteudo' => '<p>Minuta</p>', 'is_padrao' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function contrato(?string $dataAceite): Contrato
    {
        return Contrato::create([
            'matricula_id' => $this->matricula->id,
            'template_contrato_id' => $this->template->id,
            'valor_total' => 1200.00,
            'data_aceite' => $dataAceite,
            'assinafy_status' => 'pending',
        ]);
    }

    public function test_gera_faturas_de_contrato_sem_data_de_aceite_partindo_de_hoje(): void
    {
        Carbon::setTestNow('2026-03-02 10:00:00'); // segunda-feira
        $contrato = $this->contrato(null);

        Livewire::test(EditContrato::class, ['record' => $contrato->id])
            ->assertActionExists('gerarFaturas')
            ->mountAction('gerarFaturas')
            ->assertSchemaStateSet(['data_base' => '2026-03-02'])
            ->fillForm(['quantidade_parcelas' => 12, 'valor_entrada' => 0])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Faturas geradas com sucesso!');

        $faturas = $contrato->faturas()->orderBy('vencimento')->get();
        $this->assertCount(12, $faturas);
        $this->assertSame('2026-03-09', $faturas->first()->vencimento->toDateString()); // 5 dias úteis depois de hoje
        $this->assertNull($contrato->fresh()->data_aceite, 'Gerar as faturas não inventa um aceite.');
    }

    public function test_sugere_a_data_de_aceite_quando_o_contrato_ja_foi_assinado(): void
    {
        Carbon::setTestNow('2026-03-02 10:00:00');
        $contrato = $this->contrato('2026-01-10 14:00:00');

        Livewire::test(EditContrato::class, ['record' => $contrato->id])
            ->mountAction('gerarFaturas')
            ->assertSchemaStateSet(['data_base' => '2026-01-10']);
    }

    public function test_usa_a_data_base_escolhida_pelo_operador(): void
    {
        $contrato = $this->contrato(null);

        Livewire::test(EditContrato::class, ['record' => $contrato->id])
            ->callAction('gerarFaturas', ['quantidade_parcelas' => 3, 'valor_entrada' => 300, 'data_base' => '2026-02-10'])
            ->assertHasNoActionErrors();

        $faturas = $contrato->faturas()->orderBy('vencimento')->get();
        $this->assertCount(4, $faturas); // entrada + 3 parcelas
        $this->assertSame('2026-02-10', $faturas[0]->vencimento->toDateString()); // entrada na data-base
        $this->assertSame('2026-02-17', $faturas[1]->vencimento->toDateString()); // 1ª parcela: 5 dias úteis depois
    }
}

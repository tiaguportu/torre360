<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\LeadDuplicadoDetectorService;
use App\Services\LeadMesclagemService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeadDuplicadosCarteiraEscopoLoteF5Test extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $status;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->status = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
        TipoContatoInteressado::firstOrCreate(['nome' => TipoContatoInteressado::FUNIL]);

        Permission::firstOrCreate(['name' => 'ViewAny:Interessado', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Interessado', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAll:Interessado', 'guard_name' => 'web']);
    }

    private function criarConsultor(string $nome = 'Consultor'): User
    {
        $usuario = User::factory()->create([
            'name' => $nome,
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);

        $usuario->givePermissionTo('ViewAny:Interessado', 'View:Interessado', 'Update:Interessado');

        return $usuario;
    }

    private function criarLead(?User $consultor, string $telefone, string $nome = 'Lead'): Interessado
    {
        $pessoa = Pessoa::create([
            'nome' => $nome,
            'telefone' => $telefone,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return Interessado::create([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => $consultor?->id,
            'status_interessado_id' => $this->status->id,
            'origem_interessado_id' => $this->origem->id,
        ]);
    }

    public function test_consultor_a_nao_ve_lead_do_consultor_b_no_detector_mas_recebe_aviso_generico(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadA = $this->criarLead($consultorA, '11988887777', 'Lead de A');
        $leadB = $this->criarLead($consultorB, '11988887777', 'Lead de B');

        $detector = app(LeadDuplicadoDetectorService::class);

        // Consultor A verificando leadA
        $duplicadosA = $detector->detectar($leadA, $consultorA);

        // Lead B não deve ser listado como objeto interessado identificável
        $this->assertFalse(
            $duplicadosA->contains(fn (array $item) => $item['interessado']?->id === $leadB->id),
            'Lead de outro consultor não pode aparecer identificado para consultor sem permissão.'
        );

        // Deve conter o item indicando que há duplicado fora da carteira
        $itemForaCarteira = $duplicadosA->first(fn (array $item) => ($item['fora_carteira'] ?? false) === true);
        $this->assertNotNull($itemForaCarteira);
        $this->assertStringContainsString('fora da sua carteira', $itemForaCarteira['detalhes'][0]);

        // DuplicadosIds de consultor A não contém o id do lead B
        $this->assertNotContains($leadB->id, $detector->duplicadosIds($leadA, $consultorA));
    }

    public function test_consultor_com_view_all_ve_todos_os_duplicados_identificados(): void
    {
        $admin = $this->criarConsultor('Admin');
        $admin->givePermissionTo('ViewAll:Interessado');

        $consultorB = $this->criarConsultor('Consultor B');

        $leadA = $this->criarLead($admin, '11988887777', 'Lead de Admin');
        $leadB = $this->criarLead($consultorB, '11988887777', 'Lead de B');

        $detector = app(LeadDuplicadoDetectorService::class);
        $duplicados = $detector->detectar($leadA, $admin);

        $this->assertTrue(
            $duplicados->contains(fn (array $item) => $item['interessado']?->id === $leadB->id)
        );
        $this->assertContains($leadB->id, $detector->duplicadosIds($leadA, $admin));
    }

    public function test_lead_sem_consultor_e_visivel_para_consultor_como_duplicado(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');

        $leadA = $this->criarLead($consultorA, '11988887777', 'Lead de A');
        $leadSemConsultor = $this->criarLead(null, '11988887777', 'Lead Sem Consultor');

        $detector = app(LeadDuplicadoDetectorService::class);
        $duplicados = $detector->detectar($leadA, $consultorA);

        $this->assertTrue(
            $duplicados->contains(fn (array $item) => $item['interessado']?->id === $leadSemConsultor->id)
        );
    }

    public function test_mesclagem_service_lanca_authorization_exception_se_usuario_nao_enxerga_lead(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadA = $this->criarLead($consultorA, '11988887777', 'Lead A');
        $leadB = $this->criarLead($consultorB, '11988887777', 'Lead B');

        $mesclagemService = app(LeadMesclagemService::class);

        $this->expectException(AuthorizationException::class);
        $mesclagemService->mesclar($leadA, $leadB, $consultorA->id);
    }

    public function test_acao_mesclar_recusa_id_de_outra_carteira_passado_diretamente(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadA = $this->criarLead($consultorA, '11988887777', 'Lead A');
        $leadB = $this->criarLead($consultorB, '11988887777', 'Lead B');

        $this->actingAs($consultorA);

        Livewire::actingAs($consultorA)
            ->test(EditInteressado::class, ['record' => $leadA->id])
            ->callAction('mesclarLead', [
                'lead_origem_id' => $leadB->id,
                'preferir_destino' => 'este',
            ])
            ->assertHasActionErrors(['lead_origem_id']);

        // E se tentasse buscar via query com escopo da action:
        $this->expectException(ModelNotFoundException::class);
        Interessado::query()->visivelPara($consultorA)->findOrFail($leadB->id);
    }
}

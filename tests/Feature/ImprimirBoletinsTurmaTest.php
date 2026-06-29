<?php

namespace Tests\Feature;

use App\Filament\Resources\Turmas\Pages\ListTurmas;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImprimirBoletinsTurmaTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Turma $turma;

    private EtapaAvaliativa $etapa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $this->adminUser->assignRole($role);
        session(['active_role' => 'super_admin']);

        $periodo = PeriodoLetivo::factory()->create();
        $this->turma = Turma::factory()->create([
            'periodo_letivo_id' => $periodo->id,
        ]);

        $this->etapa = EtapaAvaliativa::create([
            'nome' => '1ª Etapa Teste',
            'periodo_letivo_id' => $periodo->id,
            'turma_id' => $this->turma->id,
            'data_inicio' => now()->subMonths(2)->toDateString(),
            'data_fim' => now()->addMonth()->toDateString(),
        ]);
    }

    public function test_usuario_autenticado_pode_baixar_boletim_da_turma_por_etapa(): void
    {
        $matricula = Matricula::factory()->create([
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('turmas.boletins.download', [
                'turma_ids' => [$this->turma->id],
                'etapa_id' => $this->etapa->id,
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_usuario_autenticado_pode_baixar_boletins_em_lote(): void
    {
        $turma2 = Turma::factory()->create([
            'periodo_letivo_id' => $this->etapa->periodo_letivo_id,
        ]);

        $matricula1 = Matricula::factory()->create([
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ]);

        $matricula2 = Matricula::factory()->create([
            'turma_id' => $turma2->id,
            'situacao' => 'ativa',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('turmas.boletins.download', [
                'turma_ids' => [$this->turma->id, $turma2->id],
                'etapa_id' => $this->etapa->id,
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_retorna_erro_se_nao_houver_dados_para_gerar(): void
    {
        // Turma sem matrículas ativas
        $response = $this->actingAs($this->adminUser)
            ->get(route('turmas.boletins.download', [
                'turma_ids' => [$this->turma->id],
                'etapa_id' => $this->etapa->id,
            ]));

        // Deve redirecionar de volta com mensagem de erro
        $response->assertStatus(302);
        $response->assertSessionHas('error');
    }

    public function test_usuario_nao_autenticado_nao_pode_baixar_boletins(): void
    {
        $response = $this->get(route('turmas.boletins.download', [
            'turma_ids' => [$this->turma->id],
            'etapa_id' => $this->etapa->id,
        ]));

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_tela_de_listagem_de_turmas_carrega_com_as_acoes(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ListTurmas::class)
            ->assertStatus(200)
            ->assertTableActionExists('imprimirBoletins')
            ->assertTableBulkActionExists('imprimirBoletinsLote');
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Turmas\Pages\ListTurmas;
use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
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

    public function test_tela_de_listagem_de_turmas_exibe_botao_imprimir_apenas_se_houver_notas(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Criar turma que possui notas
        $turmaComNotas = Turma::factory()->create([
            'periodo_letivo_id' => $this->etapa->periodo_letivo_id,
        ]);
        $matricula = Matricula::factory()->create([
            'turma_id' => $turmaComNotas->id,
            'situacao' => 'ativa',
        ]);
        $avaliacao = Avaliacao::create([
            'turma_id' => $turmaComNotas->id,
            'disciplina_id' => Disciplina::factory()->create()->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'peso_etapa_avaliativa' => 1.0,
            'nota_maxima' => 10.0,
            'data_ocorrencia' => now()->toDateString(),
            'data_limite_lancamento' => now()->addDays(5)->toDateString(),
        ]);
        Nota::create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'valor' => 8.5,
        ]);

        // 2. Testar no Livewire que o botão imprimirBoletins está visível na turma com notas
        // e oculto na turma que não possui notas ($this->turma)
        Livewire::test(ListTurmas::class)
            ->assertStatus(200)
            ->assertTableActionVisible('imprimirBoletins', $turmaComNotas)
            ->assertTableActionHidden('imprimirBoletins', $this->turma)
            ->assertTableBulkActionExists('imprimirBoletinsLote');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\StatusListaEspera;
use App\Filament\Resources\ListaEsperaMatriculas\Pages\CreateListaEsperaMatricula;
use App\Filament\Resources\ListaEsperaMatriculas\Pages\ListListaEsperaMatriculas;
use App\Models\ListaEsperaMatricula;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\ListaEspera\VagaDisponivelNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ListaEsperaMatriculaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $fcmMock = Mockery::mock(FcmService::class);
        $fcmMock->shouldReceive('sendPush')->andReturn(['success' => true]);
        $this->app->instance(FcmService::class, $fcmMock);
    }

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:ListaEsperaMatricula", 'guard_name' => 'web']);
            $role->givePermissionTo($permissao);
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_pagina_de_listagem_admin_carrega(): void
    {
        $this->autenticarComoAdmin();
        ListaEsperaMatricula::factory()->count(2)->create();

        Livewire::test(ListListaEsperaMatriculas::class)
            ->assertSuccessful();
    }

    public function test_criar_entrada_via_formulario(): void
    {
        $this->autenticarComoAdmin();
        $turma = Turma::factory()->create(['vagas_maximas' => 1]);
        $periodo = PeriodoLetivo::factory()->create();
        $pessoa = Pessoa::factory()->create();

        Livewire::test(CreateListaEsperaMatricula::class)
            ->fillForm([
                'turma_id' => $turma->id,
                'periodo_letivo_id' => $periodo->id,
                'pessoa_id' => $pessoa->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('lista_espera_matriculas', [
            'turma_id' => $turma->id,
            'pessoa_id' => $pessoa->id,
            'status' => StatusListaEspera::Aguardando->value,
        ]);
    }

    /**
     * @return array{turma: Turma, periodo: PeriodoLetivo}
     */
    private function turmaLotada(int $vagas = 1): array
    {
        $turma = Turma::factory()->create(['vagas_maximas' => $vagas]);
        $periodo = PeriodoLetivo::factory()->create();

        for ($i = 0; $i < $vagas; $i++) {
            Matricula::factory()->create([
                'turma_id' => $turma->id,
                'periodo_letivo_id' => $periodo->id,
                'situacao' => 'ativa',
            ]);
        }

        return ['turma' => $turma, 'periodo' => $periodo];
    }

    private function criarEsperaNotificavel(Turma $turma, PeriodoLetivo $periodo): array
    {
        $alunoEspera = Pessoa::factory()->create();
        $userEspera = User::factory()->create();
        $alunoEspera->users()->attach($userEspera->id);

        $espera = ListaEsperaMatricula::factory()->create([
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'pessoa_id' => $alunoEspera->id,
        ]);

        return ['espera' => $espera, 'pessoa' => $alunoEspera, 'user' => $userEspera];
    }

    public function test_cancelar_matricula_notifica_o_primeiro_da_fila(): void
    {
        Notification::fake();

        $dados = $this->turmaLotada();
        $matricula = Matricula::where('turma_id', $dados['turma']->id)->first();
        $espera = $this->criarEsperaNotificavel($dados['turma'], $dados['periodo']);

        $matricula->update(['situacao' => 'cancelada']);

        Notification::assertSentTo($espera['user'], VagaDisponivelNotification::class);

        $espera['espera']->refresh();
        $this->assertSame(StatusListaEspera::Notificado, $espera['espera']->status);
        $this->assertNotNull($espera['espera']->notificado_em);
    }

    public function test_excluir_matricula_notifica_o_primeiro_da_fila(): void
    {
        Notification::fake();

        $dados = $this->turmaLotada();
        $matricula = Matricula::where('turma_id', $dados['turma']->id)->first();
        $espera = $this->criarEsperaNotificavel($dados['turma'], $dados['periodo']);

        $matricula->delete();

        Notification::assertSentTo($espera['user'], VagaDisponivelNotification::class);
    }

    public function test_atualizar_campo_nao_relacionado_nao_notifica(): void
    {
        Notification::fake();

        $dados = $this->turmaLotada(vagas: 2);
        $matricula = Matricula::where('turma_id', $dados['turma']->id)->first();
        $this->criarEsperaNotificavel($dados['turma'], $dados['periodo']);

        // Não muda situação nem turma: não deve nem reavaliar a vaga.
        $matricula->update(['risco_evasao_score' => 42]);

        Notification::assertNothingSent();
    }

    public function test_situacao_muda_mas_turma_continua_lotada_nao_notifica(): void
    {
        Notification::fake();

        $turma = Turma::factory()->create(['vagas_maximas' => 2]);
        $periodo = PeriodoLetivo::factory()->create();

        // Turma acima da capacidade (3 ativas para 2 vagas): cancelar uma ainda deixa 2/2.
        $matriculas = Matricula::factory()->count(3)->create([
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);
        $this->criarEsperaNotificavel($turma, $periodo);

        $matriculas->first()->update(['situacao' => 'cancelada']);

        Notification::assertNothingSent();
    }

    public function test_notifica_apenas_o_mais_antigo_da_fila(): void
    {
        Notification::fake();

        $dados = $this->turmaLotada();
        $matricula = Matricula::where('turma_id', $dados['turma']->id)->first();

        $primeiro = $this->criarEsperaNotificavel($dados['turma'], $dados['periodo']);
        $this->travel(1)->minutes();
        $segundo = $this->criarEsperaNotificavel($dados['turma'], $dados['periodo']);

        $matricula->update(['situacao' => 'cancelada']);

        Notification::assertSentTo($primeiro['user'], VagaDisponivelNotification::class);
        Notification::assertNotSentTo($segundo['user'], VagaDisponivelNotification::class);
    }

    public function test_nova_matricula_da_mesma_pessoa_na_turma_marca_convertido(): void
    {
        Notification::fake();

        $dados = $this->turmaLotada();
        $matriculaCancelada = Matricula::where('turma_id', $dados['turma']->id)->first();
        $espera = $this->criarEsperaNotificavel($dados['turma'], $dados['periodo']);

        $matriculaCancelada->update(['situacao' => 'cancelada']);

        Matricula::factory()->create([
            'turma_id' => $dados['turma']->id,
            'periodo_letivo_id' => $dados['periodo']->id,
            'pessoa_id' => $espera['pessoa']->id,
            'situacao' => 'ativa',
        ]);

        $espera['espera']->refresh();
        $this->assertSame(StatusListaEspera::Convertido, $espera['espera']->status);
    }
}

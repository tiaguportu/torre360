<?php

namespace Tests\Feature;

use App\Filament\Resources\OcorrenciaEscolarResource\Pages\CreateOcorrenciaEscolar;
use App\Filament\Resources\OcorrenciaEscolarResource\Pages\ListOcorrenciaEscolars;
use App\Models\Matricula;
use App\Models\OcorrenciaEscolar;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\TipoOcorrencia;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\OcorrenciaRegistradaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OcorrenciaEscolarNotificacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $usuarioSemPermissao;

    private User $responsavelUser;

    private Pessoa $aluno;

    private Matricula $matricula;

    private TipoOcorrencia $tipoOcorrencia;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->usuarioSemPermissao = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);

        $this->responsavelUser = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->responsavelUser->assignRole('responsavel');

        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Pai do Aluno']);
        $responsavelPessoa->users()->attach($this->responsavelUser->id);

        $this->aluno = Pessoa::factory()->create(['nome' => 'Aluno Disciplina']);
        $this->aluno->responsaveis()->attach($responsavelPessoa->id);

        $periodo = PeriodoLetivo::factory()->create();
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);

        $this->matricula = Matricula::factory()->create([
            'pessoa_id' => $this->aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $this->tipoOcorrencia = TipoOcorrencia::create([
            'nome' => 'Atraso na Entrada',
            'categoria' => 'disciplinar',
            'gravidade' => 'leve',
            'notificar_responsaveis_padrao' => true,
        ]);
    }

    public function test_usuario_sem_permissao_nao_pode_acessar_ocorrencias(): void
    {
        $this->actingAs($this->usuarioSemPermissao);

        $this->get('/admin/ocorrencia-escolars')->assertForbidden();

        Livewire::test(ListOcorrenciaEscolars::class)->assertForbidden();
    }

    public function test_super_admin_pode_criar_ocorrencia_e_notificar_responsaveis_automaticamente(): void
    {
        Notification::fake();

        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateOcorrenciaEscolar::class)
            ->fillForm([
                'matricula_id' => $this->matricula->id,
                'tipo_ocorrencia_id' => $this->tipoOcorrencia->id,
                'data_hora' => now()->format('Y-m-d H:i:s'),
                'descricao' => 'Chegou 35 minutos atrasado no 1o horario sem justificativa prévia.',
                'medidas_tomadas' => 'Orientado na secretaria e liberado para a 2a aula.',
                'notificar_responsaveis' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('ocorrencia_escolars', [
            'matricula_id' => $this->matricula->id,
            'tipo_ocorrencia_id' => $this->tipoOcorrencia->id,
            'notificar_responsaveis' => true,
        ]);

        Notification::assertSentTo(
            [$this->responsavelUser],
            OcorrenciaRegistradaNotification::class
        );
    }

    public function test_reenviar_notificacao_action_dispara_envio_aos_responsaveis(): void
    {
        Notification::fake();

        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $ocorrencia = OcorrenciaEscolar::create([
            'matricula_id' => $this->matricula->id,
            'tipo_ocorrencia_id' => $this->tipoOcorrencia->id,
            'registrado_por_user_id' => $this->superAdmin->id,
            'data_hora' => now()->subDay(),
            'descricao' => 'Ocorrência pendente de reenvio',
            'notificar_responsaveis' => false,
        ]);

        Livewire::test(ListOcorrenciaEscolars::class)
            ->callTableAction('reenviarNotificacao', $ocorrencia)
            ->assertHasNoTableActionErrors();

        Notification::assertSentTo(
            [$this->responsavelUser],
            OcorrenciaRegistradaNotification::class
        );

        $this->assertNotNull($ocorrencia->fresh()->notificacao_enviada_em);
    }
}

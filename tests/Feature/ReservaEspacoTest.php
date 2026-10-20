<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusReservaEspaco;
use App\Exceptions\ReservaEspacoIndisponivelException;
use App\Filament\Resources\ReservaEspacos\Pages\CreateReservaEspaco;
use App\Filament\Resources\ReservaEspacos\Pages\EditReservaEspaco;
use App\Filament\Resources\ReservaEspacos\Pages\ListReservaEspacos;
use App\Filament\Resources\ReservaEspacos\Pages\OcupacaoEspacos;
use App\Models\DiaNaoLetivo;
use App\Models\Disciplina;
use App\Models\GradeHorario;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\ReservaEspaco;
use App\Models\Sala;
use App\Models\Turma;
use App\Models\User;
use App\Services\OcupacaoEspacoService;
use App\Services\ReservaEspacoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reserva pontual de espaços (Onda 21). O relógio é fixado numa segunda-feira (19/10/2026) para as datas
 * "próxima segunda" e "terça" serem sempre as mesmas, dentro do período letivo de 2026.
 */
class ReservaEspacoTest extends TestCase
{
    use RefreshDatabase;

    /** Próxima segunda-feira (dia_semana = 1). */
    private const SEGUNDA = '2026-10-26';

    private const TERCA = '2026-10-27';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-19 09:00:00'));
    }

    /**
     * @param  list<string>  $acoes  ações de ReservaEspaco que o papel recebe (ex.: ['ViewAny', 'Create'])
     */
    private function autenticarComo(string $papel, array $acoes): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($acoes as $acao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:ReservaEspaco", 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        $this->actingAs($user);
        session(['active_role' => $papel]);

        return $user;
    }

    private function gestao(): User
    {
        return $this->autenticarComo('coordenador', ['ViewAny', 'View', 'Create', 'Update', 'Confirmar', 'Delete', 'DeleteAny']);
    }

    private function professor(): User
    {
        return $this->autenticarComo('professor', ['ViewAny', 'View', 'Create', 'Update']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(Sala $sala, array $extra = []): array
    {
        return array_merge([
            'sala_id' => $sala->id,
            'data' => self::SEGUNDA,
            'hora_inicio' => '14:00',
            'hora_fim' => '15:00',
            'motivo' => 'Ensaio de formatura',
        ], $extra);
    }

    private function servico(): ReservaEspacoService
    {
        return app(ReservaEspacoService::class);
    }

    // ─── Criação e status inicial ────────────────────────────────────

    public function test_reserva_em_sala_sem_aprovacao_ja_nasce_confirmada(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();

        $reserva = $this->servico()->solicitar($this->dados($sala), $user);

        $this->assertSame(StatusReservaEspaco::Confirmada, $reserva->status);
        $this->assertSame($user->id, $reserva->solicitante_user_id);
        $this->assertSame('14:00:00', $reserva->hora_inicio);
    }

    public function test_sala_com_aprovacao_deixa_o_pedido_do_professor_aguardando(): void
    {
        $professor = $this->professor();
        $sala = Sala::factory()->create(['exige_aprovacao_reserva' => true]);

        $reserva = $this->servico()->solicitar($this->dados($sala), $professor);

        $this->assertSame(StatusReservaEspaco::Solicitada, $reserva->status);
        $this->assertNull($reserva->decidido_por_user_id);
    }

    public function test_quem_pode_aprovar_ja_tem_a_propria_reserva_confirmada(): void
    {
        $gestor = $this->gestao();
        $sala = Sala::factory()->create(['exige_aprovacao_reserva' => true]);

        $reserva = $this->servico()->solicitar($this->dados($sala), $gestor);

        $this->assertSame(StatusReservaEspaco::Confirmada, $reserva->status);
        $this->assertSame($gestor->id, $reserva->decidido_por_user_id);
        $this->assertNotNull($reserva->decidido_em);
    }

    // ─── Conflitos entre reservas ────────────────────────────────────

    public function test_horario_sobreposto_na_mesma_sala_e_bloqueado(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        ReservaEspaco::factory()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '14:00', 'hora_fim' => '15:00', 'motivo' => 'Palestra']);

        try {
            $this->servico()->solicitar($this->dados($sala, ['hora_inicio' => '14:30', 'hora_fim' => '15:30']), $user);
            $this->fail('A sobreposição deveria ser bloqueada.');
        } catch (ReservaEspacoIndisponivelException $e) {
            $this->assertStringContainsString('já está reservado', $e->getMessage());
            $this->assertStringContainsString('Palestra', $e->getMessage());
        }

        $this->assertSame(1, ReservaEspaco::count());
    }

    public function test_faixas_que_apenas_se_tocam_nao_conflitam(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        ReservaEspaco::factory()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '14:00', 'hora_fim' => '15:00']);

        $depois = $this->servico()->solicitar($this->dados($sala, ['hora_inicio' => '15:00', 'hora_fim' => '16:00']), $user);
        $antes = $this->servico()->solicitar($this->dados($sala, ['hora_inicio' => '13:00', 'hora_fim' => '14:00']), $user);

        $this->assertNotNull($depois->id);
        $this->assertNotNull($antes->id);
    }

    public function test_mesmo_horario_em_outra_sala_ou_outra_data_nao_conflita(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        ReservaEspaco::factory()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '14:00', 'hora_fim' => '15:00']);

        $outraSala = $this->servico()->solicitar($this->dados(Sala::factory()->create()), $user);
        $outraData = $this->servico()->solicitar($this->dados($sala, ['data' => self::TERCA]), $user);

        $this->assertNotNull($outraSala->id);
        $this->assertNotNull($outraData->id);
    }

    public function test_reserva_cancelada_libera_o_horario(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        ReservaEspaco::factory()->cancelada()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '14:00', 'hora_fim' => '15:00']);

        $reserva = $this->servico()->solicitar($this->dados($sala), $user);

        $this->assertNotNull($reserva->id);
    }

    public function test_pedido_aguardando_aprovacao_ja_segura_o_horario(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        ReservaEspaco::factory()->solicitada()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '14:00', 'hora_fim' => '15:00']);

        $this->expectException(ReservaEspacoIndisponivelException::class);
        $this->expectExceptionMessage('aguardando aprovação');

        $this->servico()->solicitar($this->dados($sala), $user);
    }

    // ─── Conflito com a grade horária fixa ───────────────────────────

    private function gradeNaSala(Sala $sala, array $turma = [], array $grade = []): GradeHorario
    {
        $turmaModel = Turma::factory()->create($turma);

        return GradeHorario::factory()->create(array_merge([
            'turma_id' => $turmaModel->id,
            'disciplina_id' => Disciplina::factory()->create(['nome' => 'Matemática'])->id,
            'sala_id' => $sala->id,
            'dia_semana' => 1, // segunda-feira
            'hora_inicio' => '14:00:00',
            'hora_fim' => '15:00:00',
        ], $grade));
    }

    public function test_aula_da_grade_fixa_no_mesmo_dia_da_semana_bloqueia_a_reserva(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        $this->gradeNaSala($sala);

        try {
            $this->servico()->solicitar($this->dados($sala), $user);
            $this->fail('A grade fixa deveria bloquear a reserva.');
        } catch (ReservaEspacoIndisponivelException $e) {
            $this->assertStringContainsString('grade horária fixa', $e->getMessage());
            $this->assertStringContainsString('Matemática', $e->getMessage());
        }
    }

    public function test_grade_em_outro_dia_da_semana_ou_horario_nao_bloqueia(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        $this->gradeNaSala($sala);

        $terca = $this->servico()->solicitar($this->dados($sala, ['data' => self::TERCA]), $user);
        $depoisDaAula = $this->servico()->solicitar($this->dados($sala, ['hora_inicio' => '15:00', 'hora_fim' => '16:00']), $user);

        $this->assertNotNull($terca->id);
        $this->assertNotNull($depoisDaAula->id);
    }

    public function test_grade_nao_bloqueia_em_dia_nao_letivo(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        $grade = $this->gradeNaSala($sala);

        DiaNaoLetivo::create([
            'periodo_letivo_id' => $grade->turma->periodo_letivo_id,
            'data' => self::SEGUNDA,
            'descricao' => 'Recesso',
            'flag_ativo' => true,
        ]);

        $reserva = $this->servico()->solicitar($this->dados($sala), $user);

        $this->assertNotNull($reserva->id);
    }

    public function test_grade_nao_bloqueia_fora_do_periodo_letivo_da_turma(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        $periodoEncerrado = PeriodoLetivo::factory()->create(['nome' => '2025', 'data_inicio' => '2025-02-01', 'data_fim' => '2025-12-20']);
        $this->gradeNaSala($sala, ['periodo_letivo_id' => $periodoEncerrado->id]);

        $reserva = $this->servico()->solicitar($this->dados($sala), $user);

        $this->assertNotNull($reserva->id);
    }

    // ─── Validações ──────────────────────────────────────────────────

    public function test_horario_final_precisa_ser_depois_do_inicial(): void
    {
        $this->expectException(ReservaEspacoIndisponivelException::class);
        $this->expectExceptionMessage('horário final');

        $this->servico()->solicitar($this->dados(Sala::factory()->create(), ['hora_inicio' => '15:00', 'hora_fim' => '15:00']), $this->professor());
    }

    public function test_sala_inativa_nao_aceita_reserva(): void
    {
        $this->expectException(ReservaEspacoIndisponivelException::class);
        $this->expectExceptionMessage('inativa');

        $this->servico()->solicitar($this->dados(Sala::factory()->inativa()->create()), $this->professor());
    }

    public function test_nao_reserva_horario_que_ja_passou(): void
    {
        $this->expectException(ReservaEspacoIndisponivelException::class);
        $this->expectExceptionMessage('já passou');

        $this->servico()->solicitar($this->dados(Sala::factory()->create(), ['data' => '2026-10-19', 'hora_inicio' => '07:00', 'hora_fim' => '08:00']), $this->professor());
    }

    public function test_aviso_quando_a_turma_e_maior_que_a_capacidade_da_sala(): void
    {
        $sala = Sala::factory()->create(['capacidade' => 1]);
        $turma = Turma::factory()->create();
        Matricula::factory()->count(2)->create(['turma_id' => $turma->id, 'situacao' => 'ativa']);

        $avisos = $this->servico()->avisos($sala, $turma->id);

        $this->assertCount(1, $avisos);
        $this->assertStringContainsString('comporta 1', $avisos[0]);
        $this->assertSame([], $this->servico()->avisos($sala, null));
    }

    // ─── Atualizar ───────────────────────────────────────────────────

    public function test_alterar_o_proprio_horario_ignora_a_propria_reserva_no_conflito(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        $reserva = $this->servico()->solicitar($this->dados($sala), $user);

        $atualizada = $this->servico()->atualizar($reserva, $this->dados($sala, ['hora_fim' => '15:30']), $user);

        $this->assertSame('15:30:00', $atualizada->hora_fim);
    }

    public function test_alterar_para_horario_ocupado_e_bloqueado(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();
        ReservaEspaco::factory()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '16:00', 'hora_fim' => '17:00']);
        $reserva = $this->servico()->solicitar($this->dados($sala), $user);

        $this->expectException(ReservaEspacoIndisponivelException::class);

        $this->servico()->atualizar($reserva, $this->dados($sala, ['hora_inicio' => '16:30', 'hora_fim' => '17:30']), $user);
    }

    public function test_mudar_horario_em_sala_com_aprovacao_devolve_o_pedido_para_analise(): void
    {
        $professor = $this->professor();
        $sala = Sala::factory()->create(['exige_aprovacao_reserva' => true]);
        $reserva = ReservaEspaco::factory()->create([
            'sala_id' => $sala->id,
            'solicitante_user_id' => $professor->id,
            'data' => self::SEGUNDA,
            'status' => StatusReservaEspaco::Confirmada,
        ]);

        $atualizada = $this->servico()->atualizar($reserva, $this->dados($sala, ['hora_inicio' => '16:00', 'hora_fim' => '17:00']), $professor);

        $this->assertSame(StatusReservaEspaco::Solicitada, $atualizada->status);
        $this->assertNull($atualizada->decidido_em);
    }

    public function test_mudar_so_a_finalidade_nao_mexe_na_situacao(): void
    {
        $professor = $this->professor();
        $sala = Sala::factory()->create(['exige_aprovacao_reserva' => true]);
        $reserva = ReservaEspaco::factory()->create(['sala_id' => $sala->id, 'solicitante_user_id' => $professor->id, 'data' => self::SEGUNDA]);

        $atualizada = $this->servico()->atualizar($reserva, $this->dados($sala, ['motivo' => 'Novo nome']), $professor);

        $this->assertSame(StatusReservaEspaco::Confirmada, $atualizada->status);
        $this->assertSame('Novo nome', $atualizada->motivo);
    }

    public function test_reserva_cancelada_ou_passada_nao_pode_ser_alterada(): void
    {
        $user = $this->professor();
        $sala = Sala::factory()->create();

        $cancelada = ReservaEspaco::factory()->cancelada()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA]);
        $passada = ReservaEspaco::factory()->create(['sala_id' => $sala->id, 'data' => '2026-10-12']);

        foreach ([$cancelada, $passada] as $reserva) {
            try {
                $this->servico()->atualizar($reserva, $this->dados($sala), $user);
                $this->fail('Alteração deveria ser recusada.');
            } catch (ReservaEspacoIndisponivelException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ─── Confirmar e cancelar ────────────────────────────────────────

    public function test_confirmar_pedido_aguardando_registra_quem_decidiu(): void
    {
        $gestor = $this->gestao();
        $reserva = ReservaEspaco::factory()->solicitada()->create(['data' => self::SEGUNDA]);

        $confirmada = $this->servico()->confirmar($reserva, $gestor);

        $this->assertSame(StatusReservaEspaco::Confirmada, $confirmada->status);
        $this->assertSame($gestor->id, $confirmada->decidido_por_user_id);
    }

    public function test_so_pedido_aguardando_pode_ser_confirmado(): void
    {
        $gestor = $this->gestao();

        $this->expectException(ReservaEspacoIndisponivelException::class);

        $this->servico()->confirmar(ReservaEspaco::factory()->create(['data' => self::SEGUNDA]), $gestor);
    }

    public function test_confirmar_reconfere_conflito_com_a_grade_criada_depois_do_pedido(): void
    {
        $gestor = $this->gestao();
        $sala = Sala::factory()->create(['exige_aprovacao_reserva' => true]);
        $reserva = ReservaEspaco::factory()->solicitada()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '14:00', 'hora_fim' => '15:00']);

        $this->gradeNaSala($sala); // a secretaria alocou uma aula fixa depois do pedido

        try {
            $this->servico()->confirmar($reserva, $gestor);
            $this->fail('A confirmação deveria falhar por conflito com a grade.');
        } catch (ReservaEspacoIndisponivelException $e) {
            $this->assertStringContainsString('grade horária fixa', $e->getMessage());
        }

        $this->assertSame(StatusReservaEspaco::Solicitada, $reserva->fresh()->status);
    }

    public function test_dono_cancela_a_propria_reserva_sem_justificativa(): void
    {
        $professor = $this->professor();
        $reserva = ReservaEspaco::factory()->create(['solicitante_user_id' => $professor->id, 'data' => self::SEGUNDA]);

        $cancelada = $this->servico()->cancelar($reserva, $professor);

        $this->assertSame(StatusReservaEspaco::Cancelada, $cancelada->status);
        $this->assertNull($cancelada->justificativa_decisao);
    }

    public function test_cancelar_reserva_de_outra_pessoa_exige_justificativa(): void
    {
        $gestor = $this->gestao();
        $reserva = ReservaEspaco::factory()->solicitada()->create(['data' => self::SEGUNDA]);

        try {
            $this->servico()->cancelar($reserva, $gestor);
            $this->fail('A justificativa deveria ser exigida.');
        } catch (ReservaEspacoIndisponivelException $e) {
            $this->assertStringContainsString('justificativa', $e->getMessage());
        }

        $cancelada = $this->servico()->cancelar($reserva, $gestor, 'Auditório em manutenção');

        $this->assertSame('Auditório em manutenção', $cancelada->justificativa_decisao);
    }

    public function test_reserva_que_ja_ocorreu_nao_pode_ser_cancelada_e_cancelar_duas_vezes_e_idempotente(): void
    {
        $professor = $this->professor();
        $passada = ReservaEspaco::factory()->create(['solicitante_user_id' => $professor->id, 'data' => '2026-10-12']);
        $futura = ReservaEspaco::factory()->create(['solicitante_user_id' => $professor->id, 'data' => self::SEGUNDA]);

        try {
            $this->servico()->cancelar($passada, $professor);
            $this->fail('Reserva passada não pode ser cancelada.');
        } catch (ReservaEspacoIndisponivelException) {
            $this->addToAssertionCount(1);
        }

        $this->servico()->cancelar($futura, $professor);
        $segunda = $this->servico()->cancelar($futura->fresh(), $professor);

        $this->assertSame(StatusReservaEspaco::Cancelada, $segunda->status);
    }

    // ─── Policy ──────────────────────────────────────────────────────

    public function test_professor_ve_todas_mas_so_edita_e_cancela_as_proprias(): void
    {
        $professor = $this->professor();
        $propria = ReservaEspaco::factory()->create(['solicitante_user_id' => $professor->id, 'data' => self::SEGUNDA]);
        $alheia = ReservaEspaco::factory()->create(['data' => self::SEGUNDA]);

        $this->assertTrue($professor->can('view', $alheia));
        $this->assertTrue($professor->can('update', $propria));
        $this->assertTrue($professor->can('cancelar', $propria));
        $this->assertFalse($professor->can('update', $alheia));
        $this->assertFalse($professor->can('cancelar', $alheia));
        $this->assertFalse($professor->can('confirmar', $alheia));
        $this->assertFalse($professor->can('delete', $propria));
    }

    public function test_gestao_gerencia_reservas_de_qualquer_pessoa(): void
    {
        $gestor = $this->gestao();
        $aguardando = ReservaEspaco::factory()->solicitada()->create(['data' => self::SEGUNDA]);
        $confirmada = ReservaEspaco::factory()->create(['data' => self::SEGUNDA]);

        $this->assertTrue($gestor->can('update', $aguardando));
        $this->assertTrue($gestor->can('cancelar', $aguardando));
        $this->assertTrue($gestor->can('confirmar', $aguardando));
        $this->assertFalse($gestor->can('confirmar', $confirmada), 'Só pedido aguardando pode ser confirmado.');
    }

    public function test_reserva_cancelada_nao_pode_mais_ser_editada_nem_cancelada(): void
    {
        $gestor = $this->gestao();
        $cancelada = ReservaEspaco::factory()->cancelada()->create(['data' => self::SEGUNDA]);

        $this->assertFalse($gestor->can('update', $cancelada));
        $this->assertFalse($gestor->can('cancelar', $cancelada));
    }

    public function test_usuario_sem_permissao_nao_acessa_a_listagem(): void
    {
        $this->autenticarComo('aluno', []);

        Livewire::test(ListReservaEspacos::class)->assertForbidden();
    }

    // ─── Telas (Filament) ────────────────────────────────────────────

    public function test_listagem_mostra_so_reservas_de_hoje_em_diante_por_padrao(): void
    {
        $this->professor();
        $futura = ReservaEspaco::factory()->create(['data' => self::SEGUNDA]);
        $hoje = ReservaEspaco::factory()->create(['data' => '2026-10-19']);
        $passada = ReservaEspaco::factory()->create(['data' => '2026-10-05']);

        Livewire::test(ListReservaEspacos::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$futura, $hoje])
            ->assertCanNotSeeTableRecords([$passada]);
    }

    public function test_filtro_minhas_reservas(): void
    {
        $professor = $this->professor();
        $minha = ReservaEspaco::factory()->create(['solicitante_user_id' => $professor->id, 'data' => self::SEGUNDA]);
        $alheia = ReservaEspaco::factory()->create(['data' => self::SEGUNDA]);

        Livewire::test(ListReservaEspacos::class)
            ->filterTable('minhas', true)
            ->assertCanSeeTableRecords([$minha])
            ->assertCanNotSeeTableRecords([$alheia]);
    }

    public function test_criar_reserva_pelo_formulario(): void
    {
        $this->professor();
        $sala = Sala::factory()->create();

        Livewire::test(CreateReservaEspaco::class)
            ->fillForm([
                'sala_id' => $sala->id,
                'data' => self::SEGUNDA,
                'hora_inicio' => '14:00',
                'hora_fim' => '15:00',
                'motivo' => 'Palestra para os pais',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('reservas_espaco', [
            'sala_id' => $sala->id,
            'motivo' => 'Palestra para os pais',
            'status' => 'confirmada',
        ]);
    }

    public function test_formulario_com_conflito_nao_grava_e_avisa_o_motivo(): void
    {
        $this->professor();
        $sala = Sala::factory()->create();
        ReservaEspaco::factory()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '14:00', 'hora_fim' => '15:00']);

        Livewire::test(CreateReservaEspaco::class)
            ->fillForm([
                'sala_id' => $sala->id,
                'data' => self::SEGUNDA,
                'hora_inicio' => '14:30',
                'hora_fim' => '15:30',
                'motivo' => 'Outro evento',
            ])
            ->call('create')
            ->assertNotified('Espaço indisponível');

        $this->assertSame(1, ReservaEspaco::count());
    }

    public function test_editar_reserva_pelo_formulario(): void
    {
        $professor = $this->professor();
        $sala = Sala::factory()->create();
        $reserva = $this->servico()->solicitar($this->dados($sala), $professor);

        Livewire::test(EditReservaEspaco::class, ['record' => $reserva->id])
            ->fillForm(['motivo' => 'Ensaio geral', 'hora_fim' => '15:30'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Ensaio geral', $reserva->fresh()->motivo);
        $this->assertSame('15:30:00', $reserva->fresh()->hora_fim);
    }

    public function test_professor_nao_abre_a_edicao_da_reserva_de_outra_pessoa(): void
    {
        $this->professor();
        $alheia = ReservaEspaco::factory()->create(['data' => self::SEGUNDA]);

        Livewire::test(EditReservaEspaco::class, ['record' => $alheia->id])->assertForbidden();
    }

    public function test_acao_confirmar_aparece_so_para_quem_aprova(): void
    {
        $reserva = ReservaEspaco::factory()->solicitada()->create(['data' => self::SEGUNDA]);

        $this->professor();
        Livewire::test(ListReservaEspacos::class)->assertTableActionHidden('confirmar', $reserva);

        $this->gestao();
        Livewire::test(ListReservaEspacos::class)
            ->assertTableActionVisible('confirmar', $reserva)
            ->callTableAction('confirmar', $reserva)
            ->assertNotified('Reserva confirmada');

        $this->assertSame(StatusReservaEspaco::Confirmada, $reserva->fresh()->status);
    }

    public function test_acao_cancelar_exige_justificativa_para_reserva_alheia(): void
    {
        $this->gestao();
        $reserva = ReservaEspaco::factory()->solicitada()->create(['data' => self::SEGUNDA]);

        Livewire::test(ListReservaEspacos::class)
            ->callTableAction('cancelar', $reserva, ['justificativa' => ''])
            ->assertNotified('Não foi possível cancelar');

        $this->assertSame(StatusReservaEspaco::Solicitada, $reserva->fresh()->status);

        Livewire::test(ListReservaEspacos::class)
            ->callTableAction('cancelar', $reserva, ['justificativa' => 'Espaço em manutenção'])
            ->assertNotified('Reserva cancelada');

        $this->assertSame(StatusReservaEspaco::Cancelada, $reserva->fresh()->status);
    }

    // ─── Mapa de ocupação ────────────────────────────────────────────

    public function test_mapa_de_ocupacao_junta_reservas_e_aulas_da_grade_ordenadas(): void
    {
        $sala = Sala::factory()->create();
        $this->gradeNaSala($sala, [], ['hora_inicio' => '16:00:00', 'hora_fim' => '17:00:00']);
        ReservaEspaco::factory()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '09:00', 'hora_fim' => '10:00', 'motivo' => 'Ensaio']);
        ReservaEspaco::factory()->cancelada()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'hora_inicio' => '11:00', 'hora_fim' => '12:00']);

        $mapa = app(OcupacaoEspacoService::class)->mapa($sala, Carbon::parse(self::SEGUNDA), 2);

        $this->assertCount(2, $mapa);
        $segunda = $mapa[0]['blocos'];
        $this->assertCount(2, $segunda, 'A reserva cancelada não ocupa o espaço.');
        $this->assertSame(['reserva', 'grade'], array_column($segunda, 'tipo'));
        $this->assertSame('09:00', $segunda[0]['inicio']);
        $this->assertSame([], $mapa[1]['blocos'], 'Terça-feira está livre.');
    }

    public function test_mapa_limita_o_periodo_a_31_dias(): void
    {
        $sala = Sala::factory()->create();

        $mapa = app(OcupacaoEspacoService::class)->mapa($sala, Carbon::parse(self::SEGUNDA), 400);

        $this->assertCount(OcupacaoEspacoService::MAXIMO_DIAS, $mapa);
    }

    public function test_pagina_de_ocupacao_mostra_os_blocos_do_espaco_escolhido(): void
    {
        $this->professor();
        $sala = Sala::factory()->create();
        ReservaEspaco::factory()->create(['sala_id' => $sala->id, 'data' => self::SEGUNDA, 'motivo' => 'Gravação do vídeo institucional']);

        Livewire::test(OcupacaoEspacos::class)
            ->assertSuccessful()
            ->assertSee('Escolha um espaço')
            ->set('filtros.sala_id', $sala->id)
            ->set('filtros.data_inicial', self::SEGUNDA)
            ->set('filtros.dias', 7)
            ->assertSee('Gravação do vídeo institucional');
    }
}

<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\CalendarioPreceptorias;
use App\Models\CicloPreceptoria;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\User;
use App\Notifications\Preceptorias\PreceptoriaAlteradaNotification;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class CalendarioPreceptoriasTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissoes
     */
    private function usuarioCom(array $permissoes, string $papelAtivo = 'admin'): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // o papel ativo na sessão só vale se o usuário realmente o tem (senão a página dá 403)
        $papel = Role::firstOrCreate(['name' => $papelAtivo, 'guard_name' => 'web']);
        foreach ($permissoes as $nome) {
            $papel->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->assignRole($papel);
        session(['active_role' => $papelAtivo]);

        return $user;
    }

    private function ciclo(string $nome = 'Ciclo Setembro'): CicloPreceptoria
    {
        return CicloPreceptoria::create(['nome' => $nome, 'data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30']);
    }

    public function test_acessa_com_permissao_de_listagem_e_nega_sem_ela(): void
    {
        $com = $this->usuarioCom(['ViewAny:Preceptoria']);
        Livewire::actingAs($com)->test(CalendarioPreceptorias::class)->assertSuccessful();

        $sem = $this->usuarioCom(['View:Preceptoria'], 'secretaria');
        Livewire::actingAs($sem)->test(CalendarioPreceptorias::class)->assertForbidden();
    }

    public function test_eventos_do_periodo_com_professor_aluno_e_dias_especiais(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria']);
        $professor = Pessoa::factory()->create(['nome' => 'Prof. Helena']);
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Caio']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $livre = Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => '2026-09-03', 'hora_inicio' => '09:00:00', 'hora_fim' => '09:30:00']);
        $agendada = Preceptoria::factory()->create(['professor_id' => $professor->id, 'matricula_id' => $matricula->id, 'data' => '2026-09-04', 'hora_inicio' => '10:00:00', 'hora_fim' => null]);
        $foraDoPeriodo = Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => '2026-10-05']);

        $eventos = collect(Livewire::actingAs($user)->test(CalendarioPreceptorias::class)->instance()->getEventos('2026-09-01', '2026-10-01'));

        $porId = $eventos->keyBy('id');
        $this->assertTrue($porId->has((string) $livre->id));
        $this->assertTrue($porId->has((string) $agendada->id));
        $this->assertFalse($porId->has((string) $foraDoPeriodo->id));

        $this->assertSame('2026-09-03T09:00:00', $porId[(string) $livre->id]['start']);
        $this->assertSame('2026-09-03T09:30:00', $porId[(string) $livre->id]['end']);
        $this->assertFalse($porId[(string) $livre->id]['extendedProps']['agendado']);
        $this->assertTrue($porId[(string) $livre->id]['editable']);

        // sem hora de término, o bloco ganha a duração padrão de 30 minutos
        $this->assertSame('2026-09-04T10:30:00', $porId[(string) $agendada->id]['end']);
        $this->assertSame('Aluno Caio', $porId[(string) $agendada->id]['extendedProps']['aluno']);
        $this->assertStringContainsString('Prof. Helena', $porId[(string) $agendada->id]['title']);

        // 07/09 é feriado nacional: vira faixa de fundo, não evento editável
        $feriado = $porId['especial-2026-09-07'];
        $this->assertSame('background', $feriado['display']);
        $this->assertSame('Feriado: Independência do Brasil', $feriado['title']);
        $this->assertFalse($feriado['editable']);
    }

    public function test_filtros_de_ciclo_professor_e_situacao(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria']);
        $cicloA = $this->ciclo('Ciclo A');
        $cicloB = $this->ciclo('Ciclo B');
        $helena = Pessoa::factory()->create();
        $marcos = Pessoa::factory()->create();
        $matricula = Matricula::factory()->create();

        $a = Preceptoria::factory()->create(['ciclo_preceptoria_id' => $cicloA->id, 'professor_id' => $helena->id, 'data' => '2026-09-03', 'hora_inicio' => '09:00:00']);
        $b = Preceptoria::factory()->create(['ciclo_preceptoria_id' => $cicloB->id, 'professor_id' => $marcos->id, 'matricula_id' => $matricula->id, 'data' => '2026-09-03', 'hora_inicio' => '10:00:00']);

        $ids = fn (Testable $teste) => collect($teste->instance()->getEventos('2026-09-01', '2026-10-01'))
            ->reject(fn ($e) => str_starts_with($e['id'], 'especial-'))
            ->pluck('id')->sort()->values()->all();

        $teste = Livewire::actingAs($user)->test(CalendarioPreceptorias::class);
        $this->assertSame([(string) $a->id, (string) $b->id], $ids($teste));

        $teste->set('filtros.ciclo_preceptoria_id', $cicloA->id);
        $this->assertSame([(string) $a->id], $ids($teste));

        $teste->set('filtros.ciclo_preceptoria_id', null)->set('filtros.professores', [$marcos->id]);
        $this->assertSame([(string) $b->id], $ids($teste));

        $teste->set('filtros.professores', [])->set('filtros.status', 'disponivel');
        $this->assertSame([(string) $a->id], $ids($teste));

        $teste->set('filtros.status', 'agendado');
        $this->assertSame([(string) $b->id], $ids($teste));
    }

    public function test_mover_com_permissao_atualiza_a_preceptoria(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria']);
        $preceptoria = Preceptoria::factory()->create(['data' => '2026-09-01', 'hora_inicio' => '14:00:00', 'hora_fim' => '14:30:00']);

        $resposta = Livewire::actingAs($user)->test(CalendarioPreceptorias::class)->instance()
            ->reagendarPreceptoria($preceptoria->id, '2026-09-03T09:00:00', '2026-09-03T09:30:00');

        $this->assertTrue($resposta['ok']);
        $preceptoria->refresh();
        $this->assertSame('2026-09-03', $preceptoria->data->toDateString());
        $this->assertSame('09:00', $preceptoria->hora_inicio->format('H:i'));
    }

    public function test_mover_sem_permissao_de_edicao_e_recusado(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria']);
        $preceptoria = Preceptoria::factory()->create(['data' => '2026-09-01', 'hora_inicio' => '14:00:00', 'hora_fim' => '14:30:00']);

        $resposta = Livewire::actingAs($user)->test(CalendarioPreceptorias::class)->instance()
            ->reagendarPreceptoria($preceptoria->id, '2026-09-03T09:00:00', '2026-09-03T09:30:00');

        $this->assertFalse($resposta['ok']);
        $this->assertSame('2026-09-01', $preceptoria->refresh()->data->toDateString());
    }

    public function test_professor_nao_move_preceptoria_de_outro_professor(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria'], 'professor');
        $minha = Pessoa::factory()->create();
        $user->pessoas()->attach($minha->id);

        $deOutro = Preceptoria::factory()->create(['data' => '2026-09-01', 'hora_inicio' => '14:00:00', 'hora_fim' => '14:30:00']);
        $propria = Preceptoria::factory()->create(['professor_id' => $minha->id, 'data' => '2026-09-01', 'hora_inicio' => '15:00:00', 'hora_fim' => '15:30:00']);

        $pagina = Livewire::actingAs($user)->test(CalendarioPreceptorias::class)->instance();

        $this->assertFalse($pagina->reagendarPreceptoria($deOutro->id, '2026-09-03T09:00:00', '2026-09-03T09:30:00')['ok']);
        $this->assertTrue($pagina->reagendarPreceptoria($propria->id, '2026-09-03T09:00:00', '2026-09-03T09:30:00')['ok']);

        $this->assertSame('2026-09-01', $deOutro->refresh()->data->toDateString());

        $eventos = collect($pagina->getEventos('2026-09-01', '2026-10-01'))->pluck('id')->all();
        $this->assertContains((string) $propria->id, $eventos);
        $this->assertNotContains((string) $deOutro->id, $eventos);
    }

    public function test_selecionar_no_calendario_cria_horario_livre(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = $this->ciclo();
        $professor = Pessoa::factory()->create();

        Livewire::actingAs($user)
            ->test(CalendarioPreceptorias::class)
            ->callAction('criarPreceptoria', data: [
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'data' => '2026-09-08',
                'hora_inicio' => '10:00',
                'hora_fim' => '10:30',
            ], arguments: ['data' => '2026-09-08', 'hora_inicio' => '10:00', 'hora_fim' => '10:30'])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('preceptoria', [
            'ciclo_preceptoria_id' => $ciclo->id,
            'professor_id' => $professor->id,
            'data' => '2026-09-08 00:00:00',
            'matricula_id' => null,
        ]);
    }

    public function test_criar_horario_em_choque_com_o_professor_nao_cria(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = $this->ciclo();
        $professor = Pessoa::factory()->create();
        Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => '2026-09-08', 'hora_inicio' => '10:00:00', 'hora_fim' => '10:30:00']);

        Livewire::actingAs($user)
            ->test(CalendarioPreceptorias::class)
            ->callAction('criarPreceptoria', data: [
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'data' => '2026-09-08',
                'hora_inicio' => '10:15',
                'hora_fim' => '10:45',
            ]);

        $this->assertSame(1, Preceptoria::count());
    }

    public function test_gerar_recorrencia_pula_fim_de_semana_e_feriado_por_padrao(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Create:Preceptoria']);
        $ciclo = $this->ciclo();
        $professor = Pessoa::factory()->create();

        // 01/09 (terça) a 07/09 (segunda): sáb 05 e dom 06 são fim de semana, 07 é feriado
        Livewire::actingAs($user)
            ->test(CalendarioPreceptorias::class)
            ->callAction('gerarRecorrencia', data: [
                'ciclo_preceptoria_id' => $ciclo->id,
                'professor_id' => $professor->id,
                'hora_inicio' => '10:00',
                'hora_fim' => '10:30',
                'data_inicio_range' => '2026-09-01',
                'data_fim_range' => '2026-09-07',
                'intervalo_semanas' => 1,
                'ignorar_fins_de_semana' => true,
                'ignorar_feriados' => true,
                'ignorar_dias_nao_letivos' => true,
            ])
            ->assertHasNoFormErrors();

        $this->assertEquals(
            ['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'],
            Preceptoria::orderBy('data')->get()->map(fn (Preceptoria $p) => $p->data->toDateString())->all(),
        );
    }

    public function test_acoes_de_criacao_ficam_ocultas_sem_permissao_de_criar(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria']);

        Livewire::actingAs($user)
            ->test(CalendarioPreceptorias::class)
            ->assertActionHidden('gerarRecorrencia');
    }

    public function test_so_quem_edita_abre_o_modal_de_gestao_ao_clicar(): void
    {
        $preceptoria = Preceptoria::factory()->create(['data' => '2026-09-08', 'hora_inicio' => '10:00:00', 'hora_fim' => '10:30:00']);

        $comEdicao = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria']);
        $evento = collect(Livewire::actingAs($comEdicao)->test(CalendarioPreceptorias::class)->instance()->getEventos('2026-09-01', '2026-10-01'))
            ->firstWhere('id', (string) $preceptoria->id);
        $this->assertTrue($evento['extendedProps']['gerenciavel']);

        $somenteLeitura = $this->usuarioCom(['ViewAny:Preceptoria'], 'secretaria');
        $evento = collect(Livewire::actingAs($somenteLeitura)->test(CalendarioPreceptorias::class)->instance()->getEventos('2026-09-01', '2026-10-01'))
            ->firstWhere('id', (string) $preceptoria->id);
        $this->assertFalse($evento['extendedProps']['gerenciavel']);

        Livewire::actingAs($somenteLeitura)->test(CalendarioPreceptorias::class)->assertActionHidden('gerenciarPreceptoria');
    }

    public function test_gerenciar_troca_professor_e_aluno_avisa_e_deixa_historico(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria', 'Agendar:Preceptoria']);
        $antigo = Pessoa::factory()->create(['email' => 'antigo@example.com']);
        $novo = Pessoa::factory()->create(['email' => 'novo@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);
        $preceptoria = Preceptoria::factory()->create(['professor_id' => $antigo->id, 'matricula_id' => null, 'data' => '2026-09-08', 'hora_inicio' => '10:00:00', 'hora_fim' => '10:30:00']);

        Livewire::actingAs($user)
            ->test(CalendarioPreceptorias::class)
            ->callAction('gerenciarPreceptoria', data: [
                'professor_id' => $novo->id,
                'matricula_id' => $matricula->id,
            ], arguments: ['id' => $preceptoria->id])
            ->assertHasNoFormErrors();

        $preceptoria->refresh();
        $this->assertSame($novo->id, $preceptoria->professor_id);
        $this->assertSame($matricula->id, $preceptoria->matricula_id);

        Notification::assertSentTo($antigo, PreceptoriaAlteradaNotification::class);
        Notification::assertSentTo($aluno, PreceptoriaNotification::class, fn ($n) => $n->tipo === 'agendamento');
        $this->assertEqualsCanonicalizing(
            ['troca_professor_preceptoria', 'agendamento_preceptoria', 'notificacao_alteracao_preceptoria', 'notificacao_alteracao_preceptoria'],
            $preceptoria->historico()->pluck('event')->all(),
        );
    }

    public function test_gerenciar_esvaziar_o_aluno_libera_o_horario(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria', 'Agendar:Preceptoria']);
        $professor = Pessoa::factory()->create();
        $matricula = Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create(['email' => 'aluno@example.com'])->id]);
        $preceptoria = Preceptoria::factory()->create(['professor_id' => $professor->id, 'matricula_id' => $matricula->id]);

        Livewire::actingAs($user)
            ->test(CalendarioPreceptorias::class)
            ->callAction('gerenciarPreceptoria', data: [
                'professor_id' => $professor->id,
                'matricula_id' => null,
            ], arguments: ['id' => $preceptoria->id])
            ->assertHasNoFormErrors();

        $this->assertNull($preceptoria->fresh()->matricula_id);
        $this->assertContains('liberacao_preceptoria', $preceptoria->historico()->pluck('event')->all());
    }

    public function test_gerenciar_sem_permissao_de_agendar_nao_mexe_no_aluno(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria']);
        $antigo = Pessoa::factory()->create();
        $novo = Pessoa::factory()->create();
        $matricula = Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create()->id]);
        $outra = Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create()->id]);
        $preceptoria = Preceptoria::factory()->create(['professor_id' => $antigo->id, 'matricula_id' => $matricula->id]);

        Livewire::actingAs($user)
            ->test(CalendarioPreceptorias::class)
            ->callAction('gerenciarPreceptoria', data: [
                'professor_id' => $novo->id,
                'matricula_id' => $outra->id,
            ], arguments: ['id' => $preceptoria->id]);

        $preceptoria->refresh();
        $this->assertSame($novo->id, $preceptoria->professor_id);
        $this->assertSame($matricula->id, $preceptoria->matricula_id);
    }

    public function test_gerenciar_recusa_professor_com_choque_de_horario(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria']);
        $antigo = Pessoa::factory()->create();
        $ocupado = Pessoa::factory()->create();
        Preceptoria::factory()->create(['professor_id' => $ocupado->id, 'data' => '2026-09-08', 'hora_inicio' => '10:00:00', 'hora_fim' => '10:30:00']);
        $preceptoria = Preceptoria::factory()->create(['professor_id' => $antigo->id, 'data' => '2026-09-08', 'hora_inicio' => '10:15:00', 'hora_fim' => '10:45:00']);

        Livewire::actingAs($user)
            ->test(CalendarioPreceptorias::class)
            ->callAction('gerenciarPreceptoria', data: ['professor_id' => $ocupado->id], arguments: ['id' => $preceptoria->id]);

        $this->assertSame($antigo->id, $preceptoria->fresh()->professor_id);
        Notification::assertNothingSent();
    }

    public function test_gerenciar_nao_alcanca_preceptoria_de_outro_professor(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Update:Preceptoria'], 'professor');
        $minha = Pessoa::factory()->create();
        $user->pessoas()->attach($minha->id);
        $deOutro = Preceptoria::factory()->create();
        $professorOriginal = $deOutro->professor_id;

        Livewire::actingAs($user)
            ->test(CalendarioPreceptorias::class)
            // sem acesso ao registro o modal não tem formulário: a ação roda direto e só avisa
            ->callAction('gerenciarPreceptoria', arguments: ['id' => $deOutro->id]);

        $this->assertSame($professorOriginal, $deOutro->fresh()->professor_id);
        $this->assertCount(0, $deOutro->historico());
        Notification::assertNothingSent();
    }
}

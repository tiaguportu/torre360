<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\EditPreceptoria;
use App\Filament\Resources\Preceptorias\Pages\ListPreceptorias;
use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\CicloPreceptoria;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\Preceptorias\PreceptoriaAlteradaNotification;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A edição completa do registro e a edição em lote deixam o mesmo rastro do calendário: o que mudou
 * vai para o histórico e quem é afetado é avisado.
 */
class PreceptoriaEdicaoHistoricoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $papel = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny:Preceptoria', 'View:Preceptoria', 'Update:Preceptoria'] as $nome) {
            $papel->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['name' => 'Maria da Secretaria']);
        $user->assignRole($papel);
        session(['active_role' => 'admin']);

        return $user;
    }

    private function ciclo(string $nome = 'Ciclo A'): CicloPreceptoria
    {
        // ainda válido: a listagem esconde os ciclos vencidos
        return CicloPreceptoria::firstOrCreate(['nome' => $nome], ['data_inicio' => now()->subMonth()->toDateString(), 'data_fim' => now()->addMonths(2)->toDateString()]);
    }

    /**
     * Uma preceptoria futura, com aluno, do professor. O conselheiro da turma do aluno é quem se passa em $conselheiro.
     *
     * @param  array<string, mixed>  $extra
     */
    private function agendada(Pessoa $professor, Pessoa $aluno, ?Pessoa $conselheiro = null, array $extra = []): Preceptoria
    {
        $turma = Turma::factory()->create(['professor_conselheiro_id' => ($conselheiro ?? $professor)->id]);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id, 'turma_id' => $turma->id]);

        return Preceptoria::factory()->create(array_merge([
            'ciclo_preceptoria_id' => $this->ciclo()->id,
            'professor_id' => $professor->id,
            'matricula_id' => $matricula->id,
            'data' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fim' => '10:30:00',
        ], $extra));
    }

    private function editar(User $user, Preceptoria $preceptoria, array $dados)
    {
        $pagina = Livewire::actingAs($user)->test(EditPreceptoria::class, ['record' => $preceptoria->getRouteKey()]);

        // fillForm([]) esvaziaria o formulário já preenchido com o registro
        if ($dados !== []) {
            $pagina->fillForm($dados);
        }

        return $pagina->call('save');
    }

    public function test_trocar_o_professor_na_edicao_completa_registra_e_avisa(): void
    {
        Notification::fake();

        $antigo = Pessoa::factory()->create(['nome' => 'Professora Antiga', 'email' => 'antigo@example.com']);
        $novo = Pessoa::factory()->create(['nome' => 'Professor Novo', 'email' => 'novo@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $preceptoria = $this->agendada($antigo, $aluno, conselheiro: $novo);

        $this->editar($this->usuario(), $preceptoria, ['professor_id' => $novo->id])->assertHasNoFormErrors();

        $this->assertSame($novo->id, $preceptoria->fresh()->professor_id);

        $historico = $preceptoria->historico();
        $this->assertSame(
            'Professor alterado de Professora Antiga para Professor Novo',
            $historico->firstWhere('event', 'troca_professor_preceptoria')->description,
        );
        $this->assertSame('Maria da Secretaria', $historico->firstWhere('event', 'troca_professor_preceptoria')->causer->name);

        $dessa = fn (PreceptoriaAlteradaNotification $n) => $n->mudanca === PreceptoriaAlteradaNotification::PROFESSOR && $n->antes === 'Professora Antiga';
        Notification::assertSentTo($antigo, PreceptoriaAlteradaNotification::class, $dessa);
        Notification::assertSentTo($novo, PreceptoriaAlteradaNotification::class, $dessa);
        Notification::assertSentTo($aluno, PreceptoriaAlteradaNotification::class, $dessa);
        $this->assertContains('notificacao_alteracao_preceptoria', $historico->pluck('event')->all());
    }

    public function test_mudar_so_o_horario_na_edicao_completa_manda_o_aviso_de_reagendamento(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $preceptoria = $this->agendada($professor, $aluno);
        $dia = now()->addDays(5)->format('d/m/Y');

        $this->editar($this->usuario(), $preceptoria, ['hora_inicio' => '14:00', 'hora_fim' => '14:30'])->assertHasNoFormErrors();

        $this->assertSame('14:00', $preceptoria->fresh()->hora_inicio->format('H:i'));
        $this->assertSame(
            "Preceptoria reagendada de {$dia} das 10:00 às 10:30 para {$dia} das 14:00 às 14:30",
            $preceptoria->historico()->firstWhere('event', 'reagendamento_preceptoria')->description,
        );

        $horario = fn (PreceptoriaAlteradaNotification $n) => $n->mudanca === PreceptoriaAlteradaNotification::HORARIO && $n->antes === "{$dia} das 10:00 às 10:30";
        Notification::assertSentTo($professor, PreceptoriaAlteradaNotification::class, $horario);
        Notification::assertSentTo($aluno, PreceptoriaAlteradaNotification::class, $horario);
    }

    public function test_trocar_professor_e_horario_juntos_manda_um_aviso_so_com_o_horario_anterior(): void
    {
        Notification::fake();

        $antigo = Pessoa::factory()->create(['email' => 'antigo@example.com']);
        $novo = Pessoa::factory()->create(['email' => 'novo@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $preceptoria = $this->agendada($antigo, $aluno, conselheiro: $novo);
        $dia = now()->addDays(5)->format('d/m/Y');

        $this->editar($this->usuario(), $preceptoria, ['professor_id' => $novo->id, 'hora_inicio' => '14:00', 'hora_fim' => '14:30'])->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            ['troca_professor_preceptoria', 'reagendamento_preceptoria', 'notificacao_alteracao_preceptoria'],
            $preceptoria->historico()->pluck('event')->unique()->values()->all(),
        );

        // o aluno recebe um aviso só: o de troca de professor, que já traz o horário de antes
        Notification::assertSentToTimes($aluno, PreceptoriaAlteradaNotification::class, 1);
        Notification::assertSentTo(
            $aluno,
            PreceptoriaAlteradaNotification::class,
            fn (PreceptoriaAlteradaNotification $n) => $n->mudanca === PreceptoriaAlteradaNotification::PROFESSOR && $n->horarioAnterior === "{$dia} das 10:00 às 10:30",
        );
        Notification::assertSentToTimes($novo, PreceptoriaAlteradaNotification::class, 1);
        Notification::assertSentToTimes($antigo, PreceptoriaAlteradaNotification::class, 1);
    }

    public function test_liberar_o_aluno_na_edicao_completa_avisa_quem_saiu(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['nome' => 'Aluna Que Saiu', 'email' => 'aluno@example.com']);
        $preceptoria = $this->agendada($professor, $aluno);

        $this->editar($this->usuario(), $preceptoria, ['matricula_id' => null])->assertHasNoFormErrors();

        $this->assertNull($preceptoria->fresh()->matricula_id);
        $this->assertSame(
            'Horário liberado (o aluno era Aluna Que Saiu)',
            $preceptoria->historico()->firstWhere('event', 'liberacao_preceptoria')->description,
        );
        Notification::assertSentTo(
            $aluno,
            PreceptoriaNotification::class,
            fn (PreceptoriaNotification $n) => $n->tipo === 'liberacao' && $n->alunoAnterior === 'Aluna Que Saiu',
        );
        // o professor fica com o horário vago e também é avisado
        Notification::assertSentTo($professor, PreceptoriaNotification::class, fn (PreceptoriaNotification $n) => $n->tipo === 'liberacao');
    }

    public function test_trocar_so_o_ciclo_vai_para_o_historico_sem_avisar_ninguem(): void
    {
        Notification::fake();

        $preceptoria = $this->agendada(Pessoa::factory()->create(['email' => 'professor@example.com']), Pessoa::factory()->create(['email' => 'aluno@example.com']));
        $outro = $this->ciclo('Ciclo B');

        $this->editar($this->usuario(), $preceptoria, ['ciclo_preceptoria_id' => $outro->id])->assertHasNoFormErrors();

        $this->assertSame($outro->id, $preceptoria->fresh()->ciclo_preceptoria_id);
        $this->assertSame(
            'Ciclo alterado de Ciclo A para Ciclo B',
            $preceptoria->historico()->firstWhere('event', 'troca_ciclo_preceptoria')->description,
        );
        Notification::assertNothingSent();
    }

    public function test_salvar_sem_mudar_nada_nao_registra_nem_avisa(): void
    {
        Notification::fake();

        $preceptoria = $this->agendada(Pessoa::factory()->create(['email' => 'professor@example.com']), Pessoa::factory()->create(['email' => 'aluno@example.com']));

        $this->editar($this->usuario(), $preceptoria, [])->assertHasNoFormErrors();

        $this->assertCount(0, $preceptoria->historico());
        Notification::assertNothingSent();
    }

    public function test_preceptoria_que_ja_passou_registra_a_mudanca_mas_nao_avisa(): void
    {
        Notification::fake();

        $antigo = Pessoa::factory()->create(['email' => 'antigo@example.com']);
        $novo = Pessoa::factory()->create(['email' => 'novo@example.com']);
        $preceptoria = $this->agendada($antigo, Pessoa::factory()->create(['email' => 'aluno@example.com']), conselheiro: $novo, extra: ['data' => now()->subDays(3)->toDateString()]);

        $this->editar($this->usuario(), $preceptoria, ['professor_id' => $novo->id])->assertHasNoFormErrors();

        $this->assertSame($novo->id, $preceptoria->fresh()->professor_id);
        $this->assertCount(1, $preceptoria->historico());
        Notification::assertNothingSent();
    }

    public function test_edicao_recusada_por_conflito_nao_deixa_rastro_nem_aviso(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $this->agendada($professor, Pessoa::factory()->create(['email' => 'um@example.com']));
        $outra = $this->agendada($professor, Pessoa::factory()->create(['email' => 'dois@example.com']), extra: ['hora_inicio' => '11:00:00', 'hora_fim' => '11:30:00']);

        $this->editar($this->usuario(), $outra, ['hora_inicio' => '10:15', 'hora_fim' => '10:45'])->assertNotified('Horário em conflito');

        $this->assertSame('11:00', $outra->fresh()->hora_inicio->format('H:i'));
        $this->assertCount(0, $outra->historico());
        Notification::assertNothingSent();
    }

    public function test_editar_em_lote_registra_cada_preceptoria_e_avisa(): void
    {
        Notification::fake();

        $antigo = Pessoa::factory()->create(['nome' => 'Professora Antiga', 'email' => 'antigo@example.com']);
        $novo = Pessoa::factory()->create(['nome' => 'Professor Novo', 'email' => 'novo@example.com']);
        $primeiro = Pessoa::factory()->create(['email' => 'primeiro@example.com']);
        $segundo = Pessoa::factory()->create(['email' => 'segundo@example.com']);
        $a = $this->agendada($antigo, $primeiro, conselheiro: $novo);
        $b = $this->agendada($antigo, $segundo, conselheiro: $novo, extra: ['hora_inicio' => '11:00:00', 'hora_fim' => '11:30:00']);

        Livewire::actingAs($this->usuario())
            ->test(ListPreceptorias::class)
            ->callTableBulkAction('editar_lote', [$a, $b], data: ['professor_id' => $novo->id])
            ->assertNotified();

        foreach ([$a, $b] as $preceptoria) {
            $this->assertSame($novo->id, $preceptoria->fresh()->professor_id);
            // o nome de quem era vem do banco: a relação que a listagem carregou era a de antes
            $this->assertSame(
                'Professor alterado de Professora Antiga para Professor Novo',
                $preceptoria->historico()->firstWhere('event', 'troca_professor_preceptoria')->description,
            );
        }

        Notification::assertSentTo($primeiro, PreceptoriaAlteradaNotification::class);
        Notification::assertSentTo($segundo, PreceptoriaAlteradaNotification::class);
        Notification::assertSentToTimes($antigo, PreceptoriaAlteradaNotification::class, 2);
        Notification::assertSentToTimes($novo, PreceptoriaAlteradaNotification::class, 2);
    }

    public function test_o_push_da_notificacao_de_alteracao_leva_o_link_do_registro(): void
    {
        $preceptoria = Preceptoria::factory()->create();

        $push = (new PreceptoriaAlteradaNotification($preceptoria, PreceptoriaAlteradaNotification::HORARIO, '01/01/2026 às 08:00'))
            ->toPush(User::factory()->create());

        $this->assertSame(PreceptoriaResource::getUrl('view', ['record' => $preceptoria], isAbsolute: false), $push['data']['url']);
        $this->assertSame($preceptoria->id, $push['data']['preceptoria_id']);
    }
}

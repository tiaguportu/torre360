<?php

namespace Tests\Feature\Preceptorias;

use App\Exceptions\ConflitoDeHorarioException;
use App\Filament\Resources\Preceptorias\Pages\EditPreceptoria;
use App\Filament\Resources\Preceptorias\Pages\ListPreceptorias;
use App\Models\CicloPreceptoria;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\RelatorioPreceptoria;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\Preceptorias\PreceptoriaCanceladaNotification;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use App\Services\PreceptoriaAgendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Excluir uma preceptoria guarda o registro (dá para restaurar), deixa o rastro no histórico e avisa
 * quem tinha o horário combinado.
 */
class PreceptoriaExclusaoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissoes
     */
    private function usuario(array $permissoes = ['ViewAny', 'View', 'Update', 'Delete', 'DeleteAny']): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $papel = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach ($permissoes as $nome) {
            $papel->givePermissionTo(Permission::firstOrCreate(['name' => "{$nome}:Preceptoria", 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['name' => 'Maria da Secretaria']);
        $user->assignRole($papel);
        session(['active_role' => 'admin']);

        return $user;
    }

    private function ciclo(): CicloPreceptoria
    {
        // ainda válido: a listagem esconde os ciclos vencidos
        return CicloPreceptoria::firstOrCreate(['nome' => 'Ciclo A'], ['data_inicio' => now()->subMonth()->toDateString(), 'data_fim' => now()->addMonths(2)->toDateString()]);
    }

    /**
     * Uma preceptoria do professor, por padrão futura e com aluno.
     *
     * @param  array<string, mixed>  $extra
     */
    private function preceptoria(Pessoa $professor, ?Pessoa $aluno, array $extra = []): Preceptoria
    {
        $matricula = null;
        if ($aluno) {
            $turma = Turma::factory()->create(['professor_conselheiro_id' => $professor->id]);
            $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id, 'turma_id' => $turma->id]);
        }

        return Preceptoria::factory()->create(array_merge([
            'ciclo_preceptoria_id' => $this->ciclo()->id,
            'professor_id' => $professor->id,
            'matricula_id' => $matricula?->id,
            'data' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fim' => '10:30:00',
        ], $extra));
    }

    private function comRelatorio(Preceptoria $preceptoria): void
    {
        RelatorioPreceptoria::create(['preceptoria_id' => $preceptoria->id, 'tipo' => 'Analise Geral', 'corpo' => 'Conversa produtiva.', 'publico' => false]);
    }

    private function excluida(int $id): Preceptoria
    {
        return Preceptoria::withTrashed()->findOrFail($id);
    }

    public function test_excluir_pelo_botao_guarda_o_registro_registra_e_avisa(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['nome' => 'Professora Ana', 'email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['nome' => 'Bruno Aluno', 'email' => 'aluno@example.com']);
        $responsavel = Pessoa::factory()->create(['email' => 'responsavel@example.com']);
        $aluno->responsaveis()->attach($responsavel->id);
        $preceptoria = $this->preceptoria($professor, $aluno);

        Livewire::actingAs($this->usuario())
            ->test(EditPreceptoria::class, ['record' => $preceptoria->getRouteKey()])
            ->callAction('delete')
            ->assertNotified();

        $this->assertSoftDeleted('preceptoria', ['id' => $preceptoria->id]);

        $evento = $this->excluida($preceptoria->id)->historico()->firstWhere('event', 'exclusao_preceptoria');
        $this->assertStringContainsString('excluída (aluno: Bruno Aluno; professor: Professora Ana)', $evento->description);
        $this->assertSame('Maria da Secretaria', $evento->causer->name);
        $this->assertSame('Bruno Aluno', $evento->properties['aluno']);

        $dessa = fn (PreceptoriaCanceladaNotification $n) => $n->data === now()->addDays(5)->format('d/m/Y')
            && $n->horario === '10:00 às 10:30'
            && $n->aluno === 'Bruno Aluno'
            && $n->professor === 'Professora Ana'
            && $n->canceladoPor === 'Maria da Secretaria';
        Notification::assertSentTo($professor, PreceptoriaCanceladaNotification::class, $dessa);
        Notification::assertSentTo($aluno, PreceptoriaCanceladaNotification::class, $dessa);
        Notification::assertSentTo($responsavel, PreceptoriaCanceladaNotification::class, $dessa);
        $this->assertContains('notificacao_alteracao_preceptoria', $this->excluida($preceptoria->id)->historico()->pluck('event')->all());
    }

    public function test_quem_excluiu_nao_recebe_o_aviso(): void
    {
        Notification::fake();

        $usuario = $this->usuario();
        $professor = Pessoa::factory()->create(['email' => $usuario->email]);
        $usuario->pessoas()->attach($professor->id);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $preceptoria = $this->preceptoria($professor, $aluno);

        Livewire::actingAs($usuario)
            ->test(EditPreceptoria::class, ['record' => $preceptoria->getRouteKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('preceptoria', ['id' => $preceptoria->id]);
        Notification::assertSentTo($aluno, PreceptoriaCanceladaNotification::class);
        Notification::assertNotSentTo($usuario, PreceptoriaCanceladaNotification::class);
    }

    public function test_horario_livre_e_excluido_sem_avisar_ninguem(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $preceptoria = $this->preceptoria($professor, null);

        Livewire::actingAs($this->usuario())
            ->test(EditPreceptoria::class, ['record' => $preceptoria->getRouteKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('preceptoria', ['id' => $preceptoria->id]);
        $evento = $this->excluida($preceptoria->id)->historico()->firstWhere('event', 'exclusao_preceptoria');
        $this->assertStringContainsString('horário livre', $evento->description);
        Notification::assertNothingSent();
    }

    public function test_preceptoria_que_ja_passou_e_excluida_sem_avisar(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $preceptoria = $this->preceptoria($professor, $aluno, ['data' => now()->subDays(3)->toDateString()]);

        Livewire::actingAs($this->usuario())
            ->test(EditPreceptoria::class, ['record' => $preceptoria->getRouteKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('preceptoria', ['id' => $preceptoria->id]);
        $this->assertNotNull($this->excluida($preceptoria->id)->historico()->firstWhere('event', 'exclusao_preceptoria'));
        Notification::assertNothingSent();
    }

    public function test_preceptoria_com_relatorio_nao_e_excluida(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $preceptoria = $this->preceptoria($professor, $aluno);
        $this->comRelatorio($preceptoria);

        Livewire::actingAs($this->usuario())
            ->test(EditPreceptoria::class, ['record' => $preceptoria->getRouteKey()])
            ->callAction('delete')
            ->assertNotified('Preceptoria não excluída');

        $this->assertNotSoftDeleted('preceptoria', ['id' => $preceptoria->id]);
        $this->assertSame(1, RelatorioPreceptoria::where('preceptoria_id', $preceptoria->id)->count());
        $this->assertCount(0, $preceptoria->historico());
        Notification::assertNothingSent();
    }

    public function test_o_horario_excluido_deixa_de_bloquear_o_professor_e_o_aluno(): void
    {
        $professor = Pessoa::factory()->create();
        $aluno = Pessoa::factory()->create();
        $preceptoria = $this->preceptoria($professor, $aluno);
        $matriculaId = $preceptoria->matricula_id;

        app(PreceptoriaAgendaService::class)->excluirComHistorico($preceptoria, notificar: false);

        $outra = Preceptoria::create([
            'ciclo_preceptoria_id' => $this->ciclo()->id,
            'professor_id' => $professor->id,
            'matricula_id' => $matriculaId,
            'data' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fim' => '10:30:00',
        ]);

        $this->assertNotNull($outra->id);
    }

    public function test_excluida_sai_da_listagem_e_aparece_no_filtro_de_excluidas(): void
    {
        $professor = Pessoa::factory()->create();
        $visivel = $this->preceptoria($professor, null, ['hora_inicio' => '09:00:00', 'hora_fim' => '09:30:00']);
        $excluida = $this->preceptoria($professor, null);
        app(PreceptoriaAgendaService::class)->excluirComHistorico($excluida);

        Livewire::actingAs($this->usuario())
            ->test(ListPreceptorias::class)
            ->assertCanSeeTableRecords([$visivel])
            ->assertCanNotSeeTableRecords([$excluida])
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$excluida])
            ->assertCanNotSeeTableRecords([$visivel]);
    }

    public function test_excluir_em_lote_avisa_e_mantem_as_que_tem_relatorio(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $primeiro = Pessoa::factory()->create(['email' => 'primeiro@example.com']);
        $segundo = Pessoa::factory()->create(['email' => 'segundo@example.com']);
        $a = $this->preceptoria($professor, $primeiro);
        $livre = $this->preceptoria($professor, null, ['hora_inicio' => '11:00:00', 'hora_fim' => '11:30:00']);
        $comRelatorio = $this->preceptoria($professor, $segundo, ['hora_inicio' => '12:00:00', 'hora_fim' => '12:30:00']);
        $this->comRelatorio($comRelatorio);

        Livewire::actingAs($this->usuario())
            ->test(ListPreceptorias::class)
            ->callTableBulkAction('delete', [$a, $livre, $comRelatorio])
            ->assertNotified();

        $this->assertSoftDeleted('preceptoria', ['id' => $a->id]);
        $this->assertSoftDeleted('preceptoria', ['id' => $livre->id]);
        $this->assertNotSoftDeleted('preceptoria', ['id' => $comRelatorio->id]);

        Notification::assertSentTo($primeiro, PreceptoriaCanceladaNotification::class);
        Notification::assertNotSentTo($segundo, PreceptoriaCanceladaNotification::class);
        // o professor tinha uma só preceptoria com aluno entre as excluídas
        Notification::assertSentToTimes($professor, PreceptoriaCanceladaNotification::class, 1);
        $this->assertNotNull($this->excluida($livre->id)->historico()->firstWhere('event', 'exclusao_preceptoria'));
        $this->assertCount(0, $comRelatorio->historico());
    }

    public function test_restaurar_devolve_o_horario_registra_e_avisa(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $preceptoria = $this->preceptoria($professor, $aluno);
        app(PreceptoriaAgendaService::class)->excluirComHistorico($preceptoria, notificar: false);

        Livewire::actingAs($this->usuario())
            ->test(ListPreceptorias::class)
            ->filterTable('trashed', false)
            ->callTableAction('restore', $this->excluida($preceptoria->id))
            ->assertNotified();

        $this->assertNotSoftDeleted('preceptoria', ['id' => $preceptoria->id]);
        $eventos = $preceptoria->fresh()->historico()->pluck('event')->all();
        $this->assertContains('restauracao_preceptoria', $eventos);

        Notification::assertSentTo($aluno, PreceptoriaNotification::class, fn (PreceptoriaNotification $n) => $n->tipo === 'agendamento');
        Notification::assertSentTo($professor, PreceptoriaNotification::class);
    }

    public function test_restaurar_e_recusado_se_o_professor_marcou_outra_no_horario(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $preceptoria = $this->preceptoria($professor, null);
        app(PreceptoriaAgendaService::class)->excluirComHistorico($preceptoria);
        $this->preceptoria($professor, null);

        Livewire::actingAs($this->usuario())
            ->test(ListPreceptorias::class)
            ->filterTable('trashed', false)
            ->callTableAction('restore', $this->excluida($preceptoria->id))
            ->assertNotified('Não foi possível restaurar');

        $this->assertSoftDeleted('preceptoria', ['id' => $preceptoria->id]);
        $this->assertNull($this->excluida($preceptoria->id)->historico()->firstWhere('event', 'restauracao_preceptoria'));
    }

    public function test_restaurar_em_lote_pula_a_que_bate_com_outra(): void
    {
        Notification::fake();

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $livre = $this->preceptoria($professor, null, ['hora_inicio' => '09:00:00', 'hora_fim' => '09:30:00']);
        $bate = $this->preceptoria($professor, null);
        $servico = app(PreceptoriaAgendaService::class);
        $servico->excluirComHistorico($livre);
        $servico->excluirComHistorico($bate);
        $this->preceptoria($professor, null);

        Livewire::actingAs($this->usuario())
            ->test(ListPreceptorias::class)
            ->filterTable('trashed', false)
            ->callTableBulkAction('restore', [$this->excluida($livre->id), $this->excluida($bate->id)])
            ->assertNotified();

        $this->assertNotSoftDeleted('preceptoria', ['id' => $livre->id]);
        $this->assertSoftDeleted('preceptoria', ['id' => $bate->id]);
    }

    public function test_restaurar_com_conflito_levanta_a_excecao_do_servico(): void
    {
        $professor = Pessoa::factory()->create();
        $preceptoria = $this->preceptoria($professor, null);
        $servico = app(PreceptoriaAgendaService::class);
        $servico->excluirComHistorico($preceptoria);
        $this->preceptoria($professor, null);

        $this->expectException(ConflitoDeHorarioException::class);

        $servico->restaurarComHistorico($this->excluida($preceptoria->id));
    }

    public function test_quem_pode_excluir_pode_restaurar(): void
    {
        $preceptoria = $this->preceptoria(Pessoa::factory()->create(), null);

        $quemExclui = $this->usuario(['Delete', 'DeleteAny']);
        $this->assertTrue($quemExclui->can('restore', $preceptoria));
        $this->assertTrue($quemExclui->can('restoreAny', Preceptoria::class));

        $this->assertFalse(User::factory()->create()->can('restore', $preceptoria));
    }

    public function test_previa_conta_recusadas_e_destinatarios(): void
    {
        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $comAluno = $this->preceptoria($professor, $aluno);
        $livre = $this->preceptoria($professor, null, ['hora_inicio' => '11:00:00', 'hora_fim' => '11:30:00']);
        $comRelatorio = $this->preceptoria($professor, Pessoa::factory()->create(['email' => 'outro@example.com']), ['hora_inicio' => '12:00:00', 'hora_fim' => '12:30:00']);
        $this->comRelatorio($comRelatorio);

        $previa = app(PreceptoriaAgendaService::class)->previaDaExclusao([$comAluno, $livre, $comRelatorio]);

        // professor e aluno da que tem aluno; a livre não avisa e a que tem relatório não é excluída
        $this->assertSame(['total' => 3, 'recusadas' => 1, 'avisados' => 2], $previa);
    }

    public function test_o_aviso_de_cancelamento_vai_pronto_como_texto(): void
    {
        $aviso = new PreceptoriaCanceladaNotification('24/08/2026', '07:15 às 07:45', 'Professora Ana', 'Bruno Aluno', 'Maria da Secretaria');

        // a fila remonta o aviso depois que a preceptoria já saiu das listas: não pode levar o model
        $this->assertStringNotContainsString('ModelIdentifier', serialize($aviso));
        $this->assertEquals($aviso, unserialize(serialize($aviso)));

        $pessoa = Pessoa::factory()->create(['nome' => 'Responsável Silva']);
        $texto = implode("\n", $aviso->toMail($pessoa)->introLines);
        $this->assertStringContainsString('Aluno: Bruno Aluno', $texto);
        $this->assertStringContainsString('Horário: 07:15 às 07:45', $texto);
        $this->assertStringContainsString('Cancelada por: Maria da Secretaria', $texto);

        $this->assertSame('/admin/preceptorias', $aviso->toPush($pessoa)['data']['url']);
    }
}

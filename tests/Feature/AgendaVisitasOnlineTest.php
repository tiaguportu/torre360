<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusVisitaInteressado;
use App\Filament\Resources\DisponibilidadeVisitas\Pages\ManageDisponibilidadeVisitas;
use App\Models\DiaNaoLetivo;
use App\Models\DisponibilidadeVisita;
use App\Models\Interessado;
use App\Models\PeriodoLetivo;
use App\Models\ReguaFollowUp;
use App\Models\Unidade;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Notifications\VisitaOnlineNotification;
use App\Services\AgendaVisitaService;
use Carbon\Carbon;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Agendamento online de visitas (Onda 86, sem a distribuição da Onda 85). "Hoje" é quarta-feira, 21/10/2026, 09:00.
 * O consultor A atende de segunda a sexta, das 9h às 11h; o B só às quintas, das 9h às 10h. Visitas de 30 minutos, com 24 h de
 * antecedência mínima.
 */
class AgendaVisitasOnlineTest extends TestCase
{
    use RefreshDatabase;

    private Unidade $unidade;

    private User $consultorA;

    private User $consultorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'secretaria', 'guard_name' => 'web']);

        $this->unidade = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1', 'telefone' => '(11) 3000-0000']);
        $this->consultorA = $this->consultor();
        $this->consultorB = $this->consultor();

        foreach (range(1, 5) as $dia) {
            $this->disponibilidade($this->consultorA, $dia, '09:00', '11:00');
        }

        $this->disponibilidade($this->consultorB, 4, '09:00', '10:00');
    }

    private function consultor(): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole('secretaria');

        return $usuario;
    }

    private function disponibilidade(User $consultor, int $diaSemana, string $inicio, string $fim, array $extra = []): DisponibilidadeVisita
    {
        return DisponibilidadeVisita::create($extra + [
            'user_id' => $consultor->id,
            'dia_semana' => $diaSemana,
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
        ]);
    }

    private function servico(): AgendaVisitaService
    {
        return app(AgendaVisitaService::class);
    }

    private function dadosDaFamilia(string $horario, array $extra = []): array
    {
        return $extra + [
            'unidade_id' => $this->unidade->id,
            'data_hora' => $horario,
            'responsavel_nome' => 'Maria da Silva',
            'responsavel_email' => 'maria@email.test',
            'responsavel_telefone' => '(11) 98888-7777',
            'aluno_nome' => 'João da Silva',
            'turno_preferencia' => 'Manhã',
            'consentimento' => '1',
        ];
    }

    private function marcar(string $horario, array $extra = []): VisitaInteressado
    {
        $this->post('/visita', $this->dadosDaFamilia($horario, $extra))->assertSessionHasNoErrors();

        return VisitaInteressado::query()->latest('id')->firstOrFail();
    }

    // --- Horários oferecidos ------------------------------------------------------------------------------------------

    public function test_oferece_so_horarios_dentro_da_disponibilidade_e_da_antecedencia(): void
    {
        $livres = $this->servico()->horariosLivres($this->unidade->id);

        $this->assertArrayNotHasKey('2026-10-21', $livres, 'Hoje está dentro das 24 h de antecedência mínima.');
        $this->assertSame(['09:00', '09:30', '10:00', '10:30'], array_keys($livres['2026-10-22']), 'Quinta: A das 9h às 11h, B das 9h às 10h.');
        $this->assertSame([$this->consultorA->id, $this->consultorB->id], $livres['2026-10-22']['09:00']);
        $this->assertSame([$this->consultorA->id], $livres['2026-10-22']['10:00']);
        $this->assertArrayNotHasKey('2026-10-24', $livres, 'Sábado não tem disponibilidade.');
        $this->assertArrayNotHasKey('2026-10-25', $livres);
        $this->assertArrayHasKey('2026-10-26', $livres);
    }

    public function test_dia_nao_letivo_nao_tem_horario(): void
    {
        DiaNaoLetivo::create(['periodo_letivo_id' => PeriodoLetivo::factory()->create()->id, 'data' => '2026-10-22', 'descricao' => 'Recesso', 'flag_ativo' => true]);

        $this->assertArrayNotHasKey('2026-10-22', $this->servico()->horariosLivres($this->unidade->id));
    }

    public function test_horario_ocupado_do_consultor_some_so_para_ele(): void
    {
        VisitaInteressado::factory()->create(['usuario_id' => $this->consultorA->id, 'data_hora' => '2026-10-22 09:00:00', 'status' => StatusVisitaInteressado::Agendada]);

        $livres = $this->servico()->horariosLivres($this->unidade->id)['2026-10-22'];

        $this->assertSame([$this->consultorB->id], $livres['09:00'], 'A visita do A ocupa 9h; o B continua livre.');
        $this->assertContains($this->consultorA->id, $livres['09:30'], 'A visita de 30 minutos acaba às 9h30.');
    }

    public function test_disponibilidade_de_outra_unidade_nao_aparece(): void
    {
        $outra = Unidade::create(['nome' => 'Unidade Norte', 'situacao_funcionamento' => '1']);
        DisponibilidadeVisita::query()->update(['unidade_id' => $outra->id]);

        $this->assertSame([], $this->servico()->horariosLivres($this->unidade->id));
        $this->assertNotSame([], $this->servico()->horariosLivres($outra->id));
    }

    public function test_consultor_inativo_nao_oferece_horario(): void
    {
        $this->consultorB->forceFill(['deactivated_at' => now()->subHour()])->save();
        DisponibilidadeVisita::where('user_id', $this->consultorA->id)->update(['ativo' => false]);

        $this->assertSame([], $this->servico()->horariosLivres($this->unidade->id));
    }

    // --- Marcação pela página pública ---------------------------------------------------------------------------------

    public function test_pagina_gera_o_token_do_recaptcha_quando_as_chaves_estao_configuradas(): void
    {
        // A regra de validação exige o token com as chaves configuradas; sem o script a página nunca o geraria e ninguém marcaria.
        $this->get('/visita')->assertOk()->assertDontSee('www.google.com/recaptcha', false);

        config(['services.recaptcha.site_key' => 'chave-publica-de-teste', 'services.recaptcha.secret' => 'segredo-de-teste']);

        $this->get('/visita')
            ->assertOk()
            ->assertSee('name="recaptcha_token"', false)
            ->assertSee('www.google.com/recaptcha/api.js?render=chave-publica-de-teste', false)
            ->assertSee("action: 'agendar_visita'", false);
    }

    public function test_pagina_publica_mostra_os_horarios_livres(): void
    {
        $this->get('/visita')
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('quinta-feira, 22/10')
            ->assertSee('value="2026-10-22 09:00"', false);
    }

    public function test_familia_marca_a_visita_e_recebe_a_confirmacao(): void
    {
        Notification::fake();

        $visita = $this->marcar('2026-10-22 10:00');

        $this->assertSame(StatusVisitaInteressado::Agendada, $visita->status);
        $this->assertSame('online', $visita->origem);
        $this->assertSame($this->unidade->id, $visita->unidade_id);
        $this->assertSame(48, strlen($visita->token_agendamento));
        $this->assertSame($this->consultorA->id, $visita->usuario_id);
        $this->assertSame('2026-10-22 10:00', $visita->data_hora->format('Y-m-d H:i'));

        $lead = Interessado::firstOrFail();
        $this->assertSame($this->consultorA->id, $lead->usuario_id, 'Lead novo e sem dono fica com o consultor da visita.');
        $this->assertSame('João da Silva', $lead->dependentes()->firstOrFail()->nome_crianca);
        $this->assertSame($lead->dependentes()->first()->id, $visita->interessado_dependente_id);

        Notification::assertSentOnDemand(VisitaOnlineNotification::class, fn ($n, $canais, $notifiable) => $notifiable->routes['mail'] === 'maria@email.test');
        Notification::assertSentTo($this->consultorA, DatabaseNotification::class);

        $this->get(route('visita.publica.mostrar', $visita->token_agendamento))
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('22/10/2026')
            ->assertSee('Trocar o horário');
    }

    public function test_confirmacao_por_email_traz_horario_e_links_para_trocar_ou_cancelar(): void
    {
        $visita = $this->marcar('2026-10-22 10:00');

        $mensagem = (new VisitaOnlineNotification($visita->id, VisitaOnlineNotification::CONFIRMADA))->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString('Visita confirmada', $mensagem->subject);
        $this->assertStringContainsString('10:00', implode(' ', $mensagem->introLines));
        $this->assertSame(route('visita.publica.mostrar', $visita->token_agendamento), $mensagem->actionUrl);
    }

    public function test_dois_consultores_no_mesmo_horario_dividem_pelo_menor_numero_de_visitas_do_dia(): void
    {
        $primeira = $this->marcar('2026-10-22 09:00');
        $segunda = $this->marcar('2026-10-22 09:30', ['responsavel_email' => 'outra@email.test', 'responsavel_nome' => 'Ana Souza', 'aluno_nome' => 'Pedro Souza']);

        $this->assertSame($this->consultorA->id, $primeira->usuario_id, 'Empate: o de menor id.');
        $this->assertSame($this->consultorB->id, $segunda->usuario_id, 'O A já tem uma visita no dia; o B não tem nenhuma.');
    }

    public function test_horario_ocupado_e_recusado_na_segunda_marcacao(): void
    {
        $this->marcar('2026-10-22 10:00');

        $this->post('/visita', $this->dadosDaFamilia('2026-10-22 10:00', ['responsavel_email' => 'outra@email.test']))
            ->assertSessionHasErrors('data_hora');

        $this->assertSame(1, VisitaInteressado::count());
    }

    public function test_horario_fora_da_oferta_e_recusado(): void
    {
        foreach (['2026-10-21 14:00', '2026-10-24 09:00', '2026-10-22 08:00', '2026-10-22 09:15'] as $horario) {
            $this->post('/visita', $this->dadosDaFamilia($horario, ['responsavel_email' => 'x'.md5($horario).'@email.test']))->assertSessionHasErrors('data_hora');
        }

        $this->assertSame(0, VisitaInteressado::count());
    }

    public function test_familia_que_ja_e_lead_nao_duplica_o_cadastro(): void
    {
        $this->marcar('2026-10-22 09:00');
        $this->marcar('2026-10-23 09:00');

        $this->assertSame(1, Interessado::count());
        $this->assertSame(2, VisitaInteressado::count());
    }

    public function test_formulario_exige_consentimento_e_dados(): void
    {
        $this->post('/visita', $this->dadosDaFamilia('2026-10-22 10:00', ['consentimento' => null]))->assertSessionHasErrors('consentimento');
        $this->post('/visita', $this->dadosDaFamilia('2026-10-22 10:00', ['responsavel_email' => 'nao-e-email']))->assertSessionHasErrors('responsavel_email');

        $this->assertSame(0, VisitaInteressado::count());
    }

    public function test_campo_armadilha_e_limite_de_marcacoes(): void
    {
        $this->post('/visita', $this->dadosDaFamilia('2026-10-22 10:00', ['website' => 'http://spam.test']))->assertRedirect(route('visita.publica.agendar'));
        $this->assertSame(0, VisitaInteressado::count());

        config(['visitas.max_agendamentos' => 1]);
        RateLimiter::clear('visita');
        $this->marcar('2026-10-22 09:00');
        $this->post('/visita', $this->dadosDaFamilia('2026-10-22 10:00', ['responsavel_email' => 'outra@email.test']))->assertSessionHasErrors('data_hora');
    }

    // --- Trocar e cancelar --------------------------------------------------------------------------------------------

    public function test_cancelar_libera_o_horario_avisa_o_consultor_e_nao_cancela_duas_vezes(): void
    {
        $visita = $this->marcar('2026-10-22 10:00');
        $this->assertNotContains($this->consultorA->id, $this->servico()->horariosLivres($this->unidade->id)['2026-10-22']['10:00'] ?? []);
        Notification::fake();

        $this->post(route('visita.publica.cancelar', $visita->token_agendamento))->assertRedirect(route('visita.publica.mostrar', $visita->token_agendamento));

        $this->assertSame(StatusVisitaInteressado::Cancelada, $visita->refresh()->status);
        $this->assertContains($this->consultorA->id, $this->servico()->horariosLivres($this->unidade->id)['2026-10-22']['10:00']);
        Notification::assertSentOnDemand(VisitaOnlineNotification::class);

        $this->post(route('visita.publica.cancelar', $visita->token_agendamento))->assertSessionHasErrors('visita');
        $this->get(route('visita.publica.mostrar', $visita->token_agendamento))->assertSee('Visita cancelada')->assertSee('Marcar outro horário');
    }

    public function test_reagendar_troca_o_horario_mantem_o_consultor_e_libera_o_antigo(): void
    {
        $visita = $this->marcar('2026-10-22 10:00');

        $this->get(route('visita.publica.reagendar', $visita->token_agendamento))->assertOk()->assertSee('Trocar o horário da visita');
        $this->post(route('visita.publica.reagendar.salvar', $visita->token_agendamento), ['data_hora' => '2026-10-23 09:30'])
            ->assertRedirect(route('visita.publica.mostrar', $visita->token_agendamento));

        $visita->refresh();
        $this->assertSame('2026-10-23 09:30', $visita->data_hora->format('Y-m-d H:i'));
        $this->assertSame($this->consultorA->id, $visita->usuario_id, 'O consultor continua o mesmo quando está livre.');
        $this->assertContains($this->consultorA->id, $this->servico()->horariosLivres($this->unidade->id)['2026-10-22']['10:00']);
    }

    public function test_reagendar_para_horario_ocupado_e_recusado(): void
    {
        $visita = $this->marcar('2026-10-22 10:00');
        $this->marcar('2026-10-23 09:30', ['responsavel_email' => 'outra@email.test']);

        $this->post(route('visita.publica.reagendar.salvar', $visita->token_agendamento), ['data_hora' => '2026-10-23 09:30'])->assertSessionHasErrors('data_hora');

        $this->assertSame('2026-10-22 10:00', $visita->refresh()->data_hora->format('Y-m-d H:i'));
    }

    public function test_link_inexistente_responde_404(): void
    {
        $this->get(route('visita.publica.mostrar', 'token-que-nao-existe'))->assertNotFound();
        $this->post(route('visita.publica.cancelar', str_repeat('x', 48)))->assertNotFound();
    }

    // --- Lembrete à família -------------------------------------------------------------------------------------------

    public function test_lembrete_a_familia_sai_uma_vez_e_so_para_visita_online(): void
    {
        // A migration da régua já cria uma regra ativa de lembrete de visita; este caso é o da escola que a desligou.
        ReguaFollowUp::where('gatilho', 'visita_lembrete')->update(['is_ativo' => false]);
        $online = $this->marcar('2026-10-22 09:30');
        VisitaInteressado::factory()->create(['usuario_id' => $this->consultorA->id, 'data_hora' => '2026-10-22 09:00:00', 'status' => StatusVisitaInteressado::Agendada]);
        $this->travelTo(Carbon::parse('2026-10-21 18:00:00'));
        Notification::fake();

        $this->assertSame(1, $this->servico()->enviarLembretesDaFamilia());
        $this->assertSame(0, $this->servico()->enviarLembretesDaFamilia(), 'Não repete o lembrete.');

        Notification::assertSentOnDemandTimes(VisitaOnlineNotification::class, 1);
        $this->assertNotNull($online->refresh()->lembrete_familia_enviado_em);
    }

    public function test_lembrete_nao_sai_se_a_regua_ja_tem_regra_de_lembrete_de_visita(): void
    {
        // Vale a regra que a migration da régua cria por padrão ("Lembrete de Visita à Escola (1 dia antes)").
        $this->assertTrue(ReguaFollowUp::where('gatilho', 'visita_lembrete')->where('is_ativo', true)->exists());
        $this->marcar('2026-10-22 09:30');
        $this->travelTo(Carbon::parse('2026-10-21 18:00:00'));

        $this->assertSame(0, $this->servico()->enviarLembretesDaFamilia());
    }

    // --- Agenda do consultor ------------------------------------------------------------------------------------------

    public function test_cadastro_da_agenda_pelo_painel_exige_a_permissao(): void
    {
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $acao) {
            Role::findByName('secretaria')->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:DisponibilidadeVisita", 'guard_name' => 'web']));
        }

        $semPermissao = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);
        $semPermissao->assignRole('aluno');
        session(['active_role' => 'aluno']);
        $this->actingAs($semPermissao)->get('/admin/agenda-visitas')->assertForbidden();

        session(['active_role' => 'secretaria']);
        $this->actingAs($this->consultorA);
        Livewire::test(ManageDisponibilidadeVisitas::class)
            ->assertSuccessful()
            ->callAction('create', [
                'user_id' => $this->consultorA->id,
                'dia_semana' => 6,
                'hora_inicio' => '09:00',
                'hora_fim' => '12:00',
                'duracao_minutos' => 30,
                'antecedencia_minima_horas' => 24,
                'antecedencia_maxima_dias' => 30,
                'ativo' => true,
            ]);

        $this->assertTrue(DisponibilidadeVisita::where('user_id', $this->consultorA->id)->where('dia_semana', 6)->exists());
    }
}

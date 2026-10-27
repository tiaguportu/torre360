<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConviteReuniao;
use App\Enums\StatusEncaminhamento;
use App\Enums\StatusReservaEspaco;
use App\Enums\StatusReuniao;
use App\Enums\TipoReuniao;
use App\Exceptions\ReuniaoInvalidaException;
use App\Models\DocumentoAssinatura;
use App\Models\Reuniao;
use App\Models\ReuniaoEncaminhamento;
use App\Models\ReuniaoParticipante;
use App\Models\Sala;
use App\Models\User;
use App\Services\ReuniaoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reuniões e atas (Ondas 27 e 32): agendar, convidar, remarcar, realizar, fechar a ata, adendos, cancelar, encaminhamentos e o
 * lembrete de prazo. "Hoje" é quarta-feira, 28/10/2026, 09:00.
 */
class ReuniaoServiceTest extends TestCase
{
    use RefreshDatabase;

    private const HOJE = '2026-10-28 09:00:00';

    private User $organizador;

    private User $ana;

    private User $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::HOJE));
        Storage::fake('local');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->organizador = $this->usuario('Coordenadora Célia');
        $this->ana = $this->usuario('Professora Ana');
        $this->bruno = $this->usuario('Professor Bruno');
    }

    private function usuario(string $nome): User
    {
        return User::factory()->create(['name' => $nome, 'activated_at' => now()->subDay(), 'email_verified_at' => now()]);
    }

    private function servico(): ReuniaoService
    {
        return app(ReuniaoService::class);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function agendar(array $extra = []): Reuniao
    {
        return $this->servico()->agendar(array_merge([
            'tipo' => TipoReuniao::Pedagogica,
            'titulo' => 'Reunião pedagógica de novembro',
            'inicio_em' => '2026-10-30 14:00',
            'participantes' => [$this->ana->id, $this->bruno->id],
            'pauta' => 'Planejamento do último bimestre.',
        ], $extra), $this->organizador);
    }

    /**
     * Passa o relógio para depois do início e marca a reunião como realizada com a Ana presente.
     */
    private function realizar(Reuniao $reuniao): Reuniao
    {
        $this->travelTo(Carbon::parse('2026-10-30 16:00:00'));

        return $this->servico()->marcarRealizada($reuniao, $this->organizador, [
            $this->organizador->id => true,
            $this->ana->id => true,
            $this->bruno->id => false,
        ]);
    }

    private function notificacoes(User $user): int
    {
        return $user->notifications()->count();
    }

    // ─── Agendar ─────────────────────────────────────────────────────

    public function test_agenda_a_reuniao_e_convida_os_participantes(): void
    {
        $reuniao = $this->agendar();

        $this->assertSame(StatusReuniao::Agendada, $reuniao->status);
        $this->assertSame($this->organizador->id, $reuniao->organizador_user_id);
        $this->assertFalse($reuniao->ehSigilosa());

        // 14:00 + a duração padrão da reunião pedagógica (60 minutos).
        $this->assertSame('15:00', $reuniao->fim_em->format('H:i'));

        $participantes = $reuniao->participantes()->get()->keyBy('user_id');
        $this->assertCount(3, $participantes);
        $this->assertSame(ReuniaoParticipante::PAPEL_ORGANIZADOR, $participantes[$this->organizador->id]->papel);
        $this->assertSame(ConviteReuniao::Aceito, $participantes[$this->organizador->id]->convite);
        $this->assertSame(ConviteReuniao::Pendente, $participantes[$this->ana->id]->convite);

        $this->assertSame(1, $this->notificacoes($this->ana));
        $this->assertSame(1, $this->notificacoes($this->bruno));
        $this->assertSame(0, $this->notificacoes($this->organizador));
    }

    public function test_titulo_e_horarios_sao_conferidos(): void
    {
        foreach ([
            [['titulo' => '  '], 'Dê um título'],
            [['fim_em' => '2026-10-30 13:00'], 'depois do início'],
            [['fim_em' => '2026-10-31 10:00'], 'mesmo dia'],
            [['inicio_em' => null], 'data e a hora'],
        ] as [$dados, $trecho]) {
            try {
                $this->agendar($dados);
                $this->fail("Deveria recusar: {$trecho}");
            } catch (ReuniaoInvalidaException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }

        $this->assertSame(0, Reuniao::count());
    }

    public function test_devolutiva_e_sempre_sigilosa_e_e_entre_duas_pessoas(): void
    {
        $reuniao = $this->agendar(['tipo' => TipoReuniao::Devolutiva, 'titulo' => 'Devolutiva da Ana', 'participantes' => [$this->ana->id]]);

        $this->assertTrue($reuniao->confidencial);
        $this->assertTrue($reuniao->ehSigilosa());

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('uma única pessoa');

        $this->agendar(['tipo' => TipoReuniao::Devolutiva, 'participantes' => [$this->ana->id, $this->bruno->id]]);
    }

    public function test_presidente_e_unico(): void
    {
        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('presidente só');

        $this->agendar(['participantes' => [
            ['user_id' => $this->ana->id, 'papel' => ReuniaoParticipante::PAPEL_PRESIDENTE],
            ['user_id' => $this->bruno->id, 'papel' => ReuniaoParticipante::PAPEL_PRESIDENTE],
        ]]);
    }

    public function test_sala_reserva_o_espaco_e_a_reserva_sigilosa_nao_mostra_o_assunto(): void
    {
        $sala = Sala::factory()->create(['nome' => 'Sala dos professores']);

        $aberta = $this->agendar(['sala_id' => $sala->id]);

        $this->assertNotNull($aberta->reserva_espaco_id);
        $this->assertSame('Sala dos professores', $aberta->local);
        $this->assertSame('Reunião: Reunião pedagógica de novembro', $aberta->reservaEspaco->motivo);
        $this->assertSame(StatusReservaEspaco::Confirmada, $aberta->reservaEspaco->status);

        $sigilosa = $this->agendar([
            'tipo' => TipoReuniao::Devolutiva,
            'titulo' => 'Devolutiva sobre o desempenho da Ana',
            'participantes' => [$this->ana->id],
            'sala_id' => $sala->id,
            'inicio_em' => '2026-10-30 16:00',
        ]);

        $this->assertSame('Reunião', $sigilosa->reservaEspaco->motivo);
        $this->assertNull($sigilosa->reservaEspaco->descricao);
    }

    public function test_horario_ocupado_nao_grava_nada(): void
    {
        $sala = Sala::factory()->create();
        $this->agendar(['sala_id' => $sala->id]);

        try {
            $this->agendar(['sala_id' => $sala->id, 'titulo' => 'Outra reunião no mesmo horário']);
            $this->fail('Deveria recusar o horário ocupado.');
        } catch (ReuniaoInvalidaException $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        $this->assertSame(1, Reuniao::count());
    }

    // ─── Remarcar e responder ────────────────────────────────────────

    public function test_remarcar_o_horario_pede_nova_resposta_e_avisa(): void
    {
        $reuniao = $this->agendar();
        $this->servico()->responderConvite($reuniao, $this->ana, ConviteReuniao::Aceito);

        $this->servico()->reagendar($reuniao, ['inicio_em' => '2026-10-30 15:00', 'participantes' => [$this->ana->id, $this->bruno->id]], $this->organizador);

        $reuniao->refresh();
        $this->assertSame('15:00', $reuniao->inicio_em->format('H:i'));
        $this->assertSame(ConviteReuniao::Pendente, $reuniao->participantes()->where('user_id', $this->ana->id)->first()->convite);
        // Convite inicial + aviso de remarcação.
        $this->assertSame(2, $this->notificacoes($this->ana));
    }

    public function test_mudar_so_o_titulo_mantem_a_resposta_e_nao_reenvia_convite(): void
    {
        $reuniao = $this->agendar();
        $this->servico()->responderConvite($reuniao, $this->ana, ConviteReuniao::Aceito);

        $this->servico()->reagendar($reuniao, ['titulo' => 'Pedagógica de novembro (nova pauta)', 'inicio_em' => '2026-10-30 14:00', 'fim_em' => '2026-10-30 15:00', 'participantes' => [$this->ana->id, $this->bruno->id]], $this->organizador);

        $this->assertSame(ConviteReuniao::Aceito, $reuniao->participantes()->where('user_id', $this->ana->id)->first()->convite);
        $this->assertSame(1, $this->notificacoes($this->ana));
        $this->assertSame('Pedagógica de novembro (nova pauta)', $reuniao->refresh()->titulo);
    }

    public function test_quem_entra_na_lista_recebe_convite_e_quem_sai_deixa_a_reuniao(): void
    {
        $reuniao = $this->agendar();
        $carla = $this->usuario('Professora Carla');

        $this->servico()->reagendar($reuniao, ['inicio_em' => '2026-10-30 14:00', 'fim_em' => '2026-10-30 15:00', 'participantes' => [$this->ana->id, $carla->id]], $this->organizador);

        $ids = $reuniao->participantes()->pluck('user_id')->all();
        $this->assertContains($carla->id, $ids);
        $this->assertNotContains($this->bruno->id, $ids);
        $this->assertSame(1, $this->notificacoes($carla));
    }

    public function test_so_a_reuniao_agendada_pode_ser_remarcada(): void
    {
        $reuniao = $this->realizar($this->agendar());

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('ainda agendada');

        $this->servico()->reagendar($reuniao, ['inicio_em' => '2026-10-31 10:00'], $this->organizador);
    }

    public function test_recusar_o_convite_avisa_quem_organiza(): void
    {
        $reuniao = $this->agendar();

        $this->servico()->responderConvite($reuniao, $this->ana, ConviteReuniao::Recusado);

        $this->assertSame(ConviteReuniao::Recusado, $reuniao->participantes()->where('user_id', $this->ana->id)->first()->convite);
        $this->assertSame(1, $this->notificacoes($this->organizador));

        $this->servico()->responderConvite($reuniao, $this->bruno, ConviteReuniao::Aceito);
        $this->assertSame(1, $this->notificacoes($this->organizador));
    }

    public function test_quem_nao_foi_convidado_nao_responde(): void
    {
        $reuniao = $this->agendar();

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('não está na lista');

        $this->servico()->responderConvite($reuniao, $this->usuario('Intrusa'), ConviteReuniao::Aceito);
    }

    // ─── Realizar, ata e adendo ──────────────────────────────────────

    public function test_nao_marca_como_realizada_antes_do_horario(): void
    {
        $reuniao = $this->agendar();

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('ainda não começou');

        $this->servico()->marcarRealizada($reuniao, $this->organizador);
    }

    public function test_marca_a_reuniao_como_realizada_com_a_presenca(): void
    {
        $reuniao = $this->realizar($this->agendar());

        $this->assertSame(StatusReuniao::Realizada, $reuniao->status);
        $this->assertNotNull($reuniao->realizada_em);

        $presenca = $reuniao->participantes()->pluck('presente', 'user_id')->map(fn ($p) => (bool) $p)->all();
        $this->assertTrue($presenca[$this->ana->id]);
        $this->assertFalse($presenca[$this->bruno->id]);
    }

    public function test_a_ata_exige_presenca_e_texto_para_fechar(): void
    {
        $reuniao = $this->agendar();

        try {
            $this->servico()->fecharAta($reuniao, $this->organizador);
            $this->fail('Ata de reunião não realizada não fecha.');
        } catch (ReuniaoInvalidaException $e) {
            $this->assertStringContainsString('Marque a reunião como realizada', $e->getMessage());
        }

        $this->travelTo(Carbon::parse('2026-10-30 16:00:00'));
        $reuniao = $this->servico()->marcarRealizada($reuniao, $this->organizador);

        try {
            $this->servico()->fecharAta($reuniao, $this->organizador);
            $this->fail('Sem presença registrada a ata não fecha.');
        } catch (ReuniaoInvalidaException $e) {
            $this->assertStringContainsString('quem esteve presente', $e->getMessage());
        }

        $this->servico()->registrarPresenca($reuniao, [$this->ana->id => true]);

        try {
            $this->servico()->fecharAta($reuniao, $this->organizador);
            $this->fail('Sem texto a ata não fecha.');
        } catch (ReuniaoInvalidaException $e) {
            $this->assertStringContainsString('Escreva a ata', $e->getMessage());
        }

        $this->servico()->salvarAta($reuniao, 'Definimos o calendário de provas do último bimestre.');
        $fechada = $this->servico()->fecharAta($reuniao, $this->organizador);

        $this->assertSame(StatusReuniao::AtaFechada, $fechada->status);
        $this->assertSame($this->organizador->id, $fechada->ata_fechada_por_user_id);
        $this->assertNotNull($fechada->ata_fechada_em);
        // Os participantes (menos quem fechou) recebem o aviso: convite + ata fechada.
        $this->assertSame(2, $this->notificacoes($this->ana));
        $this->assertSame(0, $this->notificacoes($this->organizador));
    }

    public function test_a_ata_fechada_nao_muda_e_recebe_adendo(): void
    {
        $reuniao = $this->realizar($this->agendar());
        $this->servico()->salvarAta($reuniao, 'Texto original da ata.');
        $this->servico()->fecharAta($reuniao, $this->organizador);

        try {
            $this->servico()->salvarAta($reuniao, 'Tentativa de reescrever.');
            $this->fail('Ata fechada não muda.');
        } catch (ReuniaoInvalidaException $e) {
            $this->assertStringContainsString('adendo', $e->getMessage());
        }

        $this->assertSame('Texto original da ata.', $reuniao->refresh()->ata);

        $adendo = $this->servico()->registrarAdendo($reuniao, '  Onde se lê 30/10, leia 31/10.  ', $this->ana);
        $this->assertSame('Onde se lê 30/10, leia 31/10.', $adendo->texto);
        $this->assertSame($this->ana->id, $adendo->user_id);
        $this->assertSame('Texto original da ata.', $reuniao->refresh()->ata);
    }

    public function test_adendo_so_existe_para_ata_fechada(): void
    {
        $reuniao = $this->realizar($this->agendar());

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('ata já fechada');

        $this->servico()->registrarAdendo($reuniao, 'Complemento', $this->organizador);
    }

    // ─── Cancelar ────────────────────────────────────────────────────

    public function test_cancelar_exige_motivo_libera_a_sala_e_avisa(): void
    {
        $sala = Sala::factory()->create();
        $reuniao = $this->agendar(['sala_id' => $sala->id]);

        try {
            $this->servico()->cancelar($reuniao, $this->organizador, '  ');
            $this->fail('O motivo é obrigatório.');
        } catch (ReuniaoInvalidaException $e) {
            $this->assertStringContainsString('por que', $e->getMessage());
        }

        $cancelada = $this->servico()->cancelar($reuniao, $this->organizador, 'Feriado emendado');

        $this->assertSame(StatusReuniao::Cancelada, $cancelada->status);
        $this->assertSame('Feriado emendado', $cancelada->motivo_cancelamento);
        $this->assertSame(StatusReservaEspaco::Cancelada, $cancelada->reservaEspaco->refresh()->status);
        $this->assertSame(2, $this->notificacoes($this->ana));

        // O horário voltou a ficar livre.
        $outra = $this->agendar(['sala_id' => $sala->id, 'titulo' => 'No mesmo horário']);
        $this->assertSame(StatusReuniao::Agendada, $outra->status);
    }

    public function test_reuniao_realizada_nao_pode_ser_cancelada(): void
    {
        $reuniao = $this->realizar($this->agendar());

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('ainda agendada');

        $this->servico()->cancelar($reuniao, $this->organizador, 'Engano');
    }

    // ─── Encaminhamentos ─────────────────────────────────────────────

    public function test_registra_o_encaminhamento_e_avisa_o_responsavel(): void
    {
        $reuniao = $this->agendar();

        $encaminhamento = $this->servico()->adicionarEncaminhamento($reuniao, [
            'descricao' => 'Enviar o calendário de provas à secretaria',
            'responsavel_user_id' => $this->ana->id,
            'prazo' => '2026-11-06',
        ], $this->organizador);

        $this->assertSame(StatusEncaminhamento::Aberto, $encaminhamento->status);
        $this->assertSame('2026-11-06', $encaminhamento->prazo->toDateString());
        // Convite + novo encaminhamento.
        $this->assertSame(2, $this->notificacoes($this->ana));
    }

    public function test_encaminhamento_recusa_prazo_no_passado_e_descricao_vazia(): void
    {
        $reuniao = $this->agendar();

        foreach ([
            [['descricao' => 'Algo', 'prazo' => '2026-10-27'], 'já passou'],
            [['descricao' => ' '], 'o que foi combinado'],
        ] as [$dados, $trecho]) {
            try {
                $this->servico()->adicionarEncaminhamento($reuniao, $dados, $this->organizador);
                $this->fail("Deveria recusar: {$trecho}");
            } catch (ReuniaoInvalidaException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }

        $this->assertSame(0, ReuniaoEncaminhamento::count());
    }

    public function test_depois_da_ata_fechada_o_combinado_novo_vai_para_o_adendo(): void
    {
        $reuniao = $this->realizar($this->agendar());
        $this->servico()->salvarAta($reuniao, 'Ata.');
        $this->servico()->fecharAta($reuniao, $this->organizador);

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('adendo');

        $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Novo combinado'], $this->organizador);
    }

    public function test_conclui_e_cancela_o_encaminhamento_uma_vez_so(): void
    {
        $reuniao = $this->agendar();
        $feito = $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Imprimir as provas', 'responsavel_user_id' => $this->ana->id], $this->organizador);
        $cancelado = $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Reservar o auditório'], $this->organizador);

        $feito = $this->servico()->concluirEncaminhamento($feito, $this->ana, 'Entregues na xerox.');
        $this->assertSame(StatusEncaminhamento::Feito, $feito->status);
        $this->assertSame($this->ana->id, $feito->concluido_por_user_id);
        $this->assertSame('Entregues na xerox.', $feito->observacao_conclusao);

        $cancelado = $this->servico()->cancelarEncaminhamento($cancelado, $this->organizador, 'A escola já tem o auditório marcado.');
        $this->assertSame(StatusEncaminhamento::Cancelado, $cancelado->status);

        $this->expectException(ReuniaoInvalidaException::class);
        $this->expectExceptionMessage('já foi encerrado');

        $this->servico()->concluirEncaminhamento($feito, $this->ana);
    }

    // ─── Lembrete de prazo ───────────────────────────────────────────

    private function coordenacao(): User
    {
        $coordenacao = $this->usuario('Direção Pedagógica');
        $papel = Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        $papel->givePermissionTo(Permission::firstOrCreate(['name' => 'VerTodas:Reuniao', 'guard_name' => 'web']));
        $coordenacao->assignRole('coordenador');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $coordenacao;
    }

    public function test_o_lembrete_avisa_responsavel_e_coordenacao_uma_vez_so(): void
    {
        $coordenacao = $this->coordenacao();
        $reuniao = $this->agendar();
        $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Fechar as notas', 'responsavel_user_id' => $this->ana->id, 'prazo' => '2026-11-02'], $this->organizador);
        $semPrazo = $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Sem prazo', 'responsavel_user_id' => $this->bruno->id], $this->organizador);

        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));
        $this->assertSame(0, $this->servico()->lembrarEncaminhamentosVencidos(), 'No dia do prazo ainda não venceu.');

        $this->travelTo(Carbon::parse('2026-11-03 09:00:00'));
        $this->assertSame(1, $this->servico()->lembrarEncaminhamentosVencidos());
        $this->assertSame(0, $this->servico()->lembrarEncaminhamentosVencidos(), 'O aviso sai uma vez só.');

        // Ana: convite + encaminhamento + vencimento. Coordenação: só o vencimento.
        $this->assertSame(3, $this->notificacoes($this->ana));
        $this->assertSame(1, $this->notificacoes($coordenacao));
        $this->assertNull($semPrazo->refresh()->vencimento_avisado_em);
    }

    public function test_reuniao_sigilosa_nao_leva_o_lembrete_a_coordenacao(): void
    {
        $coordenacao = $this->coordenacao();
        $reuniao = $this->agendar(['tipo' => TipoReuniao::Devolutiva, 'participantes' => [$this->ana->id]]);
        $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Plano de melhoria', 'responsavel_user_id' => $this->ana->id, 'prazo' => '2026-11-02'], $this->organizador);

        $this->travelTo(Carbon::parse('2026-11-03 09:00:00'));
        $this->assertSame(1, $this->servico()->lembrarEncaminhamentosVencidos());

        $this->assertSame(0, $this->notificacoes($coordenacao));
        $this->assertSame(1, $this->notificacoes($this->organizador), 'Quem organiza é avisado.');
    }

    public function test_reuniao_cancelada_nao_gera_lembrete(): void
    {
        $reuniao = $this->agendar();
        $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Algo', 'responsavel_user_id' => $this->ana->id, 'prazo' => '2026-11-02'], $this->organizador);
        $this->servico()->cancelar($reuniao, $this->organizador, 'Cancelada');

        $this->travelTo(Carbon::parse('2026-11-03 09:00:00'));
        $this->assertSame(0, $this->servico()->lembrarEncaminhamentosVencidos());
    }

    // ─── Prazo da ata, PDF e assinatura ──────────────────────────────

    public function test_a_ata_em_aberto_atrasa_depois_do_prazo_configurado(): void
    {
        config(['reunioes.ata_prazo_dias' => 5]);
        $reuniao = $this->realizar($this->agendar());

        $this->assertFalse($this->servico()->ataAtrasada($reuniao));

        $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));
        $this->assertFalse($this->servico()->ataAtrasada($reuniao), 'O quinto dia ainda vale.');

        $this->travelTo(Carbon::parse('2026-11-05 08:00:00'));
        $this->assertTrue($this->servico()->ataAtrasada($reuniao));
    }

    public function test_gera_o_pdf_da_ata(): void
    {
        $reuniao = $this->realizar($this->agendar());
        $this->servico()->salvarAta($reuniao, 'Definimos o calendário de provas.');
        $this->servico()->adicionarEncaminhamento($reuniao, ['descricao' => 'Enviar o calendário', 'responsavel_user_id' => $this->ana->id], $this->organizador);

        $pdf = $this->servico()->pdfDaAta($reuniao->refresh())->output();

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_a_ata_so_vai_para_assinatura_depois_de_fechada_e_se_nao_for_sigilosa(): void
    {
        $reuniao = $this->realizar($this->agendar());
        $this->servico()->salvarAta($reuniao, 'Ata.');

        $this->assertStringContainsString('Feche a ata', (string) $this->servico()->impedimentoDeAssinatura($reuniao));

        $this->servico()->fecharAta($reuniao, $this->organizador);
        $this->assertNull($this->servico()->impedimentoDeAssinatura($reuniao->refresh()));

        $sigilosa = $this->agendar(['tipo' => TipoReuniao::Devolutiva, 'participantes' => [$this->ana->id], 'inicio_em' => '2026-10-30 15:00']);
        $this->servico()->marcarRealizada($sigilosa, $this->organizador, [$this->organizador->id => true]);
        $this->servico()->salvarAta($sigilosa, 'Conversa sobre o desempenho.');
        $this->servico()->fecharAta($sigilosa, $this->organizador);

        $this->assertStringContainsString('sigilosa', (string) $this->servico()->impedimentoDeAssinatura($sigilosa->refresh()));
    }

    public function test_sugere_como_assinantes_quem_esteve_presente(): void
    {
        $reuniao = $this->realizar($this->agendar());

        $sugestao = $this->servico()->signatariosSugeridos($reuniao->refresh());

        $emails = collect($sugestao['assinantes'])->pluck('email')->all();
        $this->assertEqualsCanonicalizing([mb_strtolower($this->organizador->email), mb_strtolower($this->ana->email)], $emails);
        $this->assertSame([], $sugestao['sem_email']);
    }

    public function test_envia_a_ata_fechada_para_assinatura_eletronica(): void
    {
        $reuniao = $this->realizar($this->agendar());
        $this->servico()->salvarAta($reuniao, 'Ata.');
        $this->servico()->fecharAta($reuniao, $this->organizador);

        $documento = $this->servico()->enviarAtaParaAssinatura($reuniao->refresh(), [
            ['nome' => 'Ana', 'email' => 'ana@escola.test', 'papel' => 'Participante'],
        ], $this->organizador);

        $this->assertInstanceOf(DocumentoAssinatura::class, $documento);
        $this->assertSame(Reuniao::class, $documento->documentavel_type);
        $this->assertSame($reuniao->id, (int) $documento->documentavel_id);
        $this->assertSame('Ata de reunião', $documento->tipoDoRegistro());
        $this->assertStringContainsString('Ata — Reunião pedagógica de novembro', $documento->titulo);

        // Há uma assinatura em andamento: não dá para abrir outra.
        $this->assertStringContainsString('em andamento', (string) $this->servico()->impedimentoDeAssinatura($reuniao->refresh()));
    }

    public function test_a_copia_assinada_fica_na_reuniao(): void
    {
        $reuniao = $this->realizar($this->agendar());
        $this->servico()->salvarAta($reuniao, 'Ata.');
        $this->servico()->fecharAta($reuniao, $this->organizador);

        $documento = $this->servico()->enviarAtaParaAssinatura($reuniao->refresh(), [['nome' => 'Ana', 'email' => 'ana@escola.test']], $this->organizador);
        Storage::disk('local')->put('documentos_assinatura/'.$documento->id.'/assinado.pdf', "%PDF-1.4\n%assinado\n%%EOF\n");
        $documento->forceFill(['arquivo_assinado_path' => 'documentos_assinatura/'.$documento->id.'/assinado.pdf', 'concluido_em' => now()])->save();

        $reuniao->refresh()->documentoAssinado($documento->refresh());

        $reuniao->refresh();
        $this->assertTrue($reuniao->temCopiaAssinada());
        $this->assertNotNull($this->servico()->caminhoDaCopiaAssinada($reuniao));
        $this->assertSame('A ata já tem uma cópia assinada.', $this->servico()->impedimentoDeAssinatura($reuniao));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AlunoResponsavel;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\FrequenciaResumoDiarioNotification;
use App\Services\FcmService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class ResumoFaltasDiarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $fcmMock = Mockery::mock(FcmService::class);
        $fcmMock->shouldReceive('sendPush')->andReturn(['success' => true]);
        $this->app->instance(FcmService::class, $fcmMock);
    }

    /**
     * @return array{0: Pessoa, 1: User}
     */
    private function responsavel(string $nome = 'Responsável'): array
    {
        $pessoa = Pessoa::factory()->create(['nome' => $nome]);
        $user = User::factory()->create();
        $pessoa->users()->attach($user->id);

        return [$pessoa, $user];
    }

    private function vincular(Matricula $matricula, Pessoa $responsavel): void
    {
        AlunoResponsavel::create([
            'aluno_id' => $matricula->pessoa_id,
            'responsavel_id' => $responsavel->id,
            'tipo_vinculo_id' => TipoVinculo::firstOrCreate(['nome' => 'Mãe'])->id,
        ]);
    }

    private function aluno(string $nome, Turma $turma): Matricula
    {
        $pessoa = Pessoa::factory()->create(['nome' => $nome]);

        return Matricula::factory()->create(['pessoa_id' => $pessoa->id, 'turma_id' => $turma->id]);
    }

    private function aula(Turma $turma, string $disciplina, string $inicio, string $fim, ?string $data = null): CronogramaAula
    {
        return CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => Disciplina::factory()->create(['nome' => $disciplina])->id,
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Ana Souza'])->id,
            'data' => $data ?? now()->toDateString(),
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
        ]);
    }

    private function falta(Matricula $matricula, CronogramaAula $aula, array $extra = []): FrequenciaEscolar
    {
        return FrequenciaEscolar::create(array_merge([
            'matricula_id' => $matricula->id,
            'cronograma_aula_id' => $aula->id,
            'situacao' => 'ausente',
        ], $extra));
    }

    private function resumoEnviadoA(User $user): FrequenciaResumoDiarioNotification
    {
        return Notification::sent($user, FrequenciaResumoDiarioNotification::class)->first();
    }

    private function enviar(): void
    {
        $this->artisan('frequencia:enviar-resumo-diario')->assertSuccessful();
    }

    public function test_varias_faltas_do_mesmo_aluno_viram_um_unico_aviso_em_ordem_de_horario(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);

        // Lançadas fora de ordem de propósito: o resumo sai por horário.
        $this->falta($joana, $this->aula($turma, 'Ciências', '10:00:00', '10:50:00'));
        $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'));
        $this->falta($joana, $this->aula($turma, 'Português', '09:00:00', '09:50:00'));

        $this->enviar();

        Notification::assertSentToTimes($user, FrequenciaResumoDiarioNotification::class, 1);
        $resumo = $this->resumoEnviadoA($user);
        $this->assertCount(3, $resumo->toDatabase($user)['frequencia_ids']);

        $html = (string) $resumo->toMail($user)->render();
        $this->assertStringContainsString('Joana', $html);
        $this->assertStringContainsString('3 faltas', $html);
        $this->assertStringContainsString('08:00 às 08:50', $html);
        $this->assertStringContainsString('(Prof. Ana Souza)', $html);
        $this->assertLessThan(strpos($html, 'Português'), strpos($html, 'Matemática'));
        $this->assertLessThan(strpos($html, 'Ciências'), strpos($html, 'Português'));
        // Uma falta por item da lista (o e-mail não pode juntar as aulas num parágrafo só).
        $this->assertSame(3, preg_match_all('/<li[\s>]/', $html));
    }

    public function test_assunto_e_lista_do_email(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'));

        $this->enviar();

        $mail = $this->resumoEnviadoA($user)->toMail($user);
        $this->assertSame('Faltas registradas - '.now()->format('d/m/Y'), $mail->subject);
        $this->assertSame(1, preg_match_all('/<li[\s>]/', (string) $mail->render()));
    }

    public function test_dois_filhos_do_mesmo_responsavel_viram_um_aviso_com_dois_alunos(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        $pedro = $this->aluno('Pedro', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $this->vincular($pedro, $pessoa);
        $aula = $this->aula($turma, 'Matemática', '08:00:00', '08:50:00');
        $this->falta($joana, $aula);
        $this->falta($pedro, $aula);

        $this->enviar();

        Notification::assertSentToTimes($user, FrequenciaResumoDiarioNotification::class, 1);
        $resumo = $this->resumoEnviadoA($user);
        $this->assertSame('Faltas registradas: 2 alunos', $resumo->toDatabase($user)['title']);
        $html = (string) $resumo->toMail($user)->render();
        $this->assertStringContainsString('Joana', $html);
        $this->assertStringContainsString('Pedro', $html);
    }

    public function test_cada_responsavel_do_aluno_recebe_o_seu(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$mae, $userMae] = $this->responsavel('Mãe');
        [$pai, $userPai] = $this->responsavel('Pai');
        $this->vincular($joana, $mae);
        $this->vincular($joana, $pai);
        $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'));

        $this->enviar();

        Notification::assertSentToTimes($userMae, FrequenciaResumoDiarioNotification::class, 1);
        Notification::assertSentToTimes($userPai, FrequenciaResumoDiarioNotification::class, 1);
    }

    public function test_falta_corrigida_para_presenca_antes_do_corte_nao_entra(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $falta = $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'));
        $falta->update(['situacao' => 'presente']);

        $this->enviar();

        Notification::assertNothingSent();
    }

    public function test_falta_apagada_antes_do_corte_nao_entra(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'))->delete();

        $this->enviar();

        Notification::assertNothingSent();
    }

    public function test_falta_justificada_fica_fora_do_resumo(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'), ['justificada' => true]);

        $this->enviar();

        Notification::assertNothingSent();
    }

    public function test_falta_de_aluno_sem_responsavel_com_usuario_e_descartada_e_nao_acumula(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        $falta = $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'));

        $this->artisan('frequencia:enviar-resumo-diario')
            ->expectsOutputToContain('1 sem responsável com usuário')
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertFalse($falta->fresh()->aviso_resumo_pendente);

        // Responsável cadastrado depois não recebe uma falta de dias atrás.
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $this->enviar();
        Notification::assertNothingSent();
    }

    public function test_responsavel_cadastrado_antes_do_corte_recebe_a_falta_lancada_de_manha(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'));

        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $this->enviar();

        Notification::assertSentToTimes($user, FrequenciaResumoDiarioNotification::class, 1);
    }

    public function test_segunda_rodada_nao_reenvia(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $falta = $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'));

        $this->enviar();
        $this->artisan('frequencia:enviar-resumo-diario')
            ->expectsOutputToContain('Nenhuma falta pendente de aviso')
            ->assertSuccessful();

        Notification::assertSentToTimes($user, FrequenciaResumoDiarioNotification::class, 1);
        $falta = $falta->fresh();
        $this->assertFalse($falta->aviso_resumo_pendente);
        $this->assertNotNull($falta->aviso_resumo_enviado_em);
    }

    public function test_sem_faltas_pendentes_nao_envia_nada(): void
    {
        Notification::fake();

        $this->artisan('frequencia:enviar-resumo-diario')
            ->expectsOutputToContain('Nenhuma falta pendente de aviso')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_aula_de_ontem_lancada_hoje_entra_com_a_data_da_aula(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $ontem = now()->subDay();
        $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00', $ontem->toDateString()));

        $this->enviar();

        $html = (string) $this->resumoEnviadoA($user)->toMail($user)->render();
        $this->assertStringContainsString($ontem->format('d/m/Y').' · 08:00 às 08:50', $html);
    }

    public function test_dry_run_mostra_o_que_sairia_sem_consumir_as_faltas(): void
    {
        Notification::fake();
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $falta = $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'));

        $this->artisan('frequencia:enviar-resumo-diario', ['--dry-run' => true])
            ->expectsOutputToContain("{$user->email}: 1 falta(s) de Joana")
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertTrue($falta->fresh()->aviso_resumo_pendente);

        $this->enviar();
        Notification::assertSentToTimes($user, FrequenciaResumoDiarioNotification::class, 1);
    }

    public function test_falha_no_envio_devolve_a_falta_para_a_fila(): void
    {
        $turma = Turma::factory()->create();
        $joana = $this->aluno('Joana', $turma);
        [$pessoa, $user] = $this->responsavel();
        $this->vincular($joana, $pessoa);
        $falta = $this->falta($joana, $this->aula($turma, 'Matemática', '08:00:00', '08:50:00'));

        Event::listen(NotificationSending::class, fn () => throw new \RuntimeException('fila fora do ar'));

        $this->artisan('frequencia:enviar-resumo-diario')->assertFailed();

        $falta = $falta->fresh();
        $this->assertTrue($falta->aviso_resumo_pendente);
        $this->assertNull($falta->aviso_resumo_enviado_em);
    }

    public function test_o_comando_esta_agendado_no_horario_da_configuracao(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'frequencia:enviar-resumo-diario'));

        $this->assertNotNull($evento, 'O comando precisa estar no agendador.');
        $this->assertSame('0 18 * * *', $evento->expression);
    }
}

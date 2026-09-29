<?php

namespace Tests\Feature;

use App\Models\AlunoResponsavel;
use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\FrequenciaAusenciaNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class FrequenciaAusenciaNotificationTest extends TestCase
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
     * @return array{matricula: Matricula, responsavelUser: User, aula: CronogramaAula}
     */
    private function alunoComResponsavel(array $matriculaAttrs = []): array
    {
        $turma = Turma::factory()->create();
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Faltoso']);
        $matricula = Matricula::factory()->create(array_merge([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
        ], $matriculaAttrs));

        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Responsável do Faltoso']);
        $responsavelUser = User::factory()->create();
        $responsavelPessoa->users()->attach($responsavelUser->id);

        AlunoResponsavel::create([
            'aluno_id' => $aluno->id,
            'responsavel_id' => $responsavelPessoa->id,
            'tipo_vinculo_id' => TipoVinculo::create(['nome' => 'Mãe'])->id,
        ]);

        $aula = CronogramaAula::factory()->create(['turma_id' => $turma->id, 'data' => now()->toDateString()]);

        return compact('matricula', 'responsavelUser', 'aula');
    }

    public function test_registrar_falta_notifica_o_responsavel(): void
    {
        Notification::fake();
        $dados = $this->alunoComResponsavel();

        FrequenciaEscolar::create([
            'matricula_id' => $dados['matricula']->id,
            'cronograma_aula_id' => $dados['aula']->id,
            'situacao' => 'ausente',
        ]);

        Notification::assertSentTo($dados['responsavelUser'], FrequenciaAusenciaNotification::class);
    }

    public function test_presenca_nao_notifica(): void
    {
        Notification::fake();
        $dados = $this->alunoComResponsavel();

        FrequenciaEscolar::create([
            'matricula_id' => $dados['matricula']->id,
            'cronograma_aula_id' => $dados['aula']->id,
            'situacao' => 'presente',
        ]);

        Notification::assertNothingSent();
    }

    public function test_alterar_presenca_para_falta_notifica(): void
    {
        Notification::fake();
        $dados = $this->alunoComResponsavel();

        $frequencia = FrequenciaEscolar::create([
            'matricula_id' => $dados['matricula']->id,
            'cronograma_aula_id' => $dados['aula']->id,
            'situacao' => 'presente',
        ]);

        Notification::assertNothingSent();

        $frequencia->update(['situacao' => 'ausente']);

        Notification::assertSentTo($dados['responsavelUser'], FrequenciaAusenciaNotification::class);
    }

    public function test_manter_falta_nao_reenvia_notificacao(): void
    {
        Notification::fake();
        $dados = $this->alunoComResponsavel();

        $frequencia = FrequenciaEscolar::create([
            'matricula_id' => $dados['matricula']->id,
            'cronograma_aula_id' => $dados['aula']->id,
            'situacao' => 'ausente',
        ]);

        Notification::assertSentToTimes($dados['responsavelUser'], FrequenciaAusenciaNotification::class, 1);

        $frequencia->update(['situacao' => 'ausente']);

        Notification::assertSentToTimes($dados['responsavelUser'], FrequenciaAusenciaNotification::class, 1);
    }

    public function test_nao_notifica_falta_de_aula_anterior_a_ativacao_da_matricula(): void
    {
        Notification::fake();
        $dados = $this->alunoComResponsavel(['data_ativacao' => now()->addDays(5)]);

        FrequenciaEscolar::create([
            'matricula_id' => $dados['matricula']->id,
            'cronograma_aula_id' => $dados['aula']->id,
            'situacao' => 'ausente',
        ]);

        Notification::assertNothingSent();
    }

    public function test_nao_notifica_falta_apos_desativacao_da_matricula(): void
    {
        Notification::fake();
        $dados = $this->alunoComResponsavel(['data_desativacao' => now()->subDays(5)]);

        FrequenciaEscolar::create([
            'matricula_id' => $dados['matricula']->id,
            'cronograma_aula_id' => $dados['aula']->id,
            'situacao' => 'ausente',
        ]);

        Notification::assertNothingSent();
    }

    public function test_nao_notifica_quando_aluno_nao_tem_responsavel_com_usuario(): void
    {
        Notification::fake();

        $turma = Turma::factory()->create();
        $aluno = Pessoa::factory()->create();
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id, 'turma_id' => $turma->id]);
        $aula = CronogramaAula::factory()->create(['turma_id' => $turma->id, 'data' => now()->toDateString()]);

        FrequenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'cronograma_aula_id' => $aula->id,
            'situacao' => 'ausente',
        ]);

        Notification::assertNothingSent();
    }
}

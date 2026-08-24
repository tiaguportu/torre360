<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GerarBoletinsTurmaPdfJob;
use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\BoletimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GerarBoletinsTurmaPdfJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_pdf_e_notifica_usuario_quando_ha_dados(): void
    {
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();

        $periodo = PeriodoLetivo::factory()->create();
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
        $etapa = EtapaAvaliativa::create([
            'nome' => '1ª Etapa Teste',
            'periodo_letivo_id' => $periodo->id,
            'turma_id' => $turma->id,
            'data_inicio' => now()->subMonths(2)->toDateString(),
            'data_fim' => now()->addMonth()->toDateString(),
        ]);
        $matricula = Matricula::factory()->create([
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);
        $avaliacao = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => Disciplina::factory()->create()->id,
            'etapa_avaliativa_id' => $etapa->id,
            'peso_etapa_avaliativa' => 1.0,
            'nota_maxima' => 10.0,
            'data_ocorrencia' => now()->toDateString(),
            'data_limite_lancamento' => now()->addDays(5)->toDateString(),
        ]);
        Nota::create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'valor' => 8.5,
        ]);

        (new GerarBoletinsTurmaPdfJob(
            turmaIds: [$turma->id],
            etapaId: $etapa->id,
            userId: $user->id,
        ))->handle(app(BoletimService::class));

        Storage::disk('local')->assertExists(
            collect(Storage::disk('local')->allFiles('boletins/'.$user->id))->first()
        );

        Notification::assertSentTo($user, SystemNotification::class, function (SystemNotification $notification) {
            return $notification->type === 'success' && ! empty($notification->actionUrl);
        });
    }

    public function test_notifica_alerta_quando_nao_ha_dados_para_gerar(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $periodo = PeriodoLetivo::factory()->create();
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);

        (new GerarBoletinsTurmaPdfJob(
            turmaIds: [$turma->id],
            etapaId: null,
            userId: $user->id,
        ))->handle(app(BoletimService::class));

        Notification::assertSentTo($user, SystemNotification::class, function (SystemNotification $notification) {
            return $notification->type === 'warning';
        });
    }
}

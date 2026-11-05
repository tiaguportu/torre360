<?php

namespace Tests\Feature;

use App\Filament\Widgets\QueueSupervisorWidget;
use App\Jobs\EnviarComunicacaoEmMassaJob;
use App\Jobs\GerarBoletinsTurmaPdfJob;
use App\Jobs\GerarCrachasTurmaPdfJob;
use App\Jobs\GerarFolhasTurmaPdfJob;
use App\Jobs\GerarInformeAnualLotePdfJob;
use App\Jobs\ProcessarGabaritoOmrJob;
use App\Jobs\ValidarDocumentoComIaJob;
use App\Models\ComunicacaoEmMassa;
use App\Support\FilaLonga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

/**
 * Jobs que levam minutos vão para a conexão `database_longa` (mesma tabela `jobs`, `retry_after` maior), com worker
 * próprio. Antes ficavam na fila padrão, com `retry_after` de 90 s, e podiam rodar duas vezes e atrasar e-mails.
 */
class FilaLongaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, object>
     */
    private function jobsLongos(): array
    {
        return [
            'boletins' => new GerarBoletinsTurmaPdfJob([1], null, 1),
            'crachás' => new GerarCrachasTurmaPdfJob([1], 1, 1),
            'folhas de gabarito' => new GerarFolhasTurmaPdfJob(1, 1),
            'informe anual' => new GerarInformeAnualLotePdfJob(2026, null, null, 1),
            'comunicação em massa' => new EnviarComunicacaoEmMassaJob(new ComunicacaoEmMassa),
            'leitura de gabarito' => new ProcessarGabaritoOmrJob(1, 'omr/x.png'),
        ];
    }

    public function test_com_a_fila_no_banco_os_jobs_longos_vao_para_a_conexao_e_a_fila_longas(): void
    {
        config(['queue.default' => 'database']);

        foreach ($this->jobsLongos() as $nome => $job) {
            $this->assertSame('database_longa', $job->connection, "job {$nome}: conexão");
            $this->assertSame('longas', $job->queue, "job {$nome}: fila");
        }
    }

    public function test_com_outro_driver_o_job_segue_a_conexao_padrao(): void
    {
        // Nos testes a fila é `sync`: o job continua rodando na hora, como antes.
        foreach ($this->jobsLongos() as $nome => $job) {
            $this->assertNull($job->connection, "job {$nome}: conexão");
            $this->assertNull($job->queue, "job {$nome}: fila");
        }
    }

    public function test_fila_do_gabarito_escolhida_a_mao_e_respeitada(): void
    {
        config(['queue.default' => 'database', 'omr.fila' => 'gabaritos']);

        $job = new ProcessarGabaritoOmrJob(1, 'omr/x.png');

        $this->assertSame('gabaritos', $job->queue);
        $this->assertNull($job->connection);
    }

    public function test_o_job_longo_cai_na_fila_longa_e_o_worker_da_fila_padrao_nao_o_enxerga(): void
    {
        config(['queue.default' => 'database']);

        dispatch(new GerarCrachasTurmaPdfJob([1], 1, 1));

        $this->assertSame(1, DB::table('jobs')->where('queue', 'longas')->count());
        $this->assertSame(0, DB::table('jobs')->where('queue', 'default')->count());

        // O worker da fila padrão (conexão `database`, fila `default`) não pega o job longo...
        $this->assertNull(Queue::connection('database')->pop('default'));

        // ...e o da conexão longa pega, com o retry_after dela.
        $this->assertNotNull(Queue::connection('database_longa')->pop('longas'));
    }

    public function test_job_longo_e_publicado_na_fila_longas(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();

        GerarBoletinsTurmaPdfJob::dispatch([1], null, 1);

        Queue::assertPushedOn('longas', GerarBoletinsTurmaPdfJob::class);
    }

    public function test_o_retry_after_da_fila_longa_cobre_o_maior_timeout(): void
    {
        $this->assertGreaterThan(FilaLonga::TIMEOUT_MAXIMO, (int) config('queue.connections.database_longa.retry_after'));
        $this->assertSame(
            config('queue.connections.database.table'),
            config('queue.connections.database_longa.table'),
            'a fila longa tem de usar a mesma tabela `jobs`, ou o painel da fila e o "Processar Fila" não a enxergam'
        );
    }

    /**
     * Guarda: todo job do sistema tem de caber no `retry_after` da conexão em que roda. Job novo com `timeout` de 90 s
     * ou mais que não chame `FilaLonga::direcionar()` volta a poder ser entregue duas vezes.
     */
    public function test_todo_job_tem_timeout_menor_que_o_retry_after_da_conexao_em_que_roda(): void
    {
        $retryAfterPadrao = (int) config('queue.connections.database.retry_after');
        $retryAfterLonga = (int) config('queue.connections.database_longa.retry_after');
        $arquivos = glob(app_path('Jobs/*.php'));

        $this->assertNotEmpty($arquivos);

        foreach ($arquivos as $arquivo) {
            $classe = 'App\\Jobs\\'.basename($arquivo, '.php');
            $timeout = (new ReflectionClass($classe))->getDefaultProperties()['timeout'] ?? null;

            if ($timeout === null) {
                continue; // sem `timeout` próprio vale o do worker (60 s), abaixo de qualquer retry_after
            }

            if (str_contains((string) file_get_contents($arquivo), 'FilaLonga::direcionar')) {
                $this->assertLessThan($retryAfterLonga, $timeout, "{$classe}: timeout deve ficar abaixo do retry_after da fila longa");
                $this->assertLessThanOrEqual(FilaLonga::TIMEOUT_MAXIMO, $timeout, "{$classe}: timeout acima do que o worker da fila longa aceita");

                continue;
            }

            $this->assertLessThan(
                $retryAfterPadrao,
                $timeout,
                "{$classe}: timeout de {$timeout} s não cabe no retry_after da fila padrão ({$retryAfterPadrao} s). Chame FilaLonga::direcionar(\$this) no construtor."
            );
        }
    }

    public function test_o_job_da_ia_continua_na_fila_propria_com_timeout_abaixo_do_retry_after(): void
    {
        config(['queue.default' => 'database']);

        $job = new ValidarDocumentoComIaJob(1);

        $this->assertSame('ia', $job->queue);
        $this->assertNull($job->connection);
    }

    public function test_agendador_tem_um_worker_para_a_fila_longa_alem_do_padrao_e_do_da_ia(): void
    {
        Artisan::call('schedule:list');
        $saida = Artisan::output();

        $this->assertStringContainsString('queue:work database_longa --queue=longas', $saida);
        $this->assertStringContainsString('--timeout='.FilaLonga::TIMEOUT_MAXIMO, $saida);
        $this->assertMatchesRegularExpression('/queue:work --stop-when-empty/', $saida);
        $this->assertStringContainsString('queue:work --queue=ia', $saida);
    }

    public function test_botao_processar_fila_agora_tambem_atende_a_fila_longa(): void
    {
        // Com a fila padrão em `sync` (testes) não há o que processar na conexão longa.
        $this->assertNull(QueueSupervisorWidget::filaLongaParaProcessar());

        config(['queue.default' => 'database']);

        $this->assertSame(
            ['conexao' => 'database_longa', 'fila' => 'longas', 'timeout' => FilaLonga::TIMEOUT_MAXIMO],
            QueueSupervisorWidget::filaLongaParaProcessar()
        );
        // O comando da fila padrão e o da IA não mudam.
        $this->assertSame('default,ia', QueueSupervisorWidget::filasParaProcessar());
    }
}

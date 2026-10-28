<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\InformeAnualMensalidades;
use App\Jobs\GerarInformeAnualLotePdfJob;
use App\Models\Banco;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\InformeAnualMensalidadesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RelatorioTemporarioEInformeLoteAsyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Turma $turma;

    private Banco $banco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('GerarInformeAnual:Fatura', 'web');
        $role = Role::findOrCreate('secretaria', 'web');
        $role->givePermissionTo('GerarInformeAnual:Fatura');

        $this->user = User::factory()->create([
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);
        $this->user->assignRole($role);

        $unidade = Unidade::create(['nome' => 'Sede', 'cnpj' => '12345678000190']);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '5º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->turma = Turma::create(['nome' => '5º Ano A', 'serie_id' => $serie->id, 'periodo_letivo_id' => $periodo->id]);
        $this->banco = Banco::create(['nome' => 'Banco Principal']);
    }

    public function test_download_relatorio_temporario_com_url_assinada_valida(): void
    {
        Storage::fake('local');
        $token = 'informe-2026-abc123def456';
        Storage::disk('local')->put("relatorios_gerados/{$token}.pdf", '%PDF-1.4 Conteudo ficticio de teste');

        $url = URL::temporarySignedRoute(
            'relatorios.download-temporario',
            now()->addHour(),
            [
                'token' => $token,
                'filename' => 'informe-anual-2026.pdf',
            ]
        );

        $response = $this->actingAs($this->user)->get($url);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('x-content-type-options', 'nosniff');
        $response->assertHeader('content-security-policy', "default-src 'none'; sandbox");
    }

    public function test_download_rejeita_sem_assinatura_valida_ou_expirada(): void
    {
        Storage::fake('local');
        $token = 'informe-2026-abc123def456';
        Storage::disk('local')->put("relatorios_gerados/{$token}.pdf", '%PDF-1.4 Conteudo ficticio');

        $urlSemAssinatura = route('relatorios.download-temporario', ['token' => $token]);

        $this->actingAs($this->user)
            ->get($urlSemAssinatura)
            ->assertForbidden();
    }

    public function test_download_rejeita_path_traversal_no_token(): void
    {
        Storage::fake('local');
        $tokenInvalido = 'token..malicioso';

        $url = URL::temporarySignedRoute(
            'relatorios.download-temporario',
            now()->addHour(),
            ['token' => $tokenInvalido]
        );

        $this->actingAs($this->user)
            ->get($url)
            ->assertStatus(400);
    }

    public function test_download_retorna_404_quando_arquivo_nao_existe(): void
    {
        Storage::fake('local');
        $token = 'informe-inexistente-123';

        $url = URL::temporarySignedRoute(
            'relatorios.download-temporario',
            now()->addHour(),
            ['token' => $token]
        );

        $this->actingAs($this->user)
            ->get($url)
            ->assertNotFound();
    }

    public function test_download_rejeita_usuario_inativo(): void
    {
        Storage::fake('local');
        $token = 'informe-2026-teste';
        Storage::disk('local')->put("relatorios_gerados/{$token}.pdf", '%PDF-1.4');

        $url = URL::temporarySignedRoute(
            'relatorios.download-temporario',
            now()->addHour(),
            ['token' => $token]
        );

        $this->user->update(['deactivated_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get($url)
            ->assertForbidden();
    }

    public function test_action_lote_async_enfileira_o_job_no_filament(): void
    {
        Queue::fake();

        Livewire::actingAs($this->user)
            ->test(InformeAnualMensalidades::class)
            ->set('ano', 2026)
            ->callAction('loteAsync');

        Queue::assertPushed(GerarInformeAnualLotePdfJob::class, function (GerarInformeAnualLotePdfJob $job) {
            return $job->ano === 2026 && $job->userId === $this->user->id;
        });
    }

    public function test_job_gera_informe_em_lote_grava_em_disco_e_notifica(): void
    {
        Storage::fake('local');

        // Cria aluno com fatura e pagamento em 2026
        $pessoa = Pessoa::create(['nome' => 'Aluno Teste']);
        $matricula = Matricula::create([
            'pessoa_id' => $pessoa->id,
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ]);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000]);
        $responsavel = Pessoa::create(['nome' => 'Responsavel Teste']);
        $contrato->responsaveisFinanceiros()->create(['pessoa_id' => $responsavel->id, 'percentual' => 100]);

        $fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-03-10', 'status' => 'pago']);
        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade Março',
            'valor_unitario' => 1000,
            'quantidade' => 1,
            'desconto' => 0,
            'tipo_desconto' => 'percentual',
        ]);
        TransacaoBancaria::create([
            'fatura_id' => $fatura->id,
            'banco_id' => $this->banco->id,
            'tipo' => 'entrada',
            'valor' => 1000,
            'data_transacao' => '2026-03-10 10:00:00',
        ]);

        $job = new GerarInformeAnualLotePdfJob(2026, null, null, $this->user->id);
        $job->handle(app(InformeAnualMensalidadesService::class));

        // Deve existir ao menos um arquivo gerado no disco
        $arquivos = Storage::disk('local')->files('relatorios_gerados');
        $this->assertNotEmpty($arquivos);

        // Usuário deve ter recebido notificação no banco de dados com a ação de download
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->user->id,
            'notifiable_type' => User::class,
        ]);

        // Trilha de auditoria no ActivityLog
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'informe_anual',
            'causer_id' => $this->user->id,
        ]);
    }
}

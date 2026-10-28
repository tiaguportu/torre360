<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusFatura;
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
use App\Models\ResponsavelFinanceiro;
use App\Models\Serie;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\InformeAnualMensalidadesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class RelatoriosAssincronosQueueTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'GerarInformeAnual:Fatura', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'activated_at' => now()->subDay(),
        ]);
        $this->adminUser->assignRole('super_admin');
        $this->adminUser->givePermissionTo('GerarInformeAnual:Fatura');
    }

    private function criarCenarioComPagamento(int $ano = 2026): array
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste']);
        $curso = Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'EF',
            'unidade_id' => $unidade->id,
        ]);
        $periodo = PeriodoLetivo::create([
            'nome' => (string) $ano,
            'ano' => $ano,
            'data_inicio' => "{$ano}-02-01",
            'data_fim' => "{$ano}-12-15",
        ]);
        $serie = Serie::create([
            'nome' => '1º Ano',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
        $turma = Turma::create([
            'nome' => 'Turma 101',
            'serie_id' => $serie->id,
            'periodo_letivo_id' => $periodo->id,
        ]);
        $aluno = Pessoa::create(['nome' => 'Aluno Relatorio Teste']);
        $resp = Pessoa::create(['nome' => 'Responsavel Financeiro Teste', 'cpf' => '12345678909']);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'ano_letivo' => $ano,
            'status' => 'ativa',
            'data_matricula' => now(),
        ]);
        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => 1200.00,
        ]);
        ResponsavelFinanceiro::create([
            'contrato_id' => $contrato->id,
            'pessoa_id' => $resp->id,
            'percentual_pagamento' => 100.00,
            'principal' => true,
        ]);
        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => "{$ano}-03-10",
            'status' => StatusFatura::Pago,
        ]);
        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade Escolar Março',
            'valor_unitario' => 1200.00,
            'quantidade' => 1,
        ]);
        $banco = Banco::create([
            'nome' => 'Banco do Brasil',
            'agencia' => '0001',
            'conta' => '12345-6',
            'is_active' => true,
        ]);

        TransacaoBancaria::create([
            'banco_id' => $banco->id,
            'fatura_id' => $fatura->id,
            'tipo' => 'entrada',
            'valor' => 1200.00,
            'data_transacao' => "{$ano}-03-08",
        ]);

        return compact('turma', 'aluno', 'matricula');
    }

    public function test_job_gera_informe_anual_em_lote_e_salva_no_disco_privado(): void
    {
        $cenario = $this->criarCenarioComPagamento(2026);

        $job = new GerarInformeAnualLotePdfJob(2026, $cenario['turma']->id, null, $this->adminUser->id);
        $job->handle(app(InformeAnualMensalidadesService::class));

        // Verifica se o arquivo foi criado no disco privado
        $arquivos = Storage::disk('local')->files('relatorios_gerados');
        $this->assertNotEmpty($arquivos, 'O relatório PDF deve ser gravado na pasta relatorios_gerados.');

        $arquivoGerado = $arquivos[0];
        $this->assertStringEndsWith('.pdf', $arquivoGerado);

        // Verifica se notificação foi enviada ao banco de dados do usuário
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->adminUser->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_job_reutiliza_cache_privado_quando_solicitado_novamente(): void
    {
        $cenario = $this->criarCenarioComPagamento(2026);

        $job = new GerarInformeAnualLotePdfJob(2026, $cenario['turma']->id, null, $this->adminUser->id);
        $job->handle(app(InformeAnualMensalidadesService::class));

        $arquivosIniciais = Storage::disk('local')->files('relatorios_gerados');
        $this->assertCount(1, $arquivosIniciais);

        // Executa o mesmo job novamente (mesmos parâmetros)
        $job2 = new GerarInformeAnualLotePdfJob(2026, $cenario['turma']->id, null, $this->adminUser->id);
        $job2->handle(app(InformeAnualMensalidadesService::class));

        // Não deve criar arquivo duplicado, deve manter exatamente o mesmo em cache
        $arquivosFinais = Storage::disk('local')->files('relatorios_gerados');
        $this->assertCount(1, $arquivosFinais);
    }

    public function test_download_temporario_permite_acesso_com_url_assinada_valida(): void
    {
        $token = 'informes-2026-teste-token-valido';
        Storage::disk('local')->put("relatorios_gerados/{$token}.pdf", '%PDF-1.4 Fake PDF Content');

        $urlAssinada = URL::temporarySignedRoute(
            'relatorios.download-temporario',
            now()->addHours(24),
            ['token' => $token, 'filename' => 'informe.pdf']
        );

        $response = $this->actingAs($this->adminUser)->get($urlAssinada);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_download_temporario_recusa_acesso_sem_assinatura_ou_adulterado(): void
    {
        $token = 'informes-2026-teste-token-invalido';
        Storage::disk('local')->put("relatorios_gerados/{$token}.pdf", '%PDF-1.4 Fake PDF Content');

        // URL sem assinatura criptográfica (direta sem hash HMAC)
        $urlSemAssinatura = route('relatorios.download-temporario', ['token' => $token]);

        $response = $this->actingAs($this->adminUser)->get($urlSemAssinatura);
        $response->assertForbidden(); // 403 Invalid Signature
    }

    public function test_download_temporario_bloqueia_path_traversal(): void
    {
        $tokenMalicioso = 'token..tentativa..traversal';
        $urlAssinada = URL::temporarySignedRoute(
            'relatorios.download-temporario',
            now()->addHours(24),
            ['token' => $tokenMalicioso]
        );

        $response = $this->actingAs($this->adminUser)->get($urlAssinada);
        $response->assertStatus(400); // 400 Bad Request devido à regex defensiva
    }

    public function test_tela_informe_anual_enfileira_job_ao_clicar_em_lote_async(): void
    {
        Queue::fake();

        $this->actingAs($this->adminUser);

        Livewire::test(InformeAnualMensalidades::class)
            ->assertSuccessful()
            ->callAction('loteAsync')
            ->assertNotified();

        Queue::assertPushed(GerarInformeAnualLotePdfJob::class);
    }
}

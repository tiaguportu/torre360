<?php

namespace Tests\Feature;

use App\Enums\StatusAssinaturaContrato;
use App\Filament\Resources\Contratos\Pages\ListContratos;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TipoVinculo;
use App\Models\User;
use App\Services\AssinafyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssinafyAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    private const DOC_ID = 'DOC-ASSINAFY-1';

    protected function setUp(): void
    {
        parent::setUp();

        // O .env local pode ter credenciais reais do Assinafy: nenhum teste pode chamar a API de verdade.
        Http::preventStrayRequests();
        $this->configurarApi(false);
    }

    private function configurarApi(bool $ativa): void
    {
        config([
            'services.assinafy.key' => $ativa ? 'CHAVE-DE-TESTE' : '',
            'services.assinafy.url' => 'https://assinafy.teste/v1',
            'services.assinafy.webhook_secret' => null,
        ]);
    }

    /**
     * Http::fake() acumula respostas e vale a primeira que casa; recomeçar garante que a última definição prevaleça.
     *
     * @param  array<string, mixed>  $respostas
     */
    private function fakeHttp(array $respostas): void
    {
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        Http::fake($respostas);
    }

    private function servico(): AssinafyService
    {
        return new AssinafyService;
    }

    private function criarContrato(string $status = 'enviado', string $dataAceite = '2026-01-10 10:00:00'): Contrato
    {
        return Contrato::create([
            'matricula_id' => Matricula::factory()->create()->id,
            'valor_total' => 0,
            'assinafy_id' => self::DOC_ID,
            'assinafy_status' => $status,
            'data_aceite' => $dataAceite,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function evento(string $evento, ?Carbon $quando = null, array $extra = []): array
    {
        return [
            'id' => 987,
            'event' => $evento,
            'created_at' => ($quando ?? Carbon::parse('2026-09-30 14:05:00'))->timestamp,
            'object' => ['id' => self::DOC_ID],
            ...$extra,
        ];
    }

    private function fakeApi(string $statusDocumento, array $signatarios = []): void
    {
        $this->fakeHttp([
            '*/documents/*' => Http::response([
                'data' => [
                    'id' => self::DOC_ID,
                    'status' => $statusDocumento,
                    'assignment' => ['signers' => $signatarios],
                ],
            ], 200),
        ]);
    }

    #[Test]
    public function document_ready_mantem_o_status_ready_e_define_data_aceite_pelo_momento_da_conclusao(): void
    {
        $contrato = $this->criarContrato();

        $this->assertTrue($this->servico()->handleWebhook($this->evento('document_ready')));

        $contrato->refresh();
        $this->assertSame('ready', $contrato->assinafy_status);
        $this->assertTrue($contrato->estaAssinado());
        $this->assertTrue($contrato->data_aceite->equalTo(Carbon::parse('2026-09-30 14:05:00')));

        // A pendência de contrato da matrícula deixa de existir
        $this->assertFalse(Matricula::find($contrato->matricula_id)->pendencias->contratoNaoAssinado);
    }

    #[Test]
    public function assinatura_individual_nao_conclui_o_contrato_nem_altera_data_aceite(): void
    {
        $contrato = $this->criarContrato();

        $this->servico()->handleWebhook($this->evento('signer_signed_document', extra: [
            'subject' => ['type' => 'Signer', 'email' => 'Mae@Teste.com'],
        ]));

        $contrato->refresh();
        $this->assertSame('enviado', $contrato->assinafy_status);
        $this->assertFalse($contrato->estaAssinado());
        $this->assertTrue($contrato->data_aceite->equalTo(Carbon::parse('2026-01-10 10:00:00')));
        $this->assertSame('signed', $contrato->assinafy_request_log['signers_status']['mae@teste.com']['status']);
    }

    #[Test]
    public function eventos_informativos_nao_alteram_o_status(): void
    {
        $contrato = $this->criarContrato();

        foreach (['signer_viewed_document', 'assignment_created', 'document_metadata_ready', 'signer_email_verified'] as $evento) {
            $this->assertTrue($this->servico()->handleWebhook($this->evento($evento, extra: [
                'subject' => ['type' => 'Signer', 'email' => 'pai@teste.com'],
            ])));
        }

        $contrato->refresh();
        $this->assertSame('enviado', $contrato->assinafy_status);
        $this->assertSame('signer_email_verified', $contrato->assinafy_request_log['webhook_last']['event']);
        // Visualizar o documento não é assinar
        $this->assertArrayNotHasKey('pai@teste.com', $contrato->assinafy_request_log['signers_status'] ?? []);
    }

    #[Test]
    public function contrato_assinado_nao_regride_e_mantem_a_data_aceite(): void
    {
        $contrato = $this->criarContrato();
        $primeiro = Carbon::parse('2026-09-30 14:05:00');

        $this->servico()->handleWebhook($this->evento('document_ready', $primeiro));

        // Eventos atrasados ou repetidos depois da conclusão
        $this->servico()->handleWebhook($this->evento('signer_signed_document', $primeiro->copy()->addMinute()));
        $this->servico()->handleWebhook($this->evento('document_uploaded', $primeiro->copy()->addMinutes(2)));
        $this->servico()->handleWebhook($this->evento('user_rejected_document', $primeiro->copy()->addMinutes(3)));
        $this->servico()->handleWebhook($this->evento('document_ready', $primeiro->copy()->addHour()));

        $contrato->refresh();
        $this->assertSame('ready', $contrato->assinafy_status);
        $this->assertTrue($contrato->data_aceite->equalTo($primeiro));
    }

    #[Test]
    public function recusa_e_cancelamento_atualizam_o_status_antes_da_conclusao(): void
    {
        $recusado = $this->criarContrato();
        $this->servico()->handleWebhook($this->evento('signer_rejected_document'));
        $this->assertSame('rejected', $recusado->fresh()->assinafy_status);

        $recusado->update(['assinafy_status' => 'enviado']);
        $this->servico()->handleWebhook($this->evento('user_rejected_document'));
        $this->assertSame('canceled', $recusado->fresh()->assinafy_status);
    }

    #[Test]
    public function webhook_de_documento_desconhecido_nao_e_processado(): void
    {
        $this->assertFalse($this->servico()->handleWebhook($this->evento('document_ready', extra: ['object' => ['id' => 'NAO-EXISTE']])));
        $this->assertFalse($this->servico()->handleWebhook(['event' => 'document_ready']));
    }

    #[Test]
    public function com_api_configurada_a_conclusao_so_vale_se_a_api_confirmar(): void
    {
        $this->configurarApi(true);
        $contrato = $this->criarContrato();

        // A API ainda diz que falta assinatura: o aviso do webhook (que não é assinado) é ignorado
        $this->fakeApi('pending_signature');
        $this->servico()->handleWebhook($this->evento('document_ready'));

        $contrato->refresh();
        $this->assertSame('enviado', $contrato->assinafy_status);
        $this->assertTrue($contrato->data_aceite->equalTo(Carbon::parse('2026-01-10 10:00:00')));

        // API fora do ar: também não confirma
        $this->fakeHttp(['*/documents/*' => Http::response(['message' => 'erro'], 500)]);
        $this->servico()->handleWebhook($this->evento('document_ready'));
        $this->assertSame('enviado', $contrato->fresh()->assinafy_status);
    }

    #[Test]
    public function com_api_configurada_o_status_gravado_e_a_etapa_real_informada_pela_api(): void
    {
        $this->configurarApi(true);
        $contrato = $this->criarContrato();

        $this->fakeApi('certificating');
        $this->servico()->handleWebhook($this->evento('document_ready'));

        $contrato->refresh();
        $this->assertSame('certificating', $contrato->assinafy_status);
        $this->assertTrue($contrato->data_aceite->equalTo(Carbon::parse('2026-09-30 14:05:00')));
    }

    #[Test]
    public function sincronizacao_avanca_por_ready_certificating_e_certificated_com_data_da_ultima_assinatura(): void
    {
        $this->configurarApi(true);
        $contrato = $this->criarContrato();
        $signatarios = [
            ['email' => 'mae@teste.com', 'status' => 'signed', 'signed_at' => '2026-09-29T10:00:00-03:00'],
            ['email' => 'pai@teste.com', 'status' => 'signed', 'signed_at' => '2026-09-30T14:00:00-03:00'],
        ];

        foreach (['ready', 'certificating', 'certificated'] as $etapa) {
            $this->fakeApi($etapa, $signatarios);

            $resultado = $this->servico()->consultarEAtualizarStatusSignatarios($contrato->fresh());

            $this->assertTrue($resultado['success']);
            $this->assertSame($etapa, $contrato->fresh()->assinafy_status);
        }

        // data_aceite = quando a última assinatura foi coletada
        $this->assertTrue($contrato->fresh()->data_aceite->equalTo(Carbon::parse('2026-09-30T14:00:00-03:00')));

        // Uma consulta posterior com etapa anterior não faz o contrato regredir
        $this->fakeApi('ready', $signatarios);
        $this->servico()->consultarEAtualizarStatusSignatarios($contrato->fresh());
        $this->assertSame('certificated', $contrato->fresh()->assinafy_status);
    }

    #[Test]
    public function sincronizacao_corrige_contrato_legado_preso_em_ready_com_data_aceite_antiga(): void
    {
        $this->configurarApi(true);
        // Situação deixada pelo tratamento antigo: status ready e data_aceite de quando o contrato foi criado
        $contrato = $this->criarContrato('ready', '2026-09-01 08:00:00');
        $this->fakeApi('certificated', [
            ['email' => 'mae@teste.com', 'status' => 'signed', 'signed_at' => '2026-09-30T14:00:00-03:00'],
        ]);

        $this->servico()->consultarEAtualizarStatusSignatarios($contrato);

        $contrato->refresh();
        $this->assertSame('certificated', $contrato->assinafy_status);
        $this->assertTrue($contrato->data_aceite->equalTo(Carbon::parse('2026-09-30T14:00:00-03:00')));
    }

    #[Test]
    public function sincronizacao_com_falha_na_api_informa_o_erro_e_preserva_o_status(): void
    {
        $this->configurarApi(true);
        $contrato = $this->criarContrato();
        $this->fakeHttp(['*/documents/*' => Http::response(['message' => 'Documento inexistente'], 404)]);

        $resultado = $this->servico()->consultarEAtualizarStatusSignatarios($contrato);

        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('Documento inexistente', $resultado['message']);
        $this->assertSame('enviado', $contrato->fresh()->assinafy_status);
    }

    #[Test]
    public function endpoint_do_webhook_aceita_envelope_sem_assinatura_mesmo_com_segredo_configurado(): void
    {
        config(['services.assinafy.webhook_secret' => 'segredo-de-teste']);
        $contrato = $this->criarContrato();

        $this->postJson('/api/webhooks/assinafy', $this->evento('document_ready'))->assertOk();

        $this->assertSame('ready', $contrato->fresh()->assinafy_status);
    }

    #[Test]
    public function endpoint_do_webhook_rejeita_assinatura_invalida_quando_ela_e_enviada(): void
    {
        config(['services.assinafy.webhook_secret' => 'segredo-de-teste']);
        $contrato = $this->criarContrato();

        $this->postJson('/api/webhooks/assinafy', $this->evento('document_ready'), ['X-Assinafy-Signature' => 'invalida'])
            ->assertStatus(401);

        $this->assertSame('enviado', $contrato->fresh()->assinafy_status);
    }

    #[Test]
    public function comando_reconcilia_contratos_em_andamento_e_ignora_os_finalizados(): void
    {
        $this->configurarApi(true);
        $emAndamento = $this->criarContrato('enviado');
        $pronto = Contrato::create([
            'matricula_id' => Matricula::factory()->create()->id, 'valor_total' => 0,
            'assinafy_id' => 'DOC-PRONTO', 'assinafy_status' => 'ready',
        ]);
        $certificado = Contrato::create([
            'matricula_id' => Matricula::factory()->create()->id, 'valor_total' => 0,
            'assinafy_id' => 'DOC-FINAL', 'assinafy_status' => 'certificated',
        ]);
        $recusado = Contrato::create([
            'matricula_id' => Matricula::factory()->create()->id, 'valor_total' => 0,
            'assinafy_id' => 'DOC-RECUSADO', 'assinafy_status' => 'rejected',
        ]);

        $this->fakeHttp(['*/documents/*' => Http::response(['data' => ['status' => 'certificated']], 200)]);

        $this->artisan('assinafy:reconciliar')->assertSuccessful();

        $this->assertSame('certificated', $emAndamento->fresh()->assinafy_status);
        $this->assertSame('certificated', $pronto->fresh()->assinafy_status);
        $this->assertSame('rejected', $recusado->fresh()->assinafy_status);
        // O contrato já certificado não foi consultado: 2 consultas (em andamento e ready)
        Http::assertSentCount(2);
        $this->assertSame('certificated', $certificado->fresh()->assinafy_status);
    }

    #[Test]
    public function comando_aceita_contrato_especifico_e_inclui_assinados_quando_pedido(): void
    {
        $this->configurarApi(true);
        $certificado = $this->criarContrato('certificated', '2026-09-01 08:00:00');
        $this->fakeHttp(['*/documents/*' => Http::response(['data' => [
            'status' => 'certificated',
            'assignment' => ['signers' => [['email' => 'mae@teste.com', 'status' => 'signed', 'signed_at' => '2026-09-30T14:00:00-03:00']]],
        ]], 200)]);

        // Por padrão, certificados não são reprocessados
        $this->artisan('assinafy:reconciliar')->expectsOutput('Nenhum contrato para reconciliar.')->assertSuccessful();

        // Com a opção, a data_aceite é corrigida pela data da última assinatura
        $this->artisan('assinafy:reconciliar', ['--incluir-assinados' => true])->assertSuccessful();
        $this->assertTrue($certificado->fresh()->data_aceite->equalTo(Carbon::parse('2026-09-30T14:00:00-03:00')));
    }

    #[Test]
    public function status_ready_certificating_e_certificated_sao_distintos_e_contam_como_assinados(): void
    {
        $rotulos = [
            StatusAssinaturaContrato::READY->getLabel(),
            StatusAssinaturaContrato::CERTIFICATING->getLabel(),
            StatusAssinaturaContrato::CERTIFICATED->getLabel(),
        ];

        $this->assertSame(['Todos assinaram', 'Certificando', 'Certificado'], $rotulos);
        $this->assertCount(3, array_unique($rotulos));

        foreach (['ready', 'certificating', 'certificated', 'signed', 'completed'] as $status) {
            $this->assertTrue(StatusAssinaturaContrato::from($status)->foiAssinado(), $status);
        }

        foreach (['pendente', 'pending', 'enviado', 'rejected', 'canceled', 'erro_envio', 'expired'] as $status) {
            $this->assertFalse(StatusAssinaturaContrato::from($status)->foiAssinado(), $status);
        }

        // Valores desconhecidos continuam exibíveis
        $this->assertSame('Outro_status', StatusAssinaturaContrato::rotuloDe('outro_status'));
        $this->assertSame('gray', StatusAssinaturaContrato::corDe('outro_status'));
    }

    #[Test]
    public function scopes_de_assinatura_concordam_com_esta_assinado(): void
    {
        $status = ['pendente', 'enviado', 'ready', 'certificating', 'certificated', 'signed', 'completed', 'rejected'];
        $contratos = collect($status)->mapWithKeys(fn (string $s): array => [$s => Contrato::create([
            'matricula_id' => Matricula::factory()->create()->id, 'valor_total' => 0, 'assinafy_status' => $s,
        ])]);

        $assinados = Contrato::assinado()->pluck('assinafy_status')->all();
        $naoAssinados = Contrato::naoAssinado()->pluck('assinafy_status')->all();

        $this->assertEqualsCanonicalizing(['ready', 'certificating', 'certificated', 'signed', 'completed'], $assinados);
        $this->assertEqualsCanonicalizing(['pendente', 'enviado', 'rejected'], $naoAssinados);

        foreach ($contratos as $s => $contrato) {
            $this->assertSame(in_array($s, $assinados, true), $contrato->estaAssinado(), $s);
        }
    }

    #[Test]
    public function ajuda_da_lista_de_contratos_explica_cada_status_de_assinatura(): void
    {
        $admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin']));
        session(['active_role' => 'super_admin']);
        $this->actingAs($admin);

        $pagina = Livewire::test(ListContratos::class)->instance();
        $ajuda = (new ReflectionMethod($pagina, 'getHelpContent'))->invoke($pagina);

        // Os rótulos explicados na ajuda são os mesmos exibidos na coluna (vêm do enum)
        foreach ([
            StatusAssinaturaContrato::PENDENTE,
            StatusAssinaturaContrato::ENVIADO,
            StatusAssinaturaContrato::READY,
            StatusAssinaturaContrato::CERTIFICATING,
            StatusAssinaturaContrato::CERTIFICATED,
            StatusAssinaturaContrato::REJECTED,
            StatusAssinaturaContrato::CANCELED,
            StatusAssinaturaContrato::EXPIRED,
            StatusAssinaturaContrato::ERRO_ENVIO,
        ] as $status) {
            $this->assertStringContainsStringIgnoringCase((string) $status->getLabel(), $ajuda, $status->value);
        }

        $this->assertStringContainsString('Sincronizar Assinaturas', $ajuda);
        $this->assertStringContainsString('última assinatura', $ajuda);
        $this->assertStringContainsString('Contrato não assinado', $ajuda);
    }

    #[Test]
    public function lista_de_contratos_exibe_cada_etapa_com_seu_proprio_rotulo(): void
    {
        $admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin']));
        session(['active_role' => 'super_admin']);
        $this->actingAs($admin);

        // Vários contratos, cada um com pai, mãe e responsável financeiro com usuário: exercita todas as relações
        // lidas pela coluna de signatários (o modo estrito de lazy loading vale nos testes e não pode ser violado).
        $pai = TipoVinculo::create(['nome' => 'Pai']);
        $mae = TipoVinculo::create(['nome' => 'Mãe']);

        foreach (['ready', 'certificating', 'certificated'] as $status) {
            $aluno = Pessoa::factory()->create();

            foreach ([$pai, $mae] as $vinculo) {
                $responsavel = Pessoa::factory()->create();
                $aluno->responsaveis()->attach($responsavel->id, ['tipo_vinculo_id' => $vinculo->id]);
                User::factory()->create()->pessoas()->attach($responsavel->id);
            }

            $financeiro = Pessoa::factory()->create();
            User::factory()->create()->pessoas()->attach($financeiro->id);

            $contrato = Contrato::create([
                'matricula_id' => Matricula::factory()->create(['pessoa_id' => $aluno->id])->id,
                'valor_total' => 0,
                'assinafy_status' => $status,
            ]);
            $contrato->responsaveisFinanceiros()->create(['pessoa_id' => $financeiro->id, 'percentual' => 100]);
        }

        Livewire::test(ListContratos::class)
            ->assertSee('Todos assinaram')
            ->assertSee('Certificando')
            ->assertSee('Certificado');
    }

    #[Test]
    public function webhook_grava_o_marcador_de_conclusao_e_a_sincronizacao_sem_datas_da_api_o_preserva(): void
    {
        $contrato = $this->criarContrato();
        $quando = Carbon::parse('2026-09-30 14:05:00');

        $this->servico()->handleWebhook($this->evento('document_ready', $quando));

        $contrato->refresh();
        $this->assertTrue(Carbon::parse($contrato->assinafy_request_log['assinaturas_concluidas_em'])->equalTo($quando));

        // Um evento posterior troca webhook_last, mas não o marcador
        $this->servico()->handleWebhook($this->evento('signer_viewed_document', $quando->copy()->addDay()));

        // A API passa a informar certificated, sem datas por signatário: a data_aceite continua a da conclusão
        $this->configurarApi(true);
        $this->fakeApi('certificated');
        $this->servico()->consultarEAtualizarStatusSignatarios($contrato->fresh());

        $contrato->refresh();
        $this->assertSame('certificated', $contrato->assinafy_status);
        $this->assertTrue($contrato->data_aceite->equalTo($quando));
    }

    #[Test]
    public function sincronizacao_repara_contrato_legado_com_o_horario_do_document_ready_gravado_no_log(): void
    {
        $this->configurarApi(true);
        $quando = Carbon::parse('2026-09-30 14:05:00');
        // Situação deixada pelo tratamento antigo: status ready, data_aceite da criação e o último webhook no log
        $contrato = $this->criarContrato('ready', '2026-09-01 08:00:00');
        $contrato->update(['assinafy_request_log' => ['webhook_last' => $this->evento('document_ready', $quando)]]);

        // A API informa o status, mas não as datas de cada signatário
        $this->fakeApi('certificated');
        $this->servico()->consultarEAtualizarStatusSignatarios($contrato->fresh());

        $contrato->refresh();
        $this->assertSame('certificated', $contrato->assinafy_status);
        $this->assertTrue($contrato->data_aceite->equalTo($quando));
        $this->assertTrue(Carbon::parse($contrato->assinafy_request_log['assinaturas_concluidas_em'])->equalTo($quando));
    }

    #[Test]
    public function alternativa_do_log_so_vale_quando_o_ultimo_webhook_foi_document_ready(): void
    {
        $this->configurarApi(true);
        $contrato = $this->criarContrato('ready', '2026-09-01 08:00:00');
        $contrato->update(['assinafy_request_log' => ['webhook_last' => $this->evento('signer_viewed_document')]]);

        $this->fakeApi('certificated');
        $this->servico()->consultarEAtualizarStatusSignatarios($contrato->fresh());

        $contrato->refresh();
        $this->assertSame('certificated', $contrato->assinafy_status);
        $this->assertTrue($contrato->data_aceite->equalTo(Carbon::parse('2026-09-01 08:00:00')));
    }

    #[Test]
    public function datas_dos_signatarios_na_api_tem_prioridade_sobre_o_log(): void
    {
        $this->configurarApi(true);
        $contrato = $this->criarContrato('ready', '2026-09-01 08:00:00');
        $contrato->update(['assinafy_request_log' => ['webhook_last' => $this->evento('document_ready', Carbon::parse('2026-09-30 14:05:00'))]]);

        $this->fakeApi('certificated', [
            ['email' => 'mae@teste.com', 'status' => 'signed', 'signed_at' => '2026-09-29T10:00:00-03:00'],
        ]);
        $this->servico()->consultarEAtualizarStatusSignatarios($contrato->fresh());

        $this->assertTrue($contrato->fresh()->data_aceite->equalTo(Carbon::parse('2026-09-29T10:00:00-03:00')));
    }

    #[Test]
    public function comando_repara_data_aceite_de_contratos_antigos_pelo_log_quando_a_api_nao_informa_datas(): void
    {
        $this->configurarApi(true);
        $quando = Carbon::parse('2026-09-30 14:05:00');
        $legado = $this->criarContrato('certificated', '2026-09-01 08:00:00');
        $legado->update(['assinafy_request_log' => ['webhook_last' => $this->evento('document_ready', $quando)]]);

        $this->fakeHttp(['*/documents/*' => Http::response(['data' => ['status' => 'certificated']], 200)]);

        $this->artisan('assinafy:reconciliar', ['--incluir-assinados' => true])
            ->expectsTable(
                ['Contrato', 'Status anterior', 'Status atual', 'Resultado'],
                [[$legado->id, 'certificated', 'certificated', 'data de aceite corrigida']],
            )
            ->assertSuccessful();

        $this->assertTrue($legado->fresh()->data_aceite->equalTo($quando));
    }
}

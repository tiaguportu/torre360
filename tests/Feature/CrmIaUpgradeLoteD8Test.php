<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use App\Models\Unidade;
use App\Models\User;
use App\Services\BattlecardService;
use App\Services\CrmIaVendasService;
use App\Services\DocumentoIaService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CrmIaUpgradeLoteD8Test extends TestCase
{
    use RefreshDatabase;

    private OrigemInteressado $origem;

    private StatusInteressado $statusNovo;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('crm_gemini_user_1');
        RateLimiter::clear('crm_gemini_user_2');
        RateLimiter::clear('crm_gemini_user_guest');

        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
        $this->statusNovo = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);
        TipoContatoInteressado::firstOrCreate(['nome' => TipoContatoInteressado::FUNIL]);
        TipoContatoInteressado::firstOrCreate(['nome' => 'Portal de Admissão']);
        TipoContatoInteressado::firstOrCreate(['nome' => 'WhatsApp']);
    }

    private function criarInteressado(array $attrs = []): Interessado
    {
        $pessoa = Pessoa::create([
            'nome' => 'Responsável Teste',
            'telefone' => '11999999999',
            'cpf' => '12345678901',
        ]);

        return Interessado::create(array_merge([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => $this->origem->id,
            'status_interessado_id' => $this->statusNovo->id,
            'temperatura' => 'morno',
        ], $attrs));
    }

    public function test_nome_escola_configuravel_com_prioridades_e_fallbacks(): void
    {
        // 1. Sem configuração prévia, retorna fallback padrão
        config(['copiloto_ia.nome_escola' => null]);
        $nomePadrao = CrmIaVendasService::nomeEscola();
        $this->assertNotEmpty($nomePadrao);

        // 2. Se houver Unidade cadastrada no banco, usa o nome dela
        Unidade::create([
            'nome' => 'Colégio Alfa Mackenzie',
            'telefone' => '1199999999',
            'email' => 'alfa@escola.com',
        ]);
        $this->assertSame('Colégio Alfa Mackenzie', CrmIaVendasService::nomeEscola());

        // 3. Se houver configuração explícita no config, esta tem precedência máxima
        config(['copiloto_ia.nome_escola' => 'Colégio Beta Singular']);
        $this->assertSame('Colégio Beta Singular', CrmIaVendasService::nomeEscola());

        // 4. Se houver array de configuração passado dinamicamente
        $this->assertSame('Escola Gamma Inovadora', CrmIaVendasService::nomeEscola(['nome_escola' => 'Escola Gamma Inovadora']));
    }

    public function test_nome_escola_reflete_no_prompt_do_battlecard_service(): void
    {
        config(['copiloto_ia.nome_escola' => 'Colégio Luminar']);

        $mockGemini = Mockery::mock(GeminiAgentService::class);
        $mockGemini->shouldReceive('executar')
            ->once()
            ->withArgs(function (string $prompt): bool {
                return str_contains($prompt, 'escola particular "Colégio Luminar"');
            })
            ->andReturn('### 💡 Diagnóstico Rápido: OK');

        $this->app->instance(GeminiAgentService::class, $mockGemini);

        $service = app(BattlecardService::class);
        $resposta = $service->gerarContraArgumentoIa('Colégio Concorrente', 'Achei a mensalidade alta');

        $this->assertStringContainsString('Diagnóstico Rápido', $resposta);
    }

    public function test_rate_limiter_permite_ate_limite_e_bloqueia_com_mensagem_amigavel(): void
    {
        $user = User::factory()->create();

        // Limite padrão é 10 por minuto
        for ($i = 1; $i <= 10; $i++) {
            $resultado = CrmIaVendasService::verificarIncrementarRateLimit($user);
            $this->assertTrue($resultado['permitido'], "A tentativa {$i} deveria ser permitida.");
            $this->assertNull($resultado['mensagem']);
        }

        // A 11ª tentativa deve ser bloqueada
        $bloqueio = CrmIaVendasService::verificarIncrementarRateLimit($user);
        $this->assertFalse($bloqueio['permitido']);
        $this->assertSame(0, $bloqueio['restantes']);
        $this->assertGreaterThan(0, $bloqueio['segundos_espera']);
        $this->assertStringContainsString('Você atingiu o limite de consultas de IA', $bloqueio['mensagem']);
        $this->assertStringContainsString('segundos antes de tentar novamente', $bloqueio['mensagem']);
    }

    public function test_rate_limiter_isola_contadores_entre_usuarios_distintos(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Esgota o limite do user1
        for ($i = 1; $i <= 10; $i++) {
            CrmIaVendasService::verificarIncrementarRateLimit($user1);
        }
        $this->assertFalse(CrmIaVendasService::verificarIncrementarRateLimit($user1)['permitido']);

        // User2 ainda deve ter cota total disponível
        $resultadoUser2 = CrmIaVendasService::verificarIncrementarRateLimit($user2);
        $this->assertTrue($resultadoUser2['permitido']);
    }

    public function test_battlecard_retorna_aviso_quando_rate_limit_e_atingido(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Consome todo o rate limit do usuário
        for ($i = 1; $i <= 10; $i++) {
            CrmIaVendasService::verificarIncrementarRateLimit($user);
        }

        $service = app(BattlecardService::class);
        $resposta = $service->gerarContraArgumentoIa('Concorrente X', 'Preço alto');

        $this->assertStringContainsString('Limite Temporário de IA Atingido', $resposta);
        $this->assertStringContainsString('Você atingiu o limite de consultas de IA', $resposta);
    }

    public function test_documento_ia_service_persiste_status_sugerido_no_documento_inserido(): void
    {
        Storage::fake('local');
        $caminho = 'documentos/teste_rg.jpg';
        Storage::disk('local')->put($caminho, 'conteudo-fake-imagem');

        $tipoDoc = TipoDocumento::create([
            'nome' => 'Documento de Identidade (RG)',
            'categoria' => 'aluno',
            'obrigatorio' => true,
        ]);

        $interessado = $this->criarInteressado();

        $documento = DocumentoInserido::create([
            'tipo_documento_id' => $tipoDoc->id,
            'interessado_id' => $interessado->id,
            'arquivo_path' => $caminho,
            'nome_arquivo_original' => 'meu_rg.jpg',
            'status' => SituacaoDocumento::EM_ANALISE,
        ]);

        $retornoIaJson = json_encode([
            'documento_identificado' => 'rg',
            'confere_com_solicitado' => true,
            'confianca' => 'alta',
            'qualidade' => [
                'legivel' => true,
                'cortado' => false,
                'reflexo' => false,
                'observacoes' => 'Documento perfeitamente legível',
            ],
            'dados_extraidos' => [
                'nome_titular' => 'Carlos Augusto Silva',
                'cpf' => '123.456.789-01',
            ],
            'divergencias' => [],
            'status_sugerido' => 'verificado',
            'motivo_rejeicao_sugerido' => null,
            'mensagem_para_familia' => null,
        ]);

        $mockGemini = Mockery::mock(GeminiAgentService::class);
        $mockGemini->shouldReceive('callGeminiApi')
            ->once()
            ->andReturn([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $retornoIaJson],
                            ],
                        ],
                    ],
                ],
            ]);

        $service = new DocumentoIaService($mockGemini);
        $service->analisarDocumento($documento);

        $documento->refresh();
        $this->assertSame('verificado', $documento->status_sugerido);
        $this->assertSame(SituacaoDocumento::VERIFICADO, $documento->getStatusSugeridoEnum());
        $this->assertTrue($documento->temSugestaoIa());
    }

    public function test_historico_contato_do_copiloto_e_salvo_com_automatico_true(): void
    {
        $interessado = $this->criarInteressado();

        $mockGemini = Mockery::mock(GeminiAgentService::class);
        $mockGemini->shouldReceive('callGeminiApi')
            ->once()
            ->andReturn([
                'candidates' => [
                    [
                        'finishReason' => 'STOP',
                        'content' => [
                            'parts' => [
                                ['text' => 'Olá Mariana! Tudo bem? Como podemos ajudar na matrícula?'],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->app->instance(GeminiAgentService::class, $mockGemini);

        $user = User::factory()->create();
        $this->actingAs($user);

        $service = app(CrmIaVendasService::class);
        $mensagem = $service->gerarMensagemCopiloto($interessado, 'primeiro_contato');

        $historico = HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'usuario_id' => $user->id,
            'tipo_contato_interessado_id' => 1,
            'data_contato' => now(),
            'relato' => "💬 Mensagem redigida pelo Copiloto IA: {$mensagem}",
            'automatico' => true,
        ]);

        $this->assertTrue($historico->automatico);
        // Não deve ser contabilizado no scopeInteracoes (que filtra automatico === false)
        $this->assertSame(0, HistoricoContato::interacoes()->count());
        $this->assertSame(1, HistoricoContato::count());
    }
}

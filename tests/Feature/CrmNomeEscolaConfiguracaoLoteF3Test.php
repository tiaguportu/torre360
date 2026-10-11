<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CopilotoIaConfiguracao;
use App\Models\InstituicaoEnsino;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use App\Services\BattlecardService;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use App\Services\ReguaFollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CrmNomeEscolaConfiguracaoLoteF3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CopilotoIaConfiguracao::limparCache();
        config(['copiloto_ia.nome_escola' => null]);
    }

    public function test_salvar_copiloto_ia_configuracao_com_nome_escola_retorna_o_salvo(): void
    {
        CopilotoIaConfiguracao::create([
            'valores' => [
                'nome_escola' => 'Colégio Fênix do Saber',
            ],
        ]);
        CopilotoIaConfiguracao::limparCache();

        $this->assertSame('Colégio Fênix do Saber', CrmIaVendasService::nomeEscola());
    }

    public function test_sem_salvo_e_sem_env_cai_em_unidade(): void
    {
        Unidade::create([
            'nome' => 'Unidade Central Jardins',
            'telefone' => '11988887777',
            'email' => 'jardins@escola.com',
        ]);

        $this->assertSame('Unidade Central Jardins', CrmIaVendasService::nomeEscola());
    }

    public function test_sem_salvo_sem_env_e_sem_unidade_cai_em_instituicao_ensino(): void
    {
        InstituicaoEnsino::create([
            'nome' => 'Instituto Educacional Torre',
            'razao_social' => 'Instituto Educacional Torre LTDA',
            'cnpj' => '12.345.678/0001-90',
        ]);

        $this->assertSame('Instituto Educacional Torre', CrmIaVendasService::nomeEscola());
    }

    public function test_prompt_do_dossie_e_do_battlecard_contem_o_nome_salvo(): void
    {
        CopilotoIaConfiguracao::create([
            'valores' => [
                'nome_escola' => 'Colégio Ápice Pedagógico',
            ],
        ]);
        CopilotoIaConfiguracao::limparCache();

        $pessoa = Pessoa::create([
            'nome' => 'Carlos Pai Teste',
            'email' => 'carlos.pai@teste.com',
        ]);
        $status = StatusInteressado::create(['nome' => 'Novo', 'cor' => '#000000']);
        $origem = OrigemInteressado::create(['nome' => 'Site']);

        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
        ]);

        $mockGemini = Mockery::mock(GeminiAgentService::class);
        $mockGemini->shouldReceive('callGeminiApi')
            ->once()
            ->withArgs(function (array $payload): bool {
                $systemInstruction = $payload['systemInstruction']['parts'][0]['text'] ?? '';

                return str_contains($systemInstruction, 'Colégio Ápice Pedagógico');
            })
            ->andReturn([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'resumo_executivo' => 'Família engajada.',
                                        'temperatura_sugerida' => 'quente',
                                        'proxima_acao_sugerida' => 'Agendar visita',
                                        'dossie_markdown' => '### Relatório OK',
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $mockGemini->shouldReceive('executar')
            ->once()
            ->withArgs(function (string $prompt): bool {
                return str_contains($prompt, 'Colégio Ápice Pedagógico');
            })
            ->andReturn('Contra-argumento OK');

        $this->app->instance(GeminiAgentService::class, $mockGemini);

        $crmService = app(CrmIaVendasService::class);
        $dossie = $crmService->gerarDossie($interessado);

        $this->assertArrayHasKey('resumo_executivo', $dossie);

        $battlecardService = app(BattlecardService::class);
        $contra = $battlecardService->gerarContraArgumentoIa('Concorrente X', 'Preço alto');

        $this->assertSame('Contra-argumento OK', $contra);
    }

    public function test_regua_follow_up_service_nome_escola_delega_para_crm_ia_vendas_service(): void
    {
        CopilotoIaConfiguracao::create([
            'valores' => [
                'nome_escola' => 'Colégio Dinâmico Delta',
            ],
        ]);
        CopilotoIaConfiguracao::limparCache();

        $reguaService = app(ReguaFollowUpService::class);
        $metodo = new \ReflectionMethod($reguaService, 'nomeEscola');
        $metodo->setAccessible(true);

        $this->assertSame('Colégio Dinâmico Delta', $metodo->invoke($reguaService));
    }
}

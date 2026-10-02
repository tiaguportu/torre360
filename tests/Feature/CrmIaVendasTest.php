<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CrmIaVendasTest extends TestCase
{
    use RefreshDatabase;

    protected function criarInteressadoCompleto(): Interessado
    {
        $status = StatusInteressado::factory()->create(['nome' => 'Novo Contato']);
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        $usuario = User::factory()->create();

        $pessoa = Pessoa::factory()->create([
            'nome' => 'Mariana Oliveira',
            'email' => 'mariana@example.com',
            'telefone' => '11988887777',
        ]);

        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => $usuario->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'temperatura' => 'morno',
            'lead_score' => 60,
            'observacoes' => 'Mãe busca colégio com ensino bilíngue.',
        ]);

        InteressadoDependente::create([
            'interessado_id' => $interessado->id,
            'nome_crianca' => 'Lucas Oliveira',
            'data_nascimento' => now()->subYears(7)->toDateString(),
            'serie_pretendida' => '2º Ano Fundamental',
        ]);

        $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'Ligação']);
        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'usuario_id' => $usuario->id,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'data_contato' => now()->subDay(),
            'relato' => 'Mãe elogiou a proposta e quer saber mais sobre o contraturno.',
        ]);

        return $interessado;
    }

    public function test_crm_ia_vendas_service_gera_dossie_com_sucesso(): void
    {
        $interessado = $this->criarInteressadoCompleto();

        $respostaMock = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'resumo_executivo' => 'Família com alto potencial, mãe engajada buscando 2º ano.',
                                    'temperatura_sugerida' => 'quente',
                                    'proxima_acao_sugerida' => 'Convidar para o tour presencial neste sábado.',
                                    'dossie_markdown' => "### 👨‍👩‍👧 Perfil da Família\nMãe Mariana com filho Lucas.\n\n### 🚀 Roteiro\nDestacar o projeto bilíngue.",
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $geminiMock = Mockery::mock(GeminiAgentService::class);
        $geminiMock->shouldReceive('callGeminiApi')
            ->once()
            ->andReturn($respostaMock);

        $this->app->instance(GeminiAgentService::class, $geminiMock);

        $service = app(CrmIaVendasService::class);
        $dossie = $service->gerarDossie($interessado);

        $this->assertIsArray($dossie);
        $this->assertSame('Família com alto potencial, mãe engajada buscando 2º ano.', $dossie['resumo_executivo']);
        $this->assertSame('quente', $dossie['temperatura_sugerida']);
        $this->assertSame('Convidar para o tour presencial neste sábado.', $dossie['proxima_acao_sugerida']);
        $this->assertStringContainsString('Perfil da Família', $dossie['dossie_markdown']);
    }

    public function test_crm_ia_vendas_service_gera_mensagem_copiloto_com_sucesso(): void
    {
        $interessado = $this->criarInteressadoCompleto();

        $textoMensagem = "Olá, Mariana! Tudo bem?\n\nVi que você tem interesse na vaga do 2º Ano para o *Lucas*. Podemos agendar uma visita?";

        $respostaMock = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => $textoMensagem,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $geminiMock = Mockery::mock(GeminiAgentService::class);
        $geminiMock->shouldReceive('callGeminiApi')
            ->once()
            ->andReturn($respostaMock);

        $this->app->instance(GeminiAgentService::class, $geminiMock);

        $service = app(CrmIaVendasService::class);
        $mensagem = $service->gerarMensagemCopiloto($interessado, 'convite_visita');

        $this->assertSame($textoMensagem, $mensagem);
    }

    public function test_crm_ia_vendas_fallback_em_caso_de_erro_da_api(): void
    {
        $interessado = $this->criarInteressadoCompleto();

        $geminiMock = Mockery::mock(GeminiAgentService::class);
        $geminiMock->shouldReceive('callGeminiApi')
            ->andThrow(new \RuntimeException('Timeout de conexão'));

        $this->app->instance(GeminiAgentService::class, $geminiMock);

        $service = app(CrmIaVendasService::class);

        // Dossiê deve usar fallback sem estourar exception
        $dossie = $service->gerarDossie($interessado);
        $this->assertStringContainsString('Dossiê Básico (Fallback)', $dossie['dossie_markdown']);

        // Copiloto deve usar fallback amigável
        $mensagem = $service->gerarMensagemCopiloto($interessado, 'primeiro_contato');
        $this->assertStringContainsString('Mariana', $mensagem);
        $this->assertStringContainsString('Lucas', $mensagem);
    }
}

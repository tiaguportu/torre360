<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CopilotoIaConfiguracao;
use App\Models\Unidade;
use App\Services\CrmIaVendasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmNomeEscolaCopilotoPersonaLoteG1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CopilotoIaConfiguracao::limparCache();
        config(['copiloto_ia.nome_escola' => null]);
    }

    public function test_nada_configurado_mantem_persona_intacta_com_o_literal(): void
    {
        $service = app(CrmIaVendasService::class);
        $prompt = $service->montarSystemInstructionCopiloto('convite_visita');

        $this->assertStringContainsString('Você é o Copiloto de Atendimento e Vendas Educacionais da Escola Torre de Marfim.', $prompt);
        $this->assertStringNotContainsString('da Laravel', $prompt);
    }

    public function test_unidade_cadastrada_persona_usa_o_nome_da_unidade(): void
    {
        Unidade::create([
            'nome' => 'Colégio Sol do Amanhã',
            'telefone' => '11988887777',
            'email' => 'sol@escola.com',
        ]);

        $service = app(CrmIaVendasService::class);
        $prompt = $service->montarSystemInstructionCopiloto('convite_visita');

        $this->assertStringContainsString('Você é o Copiloto de Atendimento e Vendas Educacionais da Colégio Sol do Amanhã.', $prompt);
        $this->assertStringNotContainsString('Escola Torre de Marfim', $prompt);
    }

    public function test_nome_salvo_persona_usa_o_nome_salvo(): void
    {
        CopilotoIaConfiguracao::create([
            'valores' => [
                'nome_escola' => 'Academia Saber Maior',
            ],
        ]);
        CopilotoIaConfiguracao::limparCache();

        $service = app(CrmIaVendasService::class);
        $prompt = $service->montarSystemInstructionCopiloto('convite_visita');

        $this->assertStringContainsString('Você é o Copiloto de Atendimento e Vendas Educacionais da Academia Saber Maior.', $prompt);
        $this->assertStringNotContainsString('Escola Torre de Marfim', $prompt);
    }

    public function test_persona_personalizada_contendo_o_literal_com_nome_salvo_e_trocado(): void
    {
        CopilotoIaConfiguracao::create([
            'valores' => [
                'nome_escola' => 'Colégio Alpha',
                'persona' => 'Você é a Duda, consultora da Escola Torre de Marfim.',
            ],
        ]);
        CopilotoIaConfiguracao::limparCache();

        $service = app(CrmIaVendasService::class);
        $prompt = $service->montarSystemInstructionCopiloto('convite_visita');

        $this->assertStringContainsString('Você é a Duda, consultora da Colégio Alpha.', $prompt);
        $this->assertStringNotContainsString('Escola Torre de Marfim', $prompt);
    }

    public function test_persona_personalizada_sem_nome_configurado_fica_intacta(): void
    {
        CopilotoIaConfiguracao::create([
            'valores' => [
                'persona' => 'Você é a Duda, consultora da Escola Torre de Marfim.',
            ],
        ]);
        CopilotoIaConfiguracao::limparCache();

        $service = app(CrmIaVendasService::class);
        $prompt = $service->montarSystemInstructionCopiloto('convite_visita');

        $this->assertStringContainsString('Você é a Duda, consultora da Escola Torre de Marfim.', $prompt);
        $this->assertStringNotContainsString('da Laravel', $prompt);
    }

    public function test_nome_escola_nunca_e_vazio_e_tem_fallback_em_app_name(): void
    {
        $this->assertNull(CrmIaVendasService::nomeEscolaConfigurado());
        $this->assertSame((string) config('app.name'), CrmIaVendasService::nomeEscola());
        $this->assertNotEmpty(CrmIaVendasService::nomeEscola());
    }
}

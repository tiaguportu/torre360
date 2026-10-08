<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ConfiguracaoCopilotoIa;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Models\CopilotoIaConfiguracao;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\MensagemWhatsappTemplate;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CopilotoIaConfiguracaoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'View:ConfiguracaoCopilotoIa', 'guard_name' => 'web']));

        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function interessado(): Interessado
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Mariana Oliveira', 'telefone' => '11988887777']);

        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => User::factory()->create()->id,
            'status_interessado_id' => StatusInteressado::factory()->create(['nome' => 'Novo Contato'])->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'temperatura' => 'morno',
            'lead_score' => 60,
        ]);

        InteressadoDependente::create([
            'interessado_id' => $interessado->id,
            'nome_crianca' => 'Lucas Oliveira',
            'data_nascimento' => now()->subYears(7)->toDateString(),
            'serie_pretendida' => '2º Ano Fundamental',
        ]);

        return $interessado;
    }

    /**
     * Mocka o Gemini devolvendo uma mensagem fixa e guarda o payload recebido em $capturado.
     */
    private function mockGemini(?array &$capturado): void
    {
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')
            ->once()
            ->withArgs(function (array $payload) use (&$capturado): bool {
                $capturado = $payload;

                return true;
            })
            ->andReturn(['candidates' => [['content' => ['parts' => [['text' => 'Olá Mariana!']]]]]]);

        $this->app->instance(GeminiAgentService::class, $mock);
    }

    /**
     * Estado do formulário a partir dos padrões, com sobrescritas pontuais.
     *
     * @param  array<string, mixed>  $sobrescritas
     * @return array<string, mixed>
     */
    private function estadoDoFormulario(array $sobrescritas = []): array
    {
        $padrao = config('copiloto_ia');
        $padrao['diretrizes'] = array_map(fn (string $texto): array => ['texto' => $texto], $padrao['diretrizes']);

        return array_replace_recursive($padrao, $sobrescritas);
    }

    public function test_pagina_carrega(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/crm/comportamento-copiloto-ia')
            ->assertOk()
            ->assertSee('Comportamento do Copiloto IA');
    }

    public function test_sem_sobrescrita_o_prompt_usa_os_padroes_do_config(): void
    {
        $capturado = null;
        $this->mockGemini($capturado);

        app(CrmIaVendasService::class)->gerarMensagemCopiloto($this->interessado(), 'convite_visita');

        $sistema = $capturado['systemInstruction']['parts'][0]['text'];

        $this->assertStringContainsString('Você é o Copiloto de Atendimento e Vendas Educacionais da Escola Torre de Marfim.', $sistema);
        $this->assertStringContainsString('1. Deve ser pronta para envio pelo WhatsApp', $sistema);
        $this->assertStringContainsString('O objetivo deste contato é: Convite caloroso para a família fazer um Tour Pedagógico', $sistema);
        $this->assertStringContainsString('Tom de voz desejado: Caloroso, acolhedor', $sistema);
        $this->assertStringContainsString('SEGURANÇA:', $sistema);
        $this->assertSame(0.4, $capturado['generationConfig']['temperature']);
        $this->assertSame(800, $capturado['generationConfig']['maxOutputTokens']);
    }

    public function test_salvar_na_pagina_altera_o_prompt_enviado_ao_gemini(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ConfiguracaoCopilotoIa::class)
            ->fillForm($this->estadoDoFormulario([
                'persona' => 'Você é a Duda, consultora da Escola Torre de Marfim.',
                'evitar' => 'Descontos não aprovados',
                'mencionar' => 'Período integral',
                'objetivos' => ['convite_visita' => 'Convidar para o Dia de Portas Abertas.'],
                'gemini' => ['temperature' => 0.7, 'max_output_tokens' => 1200],
            ]), 'content')
            ->call('salvar')
            ->assertNotified('Configuração salva');

        $this->assertSame(1, CopilotoIaConfiguracao::count());

        $capturado = null;
        $this->mockGemini($capturado);

        app(CrmIaVendasService::class)->gerarMensagemCopiloto($this->interessado(), 'convite_visita');

        $sistema = $capturado['systemInstruction']['parts'][0]['text'];

        $this->assertStringStartsWith('Você é a Duda, consultora da Escola Torre de Marfim.', $sistema);
        $this->assertStringContainsString("Nunca mencione nem prometa:\nDescontos não aprovados", $sistema);
        $this->assertStringContainsString("Sempre mencione ou destaque, quando fizer sentido para este lead:\nPeríodo integral", $sistema);
        $this->assertStringContainsString('O objetivo deste contato é: Convidar para o Dia de Portas Abertas.', $sistema);
        $this->assertStringNotContainsString('Convite caloroso para a família', $sistema);
        $this->assertSame(0.7, $capturado['generationConfig']['temperature']);
        $this->assertSame(1200, $capturado['generationConfig']['maxOutputTokens']);
    }

    public function test_regras_fixas_e_de_seguranca_continuam_com_config_customizada(): void
    {
        CopilotoIaConfiguracao::create(['valores' => [
            'persona' => 'Persona customizada.',
            'diretrizes' => ['Só uma regra.'],
        ]]);
        CopilotoIaConfiguracao::limparCache();

        $sistema = app(CrmIaVendasService::class)->montarSystemInstructionCopiloto('primeiro_contato');

        $this->assertStringContainsString("1. Só uma regra.\n2. O objetivo deste contato é:", $sistema);
        $this->assertStringContainsString('NÃO inclua links, URLs nem endereços de sites na mensagem.', $sistema);
        $this->assertStringContainsString('Retorne APENAS o texto puro', $sistema);
        $this->assertStringEndsWith('não estejam literalmente nesses dados.', $sistema);
    }

    public function test_instrucoes_do_modelo_entram_no_prompt_e_so_quando_informadas(): void
    {
        $servico = app(CrmIaVendasService::class);

        $com = $servico->montarSystemInstructionCopiloto('primeiro_contato', instrucoesModelo: 'Manter o prazo de matrícula do texto.');
        $sem = $servico->montarSystemInstructionCopiloto('primeiro_contato');

        $this->assertStringContainsString("Instruções específicas do modelo institucional selecionado:\nManter o prazo de matrícula do texto.", $com);
        $this->assertStringNotContainsString('Instruções específicas do modelo institucional', $sem);
    }

    public function test_copiloto_repassa_as_instrucoes_do_modelo_escolhido(): void
    {
        $template = MensagemWhatsappTemplate::create([
            'nome' => 'Boas-vindas Oficial',
            'conteudo' => 'Olá [Nome do Responsável]!',
            'instrucoes_ia' => 'Nunca citar o valor da mensalidade.',
            'ativo' => true,
        ]);
        $interessado = $this->interessado();

        $capturado = null;
        $this->mockGemini($capturado);

        Livewire::actingAs($this->admin())
            ->test(EditInteressado::class, ['record' => $interessado->id])
            ->callAction('copilotoIa', [
                'mensagem_whatsapp_template_id' => $template->id,
                'objetivo' => 'primeiro_contato',
                'tom' => 'acolhedor',
                'registrar_historico' => false,
            ])
            ->assertNotified();

        $this->assertStringContainsString('Nunca citar o valor da mensalidade.', $capturado['systemInstruction']['parts'][0]['text']);
    }

    public function test_restaurar_padrao_remove_a_personalizacao(): void
    {
        CopilotoIaConfiguracao::create(['valores' => ['persona' => 'Persona customizada.']]);
        CopilotoIaConfiguracao::limparCache();

        Livewire::actingAs($this->admin())
            ->test(ConfiguracaoCopilotoIa::class)
            ->call('restaurarPadrao');

        $this->assertSame(0, CopilotoIaConfiguracao::count());
        $this->assertSame(config('copiloto_ia.persona'), CopilotoIaConfiguracao::valores()['persona']);
    }

    public function test_temperatura_fora_da_faixa_e_recusada(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ConfiguracaoCopilotoIa::class)
            ->fillForm($this->estadoDoFormulario(['gemini' => ['temperature' => 5]]), 'content')
            ->call('salvar')
            ->assertHasFormErrors(['gemini.temperature'], 'content');

        $this->assertSame(0, CopilotoIaConfiguracao::count());
    }

    public function test_previa_usa_o_formulario_ainda_nao_salvo(): void
    {
        $componente = Livewire::actingAs($this->admin())
            ->test(ConfiguracaoCopilotoIa::class)
            ->fillForm($this->estadoDoFormulario(['persona' => 'Persona só na prévia.']), 'content');

        $previa = (new \ReflectionMethod(ConfiguracaoCopilotoIa::class, 'montarPrevia'))
            ->invoke($componente->instance());

        $this->assertStringStartsWith('Persona só na prévia.', $previa);
        $this->assertSame(0, CopilotoIaConfiguracao::count());
    }
}

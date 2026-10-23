<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\MensagemWhatsappTemplate;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\ConsultorWhatsappService;
use App\Services\GeminiAgentService;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Textarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Copiloto WhatsApp IA em duas etapas: a IA redige num campo editável e o WhatsApp só abre com o texto revisado.
 * O histórico recebe a tentativa depois da revisão, com o texto final e a origem dele (IA, ajustada, contingência).
 */
class CopilotoIaRevisaoMensagemTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAGEM_IA = 'Olá, Mariana! Que alegria ter vocês por aqui. Que tal conhecer a escola?';

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->usuario = User::factory()->create(['activated_at' => now()]);
        $this->usuario->assignRole('super_admin');
    }

    private function lead(?string $telefone = '11988887777'): Interessado
    {
        $lead = Interessado::create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Mariana Oliveira', 'telefone' => $telefone])->id,
            'usuario_id' => User::factory()->create()->id,
            'status_interessado_id' => StatusInteressado::factory()->create(['nome' => 'Novo Contato'])->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'temperatura' => 'morno',
        ]);

        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas Oliveira', 'data_nascimento' => now()->subYears(7)->toDateString()]);

        return $lead;
    }

    /**
     * Gemini simulado: devolve (ou lança) cada item em sequência. Sem itens, qualquer chamada falha o teste.
     *
     * @param  array<int, array<string, mixed>|\Throwable>  $respostas
     */
    private function mockGemini(array $respostas): void
    {
        $mock = Mockery::mock(GeminiAgentService::class);

        if ($respostas === []) {
            $mock->shouldNotReceive('callGeminiApi');
        } else {
            $mock->shouldReceive('callGeminiApi')->times(count($respostas))->andReturnUsing(function () use (&$respostas): array {
                $proxima = array_shift($respostas);

                if ($proxima instanceof \Throwable) {
                    throw $proxima;
                }

                return $proxima;
            });
        }

        $this->app->instance(GeminiAgentService::class, $mock);
    }

    /** @return array<string, mixed> */
    private function respostaIa(string $texto): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $texto]]], 'finishReason' => 'STOP']]];
    }

    private function gerar(): TestAction
    {
        return TestAction::make('gerarMensagemCopiloto')->schemaComponent('acoesMensagem');
    }

    private function modal(Interessado $lead): Testable
    {
        return Livewire::actingAs($this->usuario)
            ->test(EditInteressado::class, ['record' => $lead->id])
            ->mountAction('copilotoIa');
    }

    private function urlDoWhatsapp(string $telefone, string $mensagem): string
    {
        return 'https://api.whatsapp.com/send?'.http_build_query([
            'phone' => app(ConsultorWhatsappService::class)->normalizarTelefone($telefone),
            'text' => $mensagem,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function test_modal_mostra_o_botao_de_gerar_o_campo_editavel_e_o_botao_de_abrir(): void
    {
        $this->modal($this->lead())
            ->assertSchemaComponentExists('acoesMensagem')
            ->assertSchemaComponentExists('mensagem', checkComponentUsing: fn (Textarea $campo): bool => $campo->getLabel() === 'Mensagem para o WhatsApp (revise antes de abrir)'
                && $campo->isRequired())
            ->assertSchemaComponentExists('mensagem_original')
            ->assertSchemaComponentExists('mensagem_contingencia');
    }

    // ─── Etapa 1: gerar ──────────────────────────────────────────

    public function test_botao_gerar_preenche_o_campo_editavel_sem_gravar_no_historico(): void
    {
        $lead = $this->lead();
        $this->mockGemini([$this->respostaIa(self::MENSAGEM_IA)]);

        // O `data` do callAction não chega ao formulário quando o alvo é a ação aninhada: preencher antes de clicar.
        $this->modal($lead)
            ->fillForm(['objetivo' => 'convite_visita', 'tom' => 'acolhedor'])
            ->callAction($this->gerar())
            ->assertSchemaStateSet(['mensagem' => self::MENSAGEM_IA, 'mensagem_original' => self::MENSAGEM_IA, 'mensagem_contingencia' => false])
            ->assertNotNotified();

        $this->assertSame(0, HistoricoContato::where('interessado_id', $lead->id)->count(), 'Gerar não registra nada: o consultor ainda não viu a mensagem.');
    }

    public function test_gerar_de_novo_troca_o_texto_por_uma_nova_versao(): void
    {
        $this->mockGemini([$this->respostaIa('Primeira versão.'), $this->respostaIa('Segunda versão, mais curta.')]);

        $this->modal($this->lead())
            ->callAction($this->gerar())
            ->assertSchemaStateSet(['mensagem' => 'Primeira versão.'])
            ->callAction($this->gerar())
            ->assertSchemaStateSet(['mensagem' => 'Segunda versão, mais curta.', 'mensagem_original' => 'Segunda versão, mais curta.']);
    }

    public function test_ia_fora_do_ar_preenche_o_texto_padrao_e_avisa(): void
    {
        $this->mockGemini([new \RuntimeException('Gemini indisponível')]);

        $this->modal($this->lead())
            ->callAction($this->gerar())
            ->assertSchemaStateSet(['mensagem_contingencia' => true])
            ->assertNotified('A IA não respondeu agora');
    }

    public function test_resposta_vazia_da_ia_vira_texto_padrao_e_nao_mensagem_em_branco(): void
    {
        $this->mockGemini([$this->respostaIa('   ')]);

        $this->modal($this->lead())
            ->callAction($this->gerar())
            ->assertSchemaStateSet(['mensagem_contingencia' => true])
            ->assertNotified('A IA não respondeu agora');
    }

    public function test_sem_telefone_nao_chama_a_ia(): void
    {
        $this->mockGemini([]);

        $this->modal($this->lead(telefone: null))
            ->callAction($this->gerar())
            ->assertNotified('Telefone não encontrado')
            ->assertSchemaStateSet(['mensagem' => null]);
    }

    public function test_limite_de_consultas_por_minuto_impede_nova_geracao(): void
    {
        $this->mockGemini([]);

        $chave = "crm_gemini_user_{$this->usuario->id}";
        foreach (range(1, (int) config('services.gemini.limite_por_minuto', 10)) as $ignorado) {
            RateLimiter::hit($chave, 60);
        }

        $this->modal($this->lead())
            ->callAction($this->gerar())
            ->assertNotified('Limite de consultas de IA atingido')
            ->assertSchemaStateSet(['mensagem' => null]);
    }

    public function test_modelo_institucional_escolhido_segue_para_a_ia(): void
    {
        $modelo = MensagemWhatsappTemplate::create(['nome' => 'Boas-vindas Oficial', 'conteudo' => 'Olá [Nome do Responsável]!', 'instrucoes_ia' => 'Nunca citar o valor da mensalidade.', 'ativo' => true]);
        $capturado = null;

        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->once()->andReturnUsing(function (array $payload) use (&$capturado): array {
            $capturado = $payload;

            return $this->respostaIa(self::MENSAGEM_IA);
        });
        $this->app->instance(GeminiAgentService::class, $mock);

        $this->modal($this->lead())->fillForm(['mensagem_whatsapp_template_id' => $modelo->id])->callAction($this->gerar());

        $this->assertStringContainsString('Nunca citar o valor da mensalidade.', $capturado['systemInstruction']['parts'][0]['text']);
        $this->assertStringContainsString('Olá [Nome do Responsável]!', $capturado['contents'][0]['parts'][0]['text']);
    }

    // ─── Etapa 2: revisar e abrir ────────────────────────────────

    public function test_abrir_o_whatsapp_sem_mensagem_e_recusado(): void
    {
        $lead = $this->lead();
        $this->mockGemini([]);

        $this->modal($lead)
            ->callMountedAction()
            ->assertHasFormErrors(['mensagem' => 'required'])
            ->assertNoJs();

        $this->assertSame(0, HistoricoContato::where('interessado_id', $lead->id)->count());
    }

    public function test_abre_o_whatsapp_com_o_texto_revisado_e_registra_o_que_foi_aberto(): void
    {
        $lead = $this->lead();
        $this->mockGemini([$this->respostaIa(self::MENSAGEM_IA)]);
        $editada = 'Olá, Mariana! Posso te ligar amanhã às 10h para combinarmos a visita?';

        $this->modal($lead)
            ->fillForm(['objetivo' => 'convite_visita'])
            ->callAction($this->gerar())
            ->fillForm(['mensagem' => $editada])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Abrindo o WhatsApp')
            ->assertJs('window.open('.json_encode($this->urlDoWhatsapp('11988887777', $editada)).", '_blank')");

        $historico = HistoricoContato::where('interessado_id', $lead->id)->sole();
        $this->assertTrue($historico->automatico);
        $this->assertStringContainsString('Mensagem redigida pelo Copiloto IA e ajustada pelo consultor (Objetivo: convite_visita)', $historico->relato);
        $this->assertStringContainsString($editada, $historico->relato);
        $this->assertStringNotContainsString(self::MENSAGEM_IA, $historico->relato, 'O histórico guarda o texto final, não o rascunho da IA.');
    }

    public function test_mensagem_da_ia_sem_edicao_mantem_o_texto_original_do_registro(): void
    {
        $lead = $this->lead();
        $this->mockGemini([$this->respostaIa(self::MENSAGEM_IA)]);

        $this->modal($lead)
            ->callAction($this->gerar())
            ->callMountedAction()
            ->assertJs('window.open('.json_encode($this->urlDoWhatsapp('11988887777', self::MENSAGEM_IA)).", '_blank')");

        $relato = HistoricoContato::where('interessado_id', $lead->id)->sole()->relato;
        $this->assertStringStartsWith('💬 Mensagem redigida pelo Copiloto IA (Objetivo: primeiro_contato):', $relato);
        $this->assertStringContainsString(self::MENSAGEM_IA, $relato);
    }

    public function test_texto_padrao_por_falha_da_ia_fica_identificado_no_historico(): void
    {
        $lead = $this->lead();
        $this->mockGemini([new \RuntimeException('Gemini indisponível')]);

        $this->modal($lead)->callAction($this->gerar())->callMountedAction();

        $relato = HistoricoContato::where('interessado_id', $lead->id)->sole()->relato;
        $this->assertStringContainsString('Mensagem padrão, porque a IA não respondeu', $relato);
        $this->assertStringNotContainsString('redigida pelo Copiloto IA', $relato);
    }

    public function test_texto_padrao_ajustado_pelo_consultor_diz_as_duas_coisas(): void
    {
        $lead = $this->lead();
        $this->mockGemini([new \RuntimeException('Gemini indisponível')]);

        $this->modal($lead)->callAction($this->gerar())->fillForm(['mensagem' => 'Texto que eu mesma escrevi.'])->callMountedAction();

        $this->assertStringContainsString(
            'Mensagem padrão, porque a IA não respondeu (ajustada pelo consultor)',
            HistoricoContato::where('interessado_id', $lead->id)->sole()->relato,
        );
    }

    public function test_mensagem_escrita_do_zero_sem_a_ia_tambem_abre_e_fica_identificada(): void
    {
        $lead = $this->lead();
        $this->mockGemini([]);

        $this->modal($lead)
            ->fillForm(['mensagem' => 'Oi, Mariana! Tudo bem?', 'objetivo' => 'reativacao'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertJs('window.open('.json_encode($this->urlDoWhatsapp('11988887777', 'Oi, Mariana! Tudo bem?')).", '_blank')");

        $this->assertStringStartsWith('💬 Mensagem escrita pelo consultor (Objetivo: reativacao):', HistoricoContato::where('interessado_id', $lead->id)->sole()->relato);
    }

    public function test_sem_registrar_no_historico_nada_e_gravado_mas_o_whatsapp_abre(): void
    {
        $lead = $this->lead();
        $this->mockGemini([$this->respostaIa(self::MENSAGEM_IA)]);

        $this->modal($lead)
            ->fillForm(['registrar_historico' => false])
            ->callAction($this->gerar())
            ->callMountedAction()
            ->assertJs('window.open('.json_encode($this->urlDoWhatsapp('11988887777', self::MENSAGEM_IA)).", '_blank')");

        $this->assertSame(0, HistoricoContato::where('interessado_id', $lead->id)->count());
    }

    public function test_historico_cita_o_modelo_institucional_usado(): void
    {
        $lead = $this->lead();
        $modelo = MensagemWhatsappTemplate::create(['nome' => 'Boas-vindas Oficial', 'conteudo' => 'Olá [Nome do Responsável]!', 'ativo' => true]);
        $this->mockGemini([$this->respostaIa(self::MENSAGEM_IA)]);

        $this->modal($lead)
            ->fillForm(['mensagem_whatsapp_template_id' => $modelo->id, 'objetivo' => 'primeiro_contato'])
            ->callAction($this->gerar())
            ->callMountedAction();

        $this->assertStringContainsString(
            "(Objetivo: primeiro_contato com base no modelo 'Boas-vindas Oficial')",
            HistoricoContato::where('interessado_id', $lead->id)->sole()->relato,
        );
    }

    public function test_sem_telefone_o_whatsapp_nao_abre_e_nada_e_registrado(): void
    {
        $lead = $this->lead(telefone: null);

        $this->modal($lead)
            ->fillForm(['mensagem' => 'Oi!'])
            ->callMountedAction()
            ->assertNotified('Telefone não encontrado')
            ->assertNoJs();

        $this->assertSame(0, HistoricoContato::where('interessado_id', $lead->id)->count());
    }

    public function test_o_mesmo_fluxo_funciona_pelo_menu_da_lista_de_leads(): void
    {
        $lead = $this->lead();
        $this->mockGemini([$this->respostaIa(self::MENSAGEM_IA)]);
        $editada = 'Olá, Mariana! Vamos agendar a visita?';

        Livewire::actingAs($this->usuario)
            ->test(ListInteressados::class)
            ->mountAction(TestAction::make('copilotoIa')->table($lead))
            ->callAction($this->gerar())
            ->assertSchemaStateSet(['mensagem' => self::MENSAGEM_IA])
            ->fillForm(['mensagem' => $editada])
            ->callMountedAction()
            ->assertJs('window.open('.json_encode($this->urlDoWhatsapp('11988887777', $editada)).", '_blank')");

        $this->assertStringContainsString($editada, HistoricoContato::where('interessado_id', $lead->id)->sole()->relato);
    }

    // ─── Envio rápido da tabela ──────────────────────────────────

    public function test_envio_rapido_avisa_quando_a_ia_falha_e_abre_o_modelo_sem_personalizacao(): void
    {
        $lead = $this->lead();
        $modelo = MensagemWhatsappTemplate::create(['nome' => 'Boas-vindas', 'conteudo' => 'Olá [Nome do Responsável]!', 'ativo' => true]);
        $this->mockGemini([new \RuntimeException('Gemini indisponível')]);

        Livewire::actingAs($this->usuario)
            ->test(ListInteressados::class)
            ->callTableAction('enviarWhatsapp', $lead, data: ['mensagem_whatsapp_template_id' => $modelo->id, 'adaptar_com_ia' => true])
            ->assertNotified('A IA não respondeu agora')
            ->assertJs('window.open('.json_encode($this->urlDoWhatsapp('11988887777', 'Olá Mariana Oliveira!')).", '_blank')");
    }

    public function test_envio_rapido_sem_ia_nao_avisa_nada(): void
    {
        $lead = $this->lead();
        $modelo = MensagemWhatsappTemplate::create(['nome' => 'Boas-vindas', 'conteudo' => 'Olá [Nome do Responsável]!', 'ativo' => true]);
        $this->mockGemini([]);

        Livewire::actingAs($this->usuario)
            ->test(ListInteressados::class)
            ->callTableAction('enviarWhatsapp', $lead, data: ['mensagem_whatsapp_template_id' => $modelo->id, 'adaptar_com_ia' => false])
            ->assertNotNotified();
    }
}

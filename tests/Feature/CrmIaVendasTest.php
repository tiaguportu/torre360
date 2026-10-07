<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusVisitaInteressado;
use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\MensagemWhatsappTemplate;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Role;
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

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function mockGeminiComDossie(string $markdown): void
    {
        $geminiMock = Mockery::mock(GeminiAgentService::class);
        $geminiMock->shouldReceive('callGeminiApi')
            ->atLeast()->once()
            ->andReturn([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'resumo_executivo' => 'Família engajada.',
                            'temperatura_sugerida' => 'quente',
                            'proxima_acao_sugerida' => 'Convidar para o tour.',
                            'dossie_markdown' => $markdown,
                        ]),
                    ]]],
                ]],
            ]);

        $this->app->instance(GeminiAgentService::class, $geminiMock);
    }

    public function test_modal_do_dossie_renderiza_o_markdown_como_html_formatado(): void
    {
        $interessado = $this->criarInteressadoCompleto();

        $this->mockGeminiComDossie("### 🎯 Dores e Objeções\n\n- **Preço:** quer entender o investimento\n- Metodologia\n\n<script>alert('xss')</script>");

        Livewire::actingAs($this->admin())
            ->test(EditInteressado::class, ['record' => $interessado->getKey()])
            ->mountAction('dossieIa')
            ->assertMountedActionModalSee([
                '<h3>🎯 Dores e Objeções</h3>',
                '<li><strong>Preço:</strong> quer entender o investimento</li>',
            ], escape: false)
            ->assertMountedActionModalDontSee(['### ', '**Preço:**', "<script>alert('xss')</script>"], escape: false);
    }

    public function test_dossie_salva_no_historico_o_markdown_original(): void
    {
        $interessado = $this->criarInteressadoCompleto();
        $markdown = "### 🚀 Roteiro\n\n- **Destacar** o projeto bilíngue";

        $this->mockGeminiComDossie($markdown);

        Livewire::actingAs($this->admin())
            ->test(EditInteressado::class, ['record' => $interessado->getKey()])
            ->callAction('dossieIa', ['registrar_historico' => true])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('historico_contato', [
            'interessado_id' => $interessado->id,
            'relato' => "✨ Dossiê Estratégico gerado com IA:\n\n".$markdown,
            'automatico' => true,
        ]);
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

    public function test_crm_ia_vendas_gerar_pdf_dossie_retorna_pdf_valido(): void
    {
        $interessado = $this->criarInteressadoCompleto();

        $service = app(CrmIaVendasService::class);
        $pdf = $service->gerarPdfDossie($interessado, [
            'resumo_executivo' => 'Família com grande apreço por formação humana e acolhimento.',
            'temperatura_sugerida' => 'quente',
            'proxima_acao_sugerida' => 'Agendar visita com o consultor.',
            'dossie_markdown' => "### 🎯 Dores e Objeções\n\nPreocupação com adaptação ao currículo.\n\n### 🚀 Roteiro de Abordagem\n\n- Destacar o programa de tutoria.",
        ]);

        $this->assertInstanceOf(PDF::class, $pdf);

        $conteudoPdf = $pdf->output();
        $this->assertNotEmpty($conteudoPdf);
        $this->assertStringStartsWith('%PDF', $conteudoPdf);
    }

    public function test_sanitizacao_remove_emojis_para_evitar_caracteres_quebrados_no_pdf(): void
    {
        $service = app(CrmIaVendasService::class);
        $textoComEmojis = "### 👨‍👩‍👧 Perfil da Família\n\n- 🔥 Urgência alta\n- 🎯 Foco em línguas\n- 🚀 Próximo passo\n- ✨ Excelente";
        $sanitizado = $service->sanitizarTextoParaPdf($textoComEmojis);

        $this->assertStringNotContainsString('👨‍👩‍👧', $sanitizado);
        $this->assertStringNotContainsString('🔥', $sanitizado);
        $this->assertStringNotContainsString('🎯', $sanitizado);
        $this->assertStringNotContainsString('🚀', $sanitizado);
        $this->assertStringNotContainsString('✨', $sanitizado);
        $this->assertStringContainsString('### Perfil da Família', $sanitizado);
        $this->assertStringContainsString('- Urgência alta', $sanitizado);
    }

    public function test_rota_dossie_pdf_permite_download_para_usuario_com_permissao(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $interessado = $this->criarInteressadoCompleto();

        // Alimenta o cache como se tivesse sido gerado na modal
        cache()->put("dossie_ia_lead_{$interessado->id}", [
            'resumo_executivo' => 'Resumo de teste para download de PDF.',
            'temperatura_sugerida' => 'quente',
            'proxima_acao_sugerida' => 'Realizar contato de fechamento.',
            'dossie_markdown' => '### Relatório de Teste',
        ], now()->addMinutes(10));

        $response = $this->actingAs($admin)->get(route('crm.interessados.dossie-pdf', $interessado));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Dossie_Estrategico', (string) $response->headers->get('content-disposition'));
    }

    public function test_rota_dossie_pdf_exige_autenticacao(): void
    {
        $interessado = $this->criarInteressadoCompleto();

        $response = $this->get(route('crm.interessados.dossie-pdf', $interessado));

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_crm_ia_vendas_gerar_mensagem_copiloto_com_template_base(): void
    {
        $interessado = $this->criarInteressadoCompleto();
        $textoGerado = 'Olá Mariana! Como combinado no modelo oficial, gostaríamos de convidá-la para o tour do Lucas!';

        $geminiMock = Mockery::mock(GeminiAgentService::class);
        $geminiMock->shouldReceive('callGeminiApi')
            ->once()
            ->withArgs(function (array $payload) {
                $userContent = $payload['contents'][0]['parts'][0]['text'] ?? '';
                $systemContent = $payload['systemInstruction']['parts'][0]['text'] ?? '';

                return str_contains($userContent, 'Convite Formal de Visita')
                    && str_contains($systemContent, 'MODELO INSTITUCIONAL DE REFERÊNCIA');
            })
            ->andReturn([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => $textoGerado,
                    ]]],
                ]],
            ]);

        $this->app->instance(GeminiAgentService::class, $geminiMock);

        $service = app(CrmIaVendasService::class);
        $mensagem = $service->gerarMensagemCopiloto(
            interessado: $interessado,
            objetivo: 'convite_visita',
            templateBase: 'Convite Formal de Visita: [Nome do Responsável], venha conhecer o colégio!'
        );

        $this->assertSame($textoGerado, $mensagem);
    }

    public function test_contexto_da_ia_inclui_visitas_com_status_legivel(): void
    {
        $interessado = $this->criarInteressadoCompleto();

        VisitaInteressado::create([
            'interessado_id' => $interessado->id,
            'data_hora' => now()->addDays(2),
            'status' => StatusVisitaInteressado::Faltou,
            'observacoes' => 'Família avisou que não conseguiria ir.',
        ]);

        $geminiMock = Mockery::mock(GeminiAgentService::class);
        $geminiMock->shouldReceive('callGeminiApi')
            ->once()
            ->withArgs(function (array $payload) {
                $contexto = $payload['contents'][0]['parts'][0]['text'] ?? '';

                return str_contains($contexto, '(Status: Não compareceu)');
            })
            ->andReturn([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Olá Mariana!']]],
                ]],
            ]);

        $this->app->instance(GeminiAgentService::class, $geminiMock);

        $mensagem = app(CrmIaVendasService::class)->gerarMensagemCopiloto(
            interessado: $interessado,
            objetivo: 'convite_visita',
        );

        $this->assertSame('Olá Mariana!', $mensagem);
    }

    public function test_crm_ia_vendas_fallback_com_template_base_substitui_tags(): void
    {
        $interessado = $this->criarInteressadoCompleto();

        $geminiMock = Mockery::mock(GeminiAgentService::class);
        $geminiMock->shouldReceive('callGeminiApi')
            ->andThrow(new \RuntimeException('Erro de API'));

        $this->app->instance(GeminiAgentService::class, $geminiMock);

        $service = app(CrmIaVendasService::class);
        $template = 'Olá [Nome do Responsável] ([Primeiro Nome]), vaga para [Nome do Aluno] no [Nome da Escola]!';

        $mensagem = $service->gerarMensagemCopiloto(
            interessado: $interessado,
            objetivo: 'primeiro_contato',
            templateBase: $template
        );

        $this->assertStringContainsString('Mariana Oliveira', $mensagem);
        $this->assertStringContainsString('Mariana', $mensagem);
        $this->assertStringContainsString('Lucas Oliveira', $mensagem);
        $this->assertStringContainsString('Escola Torre de Marfim', $mensagem);
        $this->assertStringNotContainsString('[Nome do Responsável]', $mensagem);
    }

    public function test_copiloto_ia_action_com_template_base_registra_historico_com_referencia(): void
    {
        $admin = $this->admin();
        $interessado = $this->criarInteressadoCompleto();
        $template = MensagemWhatsappTemplate::create([
            'nome' => 'Boas-vindas Oficial',
            'conteudo' => 'Olá [Nome do Responsável]!',
            'ativo' => true,
        ]);

        $geminiMock = Mockery::mock(GeminiAgentService::class);
        $geminiMock->shouldReceive('callGeminiApi')
            ->once()
            ->andReturn([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => 'Olá Mariana, seja bem-vinda com o Lucas!',
                    ]]],
                ]],
            ]);

        $this->app->instance(GeminiAgentService::class, $geminiMock);

        Livewire::actingAs($admin)
            ->test(EditInteressado::class, ['record' => $interessado->id])
            ->callAction('copilotoIa', [
                'mensagem_whatsapp_template_id' => $template->id,
                'objetivo' => 'primeiro_contato',
                'tom' => 'acolhedor',
                'registrar_historico' => true,
            ])
            ->assertNotified();

        $historico = HistoricoContato::where('interessado_id', $interessado->id)->latest('id')->first();
        $this->assertNotNull($historico);
        $this->assertStringContainsString("com base no modelo 'Boas-vindas Oficial'", $historico->relato);
        $this->assertStringContainsString('Olá Mariana, seja bem-vinda com o Lucas!', $historico->relato);
    }
}

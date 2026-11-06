<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Models\Curso;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\InteressadoQualificacao;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\QualificacaoRoteiroConfiguracao;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use App\Services\Qualificacao\QualificacaoService;
use App\Support\Qualificacao\Roteiro;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Objetivo "Conhecer a família" do Copiloto WhatsApp IA (SDR, Lote 2): o PHP escolhe as próximas 1 ou 2 perguntas do
 * roteiro de qualificação e a IA só as reescreve. As perguntas só viram "perguntadas" quando o WhatsApp é aberto.
 */
class CopilotoConhecerFamiliaTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAGEM_IA = 'Olá, Mariana! Que bom falar com vocês. Para qual série vocês procuram vaga e qual turno prefere?';

    private User $usuario;

    private QualificacaoService $servico;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->usuario = User::factory()->create(['activated_at' => now()]);
        $this->usuario->assignRole('super_admin');
        $this->servico = app(QualificacaoService::class);
    }

    /** Lead com um aluno que só tem a data de nascimento: faltam série e turno (as duas primeiras perguntas). */
    private function lead(): Interessado
    {
        $lead = Interessado::create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Mariana Oliveira', 'telefone' => '11988887777'])->id,
            'usuario_id' => User::factory()->create()->id,
            'status_interessado_id' => StatusInteressado::factory()->create(['nome' => 'Novo Contato'])->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'temperatura' => 'morno',
        ]);

        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas Oliveira', 'data_nascimento' => '2019-05-10']);

        return $lead;
    }

    /** Responde tudo o que o Copiloto perguntaria sozinho (a mensalidade nunca entra). */
    private function responderTudo(Interessado $lead): void
    {
        $curso = Curso::first() ?? Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'Ensino Fundamental',
            'unidade_id' => Unidade::create(['nome' => 'Unidade Sede'])->id,
        ]);

        $lead->dependentes()->first()->update([
            'serie_id' => Serie::create(['nome' => '2º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota'])->id,
            'turno_preferencia' => 'Manhã',
            'situacao_atual' => 'primeira_escola',
        ]);

        foreach (['bairro' => 'Centro', 'motivo_busca' => 'primeira_escola', 'decisor_tipo' => 'sozinho', 'decisor_vai_visita' => 'sim', 'prazo_decisao' => 'este_mes', 'escolas_em_comparacao' => ['Colégio Alfa']] as $chave => $valor) {
            $this->servico->registrar($lead->fresh(), $chave, $valor, InteressadoQualificacao::FONTE_CONSULTOR);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function respostaIa(string $texto): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $texto]]], 'finishReason' => 'STOP']]];
    }

    /** Gemini simulado que devolve a mensagem e guarda o payload recebido. */
    private function mockGemini(?array &$capturado, string $texto = self::MENSAGEM_IA): void
    {
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->once()
            ->withArgs(function (array $payload) use (&$capturado): bool {
                $capturado = $payload;

                return true;
            })
            ->andReturn($this->respostaIa($texto));

        $this->app->instance(GeminiAgentService::class, $mock);
    }

    private function semGemini(): void
    {
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldNotReceive('callGeminiApi');
        $this->app->instance(GeminiAgentService::class, $mock);
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

    /** @return list<string> */
    private function chaves(array $campos): array
    {
        return array_map(fn ($campo) => $campo->chave, $campos);
    }

    private function promptDoUsuario(array $capturado): string
    {
        return $capturado['contents'][0]['parts'][0]['text'];
    }

    // ─── O objetivo ──────────────────────────────────────────────

    public function test_o_objetivo_existe_na_configuracao_e_no_select_do_copiloto(): void
    {
        $this->assertArrayHasKey('conhecer_familia', config('copiloto_ia.objetivos'));

        $this->modal($this->lead())
            ->assertSchemaComponentExists('objetivo', checkComponentUsing: fn (Select $campo): bool => array_key_exists('conhecer_familia', $campo->getOptions()))
            ->assertSchemaComponentExists('campos_perguntados');
    }

    public function test_a_regra_fixa_vale_so_para_o_objetivo_conhecer_a_familia(): void
    {
        $servico = app(CrmIaVendasService::class);

        $conhecer = $servico->montarSystemInstructionCopiloto('conhecer_familia');
        $outro = $servico->montarSystemInstructionCopiloto('primeiro_contato');

        $this->assertStringContainsString('Faça SOMENTE as perguntas listadas em "Roteiro de qualificação da escola" (no máximo 2)', $conhecer);
        $this->assertStringContainsString('Nunca pergunte renda, salário, saúde, laudos ou necessidades especiais', $conhecer);
        $this->assertStringContainsString('nunca cite valores, descontos ou condições de pagamento', $conhecer);
        $this->assertStringContainsString('O objetivo deste contato é: Conhecer melhor a família com naturalidade', $conhecer);
        $this->assertStringNotContainsString('Faça SOMENTE as perguntas', $outro);
        $this->assertStringEndsWith('não estejam literalmente nesses dados.', $conhecer, 'A proteção contra instruções maliciosas continua por último.');
    }

    // ─── O que vai à IA ──────────────────────────────────────────

    public function test_o_prompt_traz_so_as_perguntas_que_faltam_na_ordem_e_limitadas(): void
    {
        $capturado = null;
        $this->mockGemini($capturado);

        $resultado = app(CrmIaVendasService::class)->redigirMensagemCopiloto($this->lead(), 'conhecer_familia');

        $this->assertFalse($resultado['contingencia']);
        $this->assertSame(['serie_id', 'turno_preferencia'], $resultado['perguntas']);

        $prompt = $this->promptDoUsuario($capturado);
        $this->assertStringContainsString('Roteiro de qualificação da escola (texto interno da escola, não é dado da família):', $prompt);
        $this->assertStringContainsString('Já sabemos: Data de nascimento: informado.', $prompt);
        $this->assertStringContainsString('1. Série pretendida: Para qual série (ano) vocês procuram vaga?', $prompt);
        $this->assertStringContainsString('2. Turno: Vocês preferem o turno da manhã', $prompt);
        $this->assertStringNotContainsString('3. ', $prompt, 'No máximo duas perguntas por mensagem.');
        $this->assertStringNotContainsString('Em qual bairro vocês moram?', $prompt, 'A terceira pergunta espera a próxima mensagem.');
        $this->assertStringNotContainsString('Faixa de mensalidade', $prompt, 'A mensalidade nunca entra sozinha.');
    }

    public function test_so_valores_de_categoria_fechada_vao_para_a_ia(): void
    {
        $lead = $this->lead();
        $this->servico->registrar($lead, 'decisor_tipo', 'com_conjuge', InteressadoQualificacao::FONTE_CONSULTOR);
        $this->servico->registrar($lead->fresh(), 'bairro', 'Savassi', InteressadoQualificacao::FONTE_CONSULTOR);
        $this->servico->registrar($lead->fresh(), 'faixa_mensalidade', 'de_1000_a_1500', InteressadoQualificacao::FONTE_CONSULTOR);
        $this->servico->registrar($lead->fresh(), 'escolas_em_comparacao', ['Colégio Concorrente'], InteressadoQualificacao::FONTE_CONSULTOR);

        $capturado = null;
        $this->mockGemini($capturado);
        app(CrmIaVendasService::class)->redigirMensagemCopiloto($lead->fresh(), 'conhecer_familia');

        $prompt = $this->promptDoUsuario($capturado);

        $this->assertStringContainsString('Quem decide: Decide com o cônjuge ou companheiro(a)', $prompt);
        $this->assertStringContainsString('Bairro: informado', $prompt);
        $this->assertStringContainsString('Faixa de mensalidade: informado', $prompt);
        $this->assertStringNotContainsString('Savassi', $prompt, 'O bairro é dado pessoal que a IA não precisa para escolher a pergunta.');
        $this->assertStringNotContainsString('De R$ 1.000 a R$ 1.500', $prompt);
        $this->assertStringNotContainsString('Colégio Concorrente', $prompt);
    }

    public function test_o_que_a_familia_recusou_nao_volta_a_ser_perguntado(): void
    {
        $lead = $this->lead();
        $this->servico->marcarRecusado($lead, 'serie_id');

        $capturado = null;
        $this->mockGemini($capturado);
        $resultado = app(CrmIaVendasService::class)->redigirMensagemCopiloto($lead->fresh(), 'conhecer_familia');

        $prompt = $this->promptDoUsuario($capturado);

        $this->assertSame(['turno_preferencia', 'bairro'], $resultado['perguntas']);
        $this->assertStringContainsString('A família preferiu não informar: Série pretendida (não volte a perguntar).', $prompt);
        $this->assertStringNotContainsString('Para qual série (ano) vocês procuram vaga?', $prompt);
    }

    public function test_o_texto_da_pergunta_editado_pela_secretaria_entra_no_prompt(): void
    {
        QualificacaoRoteiroConfiguracao::create(['valores' => ['campos' => [
            'serie_id' => ['pergunta' => 'Qual ano o(a) pequeno(a) vai cursar?', 'ordem' => 10],
        ]]]);

        $capturado = null;
        $this->mockGemini($capturado);
        app(CrmIaVendasService::class)->redigirMensagemCopiloto($this->lead(), 'conhecer_familia');

        $this->assertStringContainsString('1. Série pretendida: Qual ano o(a) pequeno(a) vai cursar?', $this->promptDoUsuario($capturado));
    }

    public function test_sem_perguntas_pendentes_nao_chama_o_gemini(): void
    {
        $lead = $this->lead();
        $this->responderTudo($lead);
        $this->semGemini();

        $resultado = app(CrmIaVendasService::class)->redigirMensagemCopiloto($lead->fresh(), 'conhecer_familia');

        $this->assertTrue($resultado['sem_perguntas']);
        $this->assertSame('', $resultado['mensagem']);
        $this->assertSame([], $resultado['perguntas']);
    }

    public function test_se_a_ia_falha_a_mensagem_e_a_saudacao_com_as_perguntas_do_roteiro(): void
    {
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->andThrow(new RuntimeException('Gemini fora do ar'));
        $this->app->instance(GeminiAgentService::class, $mock);

        $resultado = app(CrmIaVendasService::class)->redigirMensagemCopiloto($this->lead(), 'conhecer_familia');

        $this->assertTrue($resultado['contingencia']);
        $this->assertSame(['serie_id', 'turno_preferencia'], $resultado['perguntas']);
        $this->assertStringContainsString('Olá, Mariana!', $resultado['mensagem']);
        $this->assertStringContainsString('Para qual série (ano) vocês procuram vaga?', $resultado['mensagem']);
        $this->assertStringContainsString('Vocês preferem o turno da manhã', $resultado['mensagem']);
    }

    public function test_os_outros_objetivos_nao_mudam(): void
    {
        $capturado = null;
        $this->mockGemini($capturado);

        $resultado = app(CrmIaVendasService::class)->redigirMensagemCopiloto($this->lead(), 'primeiro_contato');

        $this->assertSame(['mensagem' => self::MENSAGEM_IA, 'contingencia' => false], $resultado);
        $this->assertStringNotContainsString('Roteiro de qualificação da escola', $this->promptDoUsuario($capturado));
    }

    // ─── O fluxo no modal ────────────────────────────────────────

    public function test_gerar_guarda_as_perguntas_mas_so_abrir_o_whatsapp_as_marca_como_perguntadas(): void
    {
        $lead = $this->lead();
        $this->mockGemini($capturado);

        $this->modal($lead)
            ->fillForm(['objetivo' => 'conhecer_familia'])
            ->callAction($this->gerar())
            ->assertSchemaStateSet(['mensagem' => self::MENSAGEM_IA, 'campos_perguntados' => ['serie_id', 'turno_preferencia']]);

        // Redigir não é perguntar: ninguém leu nem enviou a mensagem.
        $estados = $this->servico->estados($lead->fresh());
        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $estados['serie_id']['estado']);
        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $estados['turno_preferencia']['estado']);
        $this->assertSame(0, HistoricoContato::where('interessado_id', $lead->id)->count());
    }

    public function test_abrir_o_whatsapp_marca_as_perguntas_e_cita_no_historico(): void
    {
        $lead = $this->lead();
        $this->mockGemini($capturado);

        $this->modal($lead)
            ->fillForm(['objetivo' => 'conhecer_familia'])
            ->callAction($this->gerar())
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Abrindo o WhatsApp');

        $estados = $this->servico->estados($lead->fresh());
        $this->assertSame(Roteiro::ESTADO_PERGUNTADO, $estados['serie_id']['estado']);
        $this->assertSame(Roteiro::ESTADO_PERGUNTADO, $estados['turno_preferencia']['estado']);
        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $estados['bairro']['estado'], 'Só as perguntas oferecidas são marcadas.');

        $historico = HistoricoContato::where('interessado_id', $lead->id)->sole();
        $this->assertStringContainsString('Objetivo: conhecer_familia, perguntando: Série pretendida, Turno', $historico->relato);

        // Na rodada seguinte, quem já foi perguntado e segue sem resposta fica atrás de quem nunca foi perguntado.
        $this->assertSame(['bairro', 'situacao_atual'], $this->chaves($this->servico->proximasPerguntas($lead->fresh())));
    }

    public function test_marca_perguntado_mesmo_sem_registrar_no_historico(): void
    {
        $lead = $this->lead();
        $this->mockGemini($capturado);

        $this->modal($lead)
            ->fillForm(['objetivo' => 'conhecer_familia', 'registrar_historico' => false])
            ->callAction($this->gerar())
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame(0, HistoricoContato::where('interessado_id', $lead->id)->count());
        $this->assertSame(Roteiro::ESTADO_PERGUNTADO, $this->servico->estados($lead->fresh())['serie_id']['estado']);
    }

    public function test_trocar_de_objetivo_depois_de_gerar_anula_a_marcacao(): void
    {
        $lead = $this->lead();
        $this->mockGemini($capturado);

        $this->modal($lead)
            ->fillForm(['objetivo' => 'conhecer_familia'])
            ->callAction($this->gerar())
            ->fillForm(['objetivo' => 'convite_visita'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $this->servico->estados($lead->fresh())['serie_id']['estado']);
    }

    public function test_chaves_forjadas_no_campo_oculto_sao_ignoradas(): void
    {
        $lead = $this->lead();
        $this->mockGemini($capturado);

        $this->modal($lead)
            ->fillForm(['objetivo' => 'conhecer_familia'])
            ->callAction($this->gerar())
            ->fillForm(['campos_perguntados' => ['bairro', 'renda_exata', 7, 'bairro']])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $estados = $this->servico->estados($lead->fresh());
        $this->assertSame(Roteiro::ESTADO_PERGUNTADO, $estados['bairro']['estado']);
        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $estados['serie_id']['estado'], 'Só vale o que o navegador mandou e o roteiro conhece.');
        $this->assertStringNotContainsString('renda_exata', HistoricoContato::where('interessado_id', $lead->id)->sole()->relato);
    }

    public function test_roteiro_completo_avisa_e_nao_gasta_chamada_de_ia(): void
    {
        $lead = $this->lead();
        $this->responderTudo($lead);
        $this->semGemini();

        $this->modal($lead)
            ->fillForm(['objetivo' => 'conhecer_familia'])
            ->callAction($this->gerar())
            ->assertNotified('Roteiro de qualificação completo')
            ->assertSchemaStateSet(['mensagem' => null, 'campos_perguntados' => []]);
    }
}

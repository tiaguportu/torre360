<?php

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Enums\SituacaoMatricula;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\IndicacaoInteressado;
use App\Models\Interessado;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use App\Models\Turma;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use App\Services\InteressadoMatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CrmNovasFuncionalidadesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_programa_familia_indica_familia_gera_codigo_e_vincula_ao_lead(): void
    {
        $quemIndicou = Pessoa::create([
            'nome' => 'Maria Silva (Mãe Aluno)',
            'cpf' => '12345678901',
            'email' => 'maria@teste.com',
        ]);

        $codigo = $quemIndicou->obterOuCriarCodigoIndicacao();
        $this->assertNotEmpty($codigo);
        $this->assertEquals($codigo, $quemIndicou->codigo_indicacao);

        $status = StatusInteressado::create(['nome' => 'Novo Lead', 'ordem' => 1]);
        $origem = OrigemInteressado::create(['nome' => 'Indicação de Pais']);

        $pessoaLead = Pessoa::create([
            'nome' => 'Carlos Amigo',
            'cpf' => '98765432100',
            'email' => 'carlos@teste.com',
        ]);

        $interessado = Interessado::create([
            'pessoa_id' => $pessoaLead->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
        ]);

        $indicacao = IndicacaoInteressado::create([
            'indicador_pessoa_id' => $quemIndicou->id,
            'interessado_id' => $interessado->id,
            'codigo_indicacao' => $codigo,
            'status' => IndicacaoInteressado::STATUS_PENDENTE,
            'recompensa_tipo' => 'desconto_mensalidade',
            'valor_recompensa' => 100.00,
        ]);

        $this->assertEquals(IndicacaoInteressado::STATUS_PENDENTE, $indicacao->status);

        // Ao matricular o lead através do serviço de matrícula
        StatusInteressado::create(['nome' => 'Matriculado', 'ordem' => 10, 'is_ganho' => true]);
        InteressadoMatriculaService::registrarConversao($interessado);

        $indicacao->refresh();
        $this->assertEquals(IndicacaoInteressado::STATUS_MATRICULADO, $indicacao->status);
        $this->assertNotNull($indicacao->data_conversao);

        // Conceder recompensa
        $indicacao->marcarRecompensado(null, 'Desconto de R$ 100 concedido na fatura de novembro');
        $this->assertEquals(IndicacaoInteressado::STATUS_RECOMPENSADO, $indicacao->status);
        $this->assertNotNull($indicacao->data_recompensa);
    }

    public function test_portal_de_pre_admissao_upload_documento_com_token(): void
    {
        $status = StatusInteressado::create(['nome' => 'Novo Lead', 'ordem' => 1]);
        $origem = OrigemInteressado::create(['nome' => 'Site']);
        $pessoa = Pessoa::create(['nome' => 'João Responsável', 'telefone' => '11999998888']);
        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
        ]);

        $tipoDoc = TipoDocumento::create([
            'nome' => 'Certidão de Nascimento',
            'flag_obrigatorio' => true,
        ]);

        $token = $interessado->obterOuCriarTokenDocumentos();
        $this->assertNotEmpty($token);

        // 1. Acesso à página do portal
        $response = $this->get(route('candidato.documentos.show', ['token' => $token]));
        $response->assertStatus(200);
        $response->assertSee('Portal de Admissão');
        $response->assertSee('Certidão de Nascimento');

        // 2. Upload do documento
        $file = UploadedFile::fake()->create('certidao.pdf', 500, 'application/pdf');

        $uploadResponse = $this->post(route('candidato.documentos.upload', ['token' => $token]), [
            'tipo_documento_id' => $tipoDoc->id,
            'arquivo' => $file,
        ]);

        $uploadResponse->assertRedirect();
        $uploadResponse->assertSessionHas('sucesso');

        $this->assertDatabaseHas('documento_inserido', [
            'interessado_id' => $interessado->id,
            'tipo_documento_id' => $tipoDoc->id,
            'status' => SituacaoDocumento::EM_ANALISE->value,
            'nome_arquivo_original' => 'certidao.pdf',
        ]);

        $docInserido = DocumentoInserido::where('interessado_id', $interessado->id)->first();
        $this->assertNotNull($docInserido);

        // 3. Aprovação do documento pela secretaria
        $docInserido->transitionTo(SituacaoDocumento::VERIFICADO);
        $this->assertEquals(SituacaoDocumento::VERIFICADO, $docInserido->status);

        // 4. Migração automática para a matrícula quando o lead é matriculado
        $turma = Turma::create(['nome' => '1º Ano A']);
        $alunoPessoa = Pessoa::create(['nome' => 'Pedrinho Filho']);
        $matricula = Matricula::create([
            'pessoa_id' => $alunoPessoa->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA->value,
        ]);

        InteressadoMatriculaService::registrarConversao($interessado, [$matricula]);

        $docInserido->refresh();
        $this->assertEquals($matricula->id, $docInserido->matricula_id);
    }

    public function test_resumo_ia_de_conversas_longas_do_whatsapp(): void
    {
        $status = StatusInteressado::create(['nome' => 'Em Atendimento', 'ordem' => 2]);
        $origem = OrigemInteressado::create(['nome' => 'WhatsApp']);
        $pessoa = Pessoa::create(['nome' => 'Fernanda Lima', 'telefone' => '11988887777']);
        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'temperatura' => 'morno',
        ]);

        // Mock do GeminiAgentService
        $geminiMock = Mockery::mock(GeminiAgentService::class);
        $geminiMock->shouldReceive('callGeminiApi')
            ->once()
            ->andReturn([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'temperatura_sugerida' => 'quente',
                                        'data_retorno_sugerida' => '2026-10-15',
                                        'proximo_passo_sugerido' => 'Confirmar presença na visita na véspera.',
                                        'resumo_markdown' => "### 💬 Síntese da Conversa\nMãe buscou informações sobre o 2º ano bilíngue.",
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->app->instance(GeminiAgentService::class, $geminiMock);

        $conversa = "Mãe: Boa tarde! Gostaria de saber sobre o 2º ano. Meu filho tem sofrido com turmas lotadas.\nConsultor: Olá Fernanda! Nossas turmas têm no máximo 18 alunos e bilinguismo diário.\nMãe: Adorei! Podemos agendar uma visita na terça às 14h?\nConsultor: Fechado, te aguardo aqui!";

        $service = app(CrmIaVendasService::class);
        $resultado = $service->resumirConversaWhatsapp($interessado, $conversa);

        $this->assertEquals('quente', $resultado['temperatura_sugerida']);
        $this->assertEquals('2026-10-15', $resultado['data_retorno_sugerida']);
        $this->assertStringContainsString('Síntese da Conversa', $resultado['resumo_markdown']);

        // Simula o salvamento realizado pela Action no lead e na timeline
        $interessado->update([
            'temperatura' => $resultado['temperatura_sugerida'],
            'data_proximo_contato' => $resultado['data_retorno_sugerida'],
        ]);

        $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'WhatsApp']);
        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => $resultado['resumo_markdown'],
            'data_contato' => now(),
        ]);

        $interessado->refresh();
        $this->assertEquals('quente', $interessado->temperatura);

        $this->assertDatabaseHas('historico_contato', [
            'interessado_id' => $interessado->id,
        ]);
    }
}

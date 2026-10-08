<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Jobs\ValidarDocumentoComIaJob;
use App\Models\Configuracao;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ValidarDocumentoIaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function criarCenarioCandidato(): array
    {
        $origem = OrigemInteressado::create([
            'nome' => 'Site',
        ]);

        $status = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $pessoa = Pessoa::create([
            'nome' => 'Carlos Alberto Silva',
            'telefone' => '11988887777',
            'email' => 'carlos@example.com',
            'cpf' => null,
            'identidade' => null,
        ]);

        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'origem_interessado_id' => $origem->id,
            'status_interessado_id' => $status->id,
            'token_documentos' => 'token-teste-portal-ia-12345',
        ]);

        $dependente = InteressadoDependente::create([
            'interessado_id' => $interessado->id,
            'nome_crianca' => 'Lucas Silva',
            'data_nascimento' => null,
        ]);

        $tipoDoc = TipoDocumento::create([
            'nome' => 'Certidão de Nascimento ou RG do Aluno',
            'flag_obrigatorio' => true,
            'status' => 'ativo',
        ]);

        return [$interessado, $dependente, $tipoDoc, $pessoa];
    }

    public function test_upload_no_portal_despacha_job_de_validacao_ia_em_segundo_plano(): void
    {
        Queue::fake();

        [$interessado, $dependente, $tipoDoc] = $this->criarCenarioCandidato();

        $file = UploadedFile::fake()->create('documento_aluno.pdf', 500, 'application/pdf');

        $response = $this->from(route('candidato.documentos.show', ['token' => $interessado->token_documentos]))
            ->post(route('candidato.documentos.upload', ['token' => $interessado->token_documentos]), [
                'tipo_documento_id' => $tipoDoc->id,
                'interessado_dependente_id' => $dependente->id,
                'arquivo' => $file,
            ]);

        $response->assertRedirect(route('candidato.documentos.show', ['token' => $interessado->token_documentos, 'aba' => 'documentos']));
        $response->assertSessionHas('sucesso');

        $this->assertDatabaseHas('documento_inserido', [
            'interessado_id' => $interessado->id,
            'tipo_documento_id' => $tipoDoc->id,
            'status' => SituacaoDocumento::EM_ANALISE->value,
        ]);

        Queue::assertPushed(ValidarDocumentoComIaJob::class);
    }

    public function test_job_valida_documento_com_ia_e_persiste_dados_ia_e_historico(): void
    {
        [$interessado, $dependente, $tipoDoc] = $this->criarCenarioCandidato();

        $caminhoArquivo = 'documentos_candidatos/'.$interessado->id.'/certidao_lucas.pdf';
        Storage::disk('local')->put($caminhoArquivo, 'conteudo_binario_falso_pdf');

        $doc = DocumentoInserido::create([
            'interessado_id' => $interessado->id,
            'interessado_dependente_id' => $dependente->id,
            'tipo_documento_id' => $tipoDoc->id,
            'arquivo_path' => $caminhoArquivo,
            'nome_arquivo_original' => 'certidao_lucas.pdf',
            'status' => SituacaoDocumento::EM_ANALISE,
        ]);

        // Simula resposta da IA Gemini Vision
        $fakeRespostaIa = json_encode([
            'confere_com_solicitado' => true,
            'documento_identificado' => 'Certidão de Nascimento',
            'qualidade' => [
                'legivel' => true,
                'nitidez' => 'alta',
                'enquadramento' => 'completo',
            ],
            'score_confianca' => 95,
            'dados_extraidos' => [
                'nome_titular' => 'Lucas Silva',
                'cpf' => '44455566677',
                'rg' => '55667788X',
                'data_nascimento' => '2016-08-20',
                'nome_mae' => 'Mariana Silva',
                'nome_pai' => 'Carlos Alberto Silva',
                'endereco_completo' => 'Rua das Flores, 123',
            ],
            'divergencias' => [],
            'status_sugerido' => 'verificado',
            'motivo_rejeicao_sugerido' => null,
            'mensagem_para_familia' => null,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $fakeRespostaIa],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Configuracao::updateOrCreate(
            ['campo' => 'gemini_api_key'],
            ['valor' => 'fake-gemini-key-para-teste', 'grupo' => 'ia']
        );

        // Executa o Job sincronicamente
        ValidarDocumentoComIaJob::dispatchSync($doc->id);

        $doc->refresh();

        $this->assertNotNull($doc->analisado_ia_em);
        $this->assertNotNull($doc->dados_ia);
        $this->assertEquals(95, $doc->dados_ia['score_confianca']);
        $this->assertTrue($doc->isLegivelIa());
        $this->assertTrue($doc->confereTipoIa());
        $this->assertEquals('Lucas Silva', $doc->dados_ia['dados_extraidos']['nome_titular']);

        // Verifica se foi registrado no histórico do lead
        $this->assertDatabaseHas('historico_contato', [
            'interessado_id' => $interessado->id,
        ]);

        $ultimoContato = HistoricoContato::where('interessado_id', $interessado->id)->latest()->first();
        $this->assertStringContainsString('IA analisou', $ultimoContato->relato);
        $this->assertTrue($ultimoContato->automatico, 'A análise da IA é registro automático e não conta como interação.');
    }

    public function test_portal_candidato_nao_exibe_mencao_a_ia_ou_pre_analise_automatica(): void
    {
        [$interessado, $dependente, $tipoDoc] = $this->criarCenarioCandidato();

        $doc = DocumentoInserido::create([
            'interessado_id' => $interessado->id,
            'interessado_dependente_id' => $dependente->id,
            'tipo_documento_id' => $tipoDoc->id,
            'arquivo_path' => 'doc.pdf',
            'nome_arquivo_original' => 'doc.pdf',
            'status' => SituacaoDocumento::EM_ANALISE,
            'analisado_ia_em' => now(),
            'dados_ia' => [
                'score_confianca' => 92,
                'qualidade' => [
                    'legivel' => true,
                    'nitidez' => 'alta',
                    'enquadramento' => 'completo',
                ],
                'confere_com_solicitado' => true,
                'documento_identificado' => 'Certidão de Nascimento',
                'mensagem_para_familia' => null,
            ],
        ]);

        $response = $this->get(route('candidato.documentos.show', ['token' => $interessado->token_documentos, 'aba' => 'documentos']));

        $response->assertOk();
        // A conferência interna assistida por IA é restrita à Secretaria e nunca exposta à família
        $response->assertDontSeeText('Pré-análise Automática (IA)');
        $response->assertDontSeeText('Pré-análise Automática');
        $response->assertDontSeeText('IA');
        $response->assertSeeText('Em Análise');
    }
}

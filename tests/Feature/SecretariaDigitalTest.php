<?php

namespace Tests\Feature;

use App\Enums\StatusSolicitacaoDocumento;
use App\Enums\TipoTemplateDocumento;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Models\Turma;
use App\Models\User;
use App\Services\DocumentoService;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecretariaDigitalTest extends TestCase
{
    use RefreshDatabase;

    public function test_qrcode_service_gera_svg_valido(): void
    {
        $service = app(QrCodeService::class);
        $svg = $service->renderSvg('https://torre360.com.br');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function test_gerador_preenche_macros_dinamicas_corretamente(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Lucas Santos da Silva',
            'cpf' => '12345678901',
            'data_nascimento' => '2015-05-20',
        ]);

        $periodo = PeriodoLetivo::create([
            'nome' => 'Ano Letivo 2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $turma = Turma::create([
            'nome' => '5º Ano A',
            'periodo_letivo_id' => $periodo->id,
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $template = TemplateDocumento::create([
            'nome' => 'Declaração de Teste',
            'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
            'conteudo' => 'Declaramos que {{ALUNO_NOME}}, CPF {{ALUNO_CPF}}, está matriculado na turma {{TURMA_NOME}} do {{PERIODO_LETIVO}} sob o protocolo {{PROTOCOLO}}.',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);

        $solicitacao = SolicitacaoDocumento::create([
            'protocolo' => 'DOC-2026-999999',
            'codigo_verificacao' => 'TR36-TEST-ABCD-1234',
            'matricula_id' => $matricula->id,
            'template_documento_id' => $template->id,
            'solicitado_por_user_id' => User::factory()->create()->id,
            'status' => StatusSolicitacaoDocumento::Disponivel,
            'data_solicitacao' => now(),
            'data_emissao' => now(),
            'data_validade' => now()->addDays(30),
        ]);

        $service = app(DocumentoService::class);
        $textoPreenchido = $service->preencherMacros($template, $matricula, $solicitacao);

        $this->assertStringContainsString('Lucas Santos da Silva', $textoPreenchido);
        $this->assertStringContainsString('5º Ano A', $textoPreenchido);
        $this->assertStringContainsString('Ano Letivo 2026', $textoPreenchido);
        $this->assertStringContainsString('DOC-2026-999999', $textoPreenchido);
    }

    public function test_documento_service_emite_pdf_com_qr_code(): void
    {
        Storage::fake('local');

        $aluno = Pessoa::create([
            'nome' => 'Mariana Oliveira',
            'cpf' => '98765432100',
        ]);

        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $turma = Turma::create([
            'nome' => '1ª Série EM',
            'periodo_letivo_id' => $periodo->id,
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $template = TemplateDocumento::create([
            'nome' => 'Declaração Simples',
            'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
            'conteudo' => '<p>Certificamos a matrícula de {{ALUNO_NOME}}.</p>',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);

        $solicitacao = SolicitacaoDocumento::create([
            'protocolo' => 'DOC-2026-101010',
            'codigo_verificacao' => 'TR36-QR01-PDF0-TEST',
            'matricula_id' => $matricula->id,
            'template_documento_id' => $template->id,
            'solicitado_por_user_id' => User::factory()->create()->id,
            'status' => StatusSolicitacaoDocumento::Solicitado,
            'data_solicitacao' => now(),
        ]);

        $service = app(DocumentoService::class);
        $path = $service->gerarPdf($solicitacao);

        Storage::disk('local')->assertExists($path);

        $solicitacao->refresh();
        $this->assertEquals(StatusSolicitacaoDocumento::Disponivel, $solicitacao->status);
        $this->assertNotNull($solicitacao->data_emissao);
        $this->assertNotNull($solicitacao->data_validade);
    }

    public function test_validacao_publica_de_documento_com_codigo_valido(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Beatriz Mendes',
            'cpf' => '11122233344',
        ]);

        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $turma = Turma::create([
            'nome' => '3º Ano Fundamental',
            'periodo_letivo_id' => $periodo->id,
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $template = TemplateDocumento::create([
            'nome' => 'Declaração de Matrícula',
            'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
            'conteudo' => '<p>Documento oficial.</p>',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);

        $solicitacao = SolicitacaoDocumento::create([
            'protocolo' => 'DOC-2026-000555',
            'codigo_verificacao' => 'TR36-VALI-DA01-OK01',
            'matricula_id' => $matricula->id,
            'template_documento_id' => $template->id,
            'solicitado_por_user_id' => User::factory()->create()->id,
            'status' => StatusSolicitacaoDocumento::Disponivel,
            'data_solicitacao' => now(),
            'data_emissao' => now(),
            'data_validade' => now()->addDays(30),
        ]);

        $response = $this->get('/validar-documento/'.$solicitacao->codigo_verificacao);

        $response->assertStatus(200);
        $response->assertSee('Documento Autêntico e Válido');
        $response->assertSee('B****** M*****');
        $response->assertDontSee('Beatriz Mendes');
        $response->assertSee('DOC-2026-000555');
        $response->assertSee('TR36-VALI-DA01-OK01');
    }

    public function test_validacao_publica_bloqueia_busca_por_protocolo_sequencial(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Carlos Alberto Silva',
            'cpf' => '55566677788',
        ]);

        $periodo = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $turma = Turma::create([
            'nome' => '1º Ano EM',
            'periodo_letivo_id' => $periodo->id,
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $template = TemplateDocumento::create([
            'nome' => 'Declaração Escolar',
            'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
            'conteudo' => '<p>Documento oficial.</p>',
            'validade_dias' => 30,
            'is_ativo' => true,
        ]);

        $solicitacao = SolicitacaoDocumento::create([
            'protocolo' => 'DOC-2026-000999',
            'codigo_verificacao' => 'TR36-SECR-ET99-TEST',
            'matricula_id' => $matricula->id,
            'template_documento_id' => $template->id,
            'solicitado_por_user_id' => User::factory()->create()->id,
            'status' => StatusSolicitacaoDocumento::Disponivel,
            'data_solicitacao' => now(),
            'data_emissao' => now(),
            'data_validade' => now()->addDays(30),
        ]);

        // A tentativa de raspagem por protocolo sequencial deve falhar
        $response = $this->get('/validar-documento/DOC-2026-000999');

        $response->assertStatus(200);
        $response->assertSee('Documento Não Encontrado');
        $response->assertDontSee('TR36-SECR-ET99-TEST');
        $response->assertDontSee('Carlos Alberto Silva');
    }

    public function test_validacao_publica_com_codigo_invalido(): void
    {
        $response = $this->get('/validar-documento/CODIGO-INEXISTENTE-999');

        $response->assertStatus(200);
        $response->assertSee('Documento Não Encontrado');
    }

    public function test_visualizar_documento_redireciona_usuario_deslogado(): void
    {
        $response = $this->get('/visualizar-documento/documentos_emitidos/DOC-2026-000001.pdf');

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_visualizar_documento_bloqueia_path_traversal(): void
    {
        $user = User::factory()->create(['activated_at' => now()]);

        $response = $this->actingAs($user)->get('/visualizar-documento/../../.env');

        $response->assertStatus(400);
    }

    public function test_visualizar_documento_bloqueia_acesso_a_documento_de_outro_aluno(): void
    {
        Storage::fake('local');

        // Aluno A
        $alunoA = Pessoa::create(['nome' => 'Aluno A', 'cpf' => '11111111111']);
        $userA = User::factory()->create(['activated_at' => now()]);
        $userA->pessoas()->attach($alunoA->id);

        // Aluno B
        $alunoB = Pessoa::create(['nome' => 'Aluno B', 'cpf' => '22222222222']);
        $userB = User::factory()->create(['activated_at' => now()]);
        $userB->pessoas()->attach($alunoB->id);

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => 'Turma B', 'periodo_letivo_id' => $periodo->id]);
        $matriculaB = Matricula::create([
            'pessoa_id' => $alunoB->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $template = TemplateDocumento::create([
            'nome' => 'Declaração B',
            'tipo' => TipoTemplateDocumento::DeclaracaoMatricula,
            'conteudo' => 'Doc B',
            'is_ativo' => true,
        ]);

        $pathB = 'documentos_emitidos/DOC-2026-ALUNOB.pdf';
        Storage::disk('local')->put($pathB, 'CONTEUDO_PDF_ALUNO_B');

        SolicitacaoDocumento::create([
            'protocolo' => 'DOC-2026-ALUNOB',
            'codigo_verificacao' => 'TR36-DOCB-TEST-0001',
            'matricula_id' => $matriculaB->id,
            'template_documento_id' => $template->id,
            'solicitado_por_user_id' => $userB->id,
            'status' => StatusSolicitacaoDocumento::Disponivel,
            'data_solicitacao' => now(),
            'arquivo_path' => $pathB,
        ]);

        // Aluno A tenta acessar o documento de Aluno B
        $response = $this->actingAs($userA)->get('/visualizar-documento/'.$pathB);
        $response->assertStatus(403);

        // Aluno B (proprietário) consegue acessar com sucesso
        $responseB = $this->actingAs($userB)->get('/visualizar-documento/'.$pathB);
        $responseB->assertStatus(200);
    }
}

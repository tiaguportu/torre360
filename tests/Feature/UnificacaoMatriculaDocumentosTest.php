<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoriaExigenciaDocumento;
use App\Enums\SituacaoDocumento;
use App\Enums\SituacaoMatricula;
use App\Filament\Pages\EnrollmentWizard;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\DocumentoInserido;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoDocumento;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnificacaoMatriculaDocumentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
        Http::preventStrayRequests();
    }

    private function criarLead(string $nome = 'Ana Paula'): Interessado
    {
        $status = StatusInteressado::firstOrCreate(['nome' => 'Qualificado'], ['cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        $pessoa = Pessoa::create([
            'nome' => $nome,
            'cpf' => '12345678901',
            'email' => 'ana.paula@example.com',
            'telefone' => '11999998888',
        ]);

        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'token_convite' => 'token-legado-convite-12345',
            'token_convite_expira_em' => now()->addDays(7),
        ]);

        $unidade = Unidade::first() ?? Unidade::create(['nome' => 'Unidade Teste', 'flag_ativo' => true]);
        $curso = Curso::first() ?? Curso::create(['nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF', 'unidade_id' => $unidade->id]);
        $serie = Serie::first() ?? Serie::create(['nome' => '1º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);

        InteressadoDependente::create([
            'interessado_id' => $interessado->id,
            'nome_crianca' => 'Lucas Paula',
            'serie_id' => $serie->id,
            'data_nascimento' => '2018-05-10',
        ]);

        return $interessado;
    }

    public function test_tipo_documento_classificacao_e_sincronismo_retrocompativel(): void
    {
        $docContrato = TipoDocumento::create([
            'nome' => 'RG do Responsável',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);
        $this->assertTrue($docContrato->flag_obrigatorio);
        $this->assertTrue($docContrato->isObrigatorioContrato());
        $this->assertTrue($docContrato->isVisivelPortalFamilia());

        $docHistorico = TipoDocumento::create([
            'nome' => 'Histórico Anterior',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_HISTORICO,
        ]);
        $this->assertTrue($docHistorico->flag_obrigatorio);
        $this->assertTrue($docHistorico->isObrigatorioHistorico());

        $docOpcional = TipoDocumento::create([
            'nome' => 'Carteira de Vacinação Complementar',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OPCIONAL,
        ]);
        $this->assertFalse($docOpcional->flag_obrigatorio);
        $this->assertTrue($docOpcional->isOpcional());
        $this->assertTrue($docOpcional->isVisivelPortalFamilia());

        $docInterno = TipoDocumento::create([
            'nome' => 'Ficha Interna Arquivada',
            'categoria_exigencia' => CategoriaExigenciaDocumento::INTERNO,
        ]);
        $this->assertFalse($docInterno->flag_obrigatorio);
        $this->assertTrue($docInterno->isInterno());
        $this->assertFalse($docInterno->isVisivelPortalFamilia());

        // Teste de scopes
        $this->assertTrue(TipoDocumento::visivelPortalFamilia()->pluck('id')->contains($docOpcional->id));
        $this->assertFalse(TipoDocumento::visivelPortalFamilia()->pluck('id')->contains($docInterno->id));
        $this->assertTrue(TipoDocumento::obrigatoriosParaContrato()->pluck('id')->contains($docContrato->id));
    }

    public function test_redirecionamento_suave_de_link_de_convite_legado_para_portal_unificado(): void
    {
        $lead = $this->criarLead();

        $response = $this->get('/quero-matricular/convite/'.$lead->token_convite);

        $response->assertRedirect(route('candidato.documentos.show', [
            'token' => $lead->refresh()->token_documentos,
        ]));
    }

    public function test_portal_unificado_renderiza_sem_erro_quando_pessoa_possui_data_nascimento_no_banco(): void
    {
        $lead = $this->criarLead();
        $lead->pessoa->update([
            'data_nascimento' => '1985-04-12',
        ]);
        $token = $lead->obterOuCriarTokenDocumentos();

        $response = $this->get(route('candidato.documentos.show', ['token' => $token, 'aba' => 'dados']));

        $response->assertOk();
        $response->assertSee('value="12/04/1985"', false);
    }

    public function test_portal_unificado_salva_dados_cadastrais_com_mascaras_e_formato_brasileiro(): void
    {
        $lead = $this->criarLead();
        $token = $lead->obterOuCriarTokenDocumentos();
        $vinculo = TipoVinculo::firstOrCreate(['nome' => 'Mãe']);
        $dependente = $lead->dependentes->first();

        // 1. Verifica se a view renderiza os atributos data-mask e scripts de CEP
        $viewResponse = $this->get(route('candidato.documentos.show', ['token' => $token, 'aba' => 'dados']));
        $viewResponse->assertOk();
        $viewResponse->assertSee('data-mask="cpf"', false);
        $viewResponse->assertSee('data-mask="telefone"', false);
        $viewResponse->assertSee('data-mask="data"', false);
        $viewResponse->assertSee('data-mask="cep"', false);
        $viewResponse->assertSee('viacep.com.br/ws/', false);

        // 2. Envia os dados com pontuação de máscara e formato DD/MM/AAAA
        $payload = [
            'responsavel' => [
                'nome' => 'Ana Paula da Silva',
                'cpf' => '123.456.789-09',
                'data_nascimento' => '12/04/1985',
                'telefone' => '(11) 98888-7777',
                'email' => 'ana.silva@example.com',
                'tipo_vinculo_id' => $vinculo->id,
                'is_financeiro' => 1,
                'cep' => '01310-100',
                'logradouro' => 'Avenida Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'cidade_ibge' => '3550308',
            ],
            'dependentes' => [
                [
                    'id' => $dependente->id,
                    'serie_id' => $dependente->serie_id,
                    'turno_preferencia' => 'Manhã',
                    'data_nascimento' => '10/05/2018',
                    'cpf' => '987.654.321-00',
                    'sexo' => 'masculino',
                ],
            ],
            'lgpd_aceite' => 1,
        ];

        $response = $this->post(route('candidato.documentos.dados', ['token' => $token]), $payload);

        $response->assertRedirect(route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']));
        $response->assertSessionHas('sucesso');

        $lead->refresh();
        $this->assertNotNull($lead->dados_pre_matricula);
        $this->assertSame('Ana Paula da Silva', $lead->dados_pre_matricula['responsaveis'][0]['nome']);
        $this->assertSame('12345678909', $lead->dados_pre_matricula['responsaveis'][0]['cpf']);
        $this->assertSame('1985-04-12', $lead->dados_pre_matricula['responsaveis'][0]['data_nascimento']);
        $this->assertSame('São Paulo', $lead->dados_pre_matricula['responsaveis'][0]['cidade_nome']);
        $this->assertSame('SP', $lead->dados_pre_matricula['responsaveis'][0]['uf']);
    }

    public function test_portal_unificado_exibe_secoes_e_oculta_documentos_internos(): void
    {
        $lead = $this->criarLead();
        $token = $lead->obterOuCriarTokenDocumentos();

        $docContrato = TipoDocumento::create([
            'nome' => 'Certidão de Nascimento do Aluno',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);

        $docHistorico = TipoDocumento::create([
            'nome' => 'Histórico Escolar da Escola Anterior',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_HISTORICO,
        ]);

        $docOpcional = TipoDocumento::create([
            'nome' => 'Carteira do Plano de Saúde',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OPCIONAL,
        ]);

        $docInterno = TipoDocumento::create([
            'nome' => 'Dossiê Interno Confidencial',
            'categoria_exigencia' => CategoriaExigenciaDocumento::INTERNO,
        ]);

        $response = $this->get(route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']));

        $response->assertOk();
        $response->assertSee('Certidão de Nascimento do Aluno');
        $response->assertSee('Histórico Escolar da Escola Anterior');
        $response->assertSee('Carteira do Plano de Saúde');
        $response->assertDontSee('Dossiê Interno Confidencial');
    }

    public function test_enrollment_wizard_cria_contrato_e_ativa_matricula_quando_docs_de_contrato_presentes(): void
    {
        $lead = $this->criarLead();
        $unidade = Unidade::first() ?? Unidade::create(['nome' => 'Unidade Central', 'flag_ativo' => true]);
        $periodo = PeriodoLetivo::first() ?? PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);
        $curso = Curso::first() ?? Curso::create(['nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF', 'unidade_id' => $unidade->id]);
        $serie = Serie::first() ?? Serie::create(['nome' => '1º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $turma = Turma::create([
            'nome' => '1º Ano A',
            'serie_id' => $serie->id,
            'periodo_letivo_id' => $periodo->id,
            'vagas_maximas' => 30,
        ]);
        $vinculo = TipoVinculo::firstOrCreate(['nome' => 'Pai']);

        // Cria tipo de documento obrigatório para contrato
        $tipoDocContrato = TipoDocumento::create([
            'nome' => 'RG do Aluno Obrigatório',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);

        // Simula que o lead já enviou esse documento no portal
        DocumentoInserido::create([
            'interessado_id' => $lead->id,
            'tipo_documento_id' => $tipoDocContrato->id,
            'status' => SituacaoDocumento::VERIFICADO,
            'arquivo_path' => 'docs/teste.pdf',
            'nome_arquivo_original' => 'rg.pdf',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'View:EnrollmentWizard', 'guard_name' => 'web']));

        Livewire::actingAs($user)
            ->test(EnrollmentWizard::class, ['interessado' => $lead->id])
            ->set('data.alunos', [
                [
                    'nome' => 'Lucas Paula',
                    'cpf' => '98765432101',
                    'data_nascimento' => '2018-05-10',
                ],
            ])
            ->set('data.responsaveis', [
                [
                    'nome' => 'Ana Paula',
                    'cpf' => '12345678901',
                    'telefone' => '11999998888',
                    'tipo_vinculo_id' => $vinculo->id,
                    'is_financeiro' => true,
                    'percentual' => 100,
                ],
            ])
            ->set('data.unidade_id', $unidade->id)
            ->set('data.periodo_letivo_id', $periodo->id)
            ->set('data.curso_id', $curso->id)
            ->set('data.turma_id', $turma->id)
            ->set('data.situacao', SituacaoMatricula::ATIVA->value)
            ->call('save')
            ->assertHasNoErrors();

        $matricula = Matricula::latest('id')->first();
        $this->assertNotNull($matricula);
        $this->assertSame(SituacaoMatricula::ATIVA, $matricula->situacao);

        // Verifica que o Contrato foi criado
        $contrato = Contrato::where('matricula_id', $matricula->id)->first();
        $this->assertNotNull($contrato, 'O Contrato deveria ter sido criado pois os docs de contrato estavam presentes.');
    }

    public function test_enrollment_wizard_cria_matricula_pendente_sem_contrato_quando_falta_doc_obrigatorio(): void
    {
        $lead = $this->criarLead();
        $unidade = Unidade::first() ?? Unidade::create(['nome' => 'Unidade Central', 'flag_ativo' => true]);
        $periodo = PeriodoLetivo::first() ?? PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);
        $curso = Curso::first() ?? Curso::create(['nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF', 'unidade_id' => $unidade->id]);
        $serie = Serie::first() ?? Serie::create(['nome' => '1º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $turma = Turma::create([
            'nome' => '1º Ano B',
            'serie_id' => $serie->id,
            'periodo_letivo_id' => $periodo->id,
            'vagas_maximas' => 30,
        ]);
        $vinculo = TipoVinculo::firstOrCreate(['nome' => 'Mãe']);

        // Cria tipo de documento obrigatório para contrato mas NENHUM documento foi enviado
        TipoDocumento::create([
            'nome' => 'Comprovante de Renda para Contrato',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'View:EnrollmentWizard', 'guard_name' => 'web']));

        Livewire::actingAs($user)
            ->test(EnrollmentWizard::class, ['interessado' => $lead->id])
            ->set('data.alunos', [
                [
                    'nome' => 'Pedro Lucas',
                    'cpf' => '98765432102',
                    'data_nascimento' => '2018-05-10',
                ],
            ])
            ->set('data.responsaveis', [
                [
                    'nome' => 'Julia Souza',
                    'cpf' => '12345678902',
                    'telefone' => '11999998888',
                    'tipo_vinculo_id' => $vinculo->id,
                    'is_financeiro' => true,
                    'percentual' => 100,
                ],
            ])
            ->set('data.unidade_id', $unidade->id)
            ->set('data.periodo_letivo_id', $periodo->id)
            ->set('data.curso_id', $curso->id)
            ->set('data.turma_id', $turma->id)
            ->set('data.situacao', SituacaoMatricula::ATIVA->value) // Tenta salvar como Ativa
            ->call('save')
            ->assertHasNoErrors();

        $matricula = Matricula::latest('id')->first();
        $this->assertNotNull($matricula);

        // Regra de Negócio: forçada para PENDENTE e NENHUM Contrato gerado
        $this->assertSame(SituacaoMatricula::PENDENTE, $matricula->situacao);

        $contrato = Contrato::where('matricula_id', $matricula->id)->first();
        $this->assertNull($contrato, 'O Contrato NÃO deveria ser gerado quando faltam documentos obrigatórios de contrato.');
    }

    public function test_documentos_obrigatorios_consideram_cursos_vinculados_no_model_interessado(): void
    {
        $unidade = Unidade::first() ?? Unidade::create(['nome' => 'Unidade Teste', 'flag_ativo' => true]);
        $cursoA = Curso::create(['nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF', 'unidade_id' => $unidade->id]);
        $cursoB = Curso::create(['nome_externo' => 'Ensino Médio', 'nome_interno' => 'EM', 'unidade_id' => $unidade->id]);

        $serieA = Serie::create(['nome' => '5º Ano', 'curso_id' => $cursoA->id, 'sistema_avaliacao' => 'Nota']);

        // Documento exclusivo do Curso A
        $docCursoA = TipoDocumento::create([
            'nome' => 'RG do Aluno (Fundamental)',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);
        $docCursoA->cursos()->attach($cursoA->id);

        // Documento exclusivo do Curso B
        $docCursoB = TipoDocumento::create([
            'nome' => 'Certificado de Conclusão (Médio)',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);
        $docCursoB->cursos()->attach($cursoB->id);

        // Documento Geral (sem curso vinculado)
        $docGeral = TipoDocumento::create([
            'nome' => 'Comprovante de Endereço Geral',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);

        $lead = $this->criarLead();
        $lead->dependentes()->delete();
        InteressadoDependente::create([
            'interessado_id' => $lead->id,
            'nome_crianca' => 'Ana Beatriz',
            'serie_id' => $serieA->id,
        ]);

        $docsRequeridos = $lead->documentosRequeridos();
        $this->assertTrue($docsRequeridos->contains('id', $docCursoA->id), 'Deveria exigir documento do Curso A');
        $this->assertTrue($docsRequeridos->contains('id', $docGeral->id), 'Deveria exigir documento Geral');
        $this->assertFalse($docsRequeridos->contains('id', $docCursoB->id), 'NÃO deveria exigir documento do Curso B');

        // Envia apenas os docs do Curso A e Geral
        DocumentoInserido::create([
            'interessado_id' => $lead->id,
            'tipo_documento_id' => $docCursoA->id,
            'status' => SituacaoDocumento::VERIFICADO,
            'arquivo_path' => 'docs/a.pdf',
        ]);
        DocumentoInserido::create([
            'interessado_id' => $lead->id,
            'tipo_documento_id' => $docGeral->id,
            'status' => SituacaoDocumento::VERIFICADO,
            'arquivo_path' => 'docs/geral.pdf',
        ]);

        // Deve considerar todos os documentos do contrato entregues, sem exigir o documento do Curso B!
        $this->assertTrue($lead->todosDocsContratoEntregues());
    }

    public function test_enrollment_wizard_ignora_doc_obrigatorio_de_outro_curso_ao_liberar_contrato(): void
    {
        $unidade = Unidade::first() ?? Unidade::create(['nome' => 'Unidade Teste', 'flag_ativo' => true]);
        $periodo = PeriodoLetivo::first() ?? PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $cursoFundamental = Curso::create(['nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF', 'unidade_id' => $unidade->id]);
        $cursoMedio = Curso::create(['nome_externo' => 'Ensino Médio', 'nome_interno' => 'EM', 'unidade_id' => $unidade->id]);

        $serieFundamental = Serie::create(['nome' => '6º Ano', 'curso_id' => $cursoFundamental->id, 'sistema_avaliacao' => 'Nota']);
        $turma = Turma::create([
            'nome' => '6º Ano A',
            'serie_id' => $serieFundamental->id,
            'periodo_letivo_id' => $periodo->id,
            'vagas_maximas' => 30,
        ]);
        $vinculo = TipoVinculo::firstOrCreate(['nome' => 'Pai']);

        // Cria documento obrigatório EXCLUSIVO para o Ensino Médio
        $docExclusivoMedio = TipoDocumento::create([
            'nome' => 'Histórico do Fundamental (Exigido apenas no Médio)',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);
        $docExclusivoMedio->cursos()->attach($cursoMedio->id);

        $lead = $this->criarLead();

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'View:EnrollmentWizard', 'guard_name' => 'web']));

        // Matrícula no Ensino Fundamental: NÃO deve ser bloqueada pelo documento exclusivo do Médio
        Livewire::actingAs($user)
            ->test(EnrollmentWizard::class, ['interessado' => $lead->id])
            ->set('data.alunos', [
                [
                    'nome' => 'Aluno Fundamental',
                    'cpf' => '98765432103',
                    'data_nascimento' => '2015-05-10',
                ],
            ])
            ->set('data.responsaveis', [
                [
                    'nome' => 'Responsável Fundamental',
                    'cpf' => '12345678903',
                    'telefone' => '11999998888',
                    'tipo_vinculo_id' => $vinculo->id,
                    'is_financeiro' => true,
                    'percentual' => 100,
                ],
            ])
            ->set('data.unidade_id', $unidade->id)
            ->set('data.periodo_letivo_id', $periodo->id)
            ->set('data.curso_id', $cursoFundamental->id)
            ->set('data.turma_id', $turma->id)
            ->set('data.situacao', SituacaoMatricula::ATIVA->value)
            ->call('save')
            ->assertHasNoErrors();

        $matricula = Matricula::latest('id')->first();
        $this->assertNotNull($matricula);
        $this->assertSame(SituacaoMatricula::ATIVA, $matricula->situacao);

        $contrato = Contrato::where('matricula_id', $matricula->id)->first();
        $this->assertNotNull($contrato, 'O contrato deveria ter sido gerado pois o documento faltante era de outro curso.');
    }

    public function test_matricula_get_missing_mandatory_documents_considera_cursos_vinculados(): void
    {
        $unidade = Unidade::first() ?? Unidade::create(['nome' => 'Unidade Teste', 'flag_ativo' => true]);
        $periodo = PeriodoLetivo::first() ?? PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);
        $cursoA = Curso::create(['nome_externo' => 'Curso A', 'nome_interno' => 'CA', 'unidade_id' => $unidade->id]);
        $cursoB = Curso::create(['nome_externo' => 'Curso B', 'nome_interno' => 'CB', 'unidade_id' => $unidade->id]);
        $serieA = Serie::create(['nome' => 'Série A', 'curso_id' => $cursoA->id, 'sistema_avaliacao' => 'Nota']);
        $turmaA = Turma::create([
            'nome' => 'Turma A',
            'serie_id' => $serieA->id,
            'periodo_letivo_id' => $periodo->id,
        ]);

        $docA = TipoDocumento::create([
            'nome' => 'Doc Obrigatório Curso A',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);
        $docA->cursos()->attach($cursoA->id);

        $docB = TipoDocumento::create([
            'nome' => 'Doc Obrigatório Curso B',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);
        $docB->cursos()->attach($cursoB->id);

        $aluno = Pessoa::create([
            'nome' => 'Aluno Matriculado Teste',
            'cpf' => '11122233344',
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turmaA->id,
            'serie_id' => $serieA->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $faltantes = $matricula->getMissingMandatoryDocuments();
        $this->assertTrue($faltantes->contains('id', $docA->id), 'Deveria acusar pendência do documento do Curso A');
        $this->assertFalse($faltantes->contains('id', $docB->id), 'NÃO deveria acusar pendência do documento do Curso B');
    }

    public function test_matricula_get_missing_contract_documents_e_bloqueio_levam_em_conta_cursos_vinculados(): void
    {
        $unidade = Unidade::first() ?? Unidade::create(['nome' => 'Unidade Teste 2', 'flag_ativo' => true]);
        $periodo = PeriodoLetivo::first() ?? PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);
        $cursoA = Curso::create(['nome_externo' => 'Curso A Bloqueio', 'nome_interno' => 'CAB', 'unidade_id' => $unidade->id]);
        $cursoB = Curso::create(['nome_externo' => 'Curso B Bloqueio', 'nome_interno' => 'CBB', 'unidade_id' => $unidade->id]);
        $serieA = Serie::create(['nome' => 'Série A Bloqueio', 'curso_id' => $cursoA->id, 'sistema_avaliacao' => 'Nota']);
        $turmaA = Turma::create([
            'nome' => 'Turma A Bloqueio',
            'serie_id' => $serieA->id,
            'periodo_letivo_id' => $periodo->id,
        ]);

        $docContratoA = TipoDocumento::create([
            'nome' => 'Doc Contrato Curso A',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);
        $docContratoA->cursos()->attach($cursoA->id);

        $docContratoB = TipoDocumento::create([
            'nome' => 'Doc Contrato Curso B',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);
        $docContratoB->cursos()->attach($cursoB->id);

        $aluno = Pessoa::create([
            'nome' => 'Aluno Bloqueio Teste',
            'cpf' => '55566677788',
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turmaA->id,
            'serie_id' => $serieA->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => SituacaoMatricula::PENDENTE,
        ]);

        // Inicialmente, falta o doc do Curso A, logo deve acusar pendência e bloquear contrato
        $this->assertTrue($matricula->hasMissingContractDocuments());
        $docsFaltantesContrato = $matricula->getMissingContractDocuments();
        $this->assertTrue($docsFaltantesContrato->contains('id', $docContratoA->id));
        $this->assertFalse($docsFaltantesContrato->contains('id', $docContratoB->id));

        // Envia o documento do Curso A
        DocumentoInserido::create([
            'matricula_id' => $matricula->id,
            'tipo_documento_id' => $docContratoA->id,
            'status' => SituacaoDocumento::VERIFICADO,
            'arquivo_path' => 'docs/a.pdf',
            'nome_arquivo_original' => 'a.pdf',
        ]);

        // Agora não falta mais nenhum documento de contrato para o Curso A, liberando contrato mesmo com Curso B pendente
        $matricula->refresh();
        $this->assertFalse($matricula->hasMissingContractDocuments());
        $this->assertEmpty($matricula->getMissingContractDocuments());
    }

    public function test_portal_admissao_nomes_abas_e_indicadores_de_pendencia(): void
    {
        $interessado = $this->criarLead('Carla Mendes');
        $token = $interessado->obterOuCriarTokenDocumentos();

        $curso = Curso::first();

        // Cria 1 documento de contrato e 1 de histórico para o curso
        $docContrato = TipoDocumento::create([
            'nome' => 'Comprovante de Renda Contratual',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO,
        ]);
        $docContrato->cursos()->attach($curso->id);

        $docHistorico = TipoDocumento::create([
            'nome' => 'Histórico Anterior Aluno',
            'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_HISTORICO,
        ]);
        $docHistorico->cursos()->attach($curso->id);

        // 1. Acesso inicial: dados não confirmados e documentos não enviados
        $response = $this->get(route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']));
        // Verifica title, favicon e logo oficial Torre360 no header (sem emoji de escola)
        $response->assertSee('<title>Portal de Pré-Admissão | Torre360</title>', false);
        $response->assertSee('logo-adaptative.svg');
        $response->assertSee('rel="icon"', false);
        $response->assertDontSee('🏫');

        // Verifica os novos nomes das abas e ausência da aba contrato
        $response->assertSee('1. Cadastro');
        $response->assertSee('2. Documentos');
        $response->assertDontSee('3. Contrato');

        // Acessar com ?aba=status faz fallback gracioso para a aba de documentos
        $responseStatus = $this->get(route('candidato.documentos.show', ['token' => $token, 'aba' => 'status']));
        $responseStatus->assertOk();
        $responseStatus->assertSee('Documentos Obrigatórios');

        // Verifica que seções de histórico e opcionais estão colapsadas em tags <details>
        $response->assertSee('<details class="group bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs transition-all">', false);
        $response->assertSee('Documentos para o Histórico Escolar');
        $response->assertSee('Documentação acadêmica');
        $response->assertSee('Documentos Opcionais / Complementares');
        $response->assertSee('Envio facultativo');

        // Na aba "2. Documentos", a contagem de pendências e o progresso referem-se aos documentos obrigatórios para contrato
        $statusAbas = $interessado->resumoPendenciasPortal();
        $this->assertTrue($statusAbas['dados']['tem_pendencia']);
        $this->assertTrue($statusAbas['documentos']['tem_pendencia']);
        $this->assertSame(1, $statusAbas['documentos']['quantidade']);

        // Progresso considera estritamente os obrigatórios para contrato (1 e não 2)
        $progressoInicial = $interessado->progressoDocumentos();
        $this->assertSame(1, $progressoInicial['total']);
        $this->assertSame(0, $progressoInicial['enviados']);
        $this->assertSame(0, $progressoInicial['percentual']);
        $response->assertSee('0 de 1 enviados');
        $response->assertSee('style="width: 0%"', false);
        $response->assertSee('(Documentos pendentes ⏳)');

        // Badge exibe apenas o número da pendência
        $response->assertSee('title="1 documento(s) obrigatório(s) pendente(s)"', false);

        // 2. Envia o documento obrigatório para contrato
        DocumentoInserido::create([
            'interessado_id' => $interessado->id,
            'tipo_documento_id' => $docContrato->id,
            'status' => SituacaoDocumento::VERIFICADO,
            'arquivo_path' => 'docs/renda.pdf',
            'nome_arquivo_original' => 'renda.pdf',
        ]);

        // Progresso atualizado: 1 de 1 enviado (100%), mesmo com documento de histórico ainda não entregue
        $progressoFinal = $interessado->progressoDocumentos();
        $this->assertSame(1, $progressoFinal['total']);
        $this->assertSame(1, $progressoFinal['enviados']);
        $this->assertSame(100, $progressoFinal['percentual']);

        // Simula preenchimento dos dados cadastrais
        $interessado->update([
            'dados_pre_matricula' => [
                'responsaveis' => [
                    [
                        'nome' => 'Carla Mendes',
                        'cpf' => '12345678901',
                        'tipo_vinculo_id' => 1,
                        'is_financeiro' => true,
                    ],
                ],
                'alunos' => [
                    $interessado->dependentes->first()->id => [
                        'serie_id' => $interessado->dependentes->first()->serie_id,
                    ],
                ],
            ],
        ]);

        $interessado->refresh();
        $statusAtualizado = $interessado->resumoPendenciasPortal();

        // Como o doc de contrato foi enviado e dados preenchidos:
        $this->assertFalse($statusAtualizado['dados']['tem_pendencia']);
        $this->assertFalse($statusAtualizado['documentos']['tem_pendencia']);
        $this->assertSame(0, $statusAtualizado['documentos']['quantidade']);
        $this->assertFalse($statusAtualizado['contrato']['tem_pendencia']);
        $this->assertSame(0, $statusAtualizado['contrato']['quantidade']);

        $response2 = $this->get(route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']));
        $response2->assertOk();
        $response2->assertSee('✓');
        $response2->assertSee('1 de 1 enviados');
        $response2->assertSee('style="width: 100%"', false);
        $response2->assertSee('(Documentos OK ✅)');

        // Banner de Conclusão e Próximos Passos
        $response2->assertSee('Documentação Recebida com Sucesso! 🎉');
        $response2->assertSee('Próximos Passos:');
        $response2->assertSee('Aguardando Análise da Secretaria');
        $response2->assertDontSee('Pré-análise Automática (IA)');
        $response2->assertDontSee('IA estão processando');
    }
}

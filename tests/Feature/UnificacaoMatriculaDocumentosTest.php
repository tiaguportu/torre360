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

    public function test_portal_unificado_salva_dados_cadastrais_da_familia(): void
    {
        $lead = $this->criarLead();
        $token = $lead->obterOuCriarTokenDocumentos();
        $vinculo = TipoVinculo::firstOrCreate(['nome' => 'Mãe']);
        $dependente = $lead->dependentes->first();

        $payload = [
            'responsavel' => [
                'nome' => 'Ana Paula da Silva',
                'cpf' => '12345678909',
                'data_nascimento' => '1985-04-12',
                'telefone' => '11988887777',
                'email' => 'ana.silva@example.com',
                'tipo_vinculo_id' => $vinculo->id,
                'is_financeiro' => 1,
                'cep' => '01310-100',
                'logradouro' => 'Avenida Paulista',
                'numero' => '1000',
                'bairro' => 'Bela Vista',
            ],
            'dependentes' => [
                [
                    'id' => $dependente->id,
                    'serie_id' => $dependente->serie_id,
                    'turno_preferencia' => 'Manhã',
                    'data_nascimento' => '2018-05-10',
                    'cpf' => '98765432100',
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
}

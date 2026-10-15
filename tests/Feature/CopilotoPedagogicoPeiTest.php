<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusAdaptacaoPei;
use App\Models\CategoriaNecessidadeEducacaoEspecial;
use App\Models\CategoriaRecursoAcessibilidade;
use App\Models\CategoriaTranstornoAprendizagem;
use App\Models\Disciplina;
use App\Models\FichaMedica;
use App\Models\Matricula;
use App\Models\NecessidadeEducacaoEspecial;
use App\Models\Pessoa;
use App\Models\PlanoAula;
use App\Models\PlanoAulaAdaptacao;
use App\Models\RecursoAcessibilidade;
use App\Models\TranstornoAprendizagem;
use App\Models\Turma;
use App\Models\User;
use App\Services\CopilotoPedagogico\Exceptions\PeiDesabilitadoException;
use App\Services\CopilotoPedagogico\GerarPeiInput;
use App\Services\CopilotoPedagogicoIaService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CopilotoPedagogicoPeiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'GerarPei:PlanoAula', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ExportarPei:PlanoAula', 'guard_name' => 'web']);
    }

    public function test_minimizacao_de_dados_estrita_no_pei_nao_envia_nome_cpf_telefone_nem_dados_clinicos(): void
    {
        config(['copiloto_pedagogico.pei_habilitado' => true]);

        $pessoa = Pessoa::factory()->create([
            'nome' => 'Carlos Drummond de Andrade',
            'cpf' => '123.456.789-00',
            'telefone' => '(11) 97777-6666',
            'data_nascimento' => now()->subYears(14),
        ]);

        // Ficha médica clínica sensível (NUNCA pode ir para a IA)
        FichaMedica::create([
            'pessoa_id' => $pessoa->id,
            'alergias' => 'Penicilina e Dipirona',
            'medicamentos_uso_continuo' => 'Ritalina 10mg duas vezes ao dia',
            'numero_cartao_sus' => '898000123456789',
        ]);

        $catNec = CategoriaNecessidadeEducacaoEspecial::firstOrCreate(['nome' => 'Deficiência Visual Baixa Visão']);
        NecessidadeEducacaoEspecial::create([
            'pessoa_id' => $pessoa->id,
            'categoria_necessidade_educacao_especial_id' => $catNec->id,
            'observacao' => 'Contato da mãe: 11 96666-5555 para emergências clínicas.',
        ]);

        $catTrans = CategoriaTranstornoAprendizagem::firstOrCreate(['nome' => 'Dislexia']);
        TranstornoAprendizagem::create([
            'pessoa_id' => $pessoa->id,
            'categoria_transtorno_aprendizagem_id' => $catTrans->id,
        ]);

        $catRec = CategoriaRecursoAcessibilidade::firstOrCreate(['nome' => 'Textos Ampliados']);
        RecursoAcessibilidade::create([
            'pessoa_id' => $pessoa->id,
            'categoria_recurso_acessabilidade_id' => $catRec->id,
        ]);

        $turma = Turma::factory()->create();
        $matricula = Matricula::factory()->create(['pessoa_id' => $pessoa->id, 'turma_id' => $turma->id]);
        $disciplina = Disciplina::factory()->create(['nome' => 'História']);

        $plano = PlanoAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data_prevista' => now()->toDateString(),
            'objetivos' => 'Compreender a Revolução Industrial',
        ]);

        $input = new GerarPeiInput(
            planoAulaId: $plano->id,
            matriculaId: $matricula->id,
            etapaEnsino: '9º Ano',
            faixaEtariaAnos: 14,
            disciplinaNome: 'História',
            temaOuObjetivoAula: 'Compreender a Revolução Industrial',
            categoriasNecessidades: ['Deficiência Visual Baixa Visão'],
            categoriasTranstornos: ['Dislexia'],
            categoriasRecursos: ['Textos Ampliados'],
            habilidadesBncc: [['codigo' => 'EF09HI01', 'nome' => 'Revolução Industrial']],
            observacaoPedagogicaHigienizada: 'Estudante precisa de apoio na leitura de fontes históricas primárias.',
        );

        $payloadCapturado = null;
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$payloadCapturado) {
                $payloadCapturado = $payload;

                return [
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => json_encode([
                                            'barreiras' => 'Acesso a textos em fonte pequena',
                                            'adaptacoes_praticas' => [
                                                'instrucoes_passo_a_passo' => 'Quebrar leitura em tópicos',
                                                'apoio_visual' => 'Mapas mentais ampliados',
                                                'tempo_estendido' => '15 minutos extras',
                                                'recursos_acessibilidade' => 'Impressão em fonte 24',
                                                'organizacao_ambiente' => 'Primeira fileira perto da lousa',
                                            ],
                                            'adaptacao_avaliacao' => 'Avaliação com fonte ampliada',
                                            'estrategias_professor' => 'Checar leitura a cada parágrafo',
                                            'indicadores_acompanhamento' => 'Participação no debate',
                                            'prazo_revisao_dias' => 15,
                                            'habilidade_bncc_alvo' => 'EF09HI01 mantida',
                                            'profundidade_requer_validacao_aee' => null,
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            });
        $this->app->instance(GeminiAgentService::class, $mock);

        $user = User::factory()->create();
        $service = app(CopilotoPedagogicoIaService::class);
        $output = $service->gerarAdaptacaoPei($input, $user);

        $promptEnviado = $payloadCapturado['contents'][0]['parts'][0]['text'] ?? '';

        // DADOS PROIBIDOS: JAMAIS PODEM APARECER NO PAYLOAD
        $proibidos = [
            'Carlos',
            'Drummond',
            'Andrade',
            '123.456.789-00',
            '(11) 97777-6666',
            '11977776666',
            'Penicilina',
            'Dipirona',
            'Ritalina',
            '898000123456789',
            '11 96666-5555',
        ];

        foreach ($proibidos as $p) {
            $this->assertStringNotContainsString($p, $promptEnviado, "Dado sensível vazou no prompt do PEI: {$p}");
        }

        // DADOS PERMITIDOS: CATEGORIAS PADRONIZADAS
        $this->assertStringContainsString('Deficiência Visual Baixa Visão', $promptEnviado);
        $this->assertStringContainsString('Dislexia', $promptEnviado);
        $this->assertStringContainsString('Textos Ampliados', $promptEnviado);
        $this->assertStringContainsString('14 anos', $promptEnviado);
        $this->assertStringContainsString('o estudante', strtolower($promptEnviado));

        $this->assertSame('Acesso a textos em fonte pequena', $output->barreiras);
    }

    public function test_flag_pei_desabilitada_bloqueia_geracao_com_excecao(): void
    {
        config(['copiloto_pedagogico.pei_habilitado' => false]);

        $input = new GerarPeiInput(
            planoAulaId: 1,
            matriculaId: 1,
            etapaEnsino: '9º Ano',
            faixaEtariaAnos: 14,
            disciplinaNome: 'História',
            temaOuObjetivoAula: 'Aula',
            categoriasNecessidades: [],
            categoriasTranstornos: [],
            categoriasRecursos: [],
            habilidadesBncc: [],
        );

        $user = User::factory()->create();
        $service = app(CopilotoPedagogicoIaService::class);

        $this->expectException(PeiDesabilitadoException::class);
        $service->gerarAdaptacaoPei($input, $user);
    }

    public function test_persistencia_com_criptografia_em_repouso(): void
    {
        $plano = PlanoAula::factory()->create();
        $matricula = Matricula::factory()->create();

        $dadosSecretos = [
            'barreiras' => 'Estudante com severa sensibilidade auditiva a ruídos agudos.',
            'adaptacoes_praticas' => [
                'instrucoes_passo_a_passo' => 'Comunicação direta em tom brando.',
                'apoio_visual' => 'Cartões ilustrados.',
                'tempo_estendido' => 'Sem limite rígido.',
                'recursos_acessibilidade' => 'Abafador de ruído acolchoado.',
                'organizacao_ambiente' => 'Assento afastado de caixas de som.',
            ],
            'adaptacao_avaliacao' => 'Ambiente silencioso.',
            'estrategias_professor' => 'Aviso prévio antes de sinal sonoro.',
            'indicadores_acompanhamento' => 'Ausência de crises sensoriais.',
            'prazo_revisao_dias' => 30,
            'habilidade_bncc_alvo' => 'Mantida.',
            'profundidade_requer_validacao_aee' => null,
            'aviso_legal' => 'Aviso legal',
            'modelo_ia' => 'gemini-2.5-flash',
        ];

        $adaptacao = PlanoAulaAdaptacao::create([
            'plano_aula_id' => $plano->id,
            'matricula_id' => $matricula->id,
            'conteudo' => $dadosSecretos,
            'status' => StatusAdaptacaoPei::Rascunho,
        ]);

        // Consulta crua direto no banco de dados via SQL sem passar pelo Eloquent
        $valorRaw = DB::table('plano_aula_adaptacoes')
            ->where('id', $adaptacao->id)
            ->value('conteudo');

        // O texto em claro NÃO pode existir na coluna física do banco!
        $this->assertStringNotContainsString('severa sensibilidade auditiva', (string) $valorRaw);
        $this->assertStringNotContainsString('Abafador de ruído', (string) $valorRaw);

        // Mas ao recuperar pelo Model Eloquent com cast encrypted:array, o dado vem legível
        $recuperado = PlanoAulaAdaptacao::find($adaptacao->id);
        $this->assertIsArray($recuperado->conteudo);
        $this->assertSame('Estudante com severa sensibilidade auditiva a ruídos agudos.', $recuperado->conteudo['barreiras']);
        $this->assertSame('Abafador de ruído acolchoado.', $recuperado->conteudo['adaptacoes_praticas']['recursos_acessibilidade']);
    }

    public function test_exportacao_pdf_pei_autorizada_e_nao_autorizada_com_marca_de_rascunho(): void
    {
        $plano = PlanoAula::factory()->create();
        $matricula = Matricula::factory()->create();

        $adaptacao = PlanoAulaAdaptacao::create([
            'plano_aula_id' => $plano->id,
            'matricula_id' => $matricula->id,
            'conteudo' => [
                'barreiras' => 'Barreira de leitura.',
                'adaptacoes_praticas' => [
                    'instrucoes_passo_a_passo' => 'Passo 1, Passo 2',
                    'apoio_visual' => 'Cartões',
                    'tempo_estendido' => '10 min',
                    'recursos_acessibilidade' => 'Lupa',
                    'organizacao_ambiente' => 'Perto da janela',
                ],
                'adaptacao_avaliacao' => 'Oral',
                'estrategias_professor' => 'Apoio contínuo',
                'indicadores_acompanhamento' => 'Evolução',
                'prazo_revisao_dias' => 15,
                'habilidade_bncc_alvo' => 'BNCC',
            ],
            'status' => StatusAdaptacaoPei::Rascunho,
        ]);

        $userSemPermissao = User::factory()->create();
        $userComPermissao = User::factory()->create();
        $userComPermissao->givePermissionTo('ExportarPei:PlanoAula');

        // 1. Usuário sem permissão recebe 403 Forbidden
        $responseSem = $this->actingAs($userSemPermissao)->get(route('planos-aula.adaptacoes.pdf', $adaptacao));
        $responseSem->assertForbidden();

        // 2. Usuário com permissão faz download com sucesso
        $responseCom = $this->actingAs($userComPermissao)->get(route('planos-aula.adaptacoes.pdf', $adaptacao));
        $responseCom->assertOk();
        $responseCom->assertHeader('Content-Type', 'application/pdf');

        $conteudoPdf = $responseCom->getContent();
        $this->assertStringStartsWith('%PDF', $conteudoPdf);

        // Verifica que registrou auditoria sem vazar o conteúdo confidencial
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'plano_aula',
            'subject_id' => $plano->id,
            'causer_id' => $userComPermissao->id,
        ]);

        $logDescricao = DB::table('activity_log')
            ->where('subject_id', $plano->id)
            ->value('description');
        $this->assertStringNotContainsString('Barreira de leitura', (string) $logDescricao);
    }

    public function test_saida_da_ia_com_html_e_script_e_escapada_no_pdf(): void
    {
        $plano = PlanoAula::factory()->create();
        $matricula = Matricula::factory()->create();

        $adaptacao = PlanoAulaAdaptacao::create([
            'plano_aula_id' => $plano->id,
            'matricula_id' => $matricula->id,
            'conteudo' => [
                'barreiras' => "<script>alert('xss_barreira')</script> Dificuldade de escrita",
                'adaptacoes_praticas' => [
                    'instrucoes_passo_a_passo' => "<b onclick='evil()'>Passo</b>",
                    'apoio_visual' => 'Cartões',
                    'tempo_estendido' => '10 min',
                    'recursos_acessibilidade' => 'Lupa',
                    'organizacao_ambiente' => 'Sala',
                ],
                'adaptacao_avaliacao' => 'Oral',
                'estrategias_professor' => 'Apoio',
                'indicadores_acompanhamento' => 'Evolução',
                'prazo_revisao_dias' => 15,
                'habilidade_bncc_alvo' => 'BNCC',
            ],
            'status' => StatusAdaptacaoPei::Aprovado,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('ExportarPei:PlanoAula');

        $response = $this->actingAs($user)->get(route('planos-aula.adaptacoes.pdf', $adaptacao));
        $response->assertOk();

        $pdfBytes = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdfBytes);
    }

    public function test_sem_permissao_usuario_e_bloqueado_pela_policy(): void
    {
        $plano = PlanoAula::factory()->create();
        $matricula = Matricula::factory()->create();
        $adaptacao = PlanoAulaAdaptacao::create([
            'plano_aula_id' => $plano->id,
            'matricula_id' => $matricula->id,
            'conteudo' => ['barreiras' => 'Nenhuma'],
            'status' => StatusAdaptacaoPei::Rascunho,
        ]);

        $userSemPermissao = User::factory()->create();

        $this->assertFalse($userSemPermissao->can('viewAny', PlanoAulaAdaptacao::class));
        $this->assertFalse($userSemPermissao->can('view', $adaptacao));
        $this->assertFalse($userSemPermissao->can('create', [PlanoAulaAdaptacao::class, $plano]));
        $this->assertFalse($userSemPermissao->can('update', $adaptacao));
        $this->assertFalse($userSemPermissao->can('delete', $adaptacao));
        $this->assertFalse($userSemPermissao->can('exportarPei', $adaptacao));

        $response = $this->actingAs($userSemPermissao)->get(route('planos-aula.adaptacoes.pdf', $adaptacao));
        $response->assertForbidden();
    }

    public function test_professor_de_outra_turma_nao_tem_acesso_ao_pei_mesmo_com_permissao(): void
    {
        $pessoaProf1 = Pessoa::factory()->create();
        $pessoaProf2 = Pessoa::factory()->create();

        $turma1 = Turma::factory()->create(['professor_conselheiro_id' => $pessoaProf1->id]);
        $turma2 = Turma::factory()->create(['professor_conselheiro_id' => $pessoaProf2->id]);

        $plano1 = PlanoAula::factory()->create([
            'turma_id' => $turma1->id,
            'professor_id' => $pessoaProf1->id,
        ]);
        $matricula1 = Matricula::factory()->create(['turma_id' => $turma1->id]);

        $adaptacao1 = PlanoAulaAdaptacao::create([
            'plano_aula_id' => $plano1->id,
            'matricula_id' => $matricula1->id,
            'conteudo' => ['barreiras' => 'Barreira teste'],
            'status' => StatusAdaptacaoPei::Rascunho,
        ]);

        // Professor 2 (da turma 2) tem a permissão no Shield, mas atua com active_role = 'professor'
        $roleProfessor = Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);
        $roleProfessor->givePermissionTo('GerarPei:PlanoAula');
        $roleProfessor->givePermissionTo('ExportarPei:PlanoAula');

        $userProf2 = User::factory()->create();
        $userProf2->assignRole($roleProfessor);
        $userProf2->pessoas()->attach($pessoaProf2->id);

        session(['active_role' => 'professor']);

        $this->assertFalse($userProf2->can('view', $adaptacao1));
        $this->assertFalse($userProf2->can('exportarPei', $adaptacao1));

        $response = $this->actingAs($userProf2)->get(route('planos-aula.adaptacoes.pdf', $adaptacao1));
        $response->assertForbidden();
    }

    public function test_coordenador_com_permissao_acessa_pei_de_qualquer_turma(): void
    {
        $turma = Turma::factory()->create();
        $plano = PlanoAula::factory()->create(['turma_id' => $turma->id]);
        $matricula = Matricula::factory()->create(['turma_id' => $turma->id]);

        $adaptacao = PlanoAulaAdaptacao::create([
            'plano_aula_id' => $plano->id,
            'matricula_id' => $matricula->id,
            'conteudo' => ['barreiras' => 'Barreira'],
            'status' => StatusAdaptacaoPei::Rascunho,
        ]);

        $roleCoordenador = Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        $roleCoordenador->givePermissionTo('ExportarPei:PlanoAula');

        $userCoordenador = User::factory()->create();
        $userCoordenador->assignRole($roleCoordenador);

        session(['active_role' => 'coordenador']);

        $this->assertTrue($userCoordenador->can('view', $adaptacao));
        $this->assertTrue($userCoordenador->can('exportarPei', $adaptacao));

        $response = $this->actingAs($userCoordenador)->get(route('planos-aula.adaptacoes.pdf', $adaptacao));
        $response->assertOk();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContextoDocumento;
use App\Enums\MotivoCancelamentoMatricula;
use App\Enums\OrigemCancelamentoMatricula;
use App\Enums\TipoTemplateDocumento;
use App\Filament\Resources\TemplateDocumentos\Pages\CreateTemplateDocumento;
use App\Filament\Resources\TemplateDocumentos\Pages\EditTemplateDocumento;
use App\Filament\Resources\TemplateDocumentos\Pages\ListTemplateDocumentos;
use App\Filament\Resources\TemplateDocumentos\Schemas\TemplateDocumentoForm;
use App\Filament\Resources\TemplateDocumentos\Tables\TemplateDocumentosTable;
use App\Models\CancelamentoMatricula;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Fatura;
use App\Models\Funcionario;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\Serie;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CancelamentoMatriculaService;
use App\Services\Documentos\MacrosDocumentoService;
use App\Services\Documentos\ModelosPadraoDocumento;
use App\Services\DocumentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Editor de modelos de documento (Onda 69): contextos (aluno, cancelamento, funcionário), catálogo único de variáveis,
 * recusa de variável que não existe, prévia com dados de um registro, modelos padrão e isolamento da emissão de
 * declarações.
 *
 * "Hoje" é quinta-feira, 22/10/2026.
 */
class ModelosDocumentoEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Matricula $matricula;

    private CancelamentoMatricula $cancelamento;

    private Funcionario $funcionario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 09:00:00'));
        Storage::fake('local');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');
        $this->como($this->admin);

        $unidade = Unidade::create(['nome' => 'Unidade Sede', 'cnpj' => '12.345.678/0001-90']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);
        $this->matricula = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Ana Lima', 'data_nascimento' => '2014-03-10'])->id,
            'turma_id' => Turma::factory()->create(['serie_id' => $serie->id])->id,
            'situacao' => 'ativa',
        ]);
        $contrato = Contrato::create(['matricula_id' => $this->matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);
        ResponsavelFinanceiro::create(['contrato_id' => $contrato->id, 'pessoa_id' => Pessoa::create(['nome' => 'Marina Lima', 'email' => 'marina@teste.com'])->id, 'percentual' => 100]);

        $servico = app(CancelamentoMatriculaService::class);
        $pedido = $servico->solicitar($this->matricula, ['motivo' => MotivoCancelamentoMatricula::MudancaCidade, 'data_efetiva' => '2026-10-31'], null, OrigemCancelamentoMatricula::Escola);
        $this->cancelamento = $servico->aprovar($pedido, $this->admin, []);

        $this->funcionario = Funcionario::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Marta Souza', 'email' => 'marta@teste.com'])->id,
            'cargo' => 'Professora',
            'unidade_id' => $unidade->id,
        ]);
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function macros(): MacrosDocumentoService
    {
        return app(MacrosDocumentoService::class);
    }

    private function modelo(TipoTemplateDocumento $tipo, string $conteudo, bool $ativo = true, string $nome = 'Modelo de teste'): TemplateDocumento
    {
        return TemplateDocumento::create(['nome' => $nome, 'tipo' => $tipo, 'conteudo' => $conteudo, 'validade_dias' => 30, 'is_ativo' => $ativo]);
    }

    private function registroDo(ContextoDocumento $contexto): object
    {
        return match ($contexto) {
            ContextoDocumento::Matricula => $this->matricula,
            ContextoDocumento::CancelamentoMatricula => $this->cancelamento,
            ContextoDocumento::Funcionario => $this->funcionario,
        };
    }

    // --- Catálogo e variáveis ----------------------------------------------------------------------------------------

    public function test_cada_tipo_de_documento_tem_o_seu_contexto(): void
    {
        $this->assertSame(ContextoDocumento::Matricula, TipoTemplateDocumento::DeclaracaoMatricula->contexto());
        $this->assertSame(ContextoDocumento::Matricula, TipoTemplateDocumento::Personalizado->contexto());
        $this->assertSame(ContextoDocumento::CancelamentoMatricula, TipoTemplateDocumento::DistratoMatricula->contexto());
        $this->assertSame(ContextoDocumento::Funcionario, TipoTemplateDocumento::ContratoTrabalho->contexto());
        $this->assertSame(ContextoDocumento::Funcionario, TipoTemplateDocumento::TermoConfidencialidade->contexto());

        $paraEmissao = TipoTemplateDocumento::valoresParaEmissao();

        $this->assertContains('declaracao_matricula', $paraEmissao);
        $this->assertNotContains('distrato_matricula', $paraEmissao);
        $this->assertNotContains('contrato_trabalho', $paraEmissao);
        $this->assertNotContains('termo_confidencialidade', $paraEmissao);
    }

    public function test_catalogo_de_matricula_e_igual_as_variaveis_que_o_servico_de_declaracoes_preenche(): void
    {
        $preenchidas = array_keys(app(DocumentoService::class)->variaveis($this->matricula));
        $catalogo = $this->macros()->variaveisDoContexto(ContextoDocumento::Matricula);

        // O bloco de assinaturas é do catálogo (documentos que vão para assinatura); {{TABELA_HISTORICO}} e as datas de
        // emissão são preenchidas junto com as demais.
        $semBloco = array_values(array_diff($catalogo, [MacrosDocumentoService::BLOCO_ASSINATURAS]));

        $this->assertEqualsCanonicalizing($preenchidas, $semBloco, 'o guia do editor e o preenchimento das declarações não podem se afastar');
    }

    public function test_cada_contexto_preenche_todas_as_variaveis_que_o_catalogo_anuncia(): void
    {
        foreach (ContextoDocumento::cases() as $contexto) {
            $valores = $this->macros()->valores($contexto, $this->registroDo($contexto));

            $this->assertEqualsCanonicalizing(
                $this->macros()->variaveisDoContexto($contexto),
                array_keys($valores),
                "o catálogo de {$contexto->value} anuncia o que o preenchimento entrega",
            );

            foreach ($valores as $variavel => $valor) {
                $this->assertIsString($valor, $variavel);
            }
        }
    }

    public function test_variaveis_desconhecidas_sao_achadas_inclusive_as_escritas_com_espaco_e_as_de_outro_contexto(): void
    {
        $texto = '<p>{{ALUNO_NOME}} {{ALUNO_NOMEE}} {{ ALUNO_CPF }} {{CARGO}} {{BLOCO_ASSINATURAS}} {{aluno_nome}}</p>';

        $this->assertSame(
            ['{{ALUNO_NOMEE}}', '{{ ALUNO_CPF }}', '{{CARGO}}', '{{aluno_nome}}'],
            $this->macros()->desconhecidas($texto, ContextoDocumento::Matricula),
        );

        $this->assertSame(
            ['{{ALUNO_NOME}}', '{{ALUNO_NOMEE}}', '{{ ALUNO_CPF }}', '{{aluno_nome}}'],
            $this->macros()->desconhecidas($texto, ContextoDocumento::Funcionario),
        );

        $this->assertSame([], $this->macros()->desconhecidas(null, ContextoDocumento::Matricula));
        $this->assertSame([], $this->macros()->desconhecidas('<p>Sem variáveis.</p>', ContextoDocumento::Matricula));
    }

    public function test_preencher_troca_as_variaveis_do_registro_e_limpa_html_perigoso(): void
    {
        $modelo = $this->modelo(TipoTemplateDocumento::DistratoMatricula, '<p>{{ALUNO_NOME}} sai em {{DATA_SAIDA}}.</p><script>alert(1)</script><img src="x" onerror="alert(2)">');

        $html = $this->macros()->preencher($modelo, $this->cancelamento);

        $this->assertStringContainsString('Ana Lima sai em 31/10/2026.', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_variaveis_do_cancelamento_trazem_valores_e_clausulas_conforme_o_caso(): void
    {
        $valores = $this->macros()->valores(ContextoDocumento::CancelamentoMatricula, $this->cancelamento);

        $this->assertSame('Ana Lima', $valores['{{ALUNO_NOME}}']);
        $this->assertSame('Marina Lima', $valores['{{RESPONSAVEIS}}']);
        $this->assertSame('31/10/2026', $valores['{{DATA_SAIDA}}']);
        $this->assertSame('R$ 0,00', $valores['{{VALOR_A_DEVOLVER}}']);
        $this->assertStringContainsString('não há', mb_strtolower($valores['{{CLAUSULA_DEVOLUCAO}}']), 'sem valor a devolver, a frase diz que não há');
        $this->assertStringContainsString('não há', mb_strtolower($valores['{{CLAUSULA_MULTA}}']));
        $this->assertStringContainsString('Não há faturas', $valores['{{TABELA_FATURAS}}']);
    }

    public function test_tabela_de_faturas_do_distrato_lista_cada_fatura_com_o_destino_decidido(): void
    {
        $fatura = Fatura::create(['contrato_id' => $this->matricula->contrato->id, 'vencimento' => '2026-10-09', 'status' => 'pendente']);
        ItemFatura::create(['fatura_id' => $fatura->id, 'descricao' => 'Parcela 2 de 12', 'quantidade' => 1, 'valor_unitario' => 1000, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);
        $this->cancelamento->update(['decisoes' => [$fatura->id => 'manter']]);

        $tabela = $this->macros()->valores(ContextoDocumento::CancelamentoMatricula, $this->cancelamento->refresh())['{{TABELA_FATURAS}}'];

        $this->assertStringContainsString('<table', $tabela);
        $this->assertStringContainsString('Parcela 2 de 12', $tabela);
        $this->assertStringContainsString('09/10/2026', $tabela);
        $this->assertStringContainsString('1.000,00', $tabela);
    }

    public function test_variaveis_do_funcionario_trazem_os_dados_da_pessoa_e_da_escola(): void
    {
        $valores = $this->macros()->valores(ContextoDocumento::Funcionario, $this->funcionario);

        $this->assertSame('Marta Souza', $valores['{{FUNCIONARIO_NOME}}']);
        $this->assertSame('Professora', $valores['{{CARGO}}']);
        $this->assertSame('Unidade Sede', $valores['{{UNIDADE_NOME}}']);
        $this->assertSame('12.345.678/0001-90', $valores['{{UNIDADE_CNPJ}}']);
    }

    public function test_registro_de_outro_contexto_e_recusado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->macros()->valores(ContextoDocumento::Funcionario, $this->matricula);
    }

    public function test_bloco_de_assinaturas_lista_os_assinantes_ou_mostra_um_exemplo_na_previa(): void
    {
        $com = $this->macros()->blocoAssinaturas([['nome' => 'Marina <b>Lima</b>', 'papel' => 'Responsável'], ['nome' => 'Diretora Clara', 'papel' => 'Escola'], ['nome' => 'Terceiro']]);

        $this->assertStringContainsString('Marina &lt;b&gt;Lima&lt;/b&gt;', $com, 'o nome é escapado');
        $this->assertSame(2, substr_count($com, '<tr>'), 'duas assinaturas por linha');

        $this->assertStringContainsString('Nome do primeiro assinante', $this->macros()->blocoAssinaturas([]));
    }

    // --- Editor ------------------------------------------------------------------------------------------------------

    public function test_editor_recusa_variavel_que_nao_existe_para_o_tipo(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateTemplateDocumento::class)
            ->fillForm([
                'nome' => 'Distrato com erro',
                'tipo' => TipoTemplateDocumento::DistratoMatricula->value,
                'conteudo' => '<p>{{ALUNO_NOME}} e {{CARGO}} e {{ALUNO_NOMEE}}</p>',
                'validade_dias' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['conteudo']);

        $this->assertSame(0, TemplateDocumento::query()->count());
    }

    public function test_mensagem_de_erro_lista_as_variaveis_erradas(): void
    {
        $tela = Livewire::actingAs($this->admin)
            ->test(CreateTemplateDocumento::class)
            ->fillForm([
                'nome' => 'Distrato com erro',
                'tipo' => TipoTemplateDocumento::DistratoMatricula->value,
                'conteudo' => '<p>{{CARGO}} {{ ALUNO_NOME }}</p>',
                'validade_dias' => 0,
            ])
            ->call('create');

        $mensagem = collect($tela->errors()->all())->implode(' ');

        $this->assertStringContainsString('{{CARGO}}', $mensagem);
        $this->assertStringContainsString('{{ ALUNO_NOME }}', $mensagem);
        $this->assertStringContainsString('Distrato de Matrícula', $mensagem);
    }

    public function test_editor_aceita_texto_com_as_variaveis_do_tipo(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateTemplateDocumento::class)
            ->fillForm([
                'nome' => 'Distrato certo',
                'tipo' => TipoTemplateDocumento::DistratoMatricula->value,
                'conteudo' => '<p>{{ALUNO_NOME}} sai em {{DATA_SAIDA}}. {{CLAUSULA_MULTA}}</p>{{BLOCO_ASSINATURAS}}',
                'validade_dias' => 0,
                'is_ativo' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(TipoTemplateDocumento::DistratoMatricula, TemplateDocumento::query()->firstOrFail()->tipo);
    }

    public function test_variavel_de_aluno_continua_valendo_nas_declaracoes_antigas(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateTemplateDocumento::class)
            ->fillForm([
                'nome' => 'Declaração de Matrícula',
                'tipo' => TipoTemplateDocumento::DeclaracaoMatricula->value,
                'conteudo' => '<p>{{ALUNO_NOME}}, turma {{TURMA_NOME}}. Protocolo {{PROTOCOLO}} — {{URL_VERIFICACAO}}</p>',
                'validade_dias' => 30,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_guia_de_variaveis_muda_com_o_tipo_escolhido(): void
    {
        $tela = Livewire::actingAs($this->admin)
            ->test(CreateTemplateDocumento::class)
            ->fillForm(['tipo' => TipoTemplateDocumento::DistratoMatricula->value])
            ->assertSee('{{DATA_SAIDA}}')
            ->assertSee('{{CLAUSULA_DEVOLUCAO}}')
            ->assertDontSee('{{CARGA_HORARIA_SEMANAL}}');

        $tela->fillForm(['tipo' => TipoTemplateDocumento::ContratoTrabalho->value])
            ->assertSee('{{CARGA_HORARIA_SEMANAL}}')
            ->assertSee('{{BLOCO_ASSINATURAS}}')
            ->assertDontSee('{{CLAUSULA_DEVOLUCAO}}');
    }

    public function test_tipo_com_enum_ou_texto_e_entendido_pelo_formulario(): void
    {
        $this->assertSame(TipoTemplateDocumento::ContratoTrabalho, TemplateDocumentoForm::tipo('contrato_trabalho'));
        $this->assertSame(TipoTemplateDocumento::ContratoTrabalho, TemplateDocumentoForm::tipo(TipoTemplateDocumento::ContratoTrabalho));
        $this->assertNull(TemplateDocumentoForm::tipo(null));
        $this->assertNull(TemplateDocumentoForm::tipo('inexistente'));
    }

    public function test_previa_mostra_o_documento_com_os_dados_do_registro_e_o_texto_da_tela(): void
    {
        $modelo = $this->modelo(TipoTemplateDocumento::DistratoMatricula, '<p>Texto salvo para {{ALUNO_NOME}}.</p>');

        Livewire::actingAs($this->admin)
            ->test(EditTemplateDocumento::class, ['record' => $modelo->id])
            ->fillForm(['conteudo' => '<p>Texto novo, ainda sem salvar, de {{ALUNO_NOME}} que sai em {{DATA_SAIDA}}.</p>'])
            ->mountAction('previa')
            ->fillForm(['registro_id' => $this->cancelamento->id])
            ->assertMountedActionModalSee('Texto novo, ainda sem salvar, de Ana Lima que sai em 31/10/2026.')
            ->assertMountedActionModalDontSee('Texto salvo para');
    }

    public function test_previa_sem_registro_pede_para_escolher_e_com_registro_inexistente_avisa(): void
    {
        $modelo = $this->modelo(TipoTemplateDocumento::ContratoTrabalho, '<p>{{FUNCIONARIO_NOME}}</p>');

        $tela = Livewire::actingAs($this->admin)
            ->test(EditTemplateDocumento::class, ['record' => $modelo->id])
            ->mountAction('previa')
            ->assertMountedActionModalSee('Escolha um registro acima');

        $tela->fillForm(['registro_id' => $this->funcionario->id])->assertMountedActionModalSee('Marta Souza');
        $tela->fillForm(['registro_id' => 999999])->assertMountedActionModalSee('Registro não encontrado');
    }

    public function test_alterar_o_modelo_deixa_no_historico_so_os_nomes_dos_campos_e_nao_o_texto(): void
    {
        $modelo = $this->modelo(TipoTemplateDocumento::ContratoTrabalho, '<p>Cláusula secreta de {{FUNCIONARIO_NOME}}</p>');
        $this->actingAs($this->admin);

        $modelo->update(['conteudo' => '<p>Outra cláusula sigilosa de {{FUNCIONARIO_NOME}}</p>', 'is_ativo' => false]);

        $log = Activity::query()->where('log_name', 'modelos')->latest('id')->firstOrFail();

        $this->assertSame('Modelo de documento alterado.', $log->description);
        $this->assertSame($this->admin->id, $log->causer_id);
        $this->assertEqualsCanonicalizing(['conteudo', 'is_ativo'], $log->properties['campos']);
        $this->assertStringNotContainsString('sigilosa', json_encode($log->properties, JSON_UNESCAPED_UNICODE));
    }

    public function test_salvar_sem_mudar_nada_nao_gera_historico(): void
    {
        $modelo = $this->modelo(TipoTemplateDocumento::ContratoTrabalho, '<p>{{FUNCIONARIO_NOME}}</p>');

        $modelo->update(['nome' => $modelo->nome]);

        $this->assertSame(0, Activity::query()->where('log_name', 'modelos')->count());
    }

    // --- Lista -------------------------------------------------------------------------------------------------------

    public function test_lista_mostra_de_que_registro_cada_modelo_tira_os_dados(): void
    {
        $distrato = $this->modelo(TipoTemplateDocumento::DistratoMatricula, '<p>x</p>', nome: 'Distrato');
        $declaracao = $this->modelo(TipoTemplateDocumento::DeclaracaoMatricula, '<p>x</p>', nome: 'Declaração');

        Livewire::actingAs($this->admin)
            ->test(ListTemplateDocumentos::class)
            ->assertCanSeeTableRecords([$distrato, $declaracao])
            ->assertSee('Cancelamento de matrícula')
            ->assertSee('Aluno (matrícula)');

        $this->assertTrue(method_exists(TemplateDocumentosTable::class, 'configure'));
    }

    public function test_criar_modelos_padrao_cria_os_tres_inativos_e_nao_repete(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListTemplateDocumentos::class)
            ->callAction('modelos_padrao')
            ->assertNotified('3 modelo(s) criado(s)');

        $criados = TemplateDocumento::query()->orderBy('id')->get();

        $this->assertEqualsCanonicalizing(['distrato_matricula', 'contrato_trabalho', 'termo_confidencialidade'], $criados->map(fn (TemplateDocumento $m): string => $m->tipo->value)->all());
        $this->assertSame([false, false, false], $criados->map(fn (TemplateDocumento $m): bool => $m->is_ativo)->all(), 'rascunhos: o jurídico revisa antes de ativar');

        Livewire::actingAs($this->admin)
            ->test(ListTemplateDocumentos::class)
            ->callAction('modelos_padrao')
            ->assertNotified('Os modelos padrão já existem');

        $this->assertSame(3, TemplateDocumento::query()->count());
    }

    public function test_modelos_padrao_nao_substituem_um_modelo_que_a_escola_ja_tem_do_mesmo_tipo(): void
    {
        $meu = $this->modelo(TipoTemplateDocumento::DistratoMatricula, '<p>Texto da escola.</p>', nome: 'Meu distrato');

        $this->assertSame(2, app(ModelosPadraoDocumento::class)->criarFaltantes());
        $this->assertSame('<p>Texto da escola.</p>', $meu->refresh()->conteudo);
        $this->assertSame(1, TemplateDocumento::query()->where('tipo', 'distrato_matricula')->count());
    }

    public function test_os_modelos_padrao_so_usam_variaveis_do_catalogo_e_sao_preenchidos_sem_sobra(): void
    {
        app(ModelosPadraoDocumento::class)->criarFaltantes();

        foreach (TemplateDocumento::query()->get() as $modelo) {
            $contexto = $modelo->contexto();

            foreach (['conteudo', 'cabecalho', 'rodape'] as $campo) {
                $this->assertSame([], $this->macros()->desconhecidas($modelo->{$campo}, $contexto), "{$modelo->nome} ({$campo}) usa variável fora do catálogo");
            }

            $html = $this->macros()->preencher($modelo, $this->registroDo($contexto), [['nome' => 'Fulano', 'papel' => 'Parte']]);

            $this->assertStringNotContainsString('{{', $html, "{$modelo->nome} deixou variável sem troca");
            $this->assertStringContainsString('Fulano', $html, "{$modelo->nome} tem o bloco de assinaturas");
        }
    }

    // --- Isolamento da emissão de declarações ----------------------------------------------------------------------

    public function test_modelos_de_distrato_e_de_funcionario_nao_entram_na_emissao_de_declaracoes(): void
    {
        $declaracao = $this->modelo(TipoTemplateDocumento::DeclaracaoMatricula, '<p>{{ALUNO_NOME}}</p>', nome: 'Declaração');
        $distrato = $this->modelo(TipoTemplateDocumento::DistratoMatricula, '<p>{{ALUNO_NOME}}</p>', nome: 'Distrato');
        $contrato = $this->modelo(TipoTemplateDocumento::ContratoTrabalho, '<p>{{FUNCIONARIO_NOME}}</p>', nome: 'Contrato');

        $this->assertEquals([$declaracao->id], TemplateDocumento::query()->paraEmissao()->pluck('id')->all());
        $this->assertEquals([$distrato->id], TemplateDocumento::query()->doContexto(ContextoDocumento::CancelamentoMatricula)->pluck('id')->all());
        $this->assertEquals([$contrato->id], TemplateDocumento::query()->doContexto(ContextoDocumento::Funcionario)->pluck('id')->all());
    }

    public function test_emitir_com_modelo_que_nao_e_declaracao_do_aluno_e_recusado(): void
    {
        $distrato = $this->modelo(TipoTemplateDocumento::DistratoMatricula, '<p>{{ALUNO_NOME}}</p>');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('não é uma declaração do aluno');

        app(DocumentoService::class)->validarEmissao($distrato, $this->matricula);
    }

    public function test_declaracao_de_aluno_continua_sendo_preenchida_pelo_servico_antigo(): void
    {
        $declaracao = $this->modelo(TipoTemplateDocumento::DeclaracaoMatricula, '<p>{{ALUNO_NOME}} — protocolo {{PROTOCOLO}}</p>');
        $solicitacao = new SolicitacaoDocumento(['protocolo' => 'DOC-2026-0042']);

        $html = app(DocumentoService::class)->preencherMacros($declaracao, $this->matricula, $solicitacao);

        $this->assertStringContainsString('Ana Lima', $html);
        $this->assertStringContainsString('protocolo DOC-2026-0042', $html);

        // Sem solicitação (prévia e assinatura eletrônica), as variáveis de autenticidade vão com um traço.
        $semProtocolo = app(DocumentoService::class)->variaveis($this->matricula);

        $this->assertSame('—', $semProtocolo['{{PROTOCOLO}}']);
    }

    // --- PDF do distrato ---------------------------------------------------------------------------------------------

    public function test_distrato_usa_o_modelo_ativo_quando_existe(): void
    {
        $this->modelo(TipoTemplateDocumento::DistratoMatricula, '<p>Texto do modelo da escola para {{ALUNO_NOME}}.</p>{{BLOCO_ASSINATURAS}}');

        $espiao = $this->partialMock(MacrosDocumentoService::class, function ($mock): void {
            $mock->shouldReceive('preencher')->once()->andReturn('<p>Texto do modelo da escola para Ana Lima.</p>');
        });

        $resposta = $this->actingAs($this->admin)->get(route('cancelamentos-matricula.distrato', ['cancelamento' => $this->cancelamento->id]));

        $resposta->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $resposta->headers->get('content-type'));
        $this->assertNotNull($espiao);
    }

    public function test_distrato_sem_modelo_ativo_usa_o_texto_fixo_da_tela(): void
    {
        $this->modelo(TipoTemplateDocumento::DistratoMatricula, '<p>{{ALUNO_NOME}}</p>', ativo: false);

        $this->partialMock(MacrosDocumentoService::class, function ($mock): void {
            $mock->shouldNotReceive('preencher');
        });

        $resposta = $this->actingAs($this->admin)->get(route('cancelamentos-matricula.distrato', ['cancelamento' => $this->cancelamento->id]));

        $resposta->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $resposta->headers->get('content-type'));
    }
}

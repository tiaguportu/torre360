<?php

namespace Tests\Feature;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use App\Enums\SituacaoDocumento;
use App\Enums\TipoPendenciaMatricula;
use App\Filament\Resources\Matriculas\Pages\ListMatriculas;
use App\Filament\Resources\Matriculas\Widgets\MatriculasResumoStats;
use App\Filament\Resources\Turmas\Pages\EditTurma;
use App\Filament\Resources\Turmas\RelationManagers\MatriculasRelationManager;
use App\Models\Cidade;
use App\Models\DocumentoInserido;
use App\Models\Endereco;
use App\Models\Estado;
use App\Models\Matricula;
use App\Models\Pais;
use App\Models\Pessoa;
use App\Models\TipoDocumento;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MatriculaListagemTest extends TestCase
{
    use RefreshDatabase;

    private ?Cidade $cidade = null;

    private ?Pais $pais = null;

    private int $sequencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin']));
        session(['active_role' => 'super_admin']);

        $this->actingAs($admin);
    }

    private function criarPessoaCompleta(string $nome): Pessoa
    {
        $cpf = str_pad((string) ++$this->sequencia, 11, '0', STR_PAD_LEFT);

        if (! $this->cidade) {
            $this->pais = Pais::create(['nome' => 'Brasil', 'sigla' => 'BRA']);
            $estado = Estado::create(['pais_id' => $this->pais->id, 'nome' => 'São Paulo', 'sigla' => 'SP']);
            $this->cidade = Cidade::create(['estado_id' => $estado->id, 'nome' => 'São Paulo']);
        }

        $pessoa = Pessoa::factory()->create([
            'nome' => $nome,
            'data_nascimento' => '2010-01-01',
            'cpf' => $cpf,
            'email' => strtolower(str_replace(' ', '', $nome)).'@teste.com',
            'telefone' => '11999999999',
            'sexo' => Sexo::MASCULINO,
            'cor_raca' => CorRaca::BRANCA,
            'nacionalidade_id' => $this->pais->id,
            'naturalidade_id' => $this->cidade->id,
        ]);

        $pessoa->enderecos()->attach(Endereco::create([
            'logradouro' => 'Rua das Flores',
            'numero' => '123',
            'bairro' => 'Centro',
            'cidade_id' => $this->cidade->id,
            'cep' => '01001000',
            'tipo' => 'residencial',
        ])->id);

        return $pessoa;
    }

    /**
     * Matrícula sem nenhuma pendência: aluno e responsável com cadastro completo.
     */
    private function criarMatriculaEmDia(string $situacao = 'ativa'): Matricula
    {
        $aluno = $this->criarPessoaCompleta('Aluno Em Dia');
        $aluno->responsaveis()->attach($this->criarPessoaCompleta('Responsavel Em Dia')->id);

        return Matricula::factory()->create(['pessoa_id' => $aluno->id, 'situacao' => $situacao]);
    }

    /**
     * Matrícula com pendência: aluno sem responsável e com cadastro incompleto.
     */
    private function criarMatriculaComPendencia(string $situacao = 'ativa'): Matricula
    {
        return Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['cpf' => null])->id,
            'situacao' => $situacao,
        ]);
    }

    #[Test]
    public function aba_inicial_e_ativas_e_as_abas_filtram_por_situacao_sem_conflito_com_filtros(): void
    {
        $ativa = Matricula::factory()->create(['situacao' => 'ativa']);
        $cancelada = Matricula::factory()->create(['situacao' => 'cancelada']);

        $lista = Livewire::test(ListMatriculas::class);

        // Não há mais filtro de situação competindo com as abas
        $this->assertNull($lista->instance()->getTable()->getFilter('situacao'));

        $lista->assertSet('activeTab', 'ativas')
            ->assertCanSeeTableRecords([$ativa])
            ->assertCanNotSeeTableRecords([$cancelada])
            ->set('activeTab', 'canceladas')
            ->assertCanSeeTableRecords([$cancelada])
            ->assertCanNotSeeTableRecords([$ativa])
            ->set('activeTab', 'todas')
            ->assertCanSeeTableRecords([$ativa, $cancelada]);
    }

    #[Test]
    public function abas_exibem_contagens_e_ocultam_situacoes_raras_sem_matriculas(): void
    {
        Matricula::factory()->count(2)->create(['situacao' => 'ativa']);
        Matricula::factory()->create(['situacao' => 'pendente']);
        Matricula::factory()->create(['situacao' => 'trancada']);

        $abas = Livewire::test(ListMatriculas::class)->instance()->getCachedTabs();

        $this->assertEquals(2, $abas['ativas']->getBadge());
        $this->assertEquals(1, $abas['pendentes']->getBadge());
        $this->assertEquals(1, $abas['trancadas']->getBadge());
        $this->assertEquals(4, $abas['todas']->getBadge());

        $this->assertFalse($abas['trancadas']->isHidden());
        $this->assertTrue($abas['concluidas']->isHidden());
        $this->assertTrue($abas['reserva']->isHidden());
        $this->assertTrue($abas['evasao']->isHidden());
        $this->assertFalse($abas['canceladas']->isHidden());
    }

    #[Test]
    public function aba_com_pendencias_lista_apenas_matriculas_ativas_ou_pendentes_com_problema(): void
    {
        $emDia = $this->criarMatriculaEmDia();
        $comPendencia = $this->criarMatriculaComPendencia();
        $pendenteComProblema = $this->criarMatriculaComPendencia('pendente');
        $canceladaComProblema = $this->criarMatriculaComPendencia('cancelada');

        $lista = Livewire::test(ListMatriculas::class)->set('activeTab', 'com_pendencias');

        $lista->assertCanSeeTableRecords([$comPendencia, $pendenteComProblema])
            ->assertCanNotSeeTableRecords([$emDia, $canceladaComProblema]);

        $this->assertEquals(2, $lista->instance()->getCachedTabs()['com_pendencias']->getBadge());
    }

    #[Test]
    public function coluna_pendencias_mostra_o_tipo_de_cada_pendencia_e_em_dia(): void
    {
        $emDia = $this->criarMatriculaEmDia();
        $comPendencia = $this->criarMatriculaComPendencia();

        $lista = Livewire::test(ListMatriculas::class);

        // Badges empilhados (um embaixo do outro), alinhados à esquerda
        $lista->assertSeeHtml('fi-ta-text-has-line-breaks')
            ->assertSeeHtml('align-items: flex-start;');

        $lista->assertCanSeeTableRecords([$emDia, $comPendencia])
            ->assertSee('Em dia')
            ->assertSee('Sem responsável')
            ->assertSee('Cadastro incompleto');

        $coluna = $lista->instance()->getTable()->getColumn('pendencias');

        // O detalhe só abre (clique na célula) quando existe pendência
        $this->assertTrue((clone $coluna)->record($emDia->fresh())->isClickDisabled());
        $this->assertFalse((clone $coluna)->record($comPendencia->fresh())->isClickDisabled());
    }

    #[Test]
    public function filtro_de_pendencias_aplica_qualquer_um_dos_tipos_escolhidos(): void
    {
        $emDia = $this->criarMatriculaEmDia();
        $semResponsavel = Matricula::factory()->create([
            'pessoa_id' => $this->criarPessoaCompleta('Aluno Sem Resp')->id,
            'situacao' => 'ativa',
        ]);

        $cadastroIncompleto = $this->criarMatriculaEmDia();
        $cadastroIncompleto->pessoa->update(['cpf' => null]);

        Livewire::test(ListMatriculas::class)
            ->filterTable('pendencias', [TipoPendenciaMatricula::SEM_RESPONSAVEL])
            ->assertCanSeeTableRecords([$semResponsavel])
            ->assertCanNotSeeTableRecords([$emDia, $cadastroIncompleto])
            ->filterTable('pendencias', [
                TipoPendenciaMatricula::SEM_RESPONSAVEL,
                TipoPendenciaMatricula::CADASTRO_INCOMPLETO,
            ])
            ->assertCanSeeTableRecords([$semResponsavel, $cadastroIncompleto])
            ->assertCanNotSeeTableRecords([$emDia]);
    }

    #[Test]
    public function filtro_de_contrato_separa_matriculas_com_e_sem_contrato(): void
    {
        $semContrato = Matricula::factory()->create();
        $comContrato = Matricula::factory()->create();
        $comContrato->contrato()->create(['valor_total' => 0]);

        Livewire::test(ListMatriculas::class)
            ->filterTable('contrato', true)
            ->assertCanSeeTableRecords([$comContrato])
            ->assertCanNotSeeTableRecords([$semContrato])
            ->filterTable('contrato', false)
            ->assertCanSeeTableRecords([$semContrato])
            ->assertCanNotSeeTableRecords([$comContrato]);
    }

    #[Test]
    public function busca_encontra_matriculas_pelo_nome_do_aluno_ou_da_turma(): void
    {
        $turmaAlfa = Turma::factory()->create(['nome' => 'Turma Alfa']);
        $porAluno = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Zuleica Procurada'])->id,
        ]);
        $porTurma = Matricula::factory()->create(['turma_id' => $turmaAlfa->id]);
        $outra = Matricula::factory()->create();

        Livewire::test(ListMatriculas::class)
            ->searchTable('Zuleica')
            ->assertCanSeeTableRecords([$porAluno])
            ->assertCanNotSeeTableRecords([$porTurma, $outra])
            ->searchTable('Turma Alfa')
            ->assertCanSeeTableRecords([$porTurma])
            ->assertCanNotSeeTableRecords([$porAluno]);
    }

    #[Test]
    public function scopes_de_documentos_e_o_model_concordam_com_documentos_faltando_e_rejeitados(): void
    {
        $tipo = TipoDocumento::create(['nome' => 'RG do Aluno', 'flag_obrigatorio' => true]);
        $matricula = Matricula::factory()->create();
        $matricula->tiposDocumentos()->attach($tipo->id);

        // Documento obrigatório ainda não enviado
        $this->assertTrue(Matricula::comDocumentosFaltando()->whereKey($matricula->id)->exists());
        $this->assertFalse(Matricula::semDocumentosFaltando()->whereKey($matricula->id)->exists());
        $this->assertFalse(Matricula::comDocumentosRejeitados()->whereKey($matricula->id)->exists());
        $this->assertSame(1, $matricula->fresh()->getMissingMandatoryDocumentsCount());

        // Enviado: deixa de faltar
        $documento = DocumentoInserido::create([
            'tipo_documento_id' => $tipo->id,
            'matricula_id' => $matricula->id,
            'status' => SituacaoDocumento::EM_ANALISE,
            'arquivo_path' => 'documentos/rg.pdf',
        ]);

        $this->assertFalse(Matricula::comDocumentosFaltando()->whereKey($matricula->id)->exists());
        $this->assertTrue(Matricula::semDocumentosFaltando()->whereKey($matricula->id)->exists());
        $this->assertSame(0, $matricula->fresh()->getMissingMandatoryDocumentsCount());

        // Rejeitado: volta a faltar e passa a contar como rejeitado
        $documento->update(['status' => SituacaoDocumento::REJEITADO, 'observacoes' => 'Ilegível']);

        $this->assertTrue(Matricula::comDocumentosFaltando()->whereKey($matricula->id)->exists());
        $this->assertTrue(Matricula::comDocumentosRejeitados()->whereKey($matricula->id)->exists());

        // O resumo é o mesmo com a relação carregada (listagem) e sem ela (consulta direta)
        $lazy = Matricula::find($matricula->id)->pendencias;
        $eager = Matricula::with(['tiposDocumentos', 'documentoInseridos.tipoDocumento'])->find($matricula->id)->pendencias;

        foreach ([$lazy, $eager] as $pendencias) {
            $this->assertSame(1, $pendencias->documentosFaltantes->count());
            $this->assertSame(1, $pendencias->documentosRejeitados->count());
            $this->assertSame(TipoPendenciaMatricula::cases(), $pendencias->tipos());
        }

        $this->assertSame('1 documento faltando', $eager->rotulo(TipoPendenciaMatricula::DOCUMENTOS_FALTANDO));
        $this->assertSame('1 documento rejeitado', $eager->rotulo(TipoPendenciaMatricula::DOCUMENTOS_REJEITADOS));
    }

    #[Test]
    public function modal_de_pendencias_detalha_cada_problema_escapando_o_conteudo(): void
    {
        $tipo = TipoDocumento::create(['nome' => '<b>Certidão</b>', 'flag_obrigatorio' => true]);
        $matricula = $this->criarMatriculaComPendencia();
        $matricula->tiposDocumentos()->attach($tipo->id);

        $html = view('filament.matriculas.pendencias', [
            'matricula' => $matricula,
            'pendencias' => Matricula::find($matricula->id)->pendencias,
        ])->render();

        $this->assertStringContainsString('Responsável não informado', $html);
        $this->assertStringContainsString('Cadastro incompleto (Aluno)', $html);
        $this->assertStringContainsString('Documentos pendentes', $html);
        $this->assertStringContainsString('&lt;b&gt;Certidão&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Certidão</b>', $html);
    }

    #[Test]
    public function modal_de_pendencias_informa_quando_a_matricula_esta_em_dia(): void
    {
        $matricula = $this->criarMatriculaEmDia();

        $html = view('filament.matriculas.pendencias', [
            'matricula' => $matricula,
            'pendencias' => Matricula::find($matricula->id)->pendencias,
        ])->render();

        $this->assertStringContainsString('Esta matrícula está em dia.', $html);
    }

    #[Test]
    public function resumo_do_topo_conta_pendencias_e_contratos_do_que_esta_na_lista(): void
    {
        $this->criarMatriculaEmDia();
        $this->criarMatriculaComPendencia();
        $this->criarMatriculaComPendencia('cancelada');

        $widget = Livewire::test(MatriculasResumoStats::class, ['pageClass' => ListMatriculas::class, 'activeTab' => 'ativas'])->instance();
        $stats = (new \ReflectionMethod($widget, 'getStats'))->invoke($widget);

        // Aba padrão (Ativas): a cancelada fica de fora
        $this->assertEquals(2, $stats[0]->getValue());
        $this->assertEquals(1, $stats[1]->getValue());
        $this->assertEquals(1, $stats[2]->getValue());
        $this->assertEquals(2, $stats[3]->getValue());
    }

    #[Test]
    public function acoes_secundarias_ficam_no_menu_agrupado_e_obedecem_as_regras_de_visibilidade(): void
    {
        $semContrato = $this->criarMatriculaEmDia();
        $semResponsavel = $this->criarMatriculaComPendencia();
        $comContrato = $this->criarMatriculaEmDia();
        $comContrato->contrato()->create(['valor_total' => 0]);

        Livewire::test(ListMatriculas::class)
            ->assertSee('Mais ações')
            ->assertTableActionVisible('gerarContrato', $semContrato)
            ->assertTableActionHidden('gerarContrato', $semResponsavel)
            ->assertTableActionHidden('gerarContrato', $comContrato)
            ->assertTableActionVisible('inserir_documentos', $semResponsavel)
            ->assertTableActionVisible('edit', $semResponsavel);
    }

    #[Test]
    public function confirmacao_de_aviso_so_e_oferecida_com_pendencia_documental_e_avisa_quando_nao_ha_email(): void
    {
        $tipo = TipoDocumento::create(['nome' => 'Histórico Escolar', 'flag_obrigatorio' => true]);
        $comPendencia = $this->criarMatriculaEmDia();
        $comPendencia->tiposDocumentos()->attach($tipo->id);
        $emDia = $this->criarMatriculaEmDia();

        $lista = Livewire::test(ListMatriculas::class)
            ->assertTableActionVisible('enviar_email_pendencia', $comPendencia)
            ->assertTableActionHidden('enviar_email_pendencia', $emDia);

        // Nenhum usuário com e-mail vinculado ao aluno/responsáveis: o modal explica o problema
        $acao = $lista->instance()->getTable()->getAction('enviar_email_pendencia');
        $conteudo = $acao->record($comPendencia)->getModalContent();

        $this->assertStringContainsString('nenhum e-mail encontrado', $conteudo->render());
    }

    #[Test]
    public function corpo_da_confirmacao_de_aviso_lista_destinatarios_e_documentos_escapando_o_conteudo(): void
    {
        $faltante = TipoDocumento::create(['nome' => '<i>Histórico</i>', 'flag_obrigatorio' => true]);

        $html = view('filament.matriculas.confirmar-aviso', [
            'destinatarios' => collect(['mae@teste.com', 'pai@teste.com']),
            'ultimoEnvio' => now()->setDate(2026, 9, 30)->setTime(14, 5),
            'mensagem' => null,
            'faltantes' => collect([$faltante]),
            'rejeitados' => collect(),
            'cor' => 'warning',
        ])->render();

        $this->assertStringContainsString('mae@teste.com, pai@teste.com', $html);
        $this->assertStringContainsString('30/09/2026 14:05', $html);
        $this->assertStringContainsString('&lt;i&gt;Histórico&lt;/i&gt;', $html);
        $this->assertStringNotContainsString('<i>Histórico</i>', $html);
    }

    #[Test]
    public function relation_manager_de_turmas_mantem_o_filtro_de_situacao_com_padrao_ativa(): void
    {
        $turma = Turma::factory()->create();
        $ativa = Matricula::factory()->create(['turma_id' => $turma->id, 'situacao' => 'ativa']);
        $cancelada = Matricula::factory()->create(['turma_id' => $turma->id, 'situacao' => 'cancelada']);

        Livewire::test(MatriculasRelationManager::class, ['ownerRecord' => $turma, 'pageClass' => EditTurma::class])
            ->assertTableFilterExists('situacao')
            ->assertCanSeeTableRecords([$ativa])
            ->assertCanNotSeeTableRecords([$cancelada]);
    }

    #[Test]
    public function listagem_nao_dispara_queries_por_linha(): void
    {
        $tipo = TipoDocumento::create(['nome' => 'Comprovante', 'flag_obrigatorio' => true]);

        $criar = function (int $quantidade) use ($tipo): void {
            for ($i = 0; $i < $quantidade; $i++) {
                $matricula = $this->criarMatriculaComPendencia();
                $matricula->tiposDocumentos()->attach($tipo->id);
                DocumentoInserido::create([
                    'tipo_documento_id' => $tipo->id,
                    'matricula_id' => $matricula->id,
                    'status' => SituacaoDocumento::REJEITADO,
                    'arquivo_path' => 'documentos/x.pdf',
                ]);
            }
        };

        $contarQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(ListMatriculas::class);
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        // Aquecimento: papéis/permissões do usuário são consultados só na primeira renderização
        $contarQueries();

        $criar(3);
        $comTres = $contarQueries();

        $criar(9);
        $comDoze = $contarQueries();

        // Mais linhas não podem significar mais queries: pendências, ações e colunas usam dados já carregados
        $this->assertSame($comTres, $comDoze);
    }
}

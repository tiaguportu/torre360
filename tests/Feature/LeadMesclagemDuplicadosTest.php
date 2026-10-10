<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Enums\StatusVisitaInteressado;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\InteressadoStatusHistorico;
use App\Models\OrigemInteressado;
use App\Models\PesquisaSatisfacaoVisita;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\LeadDuplicadoDetectorService;
use App\Services\LeadMesclagemService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadMesclagemDuplicadosTest extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $statusInicial;

    private StatusInteressado $statusEmContato;

    private OrigemInteressado $origemPadrao;

    private TipoContatoInteressado $tipoContato;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusInicial = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->statusEmContato = StatusInteressado::create([
            'nome' => 'Em Contato',
            'cor' => 'warning',
            'ordem' => 2,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->origemPadrao = OrigemInteressado::create([
            'nome' => 'Site Institucional',
        ]);

        $this->tipoContato = TipoContatoInteressado::create([
            'nome' => 'Telefone',
            'icone' => 'heroicon-o-phone',
            'ativo' => true,
        ]);

        TipoContatoInteressado::firstOrCreate(
            ['nome' => 'Sistema'],
            ['icone' => 'heroicon-o-cpu-chip', 'ativo' => true]
        );
    }

    private function criarLead(array $dados = []): Interessado
    {
        if (! isset($dados['pessoa_id'])) {
            $dados['pessoa_id'] = Pessoa::create([
                'nome' => 'Contato '.fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'telefone' => '119'.fake()->numerify('########'),
            ])->id;
        }

        return Interessado::create(array_merge([
            'status_interessado_id' => $this->statusInicial->id,
            'origem_interessado_id' => $this->origemPadrao->id,
        ], $dados));
    }

    public function test_detecta_duplicados_por_telefone_com_formatos_diferentes(): void
    {
        $pessoaA = Pessoa::create([
            'nome' => 'Carlos Silva',
            'telefone' => '(11) 98765-4321',
        ]);
        $leadA = $this->criarLead(['pessoa_id' => $pessoaA->id]);

        $pessoaB = Pessoa::create([
            'nome' => 'Carlos E. Silva',
            'telefone' => '11987654321',
        ]);
        $leadB = $this->criarLead(['pessoa_id' => $pessoaB->id]);

        $detector = app(LeadDuplicadoDetectorService::class);

        $this->assertTrue($detector->temDuplicados($leadA));
        $this->assertSame(1, $detector->contarDuplicados($leadA));

        $duplicados = $detector->detectar($leadA);
        $this->assertCount(1, $duplicados);
        $this->assertSame($leadB->id, $duplicados->first()['interessado']->id);
        $this->assertContains('telefone', $duplicados->first()['motivos']);
    }

    public function test_detecta_duplicados_por_cpf(): void
    {
        $pessoa = Pessoa::create([
            'nome' => 'Ana Paula',
            'cpf' => '123.456.789-00',
        ]);
        $leadA = $this->criarLead(['pessoa_id' => $pessoa->id]);
        $leadB = $this->criarLead(['pessoa_id' => $pessoa->id]);

        $detector = app(LeadDuplicadoDetectorService::class);

        $duplicados = $detector->detectar($leadA);
        $this->assertCount(1, $duplicados);
        $this->assertSame($leadB->id, $duplicados->first()['interessado']->id);
        $this->assertContains('cpf', $duplicados->first()['motivos']);
    }

    public function test_detecta_duplicados_por_email_normalizado(): void
    {
        $pessoaA = Pessoa::create([
            'nome' => 'Juliana Mendes',
            'email' => 'JULIANA.MENDES@EMAIL.COM',
        ]);
        $leadA = $this->criarLead(['pessoa_id' => $pessoaA->id]);

        $pessoaB = Pessoa::create([
            'nome' => 'Juliana',
            'email' => '  juliana.mendes@email.com ',
        ]);
        $leadB = $this->criarLead(['pessoa_id' => $pessoaB->id]);

        $detector = app(LeadDuplicadoDetectorService::class);

        $duplicados = $detector->detectar($leadA);
        $this->assertCount(1, $duplicados);
        $this->assertSame($leadB->id, $duplicados->first()['interessado']->id);
        $this->assertContains('email', $duplicados->first()['motivos']);
    }

    public function test_detecta_duplicados_por_aluno_dependente_em_comum(): void
    {
        $leadA = $this->criarLead();
        InteressadoDependente::create([
            'interessado_id' => $leadA->id,
            'nome_crianca' => 'Lucas  Silva',
            'data_nascimento' => '2016-04-12',
        ]);

        $leadB = $this->criarLead();
        InteressadoDependente::create([
            'interessado_id' => $leadB->id,
            'nome_crianca' => 'lucas silva',
            'data_nascimento' => '2016-04-12',
        ]);

        $detector = app(LeadDuplicadoDetectorService::class);

        $duplicados = $detector->detectar($leadA);
        $this->assertCount(1, $duplicados);
        $this->assertSame($leadB->id, $duplicados->first()['interessado']->id);
        $this->assertContains('dependente', $duplicados->first()['motivos']);
    }

    public function test_nao_detecta_a_si_mesmo_nem_leads_sem_correspondencia(): void
    {
        $pessoaA = Pessoa::create(['nome' => 'Pessoa Única', 'telefone' => '11999990001']);
        $leadA = $this->criarLead(['pessoa_id' => $pessoaA->id]);

        $pessoaB = Pessoa::create(['nome' => 'Outra Pessoa', 'telefone' => '11888880002']);
        $this->criarLead(['pessoa_id' => $pessoaB->id]);

        $detector = app(LeadDuplicadoDetectorService::class);

        $this->assertFalse($detector->temDuplicados($leadA));
        $this->assertSame(0, $detector->contarDuplicados($leadA));
    }

    public function test_mesclar_preserva_lead_mais_antigo_por_padrao_e_exclui_origem(): void
    {
        $leadAntigo = $this->criarLead();
        $leadAntigo->forceFill(['created_at' => Carbon::now()->subDays(5)])->saveQuietly();

        $leadNovo = $this->criarLead();

        $servico = app(LeadMesclagemService::class);
        $preservado = $servico->mesclar($leadNovo, $leadAntigo);

        $this->assertSame($leadAntigo->id, $preservado->id);
        $this->assertDatabaseHas('interessado', ['id' => $leadAntigo->id]);
        $this->assertDatabaseMissing('interessado', ['id' => $leadNovo->id]);
    }

    public function test_mesclar_nunca_perde_visitas_nem_pesquisas_nps(): void
    {
        $consultor = User::factory()->create();

        $leadAntigo = $this->criarLead();
        $leadAntigo->forceFill(['created_at' => Carbon::now()->subDays(10)])->saveQuietly();

        $leadNovo = $this->criarLead();

        $visita1 = VisitaInteressado::create([
            'interessado_id' => $leadNovo->id,
            'usuario_id' => $consultor->id,
            'data_hora' => now()->addDays(2),
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        $visita2 = VisitaInteressado::create([
            'interessado_id' => $leadNovo->id,
            'usuario_id' => $consultor->id,
            'data_hora' => now()->subDay(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $pesquisa = PesquisaSatisfacaoVisita::create([
            'visita_interessado_id' => $visita2->id,
            'interessado_id' => $leadNovo->id,
            'token' => PesquisaSatisfacaoVisita::gerarToken(),
            'nota_nps' => 9,
        ]);

        $servico = app(LeadMesclagemService::class);
        $preservado = $servico->mesclar($leadAntigo, $leadNovo);

        $this->assertSame($leadAntigo->id, $preservado->id);

        $this->assertDatabaseHas('visita_interessado', [
            'id' => $visita1->id,
            'interessado_id' => $leadAntigo->id,
        ]);
        $this->assertDatabaseHas('visita_interessado', [
            'id' => $visita2->id,
            'interessado_id' => $leadAntigo->id,
        ]);

        $this->assertDatabaseHas('visita_pesquisa_satisfacao', [
            'id' => $pesquisa->id,
            'interessado_id' => $leadAntigo->id,
            'visita_interessado_id' => $visita2->id,
        ]);
    }

    public function test_mesclar_nunca_perde_documentos_inseridos(): void
    {
        $tipoDoc = TipoDocumento::create([
            'nome' => 'Certidão de Nascimento',
            'categoria' => 'academico',
        ]);

        $leadAntigo = $this->criarLead();
        $leadAntigo->forceFill(['created_at' => Carbon::now()->subDays(10)])->saveQuietly();

        $leadNovo = $this->criarLead();

        $doc = DocumentoInserido::create([
            'interessado_id' => $leadNovo->id,
            'tipo_documento_id' => $tipoDoc->id,
            'status' => SituacaoDocumento::EM_ANALISE,
            'nome_arquivo_original' => 'certidao.pdf',
            'arquivo_path' => 'documentos/certidao.pdf',
        ]);

        $servico = app(LeadMesclagemService::class);
        $servico->mesclar($leadAntigo, $leadNovo);

        $this->assertDatabaseHas('documento_inserido', [
            'id' => $doc->id,
            'interessado_id' => $leadAntigo->id,
        ]);
    }

    public function test_mesclar_unifica_dependentes_com_mesmo_nome_normalizado_sem_duplicar(): void
    {
        $leadAntigo = $this->criarLead();
        $leadAntigo->forceFill(['created_at' => Carbon::now()->subDays(10)])->saveQuietly();

        $depAntigo = InteressadoDependente::create([
            'interessado_id' => $leadAntigo->id,
            'nome_crianca' => 'Pedro  Alvares',
            'data_nascimento' => null,
        ]);

        $leadNovo = $this->criarLead();

        $depNovoDuplicado = InteressadoDependente::create([
            'interessado_id' => $leadNovo->id,
            'nome_crianca' => 'pedro alvares',
            'data_nascimento' => '2015-08-20',
            'turno_preferencia' => 'Manhã',
        ]);

        $depNovoDistinto = InteressadoDependente::create([
            'interessado_id' => $leadNovo->id,
            'nome_crianca' => 'Mariana Alvares',
            'data_nascimento' => '2018-02-10',
        ]);

        $visita = VisitaInteressado::create([
            'interessado_id' => $leadNovo->id,
            'interessado_dependente_id' => $depNovoDuplicado->id,
            'data_hora' => now()->addDay(),
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        $servico = app(LeadMesclagemService::class);
        $preservado = $servico->mesclar($leadAntigo, $leadNovo);

        $this->assertCount(2, $preservado->dependentes);

        $depAntigo->refresh();
        $this->assertEquals('2015-08-20', $depAntigo->data_nascimento->format('Y-m-d'));
        $this->assertSame('Manhã', $depAntigo->turno_preferencia);

        $this->assertDatabaseHas('visita_interessado', [
            'id' => $visita->id,
            'interessado_id' => $leadAntigo->id,
            'interessado_dependente_id' => $depAntigo->id,
        ]);

        $this->assertDatabaseMissing('interessado_dependente', ['id' => $depNovoDuplicado->id]);

        $this->assertDatabaseHas('interessado_dependente', [
            'id' => $depNovoDistinto->id,
            'interessado_id' => $leadAntigo->id,
        ]);
    }

    public function test_mesclar_move_historico_de_contatos_e_transicoes_de_etapa(): void
    {
        $consultor = User::factory()->create();

        $leadAntigo = $this->criarLead();
        $leadAntigo->forceFill(['created_at' => Carbon::now()->subDays(5)])->saveQuietly();

        $leadNovo = $this->criarLead();

        $contato = HistoricoContato::create([
            'interessado_id' => $leadNovo->id,
            'usuario_id' => $consultor->id,
            'tipo_contato_interessado_id' => $this->tipoContato->id,
            'relato' => 'Conversa sobre grade curricular.',
            'data_contato' => now(),
            'automatico' => false,
        ]);

        $transicao = InteressadoStatusHistorico::create([
            'interessado_id' => $leadNovo->id,
            'status_anterior_id' => $this->statusInicial->id,
            'status_novo_id' => $this->statusEmContato->id,
            'usuario_id' => $consultor->id,
            'data_transicao' => now()->subHour(),
        ]);

        $servico = app(LeadMesclagemService::class);
        $servico->mesclar($leadAntigo, $leadNovo);

        $this->assertDatabaseHas('historico_contato', [
            'id' => $contato->id,
            'interessado_id' => $leadAntigo->id,
        ]);

        $this->assertDatabaseHas('interessado_status_historico', [
            'id' => $transicao->id,
            'interessado_id' => $leadAntigo->id,
        ]);
    }

    public function test_mesclar_preserva_tokens_e_dados_de_pre_matricula(): void
    {
        $leadAntigo = $this->criarLead();
        $leadAntigo->forceFill(['created_at' => Carbon::now()->subDays(5)])->saveQuietly();

        $leadNovo = $this->criarLead([
            'token_documentos' => 'token-docs-12345678',
            'token_documentos_expira_em' => now()->addDays(7),
            'token_convite' => 'token-convite-87654321',
            'token_convite_expira_em' => now()->addDays(5),
            'dados_pre_matricula' => ['observacao_familiar' => 'Prefere período da tarde'],
            'dados_pre_matricula_em' => now(),
        ]);

        $servico = app(LeadMesclagemService::class);
        $preservado = $servico->mesclar($leadAntigo, $leadNovo);

        $this->assertSame('token-docs-12345678', $preservado->token_documentos);
        $this->assertSame('token-convite-87654321', $preservado->token_convite);
        $this->assertEquals(['observacao_familiar' => 'Prefere período da tarde'], $preservado->dados_pre_matricula);
    }

    public function test_mesclar_conflito_de_campos_e_concatenacao_de_observacoes(): void
    {
        $origemInstagram = OrigemInteressado::create(['nome' => 'Instagram Ads']);

        $leadAntigo = $this->criarLead([
            'observacoes' => 'Primeira observação feita no lead antigo.',
            'valor_estimado' => null,
        ]);
        $leadAntigo->forceFill(['created_at' => Carbon::now()->subDays(5)])->saveQuietly();

        $leadNovo = $this->criarLead([
            'observacoes' => 'Segunda observação vinda do reenvio.',
            'origem_interessado_id' => $origemInstagram->id,
            'valor_estimado' => 2500.00,
        ]);

        $servico = app(LeadMesclagemService::class);
        $preservado = $servico->mesclar($leadAntigo, $leadNovo);

        $this->assertEquals(2500.00, (float) $preservado->valor_estimado);

        $this->assertStringContainsString('Primeira observação feita no lead antigo.', $preservado->observacoes);
        $this->assertStringContainsString('Segunda observação vinda do reenvio.', $preservado->observacoes);
        $this->assertStringContainsString("Mesclado do Lead #{$leadNovo->id}", $preservado->observacoes);
    }

    public function test_mesclar_registra_auditoria_e_linha_do_tempo(): void
    {
        $usuario = User::factory()->create();

        $leadAntigo = $this->criarLead();
        $leadAntigo->forceFill(['created_at' => Carbon::now()->subDays(5)])->saveQuietly();

        $leadNovo = $this->criarLead();

        $servico = app(LeadMesclagemService::class);
        $servico->mesclar($leadAntigo, $leadNovo, $usuario->id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'crm',
            'subject_id' => $leadAntigo->id,
            'causer_id' => $usuario->id,
        ]);

        $this->assertDatabaseHas('historico_contato', [
            'interessado_id' => $leadAntigo->id,
            'automatico' => true,
        ]);
    }

    public function test_mesclar_com_preferencia_explicita_de_destino(): void
    {
        $leadAntigo = $this->criarLead();
        $leadAntigo->forceFill(['created_at' => Carbon::now()->subDays(5)])->saveQuietly();

        $leadNovo = $this->criarLead();

        $servico = app(LeadMesclagemService::class);
        $preservado = $servico->mesclar($leadAntigo, $leadNovo, null, $leadNovo);

        $this->assertSame($leadNovo->id, $preservado->id);
        $this->assertDatabaseHas('interessado', ['id' => $leadNovo->id]);
        $this->assertDatabaseMissing('interessado', ['id' => $leadAntigo->id]);
    }
}

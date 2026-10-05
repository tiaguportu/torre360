<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Models\Curso;
use App\Models\DocumentoInserido;
use App\Models\IndicacaoInteressado;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoDocumento;
use App\Models\TipoVinculo;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Services\MatriculaOnlineService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matrícula 100% online: ao concluir, o lead de origem é convertido pelo mesmo caminho do Assistente de
 * Matrícula (`InteressadoMatriculaService::registrarConversao`) e só quando a ligação com a família é segura.
 */
class MatriculaOnlineConversaoCrmTest extends TestCase
{
    use RefreshDatabase;

    private Turma $turma;

    private StatusInteressado $novo;

    private StatusInteressado $matriculado;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        TipoVinculo::firstOrCreate(['nome' => 'Responsável Legal']);

        $unidade = Unidade::create(['nome' => 'Unidade Central', 'flag_ativo' => true]);
        $curso = Curso::create(['nome_externo' => 'Ensino Fundamental II', 'nome_interno' => 'EF II', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '7º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-20']);
        $turno = Turno::firstOrCreate(['nome' => 'Matutino'], ['hora_inicio' => '07:30:00', 'hora_fim' => '12:00:00']);

        $this->turma = Turma::create([
            'nome' => '7º Ano A',
            'serie_id' => $serie->id,
            'periodo_letivo_id' => $periodo->id,
            'turno_id' => $turno->id,
            'vagas_maximas' => 30,
        ]);

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 2, 'is_final' => true, 'is_ganho' => true]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(string $nomeAluno, string $emailResponsavel, string $cpfResponsavel): array
    {
        return [
            'turma_id' => $this->turma->id,
            'aluno' => ['nome' => $nomeAluno, 'cpf' => null],
            'responsavel' => ['nome' => 'Responsável da Matrícula', 'cpf' => $cpfResponsavel, 'email' => $emailResponsavel],
        ];
    }

    private function criarLead(Pessoa $contato, string $nomeCrianca): Interessado
    {
        $lead = Interessado::create([
            'pessoa_id' => $contato->id,
            'status_interessado_id' => $this->novo->id,
            'origem_interessado_id' => $this->origem->id,
            'dados_pre_matricula' => ['rascunho' => true],
        ]);

        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => $nomeCrianca]);

        return $lead;
    }

    public function test_matricula_online_converte_o_lead_pelo_caminho_completo(): void
    {
        $responsavel = Pessoa::create(['nome' => 'Carlos Souza', 'cpf' => '12345678909', 'email' => 'carlos@example.com']);
        $lead = $this->criarLead($responsavel, 'Pedro Souza');

        $indicadora = Pessoa::create(['nome' => 'Família Indicadora', 'email' => 'indicadora@example.com']);
        $indicacao = IndicacaoInteressado::create([
            'indicador_pessoa_id' => $indicadora->id,
            'interessado_id' => $lead->id,
            'status' => IndicacaoInteressado::STATUS_PENDENTE,
        ]);

        app(MatriculaOnlineService::class)->processarMatricula($this->dados('Pedro Souza', 'carlos@example.com', '123.456.789-09'));

        $lead->refresh();
        $this->assertSame($this->matriculado->id, $lead->status_interessado_id, 'O lead deixa de ser ativo (régua e alertas param).');
        $this->assertNotNull($lead->data_conversao);
        $this->assertNull($lead->dados_pre_matricula, 'O rascunho de pré-matrícula é descartado (LGPD).');
        $this->assertSame(IndicacaoInteressado::STATUS_MATRICULADO, $indicacao->fresh()->status, 'A indicação passa a elegível a recompensa.');
    }

    public function test_documentos_da_pre_admissao_migram_para_a_matricula_online(): void
    {
        $responsavel = Pessoa::create(['nome' => 'Carlos Souza', 'cpf' => '12345678909', 'email' => 'carlos@example.com']);
        $lead = $this->criarLead($responsavel, 'Pedro Souza');

        $tipo = TipoDocumento::create(['nome' => 'Certidão', 'flag_obrigatorio' => true, 'status' => 'ativo']);
        $documento = DocumentoInserido::create([
            'interessado_id' => $lead->id,
            'tipo_documento_id' => $tipo->id,
            'arquivo_path' => 'documentos_candidatos/'.$lead->id.'/certidao.pdf',
            'nome_arquivo_original' => 'certidao.pdf',
            'status' => SituacaoDocumento::EM_ANALISE,
        ]);

        $matricula = app(MatriculaOnlineService::class)->processarMatricula($this->dados('Pedro Souza', 'carlos@example.com', '123.456.789-09'));

        $this->assertSame($matricula->id, $documento->fresh()->matricula_id);
    }

    public function test_lead_cujo_contato_e_o_proprio_aluno_tambem_e_convertido(): void
    {
        $aluno = Pessoa::create(['nome' => 'Joana Adulta', 'email' => 'joana@example.com']);
        $lead = $this->criarLead($aluno, 'Joana Adulta');

        // O serviço localiza a pessoa do aluno pelo CPF informado; o lead aponta para a mesma pessoa.
        $aluno->update(['cpf' => '52998224725']);

        app(MatriculaOnlineService::class)->processarMatricula([
            'turma_id' => $this->turma->id,
            'aluno' => ['nome' => 'Joana Adulta', 'cpf' => '529.982.247-25'],
            'responsavel' => ['nome' => 'Responsável Joana', 'cpf' => '987.654.321-00', 'email' => 'responsavel.joana@example.com'],
        ]);

        $this->assertSame($this->matriculado->id, $lead->fresh()->status_interessado_id);
    }

    public function test_nome_parecido_de_outra_familia_nao_converte_o_lead_errado(): void
    {
        // "Ana" está dentro de "Mariana": a busca antiga por `like %nome%` converteria este lead.
        $outraFamilia = Pessoa::create(['nome' => 'Outra Família', 'cpf' => '98765432100', 'email' => 'outra@example.com']);
        $leadAlheio = $this->criarLead($outraFamilia, 'Mariana Costa');

        app(MatriculaOnlineService::class)->processarMatricula($this->dados('Ana', 'carlos.novo@example.com', '111.444.777-35'));

        $leadAlheio->refresh();
        $this->assertSame($this->novo->id, $leadAlheio->status_interessado_id);
        $this->assertNull($leadAlheio->data_conversao);
        $this->assertNotNull($leadAlheio->dados_pre_matricula);
    }

    public function test_aluno_de_mesmo_nome_em_outra_familia_nao_converte_o_lead_alheio(): void
    {
        $outraFamilia = Pessoa::create(['nome' => 'Outra Família', 'cpf' => '98765432100', 'email' => 'outra@example.com']);
        $leadAlheio = $this->criarLead($outraFamilia, 'Pedro Souza');

        app(MatriculaOnlineService::class)->processarMatricula($this->dados('Pedro Souza', 'carlos@example.com', '123.456.789-09'));

        $this->assertNull($leadAlheio->fresh()->data_conversao);
    }

    public function test_sem_lead_de_origem_a_matricula_segue_normalmente(): void
    {
        $matricula = app(MatriculaOnlineService::class)->processarMatricula($this->dados('Aluno Sem Lead', 'semlead@example.com', '123.456.789-09'));

        $this->assertNotNull($matricula->id);
        $this->assertSame(0, Interessado::whereNotNull('data_conversao')->count());
    }

    public function test_dependente_de_nome_identico_e_mesmo_contato_converte_mesmo_com_pessoa_duplicada(): void
    {
        // O lead aponta para uma pessoa duplicada (mesmo e-mail com caixa diferente), não a que o serviço resolve.
        $duplicada = Pessoa::create(['nome' => 'Carlos Duplicado', 'email' => 'Carlos@Example.com']);
        $lead = $this->criarLead($duplicada, 'Pedro  SOUZA');

        $responsavel = Pessoa::create(['nome' => 'Carlos Souza', 'cpf' => '12345678909', 'email' => 'carlos@example.com']);
        $aluno = Pessoa::create(['nome' => 'Pedro Souza']);

        $metodo = new \ReflectionMethod(MatriculaOnlineService::class, 'localizarLeadParaConversao');
        $encontrado = $metodo->invoke(app(MatriculaOnlineService::class), $responsavel, $aluno);

        $this->assertSame($lead->id, $encontrado?->id);
    }
}

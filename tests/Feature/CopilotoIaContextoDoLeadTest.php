<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoriaExigenciaDocumento;
use App\Enums\SituacaoDocumento;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * O que o Copiloto WhatsApp IA (e o Dossiê) sabem do lead: data de hoje, andamento do atendimento, resultado dos
 * contatos e situação da pré-admissão, sem carregar relacionamentos por registro.
 */
class CopilotoIaContextoDoLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira, 21/10/2026, 10h: as datas relativas do prompt dependem de "hoje".
        Carbon::setTestNow(Carbon::create(2026, 10, 21, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function lead(array $atributos = []): Interessado
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Mariana Oliveira']);

        $lead = Interessado::create($atributos + [
            'pessoa_id' => $pessoa->id,
            'usuario_id' => User::factory()->create()->id,
            'status_interessado_id' => StatusInteressado::factory()->create(['nome' => 'Novo Contato'])->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'temperatura' => 'morno',
        ]);

        InteressadoDependente::create([
            'interessado_id' => $lead->id,
            'nome_crianca' => 'Lucas Oliveira',
            'data_nascimento' => now()->subYears(7)->toDateString(),
        ]);

        return $lead;
    }

    private function contato(Interessado $lead, string $relato, Carbon $quando, array $extras = []): HistoricoContato
    {
        return HistoricoContato::create($extras + [
            'interessado_id' => $lead->id,
            'usuario_id' => User::factory()->create(['name' => 'Camila Atendente'])->id,
            'tipo_contato_interessado_id' => TipoContatoInteressado::firstOrCreate(['nome' => 'WhatsApp'])->id,
            'relato' => $relato,
            'data_contato' => $quando,
        ]);
    }

    /** Roda o Copiloto com o Gemini mockado e devolve tudo o que foi enviado a ele (instrução + conteúdo). */
    private function textoDoCopiloto(Interessado $lead, string $objetivo = 'reativacao'): string
    {
        $enviado = '';
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->andReturnUsing(function (array $payload) use (&$enviado): array {
            $enviado .= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['candidates' => [['content' => ['parts' => [['text' => 'Olá, Mariana!']]], 'finishReason' => 'STOP']]];
        });
        $this->app->instance(GeminiAgentService::class, $mock);

        app(CrmIaVendasService::class)->gerarMensagemCopiloto($lead->fresh(), $objetivo);

        return $enviado;
    }

    public function test_o_prompt_informa_a_data_de_hoje_com_o_dia_da_semana(): void
    {
        $enviado = $this->textoDoCopiloto($this->lead());

        $this->assertStringContainsString('Data de hoje: 21/10/2026 (quarta-feira)', $enviado);
    }

    public function test_ultima_interacao_ignora_registros_automaticos(): void
    {
        $lead = $this->lead();
        $this->contato($lead, 'Mãe pediu valores.', now()->subDays(12));
        $this->contato($lead, 'E-mail da régua de relacionamento.', now()->subDay(), ['automatico' => true]);

        $enviado = $this->textoDoCopiloto($lead);

        $this->assertStringContainsString('Última interação registrada pela equipe: 09/10/2026 (há 12 dias)', $enviado);
        $this->assertStringContainsString('E-mail da régua de relacionamento.', $enviado, 'O registro automático segue no histórico.');
    }

    public function test_lead_sem_interacao_humana_diz_isso_em_vez_de_inventar_uma_data(): void
    {
        $lead = $this->lead();
        $this->contato($lead, 'Resumo gerado pela IA.', now()->subDays(2), ['automatico' => true]);

        $this->assertStringContainsString(
            'Última interação registrada pela equipe: nenhuma até o momento',
            $this->textoDoCopiloto($lead),
        );
    }

    public function test_proximo_contato_combinado_aparece_com_o_prazo_ou_o_atraso(): void
    {
        $this->assertStringContainsString(
            'Próximo contato combinado: 24/10/2026 às 15:30 (em 3 dias)',
            $this->textoDoCopiloto($this->lead(['data_proximo_contato' => Carbon::create(2026, 10, 24, 15, 30)])),
        );

        $this->assertStringContainsString(
            'Próximo contato combinado: 19/10/2026 às 09:00 (há 2 dias)',
            $this->textoDoCopiloto($this->lead(['data_proximo_contato' => Carbon::create(2026, 10, 19, 9, 0)])),
        );

        $this->assertStringContainsString(
            'Próximo contato combinado: nenhum agendado',
            $this->textoDoCopiloto($this->lead()),
        );
    }

    public function test_resultado_e_duracao_do_contato_vao_junto_do_relato(): void
    {
        $lead = $this->lead();
        $this->contato($lead, 'Ligação sobre o integral.', now()->subDays(3), ['resultado' => 'agendou_visita', 'duracao_minutos' => 12]);
        $this->contato($lead, 'Tentativa sem resposta.', now()->subDays(2));

        $enviado = $this->textoDoCopiloto($lead);

        $this->assertStringContainsString('WhatsApp por Camila Atendente (resultado: Agendou Visita; duração: 12 min): Ligação sobre o integral.', $enviado);
        $this->assertStringContainsString('WhatsApp por Camila Atendente: Tentativa sem resposta.', $enviado, 'Sem resultado nem duração o formato não muda.');
    }

    public function test_pre_admissao_nao_aparece_para_quem_nao_foi_convidado(): void
    {
        $enviado = $this->textoDoCopiloto($this->lead());

        $this->assertStringNotContainsString('PRÉ-ADMISSÃO', $enviado);
    }

    public function test_pre_admissao_lista_o_que_falta_sem_expor_o_nome_completo_do_aluno(): void
    {
        $lead = $this->lead(['token_documentos' => 'token-de-teste', 'token_documentos_expira_em' => now()->addDays(5)]);

        $certidao = TipoDocumento::create(['nome' => 'Certidão de nascimento', 'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO]);
        TipoDocumento::create(['nome' => 'Comprovante de residência', 'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO]);
        TipoDocumento::create(['nome' => 'Foto 3x4', 'categoria_exigencia' => CategoriaExigenciaDocumento::OPCIONAL]);

        DocumentoInserido::create([
            'tipo_documento_id' => $certidao->id,
            'interessado_id' => $lead->id,
            'arquivo_path' => 'documentos/certidao.pdf',
            'status' => SituacaoDocumento::EM_ANALISE,
        ]);

        $enviado = $this->textoDoCopiloto($lead, 'fechamento');

        $this->assertStringContainsString('PRÉ-ADMISSÃO (PORTAL DE DOCUMENTOS)', $enviado);
        $this->assertStringContainsString('Dados cadastrais: ainda não confirmados pela família', $enviado);
        $this->assertStringContainsString('Documentos obrigatórios do contrato: 1 de 2 entregues', $enviado);
        $this->assertStringContainsString('Faltam ou precisam ser reenviados: Comprovante de residência.', $enviado);
        $this->assertStringNotContainsString('Foto 3x4', $enviado, 'Documento opcional não é cobrado da família.');
        $this->assertStringNotContainsString('Oliveira', $enviado, 'O sobrenome da família não pode chegar à IA.');
    }

    public function test_pre_admissao_completa_diz_que_nada_falta(): void
    {
        $lead = $this->lead(['dados_pre_matricula' => ['responsaveis' => [['nome' => 'Mariana']]], 'dados_pre_matricula_em' => now()->subDay()]);

        $certidao = TipoDocumento::create(['nome' => 'Certidão de nascimento', 'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO]);
        DocumentoInserido::create(['tipo_documento_id' => $certidao->id, 'interessado_id' => $lead->id, 'arquivo_path' => 'documentos/certidao.pdf', 'status' => SituacaoDocumento::VERIFICADO]);

        $enviado = $this->textoDoCopiloto($lead, 'fechamento');

        $this->assertStringContainsString('Dados cadastrais: confirmados pela família em 20/10/2026', $enviado);
        $this->assertStringContainsString('Documentos obrigatórios do contrato: 1 de 1 entregues (1 aprovado(s), 0 em análise', $enviado);
        $this->assertStringContainsString('Todos os documentos do contrato já foram entregues.', $enviado);
    }

    /**
     * Regressão: o Copiloto carregava só `historicos`, e o tipo e o atendente de cada contato (e a origem) vinham em
     * consultas extras por registro. Com o modo estrito de lazy loading (ligado nos testes) isso lança exceção.
     */
    public function test_copiloto_nao_carrega_relacionamentos_por_registro_do_historico(): void
    {
        $lead = $this->lead();

        foreach (range(1, 4) as $i) {
            $this->contato($lead, "Contato {$i}.", now()->subDays($i));
        }

        $enviado = $this->textoDoCopiloto($lead);

        $this->assertStringContainsString('Contato 4.', $enviado);
        $this->assertStringContainsString('Origem: Site', $enviado);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CategoriaExigenciaDocumento;
use App\Enums\SituacaoDocumento;
use App\Enums\StatusListaEspera;
use App\Enums\StatusPropostaComercial;
use App\Enums\StatusVisitaInteressado;
use App\Models\Curso;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\ListaEsperaMatricula;
use App\Models\OrigemInteressado;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\PropostaComercial;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\TipoDocumento;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

    // ─── Pesquisa pós-visita, propostas e lista de espera ────────

    private function visita(Interessado $lead, array $atributos = []): VisitaInteressado
    {
        return VisitaInteressado::create($atributos + [
            'interessado_id' => $lead->id,
            'usuario_id' => User::factory()->create()->id,
            'data_hora' => now()->subDays(2),
            'status' => StatusVisitaInteressado::Realizada,
        ]);
    }

    private function proposta(Interessado $lead, array $atributos = []): PropostaComercial
    {
        $unidade = Unidade::first() ?? Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        $curso = Curso::first() ?? Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'Ensino Fundamental']);
        $serie = Serie::first() ?? Serie::create(['curso_id' => $curso->id, 'nome' => '2º Ano', 'sistema_avaliacao' => 'Nota']);

        return PropostaComercial::create($atributos + [
            'codigo' => PropostaComercial::gerarCodigo(),
            'unidade_id' => $unidade->id,
            'curso_id' => $curso->id,
            'serie_id' => $serie->id,
            'interessado_id' => $lead->id,
            'responsavel_nome' => 'Mariana Oliveira',
            'valor_tabela_mensal' => 1200.00,
            'valor_desconto_mensal' => 144.00,
            'valor_liquido_mensal' => 1056.00,
            'valor_total_anual' => 12672.00,
            'quantidade_parcelas' => 12,
            'status' => StatusPropostaComercial::Aprovada,
            'solicitado_por_user_id' => User::factory()->create()->id,
            'validade' => now()->addDays(5),
        ]);
    }

    private function entradaNaListaDeEspera(Interessado $lead, array $atributos = []): ListaEsperaMatricula
    {
        return ListaEsperaMatricula::create($atributos + [
            'turma_id' => Turma::factory()->create(['nome' => '2º Ano A'])->id,
            'periodo_letivo_id' => PeriodoLetivo::factory()->create(['nome' => '2027'])->id,
            'pessoa_id' => $lead->pessoa_id,
            'interessado_id' => $lead->id,
            'interessado_dependente_id' => $lead->dependentes()->first()->id,
            'status' => StatusListaEspera::Aguardando,
        ]);
    }

    public function test_pesquisa_pos_visita_respondida_traz_as_notas_e_o_comentario_sem_dados_pessoais(): void
    {
        $lead = $this->lead();
        $this->visita($lead)->pesquisa()->create([
            'interessado_id' => $lead->id,
            'token' => 'token-pesquisa-1',
            'nota_nps' => 6,
            'nota_atendimento' => 5,
            'nota_infraestrutura' => 3,
            'nota_proposta_pedagogica' => 4,
            'comentario' => 'Gostamos da escola, mas achei caro. Me liguem no (21) 97777-6666.',
            'respondido_em' => now()->subDay(),
        ]);

        $enviado = $this->textoDoCopiloto($lead, 'quebra_objecao');

        $this->assertStringContainsString('Pesquisa pós-visita respondida em 20/10/2026: NPS 6/10 (Detrator), atendimento 5/5, infraestrutura 3/5, proposta pedagógica 4/5', $enviado);
        $this->assertStringContainsString('comentário da família: «Gostamos da escola, mas achei caro. Me liguem no [telefone omitido].»', $enviado);
        $this->assertStringNotContainsString('97777-6666', $enviado);
    }

    public function test_pesquisa_enviada_e_ainda_sem_resposta_aparece_como_pendente(): void
    {
        $lead = $this->lead();
        $this->visita($lead)->pesquisa()->create(['interessado_id' => $lead->id, 'token' => 'token-pesquisa-2']);

        $this->assertStringContainsString(
            'Pesquisa pós-visita: enviada, ainda sem resposta da família',
            $this->textoDoCopiloto($lead),
        );
    }

    public function test_visita_sem_pesquisa_nao_ganha_trecho_de_pesquisa(): void
    {
        $lead = $this->lead();
        $this->visita($lead);

        $this->assertStringNotContainsString('Pesquisa pós-visita', $this->textoDoCopiloto($lead));
    }

    public function test_comentario_longo_da_pesquisa_e_cortado(): void
    {
        $lead = $this->lead();
        $this->visita($lead)->pesquisa()->create([
            'interessado_id' => $lead->id,
            'token' => 'token-pesquisa-3',
            'nota_nps' => 10,
            'comentario' => str_repeat('a', 900),
            'respondido_em' => now(),
        ]);

        $enviado = $this->textoDoCopiloto($lead);

        $this->assertStringContainsString(str_repeat('a', 500).'...', $enviado);
        $this->assertStringNotContainsString(str_repeat('a', 501), $enviado);
    }

    public function test_proposta_em_aberto_mostra_situacao_e_validade_mas_nunca_valores(): void
    {
        $lead = $this->lead();
        $this->proposta($lead, ['codigo' => 'PROP-TESTE-1']);

        $enviado = $this->textoDoCopiloto($lead, 'fechamento');

        $this->assertStringContainsString('PROPOSTAS COMERCIAIS', $enviado);
        $this->assertStringContainsString('Proposta PROP-TESTE-1: Aprovada pela Alçada, criada em 21/10/2026, válida até 26/10/2026 (em 5 dias)', $enviado);
        foreach (['1056', '1.056', '1200', '1.200', '12672', '12.672', '144,00', 'Mariana Oliveira'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $enviado, "Dado da proposta não pode ir à IA: {$proibido}.");
        }
    }

    public function test_proposta_vencida_e_destacada_e_proposta_encerrada_nao_fala_de_validade(): void
    {
        $lead = $this->lead();
        $this->proposta($lead, ['codigo' => 'PROP-VENCIDA', 'status' => StatusPropostaComercial::AguardandoAprovacao, 'validade' => now()->subDays(3)]);
        $this->proposta($lead, ['codigo' => 'PROP-FECHADA', 'status' => StatusPropostaComercial::Convertida, 'validade' => now()->subDays(30)]);

        $enviado = $this->textoDoCopiloto($lead, 'fechamento');

        $this->assertStringContainsString('Proposta PROP-VENCIDA: Aguardando Aprovação, criada em 21/10/2026, VENCIDA em 18/10/2026 (há 3 dias)', $enviado);
        $this->assertStringContainsString('Proposta PROP-FECHADA: Convertida em Matrícula, criada em 21/10/2026', $enviado);
        $this->assertStringNotContainsString('PROP-FECHADA: Convertida em Matrícula, criada em 21/10/2026, ', $enviado, 'Proposta já encerrada não tem validade a cobrar.');
    }

    public function test_so_as_propostas_mais_recentes_vao_para_a_ia(): void
    {
        $lead = $this->lead();
        foreach (range(1, 7) as $n) {
            $this->proposta($lead, ['codigo' => "PROP-N{$n}"]);
        }

        $enviado = $this->textoDoCopiloto($lead, 'fechamento');

        foreach ([3, 4, 5, 6, 7] as $n) {
            $this->assertStringContainsString("PROP-N{$n}:", $enviado);
        }
        foreach ([1, 2] as $n) {
            $this->assertStringNotContainsString("PROP-N{$n}:", $enviado);
        }
    }

    public function test_lead_sem_proposta_e_sem_lista_de_espera_nao_ganha_esses_blocos(): void
    {
        $enviado = $this->textoDoCopiloto($this->lead());

        $this->assertStringNotContainsString('PROPOSTAS COMERCIAIS', $enviado);
        $this->assertStringNotContainsString('LISTA DE ESPERA', $enviado);
    }

    public function test_lista_de_espera_mostra_turma_aluno_so_com_o_primeiro_nome_e_situacao(): void
    {
        $lead = $this->lead();
        $this->entradaNaListaDeEspera($lead, ['status' => StatusListaEspera::Notificado, 'notificado_em' => now()->subDay()]);

        $enviado = $this->textoDoCopiloto($lead, 'fechamento');

        $this->assertStringContainsString('LISTA DE ESPERA DE MATRÍCULA', $enviado);
        $this->assertStringContainsString('- 2º Ano A (2027) para Lucas: Notificado, na lista desde 21/10/2026, avisada da vaga em 20/10/2026', $enviado);
        $this->assertStringNotContainsString('Lucas Oliveira', $enviado);
    }

    // ─── Dossiê em cache ─────────────────────────────────────────

    public function test_dossie_em_cache_e_descartado_quando_algo_que_a_ia_le_muda(): void
    {
        $lead = $this->lead();
        $visita = $this->visita($lead);
        $pesquisa = $visita->pesquisa()->create(['interessado_id' => $lead->id, 'token' => 'token-cache']);
        $proposta = $this->proposta($lead);
        $espera = $this->entradaNaListaDeEspera($lead);
        $dependente = $lead->dependentes()->first();
        $certidao = TipoDocumento::create(['nome' => 'Certidão', 'categoria_exigencia' => CategoriaExigenciaDocumento::OBRIGATORIO_CONTRATO]);

        $mudancas = [
            'lead: próximo contato' => fn () => $lead->update(['data_proximo_contato' => now()->addDays(3)]),
            'lead: observações' => fn () => $lead->update(['observacoes' => 'Prefere o período integral.']),
            'lead: temperatura' => fn () => $lead->update(['temperatura' => 'quente']),
            'visita nova' => fn () => $this->visita($lead, ['data_hora' => now()->addDays(4), 'status' => StatusVisitaInteressado::Agendada]),
            'visita remarcada' => fn () => $visita->update(['data_hora' => now()->addDays(5)]),
            'pesquisa respondida' => fn () => $pesquisa->update(['nota_nps' => 9, 'respondido_em' => now()]),
            'proposta aceita' => fn () => $proposta->update(['status' => StatusPropostaComercial::AceitaPelaFamilia]),
            'lista de espera notificada' => fn () => $espera->update(['status' => StatusListaEspera::Notificado]),
            'dependente novo' => fn () => InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Sofia Oliveira']),
            'documento enviado' => fn () => DocumentoInserido::create(['tipo_documento_id' => $certidao->id, 'interessado_id' => $lead->id, 'arquivo_path' => 'documentos/c.pdf', 'status' => SituacaoDocumento::EM_ANALISE]),
            'visita apagada' => fn () => $visita->delete(),
            'dependente apagado' => fn () => $dependente->delete(),
        ];

        $chave = CrmIaVendasService::chaveCacheDossie($lead->id);

        foreach ($mudancas as $descricao => $mudar) {
            Cache::put($chave, ['dossie_markdown' => 'dossiê antigo'], 600);

            $mudar();

            $this->assertFalse(Cache::has($chave), "O dossiê em cache sobreviveu a: {$descricao}.");
        }
    }

    public function test_dossie_em_cache_sobrevive_a_marcadores_internos_que_a_ia_nao_le(): void
    {
        $lead = $this->lead();
        $chave = CrmIaVendasService::chaveCacheDossie($lead->id);
        Cache::put($chave, ['dossie_markdown' => 'dossiê vigente'], 600);

        $lead->update(['lead_score_atualizado_em' => now()]);
        $lead->update(['token_documentos_expira_em' => now()->addDays(7)]);

        $this->assertTrue(Cache::has($chave), 'Data do cálculo do score e validade do token não entram no prompt.');
    }

    public function test_dossie_em_cache_de_um_lead_nao_e_afetado_pela_mudanca_em_outro(): void
    {
        $lead = $this->lead();
        $outro = $this->lead();
        Cache::put(CrmIaVendasService::chaveCacheDossie($lead->id), ['dossie_markdown' => 'dossiê do primeiro'], 600);

        $this->visita($outro);
        $outro->update(['temperatura' => 'quente']);

        $this->assertTrue(Cache::has(CrmIaVendasService::chaveCacheDossie($lead->id)));
    }

    // ─── Aviso de contingência ───────────────────────────────────

    public function test_redigir_mensagem_diz_se_veio_da_ia_ou_do_texto_de_contingencia(): void
    {
        $lead = $this->lead();

        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->twice()->andReturn(
            ['candidates' => [['content' => ['parts' => [['text' => 'Olá, Mariana! Tudo bem?']]], 'finishReason' => 'STOP']]],
            ['candidates' => [['content' => ['parts' => [['text' => '']]], 'finishReason' => 'SAFETY']]],
        );
        $this->app->instance(GeminiAgentService::class, $mock);
        $servico = app(CrmIaVendasService::class);

        $daIa = $servico->redigirMensagemCopiloto($lead->fresh(), 'primeiro_contato');
        $this->assertSame(['mensagem' => 'Olá, Mariana! Tudo bem?', 'contingencia' => false], $daIa);

        $contingencia = $servico->redigirMensagemCopiloto($lead->fresh(), 'primeiro_contato');
        $this->assertTrue($contingencia['contingencia'], 'Uma resposta sem texto não pode virar mensagem em branco.');
        $this->assertStringContainsString('Mariana', $contingencia['mensagem']);
    }

    public function test_gerar_mensagem_copiloto_continua_devolvendo_so_o_texto(): void
    {
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')->once()->andReturn(['candidates' => [['content' => ['parts' => [['text' => 'Texto da IA.']]], 'finishReason' => 'STOP']]]);
        $this->app->instance(GeminiAgentService::class, $mock);

        $this->assertSame('Texto da IA.', app(CrmIaVendasService::class)->gerarMensagemCopiloto($this->lead(), 'primeiro_contato'));
    }
}

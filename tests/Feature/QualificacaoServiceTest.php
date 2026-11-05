<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\InteressadoQualificacao;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Qualificacao\QualificacaoService;
use App\Support\Qualificacao\Roteiro;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Roteiro de qualificação do lead (SDR, Lote 1): estados dos campos, o que perguntar a seguir, gravação sem sobrescrever,
 * o gate "qualificado para a visita", os avisos objetivos e a sugestão de etapa.
 *
 * "Hoje" é 05/11/2026, então o corte etário (31/03) do próximo ingresso é 31/03/2027.
 */
class QualificacaoServiceTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private QualificacaoService $servico;

    private StatusInteressado $novo;

    private OrigemInteressado $origem;

    private User $consultor;

    private ?Curso $curso = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-05 10:00:00'));
        $this->servico = app(QualificacaoService::class);
        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
        $this->consultor = User::factory()->create();
    }

    private function serie(string $nome = '1º Ano', ?int $idadeMinima = null): Serie
    {
        $this->curso ??= Curso::create([
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'Ensino Fundamental',
            'unidade_id' => Unidade::create(['nome' => 'Unidade Sede'])->id,
        ]);

        return Serie::create(['nome' => $nome, 'curso_id' => $this->curso->id, 'sistema_avaliacao' => 'Nota', 'idade_minima' => $idadeMinima]);
    }

    private function lead(): Interessado
    {
        return Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'usuario_id' => $this->consultor->id,
            'status_interessado_id' => $this->novo->id,
            'origem_interessado_id' => $this->origem->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function aluno(Interessado $lead, array $atributos = []): InteressadoDependente
    {
        return InteressadoDependente::create($atributos + ['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas']);
    }

    /** Responde todos os campos do gate de um lead com um aluno, pelo serviço. */
    private function qualificar(Interessado $lead, InteressadoDependente $aluno): void
    {
        $aluno->update([
            'serie_id' => $this->serie('2º Ano')->id,
            'data_nascimento' => '2019-05-10',
            'turno_preferencia' => 'Manhã',
            'situacao_atual' => 'primeira_escola',
        ]);

        foreach ([
            'bairro' => 'Centro',
            'motivo_busca' => 'primeira_escola',
            'decisor_tipo' => 'sozinho',
            'decisor_vai_visita' => 'sim',
            'prazo_decisao' => 'este_mes',
        ] as $chave => $valor) {
            $this->assertSame(QualificacaoService::GRAVADO, $this->servico->registrar($lead, $chave, $valor, InteressadoQualificacao::FONTE_CONSULTOR));
        }
    }

    public function test_lead_sem_nada_nao_iniciou_o_roteiro(): void
    {
        $lead = $this->lead();

        $avaliacao = $this->servico->avaliar($lead);

        $this->assertSame(InteressadoQualificacao::RESULTADO_NAO_INICIADA, $avaliacao['resultado']);
        $this->assertFalse($avaliacao['gate_ok']);
        $this->assertSame(0, $avaliacao['respondidos']);
        $this->assertNull($this->servico->qualificacaoDe($lead), 'Lead sem resposta não ganha linha de qualificação.');
    }

    public function test_dados_do_cadastro_contam_e_o_aluno_cria_a_linha_de_qualificacao(): void
    {
        $lead = $this->lead();
        $this->aluno($lead, ['serie_id' => $this->serie()->id, 'turno_preferencia' => 'Tarde']);

        $estados = $this->servico->estados($lead->fresh());

        $this->assertSame(Roteiro::ESTADO_RESPONDIDO, $estados['serie_id']['estado']);
        $this->assertSame(Roteiro::ESTADO_RESPONDIDO, $estados['turno_preferencia']['estado']);
        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $estados['data_nascimento']['estado']);
        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $estados['decisor_tipo']['estado']);

        // O gancho do aluno reavaliou o lead: começou o roteiro.
        $this->assertSame(InteressadoQualificacao::RESULTADO_EM_QUALIFICACAO, $lead->qualificacao()->first()->resultado);
    }

    public function test_escola_atual_so_se_aplica_a_quem_estuda_em_outra_escola(): void
    {
        $lead = $this->lead();
        $aluno = $this->aluno($lead, ['situacao_atual' => 'primeira_escola']);

        $this->assertSame(Roteiro::ESTADO_NAO_APLICAVEL, $this->servico->estados($lead->fresh())['escola_atual']['estado']);

        $aluno->update(['situacao_atual' => 'em_outra_escola']);

        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $this->servico->estados($lead->fresh())['escola_atual']['estado']);
        $this->assertNotContains('escola_atual', $this->servico->avaliar($lead->fresh())['faltando'], 'A escola atual não faz parte do gate.');
    }

    public function test_registrar_grava_com_procedencia_e_nao_sobrescreve(): void
    {
        $lead = $this->lead();

        $resultado = $this->servico->registrar($lead, 'prazo_decisao', 'este_mes', InteressadoQualificacao::FONTE_IA_CONFIRMADA, '  Preciso decidir até o fim do mês  ', $this->consultor->id);

        $this->assertSame(QualificacaoService::GRAVADO, $resultado);

        $qualificacao = $lead->qualificacao()->first();
        $this->assertSame('este_mes', $qualificacao->prazo_decisao);
        $this->assertSame(InteressadoQualificacao::FONTE_IA_CONFIRMADA, $qualificacao->campos['prazo_decisao']['fonte']);
        $this->assertSame('Preciso decidir até o fim do mês', $qualificacao->campos['prazo_decisao']['evidencia']);
        $this->assertSame($this->consultor->id, $qualificacao->campos['prazo_decisao']['atualizado_por']);

        // Segunda resposta diferente: conflito, nada muda.
        $this->assertSame(QualificacaoService::CONFLITO, $this->servico->registrar($lead->fresh(), 'prazo_decisao', 'esta_semana', InteressadoQualificacao::FONTE_IA_CONFIRMADA));
        $this->assertSame('este_mes', $lead->qualificacao()->first()->prazo_decisao);

        // Só com confirmação explícita de quem revisa.
        $this->assertSame(QualificacaoService::GRAVADO, $this->servico->registrar($lead->fresh(), 'prazo_decisao', 'esta_semana', InteressadoQualificacao::FONTE_CONSULTOR, sobrescrever: true));
        $this->assertSame('esta_semana', $lead->qualificacao()->first()->prazo_decisao);
    }

    public function test_registrar_recusa_valor_fora_da_lista_e_campo_desconhecido(): void
    {
        $lead = $this->lead();

        $this->assertSame(QualificacaoService::INVALIDO, $this->servico->registrar($lead, 'decisor_tipo', 'o_gato', InteressadoQualificacao::FONTE_CONSULTOR));
        $this->assertSame(QualificacaoService::INVALIDO, $this->servico->registrar($lead, 'motivo_busca', 42, InteressadoQualificacao::FONTE_CONSULTOR));
        $this->assertSame(QualificacaoService::INVALIDO, $this->servico->registrar($lead, 'bairro', '   ', InteressadoQualificacao::FONTE_CONSULTOR));
        $this->assertNull($lead->qualificacao()->first());

        $this->expectException(InvalidArgumentException::class);
        $this->servico->registrar($lead, 'renda_exata', '12000', InteressadoQualificacao::FONTE_CONSULTOR);
    }

    public function test_campo_de_aluno_pede_o_aluno_quando_ha_mais_de_um(): void
    {
        $lead = $this->lead();
        $lucas = $this->aluno($lead);
        $ana = $this->aluno($lead, ['nome_crianca' => 'Ana']);

        $this->assertSame(QualificacaoService::SEM_ALUNO, $this->servico->registrar($lead, 'situacao_atual', 'primeira_escola', InteressadoQualificacao::FONTE_CONSULTOR));
        $this->assertSame(QualificacaoService::GRAVADO, $this->servico->registrar($lead, 'situacao_atual', 'em_outra_escola', InteressadoQualificacao::FONTE_CONSULTOR, aluno: $ana));

        $this->assertSame('em_outra_escola', $ana->fresh()->situacao_atual);
        $this->assertNull($lucas->fresh()->situacao_atual);

        // Aluno de outro lead não vale.
        $outro = $this->aluno($this->lead());
        $this->assertSame(QualificacaoService::SEM_ALUNO, $this->servico->registrar($lead, 'situacao_atual', 'primeira_escola', InteressadoQualificacao::FONTE_CONSULTOR, aluno: $outro));
    }

    public function test_campo_de_aluno_so_esta_respondido_quando_todos_os_alunos_tem_o_valor(): void
    {
        $lead = $this->lead();
        $this->aluno($lead, ['turno_preferencia' => 'Manhã']);
        $this->aluno($lead, ['nome_crianca' => 'Ana']);

        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $this->servico->estados($lead->fresh())['turno_preferencia']['estado']);

        InteressadoDependente::where('nome_crianca', 'Ana')->first()->update(['turno_preferencia' => 'Tarde']);

        $this->assertSame(Roteiro::ESTADO_RESPONDIDO, $this->servico->estados($lead->fresh())['turno_preferencia']['estado']);
    }

    public function test_gate_completo_qualifica_o_lead_e_a_mensalidade_nunca_e_exigida(): void
    {
        $lead = $this->lead();
        $aluno = $this->aluno($lead);

        $this->qualificar($lead, $aluno);

        $avaliacao = $this->servico->avaliar($lead->fresh());
        $this->assertTrue($avaliacao['gate_ok']);
        $this->assertSame([], $avaliacao['faltando']);
        $this->assertSame(9, $avaliacao['total']);

        $qualificacao = $lead->qualificacao()->first();
        $this->assertSame(InteressadoQualificacao::RESULTADO_QUALIFICADO, $qualificacao->resultado);
        $this->assertEquals(now()->toDateTimeString(), $qualificacao->qualificado_em->toDateTimeString());
        $this->assertNull($qualificacao->faixa_mensalidade);

        // Apagar uma resposta tira o lead de "qualificado".
        $aluno->update(['turno_preferencia' => null]);
        $this->assertSame(InteressadoQualificacao::RESULTADO_EM_QUALIFICACAO, $lead->qualificacao()->first()->resultado);
        $this->assertNull($lead->qualificacao()->first()->qualificado_em);
    }

    public function test_familia_que_prefere_nao_informar_encerra_a_pergunta_e_nao_trava_o_gate(): void
    {
        $lead = $this->lead();
        $aluno = $this->aluno($lead);
        $this->qualificar($lead, $aluno);
        $lead->qualificacao()->first()->forceFill(['bairro' => null, 'campos' => null])->save();
        $this->servico->reavaliar($lead->fresh());
        $this->assertSame(InteressadoQualificacao::RESULTADO_EM_QUALIFICACAO, $lead->qualificacao()->first()->resultado);

        $this->servico->marcarRecusado($lead->fresh(), 'bairro', $this->consultor->id);

        $this->assertSame(Roteiro::ESTADO_RECUSOU, $this->servico->estados($lead->fresh())['bairro']['estado']);
        $this->assertSame(InteressadoQualificacao::RESULTADO_QUALIFICADO, $lead->qualificacao()->first()->resultado);
        $this->assertNotContains('bairro', array_map(fn ($c) => $c->chave, $this->servico->lacunas($lead->fresh())));
    }

    public function test_proximas_perguntas_seguem_a_ordem_do_roteiro_limitam_e_pulam_a_mensalidade(): void
    {
        $lead = $this->lead();
        $this->aluno($lead, ['serie_id' => $this->serie()->id]);

        $chaves = fn (array $campos): array => array_map(fn ($c) => $c->chave, $campos);

        // Série já respondida; as duas próximas são nascimento e turno (encaixe), na ordem do roteiro.
        $this->assertSame(['data_nascimento', 'turno_preferencia'], $chaves($this->servico->proximasPerguntas($lead->fresh())));
        $this->assertSame(['data_nascimento'], $chaves($this->servico->proximasPerguntas($lead->fresh(), 1)));

        // Quem já foi perguntado e segue sem resposta vai para depois de quem nunca foi perguntado.
        $this->servico->marcarPerguntado($lead->fresh(), ['data_nascimento']);
        $this->assertSame(['turno_preferencia', 'bairro'], $chaves($this->servico->proximasPerguntas($lead->fresh())));

        // A mensalidade está nas lacunas, mas nunca entra sozinha na pergunta automática.
        $this->assertContains('faixa_mensalidade', $chaves($this->servico->lacunas($lead->fresh())));
        $this->assertNotContains('faixa_mensalidade', $chaves($this->servico->proximasPerguntas($lead->fresh(), 99)));
    }

    public function test_marcar_perguntado_nao_mexe_em_quem_ja_respondeu_ou_recusou(): void
    {
        $lead = $this->lead();
        $this->servico->registrar($lead, 'decisor_tipo', 'sozinho', InteressadoQualificacao::FONTE_CONSULTOR);
        $this->servico->marcarRecusado($lead->fresh(), 'bairro');

        $this->servico->marcarPerguntado($lead->fresh(), ['decisor_tipo', 'bairro', 'prazo_decisao']);

        $estados = $this->servico->estados($lead->fresh());
        $this->assertSame(Roteiro::ESTADO_RESPONDIDO, $estados['decisor_tipo']['estado']);
        $this->assertSame(Roteiro::ESTADO_RECUSOU, $estados['bairro']['estado']);
        $this->assertSame(Roteiro::ESTADO_PERGUNTADO, $estados['prazo_decisao']['estado']);
    }

    public function test_campo_editado_pela_ficha_vira_procedencia_consultor(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->consultor);

        $qualificacao = new InteressadoQualificacao(['bairro' => 'Savassi', 'decisor_tipo' => 'com_conjuge']);
        $qualificacao->forceFill(['interessado_id' => $lead->id])->save();

        $campos = $qualificacao->fresh()->campos;
        $this->assertSame(Roteiro::ESTADO_RESPONDIDO, $campos['bairro']['estado']);
        $this->assertSame(InteressadoQualificacao::FONTE_CONSULTOR, $campos['bairro']['fonte']);
        $this->assertSame($this->consultor->id, $campos['decisor_tipo']['atualizado_por']);

        // Limpar o campo o devolve a "não perguntado".
        $qualificacao->update(['bairro' => null]);
        $this->assertArrayNotHasKey('bairro', $qualificacao->fresh()->campos);
        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $this->servico->estados($lead->fresh())['bairro']['estado']);
    }

    public function test_alertas_objetivos_de_idade_e_distancia(): void
    {
        $lead = $this->lead();
        $lead->forceFill(['faixa_distancia_escola' => 'mais_de_10km'])->save();
        // Nascido em 10/06/2021: terá 5 anos em 31/03/2027, abaixo dos 6 que a série pede.
        $this->aluno($lead, ['serie_id' => $this->serie('1º Ano', 6)->id, 'data_nascimento' => '2021-06-10']);

        $alertas = $this->servico->alertas($lead->fresh());

        $this->assertCount(2, $alertas);
        $this->assertStringContainsString('terá 5 ano(s) em 31/03/2027', $alertas[0]);
        $this->assertStringContainsString('pede no mínimo 6', $alertas[0]);
        $this->assertSame('Mora a mais de 10 km da escola.', $alertas[1]);
    }

    public function test_sem_alertas_quando_a_idade_atende_a_serie(): void
    {
        $lead = $this->lead();
        $this->aluno($lead, ['serie_id' => $this->serie('1º Ano', 6)->id, 'data_nascimento' => '2020-12-01']);

        $this->assertSame([], $this->servico->alertas($lead->fresh()));
    }

    public function test_corte_etario_passa_para_o_ano_seguinte_depois_de_31_de_marco(): void
    {
        $this->travelTo(Carbon::parse('2027-02-10 10:00:00'));
        $lead = $this->lead();
        // Em 10/02/2027 o corte ainda é o de 31/03/2027.
        $this->aluno($lead, ['serie_id' => $this->serie('1º Ano', 6)->id, 'data_nascimento' => '2021-04-15']);

        $this->assertStringContainsString('terá 5 ano(s) em 31/03/2027', $this->servico->alertas($lead->fresh())[0]);

        $this->travelTo(Carbon::parse('2027-04-02 10:00:00'));
        // Depois de 31/03 o corte relevante é o do ano seguinte: 31/03/2028, e a criança terá 6.
        $this->assertSame([], $this->servico->alertas($lead->fresh()));
    }

    public function test_sugere_a_etapa_configurada_so_para_lead_qualificado_e_so_para_frente(): void
    {
        $visita = StatusInteressado::create(['nome' => 'Visita Agendada', 'cor' => 'primary', 'ordem' => 3, 'is_final' => false, 'is_ganho' => false]);
        StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 9, 'is_final' => true, 'is_ganho' => true]);

        $lead = $this->lead();
        $this->qualificar($lead, $this->aluno($lead));

        // Sem configuração, nada é sugerido (e nenhuma etapa é criada).
        config(['crm.qualificacao.etapa_qualificado' => null]);
        $this->assertNull($this->servico->sugerirEtapa($lead->fresh()));
        $this->assertSame(3, StatusInteressado::count(), 'Nenhuma etapa é criada pelo serviço.');

        config(['crm.qualificacao.etapa_qualificado' => ' visita agendada ']);
        $this->assertSame($visita->id, $this->servico->sugerirEtapa($lead->fresh())?->id);

        // Etapa final não é sugerida.
        config(['crm.qualificacao.etapa_qualificado' => 'Matriculado']);
        $this->assertNull($this->servico->sugerirEtapa($lead->fresh()));

        // Lead que já está na etapa, ou além dela, não "volta".
        config(['crm.qualificacao.etapa_qualificado' => 'Visita Agendada']);
        $lead->update(['status_interessado_id' => $visita->id]);
        $this->assertNull($this->servico->sugerirEtapa($lead->fresh()));

        // Lead ainda não qualificado não recebe sugestão.
        $outro = $this->lead();
        $this->assertNull($this->servico->sugerirEtapa($outro));
    }

    public function test_painel_resume_o_roteiro_por_dimensao(): void
    {
        $lead = $this->lead();
        $this->aluno($lead, ['serie_id' => $this->serie()->id]);
        $this->servico->registrar($lead->fresh(), 'decisor_tipo', 'com_conjuge', InteressadoQualificacao::FONTE_CONSULTOR);

        $painel = $this->servico->painel($lead->fresh());

        $this->assertSame('Em qualificação', $painel['resultado_rotulo']);
        $this->assertSame(['encaixe', 'necessidade', 'autoridade', 'urgencia', 'dinheiro'], array_keys($painel['dimensoes']));

        $autoridade = collect($painel['dimensoes']['autoridade']['campos'])->keyBy('chave');
        $this->assertSame(Roteiro::ESTADO_RESPONDIDO, $autoridade['decisor_tipo']['estado']);
        $this->assertSame('Decide com o cônjuge ou companheiro(a)', $autoridade['decisor_tipo']['valor']);
        $this->assertStringStartsWith('Respondido · Consultor', $autoridade['decisor_tipo']['texto']);
        $this->assertSame(Roteiro::ESTADO_NAO_PERGUNTADO, $autoridade['decisor_vai_visita']['estado']);

        $encaixe = collect($painel['dimensoes']['encaixe']['campos'])->keyBy('chave');
        $this->assertSame('1º Ano', $encaixe['serie_id']['valor']);
        $this->assertArrayNotHasKey('escola_atual', collect($painel['dimensoes']['necessidade']['campos'])->keyBy('chave')->all(), 'Campo que não se aplica não aparece.');
    }

    public function test_excluir_o_lead_apaga_a_qualificacao(): void
    {
        $lead = $this->lead();
        $this->servico->registrar($lead, 'bairro', 'Centro', InteressadoQualificacao::FONTE_CONSULTOR);
        $this->assertSame(1, InteressadoQualificacao::count());

        $lead->delete();

        $this->assertSame(0, InteressadoQualificacao::count());
    }
}

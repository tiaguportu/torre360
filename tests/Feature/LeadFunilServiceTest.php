<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\LeadFunilService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Regras únicas de movimentação do lead no funil (`LeadFunilService`) e helpers de etapa
 * (`StatusInteressado::inicial()/ganho()/perdido()`).
 */
class LeadFunilServiceTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $novo;

    private StatusInteressado $atendimento;

    private StatusInteressado $matriculado;

    private StatusInteressado $perdido;

    private LeadFunilService $funil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->atendimento = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 3, 'is_final' => true, 'is_ganho' => true]);
        $this->perdido = StatusInteressado::create(['nome' => 'Perdido', 'cor' => 'danger', 'ordem' => 4, 'is_final' => true, 'is_ganho' => false]);

        OrigemInteressado::create(['nome' => 'Site']);

        $this->funil = app(LeadFunilService::class);
    }

    private function criarLead(?StatusInteressado $status = null, array $atributos = []): Interessado
    {
        return Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => ($status ?? $this->novo)->id,
            'origem_interessado_id' => OrigemInteressado::firstOrFail()->id,
        ]);
    }

    // ─── Helpers de etapa ───────────────────────────────────────

    public function test_helpers_de_etapa_resolvem_por_nome_e_caem_nas_flags_quando_renomeadas(): void
    {
        $this->assertSame($this->novo->id, StatusInteressado::inicial()->id);
        $this->assertSame($this->matriculado->id, StatusInteressado::ganho()->id);
        $this->assertSame($this->perdido->id, StatusInteressado::perdido()->id);

        $this->novo->update(['nome' => 'Lead recebido']);
        $this->matriculado->update(['nome' => 'Aluno ativo']);
        $this->perdido->update(['nome' => 'Encerrado sem matrícula']);

        // Sem as chamadas por nome, vale a primeira etapa ativa / a de ganho / a final que não é de ganho.
        $this->assertSame($this->novo->id, StatusInteressado::inicial()->id);
        $this->assertSame($this->matriculado->id, StatusInteressado::ganho()->id);
        $this->assertSame($this->perdido->id, StatusInteressado::perdido()->id);
    }

    public function test_helpers_retornam_null_quando_nao_ha_etapa_correspondente(): void
    {
        StatusInteressado::query()->delete();

        $this->assertNull(StatusInteressado::inicial());
        $this->assertNull(StatusInteressado::ganho());
        $this->assertNull(StatusInteressado::perdido());
    }

    // ─── moverParaEtapaAtiva ────────────────────────────────────

    public function test_move_lead_entre_etapas_ativas_e_recalcula_o_score(): void
    {
        $lead = $this->criarLead();

        $this->assertTrue($this->funil->moverParaEtapaAtiva($lead, $this->atendimento, null));

        $lead->refresh();
        $this->assertSame($this->atendimento->id, $lead->status_interessado_id);
        $this->assertNotNull($lead->lead_score_atualizado_em);
    }

    public function test_mover_para_a_mesma_etapa_nao_faz_nada(): void
    {
        $lead = $this->criarLead($this->atendimento);

        $this->assertFalse($this->funil->moverParaEtapaAtiva($lead, $this->atendimento));
        $this->assertSame(0, HistoricoContato::count());
    }

    public function test_reativar_lead_perdido_limpa_o_motivo_e_registra_na_linha_do_tempo(): void
    {
        $lead = $this->criarLead($this->perdido, ['motivo_perda' => 'Preço']);
        $usuario = User::factory()->create();

        $this->funil->moverParaEtapaAtiva($lead, $this->atendimento, $usuario->id);

        $lead->refresh();
        $this->assertSame($this->atendimento->id, $lead->status_interessado_id);
        $this->assertNull($lead->motivo_perda);

        $historico = HistoricoContato::with('tipoContato')->firstOrFail();
        $this->assertSame($lead->id, $historico->interessado_id);
        $this->assertSame($usuario->id, $historico->usuario_id);
        $this->assertSame('retornar', $historico->resultado);
        $this->assertSame(TipoContatoInteressado::FUNIL, $historico->tipoContato->nome);
        $this->assertStringContainsString("de 'Perdido' para 'Em Atendimento'", $historico->relato);
    }

    public function test_nao_move_para_etapas_de_encerramento(): void
    {
        $lead = $this->criarLead();

        foreach ([$this->matriculado, $this->perdido] as $etapaFinal) {
            try {
                $this->funil->moverParaEtapaAtiva($lead, $etapaFinal);
                $this->fail("Mover para \"{$etapaFinal->nome}\" deveria ser recusado.");
            } catch (DomainException $e) {
                $this->assertStringContainsString($etapaFinal->nome, $e->getMessage());
            }
        }

        $this->assertSame($this->novo->id, $lead->fresh()->status_interessado_id);
    }

    public function test_lead_matriculado_nao_volta_para_etapa_ativa(): void
    {
        $lead = $this->criarLead($this->matriculado);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('já foi matriculado');

        $this->funil->moverParaEtapaAtiva($lead, $this->atendimento);
    }

    // ─── marcarComoPerdido ──────────────────────────────────────

    public function test_marca_lead_como_perdido_com_motivo_e_historico(): void
    {
        $lead = $this->criarLead();
        $usuario = User::factory()->create();

        $motivo = $this->funil->marcarComoPerdido($lead, $this->perdido, 'Preço', null, 'Achou caro', $usuario->id);

        $this->assertSame('Preço', $motivo);

        $lead->refresh();
        $this->assertSame($this->perdido->id, $lead->status_interessado_id);
        $this->assertSame('Preço', $lead->motivo_perda);

        $historico = HistoricoContato::with('tipoContato')->firstOrFail();
        $this->assertSame('sem_interesse', $historico->resultado);
        $this->assertSame($usuario->id, $historico->usuario_id);
        $this->assertSame(TipoContatoInteressado::FUNIL, $historico->tipoContato->nome);
        $this->assertStringContainsString('Motivo: Preço.', $historico->relato);
        $this->assertStringContainsString('Detalhes: Achou caro', $historico->relato);
    }

    public function test_perda_para_concorrencia_guarda_o_nome_da_escola_e_o_motivo_base_continua_agregavel(): void
    {
        $lead = $this->criarLead();

        $motivo = $this->funil->marcarComoPerdido($lead, $this->perdido, 'Concorrência', '  Colégio São Francisco ');

        $this->assertSame('Concorrência: Colégio São Francisco', $motivo);
        $this->assertSame($motivo, $lead->fresh()->motivo_perda);
        $this->assertSame('Concorrência', LeadFunilService::motivoBase($motivo));
        $this->assertSame('Preço', LeadFunilService::motivoBase('Preço'));
        $this->assertNull(LeadFunilService::motivoBase(null));
        $this->assertNull(LeadFunilService::motivoBase(''));

        // O nome da escola só entra no motivo de Concorrência.
        $this->assertSame('Preço', LeadFunilService::motivoPerdaFormatado('Preço', 'Colégio X'));
    }

    public function test_perda_aceita_atributos_extras_e_trecho_de_relato_para_inteligencia_competitiva(): void
    {
        $lead = $this->criarLead();

        $this->funil->marcarComoPerdido(
            $lead,
            $this->perdido,
            'Concorrência',
            'Colégio X',
            null,
            null,
            atributosExtras: ['temperatura' => 'frio'],
            relatoExtra: 'Fator decisivo: mensalidade.',
        );

        $this->assertSame('frio', $lead->fresh()->temperatura);
        $this->assertStringContainsString('Fator decisivo: mensalidade.', HistoricoContato::firstOrFail()->relato);
    }

    public function test_perda_recusa_motivo_fora_da_lista_etapa_que_nao_e_de_perda_e_lead_matriculado(): void
    {
        $lead = $this->criarLead();

        try {
            $this->funil->marcarComoPerdido($lead, $this->perdido, 'Motivo inventado');
            $this->fail('Motivo fora da lista deveria ser recusado.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('motivos de perda', $e->getMessage());
        }

        try {
            $this->funil->marcarComoPerdido($lead, $this->atendimento, 'Preço');
            $this->fail('Etapa ativa não pode receber motivo de perda.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('não é uma etapa de perda', $e->getMessage());
        }

        $matriculado = $this->criarLead($this->matriculado);

        try {
            $this->funil->marcarComoPerdido($matriculado, $this->perdido, 'Preço');
            $this->fail('Lead matriculado não pode ser perdido.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('já foi matriculado', $e->getMessage());
        }

        $this->assertSame($this->novo->id, $lead->fresh()->status_interessado_id);
        $this->assertSame($this->matriculado->id, $matriculado->fresh()->status_interessado_id);
        $this->assertSame(0, HistoricoContato::count());
    }

    // ─── marcarMatriculado ──────────────────────────────────────

    public function test_marcar_matriculado_aplica_a_conversao_completa_do_assistente(): void
    {
        $lead = $this->criarLead($this->atendimento, ['dados_pre_matricula' => ['rascunho' => true]]);

        $this->funil->marcarMatriculado($lead);

        $lead->refresh();
        $this->assertSame($this->matriculado->id, $lead->status_interessado_id);
        $this->assertNotNull($lead->data_conversao);
        $this->assertNull($lead->dados_pre_matricula, 'O rascunho de pré-matrícula é descartado na conversão (LGPD).');
    }

    public function test_marcar_matriculado_sem_etapa_de_ganho_cadastrada_nao_deixa_o_lead_sem_status(): void
    {
        $lead = $this->criarLead();
        StatusInteressado::query()->where('is_ganho', true)->delete();

        try {
            $this->funil->marcarMatriculado($lead);
            $this->fail('Sem etapa de ganho a operação deveria ser recusada.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('etapa de matrícula', $e->getMessage());
        }

        $lead->refresh();
        $this->assertSame($this->novo->id, $lead->status_interessado_id);
        $this->assertNull($lead->data_conversao);
    }

    // ─── registrarAtendimento ───────────────────────────────────

    public function test_registrar_atendimento_grava_contato_reagenda_e_preenche_o_primeiro_contato_uma_vez(): void
    {
        $tipo = TipoContatoInteressado::create(['nome' => 'Ligação']);
        $lead = $this->criarLead();
        $consultor = User::factory()->create();
        $proximo = now()->addDays(3)->startOfMinute();

        $this->assertNull($lead->data_primeiro_contato);

        $historico = $this->funil->registrarAtendimento($lead, [
            'tipo_contato_interessado_id' => $tipo->id,
            'relato' => 'Mãe quer conhecer a escola.',
            'data_contato' => now()->subDays(2)->startOfMinute(),
            'duracao_minutos' => 12,
            'resultado' => 'agendou_visita',
            'data_proximo_contato' => $proximo,
        ], $consultor->id);

        $lead->refresh();
        $this->assertSame($consultor->id, $historico->usuario_id);
        $this->assertFalse($historico->automatico);
        $this->assertEquals(now()->subDays(2)->startOfMinute()->timestamp, $historico->data_contato->timestamp);
        $this->assertEquals($historico->data_contato->timestamp, $lead->data_primeiro_contato->timestamp);
        $this->assertEquals($proximo->timestamp, $lead->data_proximo_contato->timestamp);

        $primeiroContato = $lead->data_primeiro_contato->timestamp;

        $this->funil->registrarAtendimento($lead, [
            'tipo_contato_interessado_id' => $tipo->id,
            'relato' => 'Retorno combinado.',
            'data_proximo_contato' => null,
        ]);

        $lead->refresh();
        $this->assertSame($primeiroContato, $lead->data_primeiro_contato->timestamp);
        $this->assertNull($lead->data_proximo_contato);
        $this->assertSame(2, $lead->historicos()->count());
    }

    public function test_dependente_converte_data_de_nascimento_para_data_e_normaliza_nomes(): void
    {
        $lead = $this->criarLead();
        $dependente = InteressadoDependente::create([
            'interessado_id' => $lead->id,
            'nome_crianca' => 'Lucas',
            'data_nascimento' => '2018-05-20',
        ])->fresh();

        $this->assertSame('2018-05-20', $dependente->data_nascimento->toDateString());
        $this->assertSame('20/05/2018', $dependente->data_nascimento->format('d/m/Y'));

        $this->assertSame('joao da silva', InteressadoDependente::nomeNormalizado('  João   da  SILVA '));
        $this->assertSame(InteressadoDependente::nomeNormalizado('Ágata Çavalcante'), InteressadoDependente::nomeNormalizado('agata cavalcante'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Interessado;
use App\Models\InteressadoStatusHistorico;
use App\Models\StatusInteressado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Todo lead que muda de etapa por fora do LeadFunilService (formulário público, importação, edição direta no model)
 * ganha o histórico de transição pelo observer; quando o serviço já registrou, o observer não duplica.
 */
class InteressadoObserverTest extends TestCase
{
    use RefreshDatabase;

    private function historico(Interessado $lead)
    {
        return InteressadoStatusHistorico::query()->where('interessado_id', $lead->id)->orderBy('id');
    }

    public function test_lead_criado_com_etapa_ganha_a_primeira_transicao(): void
    {
        $etapa = StatusInteressado::factory()->create();

        $lead = Interessado::factory()->create(['status_interessado_id' => $etapa->id]);

        $registros = $this->historico($lead)->get();
        $this->assertCount(1, $registros);
        $this->assertNull($registros[0]->status_anterior_id);
        $this->assertSame($etapa->id, $registros[0]->status_novo_id);
        $this->assertTrue($registros[0]->data_transicao->equalTo($lead->created_at));
        $this->assertFalse((bool) $registros[0]->estimada);
    }

    public function test_mudar_a_etapa_registra_de_onde_veio_e_para_onde_foi(): void
    {
        $primeira = StatusInteressado::factory()->create();
        $segunda = StatusInteressado::factory()->create();
        $lead = Interessado::factory()->create(['status_interessado_id' => $primeira->id]);

        $lead->update(['status_interessado_id' => $segunda->id]);

        $ultimo = $this->historico($lead)->get()->last();
        $this->assertSame(2, $this->historico($lead)->count());
        $this->assertSame($primeira->id, $ultimo->status_anterior_id);
        $this->assertSame($segunda->id, $ultimo->status_novo_id);
    }

    public function test_mudar_outro_campo_nao_registra_transicao(): void
    {
        $lead = Interessado::factory()->create(['status_interessado_id' => StatusInteressado::factory()->create()->id]);

        $lead->update(['observacoes' => 'ligar na segunda']);

        $this->assertSame(1, $this->historico($lead)->count(), 'só a transição da criação');
    }

    public function test_quando_o_servico_ja_registrou_o_observer_nao_duplica_e_zera_a_marca(): void
    {
        $segunda = StatusInteressado::factory()->create();
        $lead = Interessado::factory()->create(['status_interessado_id' => StatusInteressado::factory()->create()->id]);

        $lead->transicaoRegistradaPorServico = true;
        $lead->update(['status_interessado_id' => $segunda->id]);

        $this->assertSame(1, $this->historico($lead)->count(), 'o serviço grava a sua; o observer não repete');
        $this->assertFalse($lead->transicaoRegistradaPorServico, 'a marca volta a falso para a próxima mudança');

        $lead->update(['status_interessado_id' => StatusInteressado::factory()->create()->id]);

        $this->assertSame(2, $this->historico($lead)->count(), 'a mudança seguinte volta a ser registrada');
    }

    public function test_lead_criado_pelo_servico_nao_duplica_a_transicao_inicial(): void
    {
        $etapa = StatusInteressado::factory()->create();
        $lead = new Interessado(Interessado::factory()->make(['status_interessado_id' => $etapa->id])->getAttributes());
        $lead->transicaoRegistradaPorServico = true;

        $lead->save();

        $this->assertSame(0, $this->historico($lead)->count());
    }
}

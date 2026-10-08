<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Services\GeracaoFaturasContratoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GeracaoFaturasContratoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function criarContrato(float $valorTotal, ?string $dataAceite = '2026-01-10'): Contrato
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
        $aluno = Pessoa::create(['nome' => 'Aluno Teste Geracao Faturas']);
        $matricula = Matricula::create(['pessoa_id' => $aluno->id, 'turma_id' => Turma::factory()->create(['periodo_letivo_id' => $periodo->id])->id, 'situacao' => 'ativa']);

        return Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => $valorTotal,
            'data_aceite' => $dataAceite,
        ]);
    }

    public function test_gera_parcelas_sem_entrada(): void
    {
        $contrato = $this->criarContrato(1200.0);

        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, 12, 0.0);

        $this->assertCount(12, $faturas);
        $this->assertEquals(12, $contrato->faturas()->count());
        $this->assertEquals(100.0, (float) $faturas->first()->itens->first()->valor_unitario);
    }

    public function test_gera_fatura_de_entrada_mais_parcelas(): void
    {
        $contrato = $this->criarContrato(1200.0);

        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, 11, 100.0);

        // 1 entrada + 11 parcelas
        $this->assertCount(12, $faturas);
        $this->assertEquals(100.0, (float) $faturas->first()->itens->first()->valor_unitario);
        $this->assertEquals('2026-01-10', $faturas->first()->vencimento->toDateString());

        // (1200 - 100) / 11 = 100 por parcela
        $this->assertEquals(100.0, (float) $faturas->last()->itens->first()->valor_unitario);
    }

    public function test_primeira_parcela_vence_5_dias_uteis_apos_aceite(): void
    {
        // 2026-01-10 é sábado -> 5 dias úteis depois: seg(12),ter(13),qua(14),qui(15),sex(16)
        $contrato = $this->criarContrato(1000.0, '2026-01-10');

        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, 10, 0.0);

        $this->assertEquals('2026-01-16', $faturas->first()->vencimento->toDateString());
    }

    public function test_regenerar_substitui_faturas_existentes(): void
    {
        $contrato = $this->criarContrato(1200.0);
        $service = app(GeracaoFaturasContratoService::class);

        $service->gerar($contrato, 12, 0.0);
        $this->assertEquals(12, $contrato->faturas()->count());

        $service->gerar($contrato, 6, 0.0);
        $this->assertEquals(6, $contrato->faturas()->count());
    }

    public function test_lanca_excecao_sem_data_aceite(): void
    {
        $contrato = $this->criarContrato(1200.0, null);

        $this->expectException(\InvalidArgumentException::class);
        app(GeracaoFaturasContratoService::class)->gerar($contrato, 12, 0.0);
    }

    public function test_data_base_informada_dispensa_data_aceite_e_tem_precedencia_sobre_ela(): void
    {
        $service = app(GeracaoFaturasContratoService::class);

        // Sem data_aceite: a data-base explícita basta (sábado -> 1ª parcela na sexta seguinte)
        $semAceite = $this->criarContrato(1200.0, null);
        $faturas = $service->gerar($semAceite, 11, 100.0, Carbon::parse('2026-01-10'));

        $this->assertSame('2026-01-10', $faturas[0]->vencimento->toDateString()); // entrada
        $this->assertSame('2026-01-16', $faturas[1]->vencimento->toDateString()); // 1ª parcela

        // Com data_aceite diferente: vale a data-base informada
        $comAceite = $this->criarContrato(1200.0, '2026-03-02');
        $faturas = $service->gerar($comAceite, 12, 0.0, Carbon::parse('2026-01-10'));

        $this->assertSame('2026-01-16', $faturas->first()->vencimento->toDateString());
    }

    public function test_lanca_excecao_quando_entrada_maior_que_total(): void
    {
        $contrato = $this->criarContrato(100.0);

        $this->expectException(\InvalidArgumentException::class);
        app(GeracaoFaturasContratoService::class)->gerar($contrato, 1, 200.0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\BolsaConcedida;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\TipoBolsa;
use App\Services\GeracaoFaturasContratoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeracaoFaturasComBolsaTest extends TestCase
{
    use RefreshDatabase;

    private function criarContrato(float $valorTotal): Contrato
    {
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31']);
        $aluno = Pessoa::create(['nome' => 'Aluno Teste Bolsa']);
        $matricula = Matricula::create(['pessoa_id' => $aluno->id, 'periodo_letivo_id' => $periodo->id, 'situacao' => 'ativa']);

        return Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => $valorTotal,
            'data_aceite' => '2026-01-10',
        ]);
    }

    public function test_sem_bolsa_ativa_nao_aplica_desconto(): void
    {
        $contrato = $this->criarContrato(1200.0);

        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, 12, 0.0);

        $item = $faturas->first()->itens->first();
        $this->assertSame(0, (int) $item->desconto);
        $this->assertSame('absoluto', $item->tipo_desconto);
    }

    public function test_com_bolsa_aprovada_aplica_desconto_percentual_em_cada_item(): void
    {
        $contrato = $this->criarContrato(1200.0);
        $tipo = TipoBolsa::factory()->create(['percentual_maximo' => 50]);
        BolsaConcedida::factory()->create([
            'matricula_id' => $contrato->matricula_id,
            'tipo_bolsa_id' => $tipo->id,
            'percentual' => 30,
            'status' => 'aprovada',
            'data_inicio' => now()->subMonth(),
        ]);

        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, 12, 0.0);

        foreach ($faturas as $fatura) {
            $item = $fatura->itens->first();
            $this->assertSame(30, (int) $item->desconto);
            $this->assertSame('percentual', $item->tipo_desconto);
        }
    }

    public function test_bolsa_solicitada_ainda_nao_aprovada_nao_aplica_desconto(): void
    {
        $contrato = $this->criarContrato(1200.0);
        $tipo = TipoBolsa::factory()->create();
        BolsaConcedida::factory()->create([
            'matricula_id' => $contrato->matricula_id,
            'tipo_bolsa_id' => $tipo->id,
            'percentual' => 30,
            'status' => 'solicitada',
        ]);

        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, 12, 0.0);

        $this->assertSame(0, (int) $faturas->first()->itens->first()->desconto);
    }
}

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

    public function test_sem_data_aceite_parte_de_hoje_em_vez_de_recusar(): void
    {
        // As faturas são geradas antes da assinatura: o contrato ainda não tem aceite.
        Carbon::setTestNow('2026-01-10 09:00:00'); // sábado
        $contrato = $this->criarContrato(1200.0, null);

        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, 11, 100.0);

        $this->assertCount(12, $faturas);
        $this->assertSame('2026-01-10', $faturas[0]->vencimento->toDateString()); // entrada, hoje
        $this->assertSame('2026-01-16', $faturas[1]->vencimento->toDateString()); // 1ª parcela, 5 dias úteis depois
        $this->assertNull($contrato->fresh()->data_aceite);

        Carbon::setTestNow();
    }

    public function test_regenerar_apos_reset_da_assinatura_nao_exige_aceite(): void
    {
        // resetAssinafyState() zera data_aceite; a regeneração seguinte não pode ficar bloqueada por isso.
        $contrato = $this->criarContrato(1200.0);
        $contrato->update(['assinafy_id' => 'doc-1', 'assinafy_status' => 'enviado']);
        $service = app(GeracaoFaturasContratoService::class);

        $service->gerar($contrato, 12, 0.0);
        $contrato->resetAssinafyState();
        $this->assertNull($contrato->fresh()->data_aceite);

        $service->gerar($contrato->fresh(), 6, 0.0);

        $this->assertEquals(6, $contrato->faturas()->count());
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

    /** @return list<string> valor de cada fatura, em reais com duas casas, na ordem de vencimento */
    private function valoresDasFaturas(Contrato $contrato): array
    {
        return $contrato->faturas()->with('itens')->orderBy('vencimento')->orderBy('id')->get()
            ->map(fn ($fatura): string => number_format((float) $fatura->itens->first()->valor_unitario, 2, '.', ''))
            ->all();
    }

    public function test_divisao_inexata_deixa_os_centavos_que_sobram_na_primeira_parcela(): void
    {
        $contrato = $this->criarContrato(1000.0);

        app(GeracaoFaturasContratoService::class)->gerar($contrato, 3, 0.0);

        $this->assertSame(['333.34', '333.33', '333.33'], $this->valoresDasFaturas($contrato));
    }

    public function test_a_soma_das_parcelas_fecha_com_o_contrato_mesmo_quando_arredondar_passaria(): void
    {
        // 100,00 / 6 = 16,666...: arredondar cada uma para 16,67 somaria 100,02.
        $contrato = $this->criarContrato(100.0);

        app(GeracaoFaturasContratoService::class)->gerar($contrato, 6, 0.0);

        $valores = $this->valoresDasFaturas($contrato);
        $this->assertSame(['16.70', '16.66', '16.66', '16.66', '16.66', '16.66'], $valores);
        $this->assertEqualsWithDelta(100.0, array_sum(array_map('floatval', $valores)), 0.001);
    }

    public function test_com_entrada_os_centavos_sobrantes_vao_para_a_primeira_parcela_e_nao_para_a_entrada(): void
    {
        // Saldo de 900,00 em 7 parcelas = 128,571...: entrada intacta, sobra de 1 centavo na Parcela 1.
        $contrato = $this->criarContrato(1000.0);

        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, 7, 100.0);

        $this->assertSame(
            ['100.00', '128.58', '128.57', '128.57', '128.57', '128.57', '128.57', '128.57'],
            $this->valoresDasFaturas($contrato)
        );
        $this->assertSame('Entrada', $faturas->first()->itens->first()->descricao);
        $this->assertSame('Parcela 1 de 7', $faturas->get(1)->itens->first()->descricao);
    }

    public function test_dividir_em_centavos(): void
    {
        $servico = app(GeracaoFaturasContratoService::class);

        $this->assertSame([3334, 3333, 3333], $servico->dividirEmCentavos(10000, 3));
        $this->assertSame([2500, 2500, 2500, 2500], $servico->dividirEmCentavos(10000, 4));
        $this->assertSame([3, 1, 1], $servico->dividirEmCentavos(5, 3));
        $this->assertSame([10000], $servico->dividirEmCentavos(10000, 1));
    }

    public function test_dividir_em_centavos_exige_ao_menos_uma_parte(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(GeracaoFaturasContratoService::class)->dividirEmCentavos(10000, 0);
    }

    public function test_lanca_excecao_quando_entrada_maior_que_total(): void
    {
        $contrato = $this->criarContrato(100.0);

        $this->expectException(\InvalidArgumentException::class);
        app(GeracaoFaturasContratoService::class)->gerar($contrato, 1, 200.0);
    }
}

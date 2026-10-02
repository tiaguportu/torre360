<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\Fatura;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\SituacaoFinalDisciplina;
use App\Services\RiscoEvasaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiscoEvasaoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcular_sem_nenhum_fator_de_risco_retorna_zero(): void
    {
        $matricula = Matricula::factory()->create();

        $this->assertSame(0, RiscoEvasaoService::calcular($matricula));
    }

    private function criarFrequencia(Matricula $matricula, int $totalAulas, int $faltas): void
    {
        for ($i = 0; $i < $totalAulas; $i++) {
            $aula = CronogramaAula::factory()->create(['data' => now()->subDays(5)->toDateString()]);

            FrequenciaEscolar::create([
                'matricula_id' => $matricula->id,
                'cronograma_aula_id' => $aula->id,
                'situacao' => $i < $faltas ? 'ausente' : 'presente',
            ]);
        }
    }

    public function test_pontos_frequencia_acima_de_30_por_cento_de_faltas(): void
    {
        $matricula = Matricula::factory()->create();
        $this->criarFrequencia($matricula, 10, 4); // 40% de faltas

        $detalhe = collect(RiscoEvasaoService::detalhar($matricula))->firstWhere('fator', 'Frequência');

        $this->assertSame(40, $detalhe['pontos']);
    }

    public function test_pontos_frequencia_com_poucas_faltas(): void
    {
        $matricula = Matricula::factory()->create();
        $this->criarFrequencia($matricula, 10, 1); // 10% de faltas

        $detalhe = collect(RiscoEvasaoService::detalhar($matricula))->firstWhere('fator', 'Frequência');

        $this->assertSame(10, $detalhe['pontos']);
    }

    public function test_pontos_frequencia_zero_sem_aulas_registradas(): void
    {
        $matricula = Matricula::factory()->create();

        $detalhe = collect(RiscoEvasaoService::detalhar($matricula))->firstWhere('fator', 'Frequência');

        $this->assertSame(0, $detalhe['pontos']);
    }

    public function test_pontos_frequencia_ignora_aulas_fora_da_janela_de_30_dias(): void
    {
        $matricula = Matricula::factory()->create();
        $aulaAntiga = CronogramaAula::factory()->create(['data' => now()->subDays(60)->toDateString()]);

        FrequenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'cronograma_aula_id' => $aulaAntiga->id,
            'situacao' => 'ausente',
        ]);

        $detalhe = collect(RiscoEvasaoService::detalhar($matricula))->firstWhere('fator', 'Frequência');

        $this->assertSame(0, $detalhe['pontos']);
    }

    public function test_pontos_desempenho_reprovado_vale_o_maximo(): void
    {
        $matricula = Matricula::factory()->create();
        $periodo = PeriodoLetivo::factory()->create();
        $disciplina = Disciplina::factory()->create();

        SituacaoFinalDisciplina::create([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'reprovado',
        ]);

        $detalhe = collect(RiscoEvasaoService::detalhar($matricula))->firstWhere('fator', 'Desempenho');

        $this->assertSame(35, $detalhe['pontos']);
    }

    public function test_pontos_desempenho_considera_apenas_o_periodo_mais_recente(): void
    {
        $matricula = Matricula::factory()->create();
        $disciplina = Disciplina::factory()->create();
        $periodoAntigo = PeriodoLetivo::factory()->create();
        $periodoRecente = PeriodoLetivo::factory()->create();

        SituacaoFinalDisciplina::create([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodoAntigo->id,
            'situacao' => 'reprovado',
        ]);
        SituacaoFinalDisciplina::create([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodoRecente->id,
            'situacao' => 'aprovado',
        ]);

        $detalhe = collect(RiscoEvasaoService::detalhar($matricula))->firstWhere('fator', 'Desempenho');

        // Período mais recente (maior id) está Aprovado - ignora a reprovação do período antigo.
        $this->assertSame(0, $detalhe['pontos']);
    }

    public function test_pontos_inadimplencia_quando_ha_fatura_vencida(): void
    {
        $matricula = Matricula::factory()->create();
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 1000]);
        Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => now()->subDays(10)->toDateString(),
            'status' => 'atrasado',
        ]);

        $detalhe = collect(RiscoEvasaoService::detalhar($matricula->fresh()))->firstWhere('fator', 'Inadimplência');

        $this->assertSame(25, $detalhe['pontos']);
    }

    public function test_pontos_inadimplencia_zero_sem_debitos(): void
    {
        $matricula = Matricula::factory()->create();

        $detalhe = collect(RiscoEvasaoService::detalhar($matricula))->firstWhere('fator', 'Inadimplência');

        $this->assertSame(0, $detalhe['pontos']);
    }

    public function test_recalcular_persiste_score_e_data(): void
    {
        $matricula = Matricula::factory()->create();
        $this->criarFrequencia($matricula, 10, 4);

        $score = RiscoEvasaoService::recalcular($matricula);

        $this->assertSame(40, $score);
        $this->assertSame(40, $matricula->fresh()->risco_evasao_score);
        $this->assertNotNull($matricula->fresh()->risco_evasao_atualizado_em);
    }

    public function test_cor_reflete_as_faixas_configuradas(): void
    {
        $this->assertSame('danger', RiscoEvasaoService::cor(70));
        $this->assertSame('warning', RiscoEvasaoService::cor(40));
        $this->assertSame('success', RiscoEvasaoService::cor(10));
        $this->assertSame('gray', RiscoEvasaoService::cor(null));
    }
}

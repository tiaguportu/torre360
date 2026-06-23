<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Services\BoletimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BoletimServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_deve_identificar_ultima_etapa_e_calcular_frequencia_acumulada(): void
    {
        // 1. Criar PeriodoLetivo
        $periodo = PeriodoLetivo::create([
            'nome' => 'Ano Letivo Teste',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
        ]);

        // 2. Criar Turma
        $turma = Turma::create([
            'nome' => 'Turma Teste Boletim',
            'periodo_letivo_id' => $periodo->id,
        ]);

        // 3. Criar Etapas Avaliativas (Etapa 1 e Etapa 2)
        $etapaData1 = [
            'nome' => '1ª Etapa Teste',
            'periodo_letivo_id' => $periodo->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-04-30',
        ];

        $etapaData2 = [
            'nome' => '2ª Etapa Teste (Ultima)',
            'periodo_letivo_id' => $periodo->id,
            'data_inicio' => '2026-05-01',
            'data_fim' => '2026-07-31',
        ];

        if (Schema::hasColumn('etapa_avaliativa', 'turma_id')) {
            $etapaData1['turma_id'] = $turma->id;
            $etapaData2['turma_id'] = $turma->id;
        }

        $etapa1 = EtapaAvaliativa::create($etapaData1);
        $etapa2 = EtapaAvaliativa::create($etapaData2);

        // 4. Criar Disciplina e Pessoas
        $disciplina = Disciplina::create([
            'nome' => 'Disciplina Teste Boletim',
            'ordem_boletim' => 1,
        ]);

        $aluno = Pessoa::create(['nome' => 'Aluno Teste']);
        $professor = Pessoa::create(['nome' => 'Professor Teste']);

        // 4. Criar Matricula
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'status' => 'ativo',
        ]);

        // 5. Criar Aulas (Cronograma)
        // Aula na Etapa 1
        $aulaEtapa1 = CronogramaAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => '2026-03-10',
        ]);

        // Aula na Etapa 2
        $aulaEtapa2 = CronogramaAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => '2026-06-15',
        ]);

        // 6. Registrar Frequências (Presença na etapa 1, Falta na etapa 2)
        FrequenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'cronograma_aula_id' => $aulaEtapa1->id,
            'situacao' => 'presente',
        ]);

        FrequenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'cronograma_aula_id' => $aulaEtapa2->id,
            'situacao' => 'ausente',
        ]);

        // 7. Criar avaliações e notas para forçar getDadosBoletim a trazer ambas as etapas
        $categoria = CategoriaAvaliacao::first() ?? CategoriaAvaliacao::create(['nome' => 'Prova']);

        $av1 = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'etapa_avaliativa_id' => $etapa1->id,
            'categoria_avaliacao_id' => $categoria->id,
            'data_prevista' => '2026-03-10',
            'data_ocorrencia' => '2026-03-10',
            'data_limite_lancamento' => '2026-03-25',
            'nota_maxima' => 10,
        ]);

        $av2 = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'etapa_avaliativa_id' => $etapa2->id,
            'categoria_avaliacao_id' => $categoria->id,
            'data_prevista' => '2026-06-15',
            'data_ocorrencia' => '2026-06-15',
            'data_limite_lancamento' => '2026-06-30',
            'nota_maxima' => 10,
        ]);

        Nota::create([
            'matricula_id' => $matricula->id,
            'avaliacao_id' => $av1->id,
            'valor' => 8.5,
        ]);

        Nota::create([
            'matricula_id' => $matricula->id,
            'avaliacao_id' => $av2->id,
            'valor' => 7.0,
        ]);

        // Executar o BoletimService
        $service = new BoletimService;
        $dados = $service->getDadosBoletim($matricula);

        $etapasRetornadas = $dados['etapas'];

        // Devem retornar as duas etapas configuradas que possuem notas
        $this->assertCount(2, $etapasRetornadas);

        // Validar Etapa 1 (não deve ser a última)
        $dadosEtapa1 = collect($etapasRetornadas)->firstWhere('etapa.id', $etapa1->id);
        $this->assertNotNull($dadosEtapa1);
        $this->assertFalse($dadosEtapa1['is_ultima_etapa']);

        // Frequência individual da etapa 1: 1 presença de 1 aula (100%)
        $linhaDiscEtapa1 = collect($dadosEtapa1['linhas'])->firstWhere('disciplina.id', $disciplina->id);
        $this->assertEquals(100.0, $linhaDiscEtapa1['frequencia']);

        // Validar Etapa 2 (deve ser a última)
        $dadosEtapa2 = collect($etapasRetornadas)->firstWhere('etapa.id', $etapa2->id);
        $this->assertNotNull($dadosEtapa2);
        $this->assertTrue($dadosEtapa2['is_ultima_etapa']);

        // Frequência acumulada da etapa 2: 1 presença e 1 falta em 2 aulas (50%)
        $linhaDiscEtapa2 = collect($dadosEtapa2['linhas'])->firstWhere('disciplina.id', $disciplina->id);
        $this->assertEquals(50.0, $linhaDiscEtapa2['frequencia']);
    }
}

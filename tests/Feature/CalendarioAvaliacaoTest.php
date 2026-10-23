<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Curso;
use App\Models\DiaNaoLetivo;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Services\CalendarioAvaliacaoService;
use Database\Factories\PeriodoLetivoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioAvaliacaoTest extends TestCase
{
    use RefreshDatabase;

    private function criarCenarioAvaliacoes(): array
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste', 'situacao_funcionamento' => '1']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:30:00', 'hora_fim' => '12:00:00']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        $turma = Turma::create([
            'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao(),
            'nome' => 'Turma 701',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'vagas_maximas' => 30,
        ]);

        $etapa = EtapaAvaliativa::create([
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $turma->periodo_letivo_id,
            'nome' => '1º Bimestre',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-04-30',
        ]);

        $categoria = CategoriaAvaliacao::create(['nome' => 'Prova Bimestral', 'peso' => 1.0]);
        $disciplina1 = Disciplina::create(['nome' => 'Geografia', 'sigla' => 'GEO']);
        $disciplina2 = Disciplina::create(['nome' => 'Ciências', 'sigla' => 'CIE']);
        $disciplina3 = Disciplina::create(['nome' => 'História', 'sigla' => 'HIST']);
        $professor = Pessoa::create(['nome' => 'Professora Ana', 'data_nascimento' => '1988-10-15']);

        return [$turma, $etapa, $categoria, $disciplina1, $disciplina2, $disciplina3, $professor];
    }

    public function test_identifica_limite_diario_de_provas_excedido(): void
    {
        $service = app(CalendarioAvaliacaoService::class);
        [$turma, $etapa, $categoria, $d1, $d2, $d3, $prof] = $this->criarCenarioAvaliacoes();

        $dataTeste = '2026-03-20';

        // 1ª Prova no dia
        Avaliacao::create([
            'turma_id' => $turma->id,
            'etapa_avaliativa_id' => $etapa->id,
            'categoria_avaliacao_id' => $categoria->id,
            'disciplina_id' => $d1->id,
            'professor_id' => $prof->id,
            'data_prevista' => $dataTeste,
            'data_limite_lancamento' => '2026-03-30',
            'nota_maxima' => 10.0,
        ]);

        // Consulta com 1 prova: limite não foi atingido
        $conflito1 = $service->verificarConflitos($turma->id, $dataTeste);
        $this->assertFalse($conflito1['limite_diario_excedido']);
        $this->assertEquals(1, $conflito1['total_no_dia']);

        // 2ª Prova no dia
        Avaliacao::create([
            'turma_id' => $turma->id,
            'etapa_avaliativa_id' => $etapa->id,
            'categoria_avaliacao_id' => $categoria->id,
            'disciplina_id' => $d2->id,
            'professor_id' => $prof->id,
            'data_prevista' => $dataTeste,
            'data_limite_lancamento' => '2026-03-30',
            'nota_maxima' => 10.0,
        ]);

        // Ao tentar marcar uma 3ª prova para a turma no mesmo dia:
        $conflito2 = $service->verificarConflitos($turma->id, $dataTeste);
        $this->assertTrue($conflito2['limite_diario_excedido']);
        $this->assertEquals(2, $conflito2['total_no_dia']);
        $this->assertStringContainsString('Limite diário pedagógico excedido', $conflito2['aviso_formatado']);
        $this->assertStringContainsString('Geografia, Ciências', $conflito2['aviso_formatado']);
    }

    public function test_identifica_conflito_com_dia_nao_letivo_ou_feriado(): void
    {
        $service = app(CalendarioAvaliacaoService::class);
        [$turma, $etapa, $categoria, $d1] = $this->criarCenarioAvaliacoes();

        $dataFeriado = '2026-04-21'; // Tiradentes

        DiaNaoLetivo::create([
            'periodo_letivo_id' => $turma->periodo_letivo_id,
            'data' => $dataFeriado,
            'descricao' => 'Feriado de Tiradentes',
            'flag_ativo' => '1',
        ]);

        $resultado = $service->verificarConflitos($turma->id, $dataFeriado);

        $this->assertTrue($resultado['eh_dia_nao_letivo']);
        $this->assertStringContainsString('Feriado de Tiradentes', $resultado['aviso_formatado']);
    }
}

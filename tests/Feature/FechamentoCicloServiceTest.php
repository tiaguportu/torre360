<?php

namespace Tests\Feature;

use App\Enums\SituacaoFinal;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\SituacaoFinalDisciplina;
use App\Models\Turma;
use App\Services\FechamentoCicloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class FechamentoCicloServiceTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private function criarCenario(bool $recuperacaoPorEtapa, bool $exameFinalHabilitado = false): array
    {
        $periodo = PeriodoLetivo::create([
            'nome' => 'Ano Letivo Teste',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
            'nota_aprovacao' => 7,
            'nota_recuperacao_minima' => 5,
            'recuperacao_por_etapa' => $recuperacaoPorEtapa,
            'exame_final_habilitado' => $exameFinalHabilitado,
            'nota_aprovacao_pos_exame' => 5,
        ]);

        $turma = Turma::create([
            'nome' => 'Turma Teste Fechamento',
            'periodo_letivo_id' => $periodo->id,
            'tipo_avaliacao' => 'notas',
        ]);

        $etapa1 = EtapaAvaliativa::create([
            'nome' => '1ª Etapa',
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-04-30',
        ]);

        $etapa2 = EtapaAvaliativa::create([
            'nome' => '2ª Etapa',
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'data_inicio' => '2026-05-01',
            'data_fim' => '2026-07-31',
        ]);

        $disciplina = Disciplina::create(['nome' => 'Disciplina Teste Fechamento']);

        $aluno = Pessoa::create(['nome' => 'Aluno Teste Fechamento']);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $categoriaNormal = CategoriaAvaliacao::create(['nome' => 'Prova', 'eh_recuperacao' => false]);
        $categoriaRecuperacao = CategoriaAvaliacao::create(['nome' => 'Recuperação', 'eh_recuperacao' => true]);

        return compact('periodo', 'turma', 'etapa1', 'etapa2', 'disciplina', 'aluno', 'matricula', 'categoriaNormal', 'categoriaRecuperacao');
    }

    private function criarAvaliacaoComNota(Turma $turma, Disciplina $disciplina, EtapaAvaliativa $etapa, CategoriaAvaliacao $categoria, Matricula $matricula, float $nota): Avaliacao
    {
        $avaliacao = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'etapa_avaliativa_id' => $etapa->id,
            'categoria_avaliacao_id' => $categoria->id,
            'data_prevista' => $etapa->data_inicio,
            'data_ocorrencia' => $etapa->data_inicio,
            'data_limite_lancamento' => $etapa->data_fim,
            'nota_maxima' => 10,
        ]);

        Nota::create([
            'matricula_id' => $matricula->id,
            'avaliacao_id' => $avaliacao->id,
            'valor' => $nota,
        ]);

        return $avaliacao;
    }

    public function test_recuperacao_anual_substitui_apenas_a_menor_etapa(): void
    {
        [
            'periodo' => $periodo,
            'turma' => $turma,
            'etapa1' => $etapa1,
            'etapa2' => $etapa2,
            'disciplina' => $disciplina,
            'matricula' => $matricula,
            'categoriaNormal' => $categoriaNormal,
            'categoriaRecuperacao' => $categoriaRecuperacao,
        ] = $this->criarCenario(recuperacaoPorEtapa: false);

        // Etapa 1 pior (2.0) que Etapa 2 (3.0)
        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa1, $categoriaNormal, $matricula, 2.0);
        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa2, $categoriaNormal, $matricula, 3.0);

        // Recuperações lançadas em cada etapa, mas no modo anual elas são somadas e só a MENOR etapa é trocada
        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa1, $categoriaRecuperacao, $matricula, 9.0);
        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa2, $categoriaRecuperacao, $matricula, 8.0);

        $resultado = app(FechamentoCicloService::class)->calcularSituacaoFinal($matricula, $disciplina, $periodo);

        $mediaEtapa1 = collect($resultado['medias_etapas'])->firstWhere('etapa_id', $etapa1->id)['media'];
        $mediaEtapa2 = collect($resultado['medias_etapas'])->firstWhere('etapa_id', $etapa2->id)['media'];

        // Consolidado da recuperação = média(9.0, 8.0) = 8.5, substitui só a etapa 1 (2.0, a menor)
        $this->assertEquals(8.5, $mediaEtapa1);
        $this->assertEquals(3.0, $mediaEtapa2, 'No modo anual a etapa 2 não deveria ser alterada.');
        $this->assertEquals(5.75, $resultado['media_final']);
        $this->assertSame(SituacaoFinal::RECUPERACAO, $resultado['situacao']);
        $this->assertTrue($resultado['recuperacao']['aplicada']);
        $this->assertEquals($etapa1->id, $resultado['recuperacao']['etapa_substituida_id']);
    }

    public function test_recuperacao_por_etapa_substitui_cada_etapa_de_forma_independente(): void
    {
        [
            'periodo' => $periodo,
            'turma' => $turma,
            'etapa1' => $etapa1,
            'etapa2' => $etapa2,
            'disciplina' => $disciplina,
            'matricula' => $matricula,
            'categoriaNormal' => $categoriaNormal,
            'categoriaRecuperacao' => $categoriaRecuperacao,
        ] = $this->criarCenario(recuperacaoPorEtapa: true);

        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa1, $categoriaNormal, $matricula, 2.0);
        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa2, $categoriaNormal, $matricula, 3.0);

        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa1, $categoriaRecuperacao, $matricula, 9.0);
        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa2, $categoriaRecuperacao, $matricula, 8.0);

        $resultado = app(FechamentoCicloService::class)->calcularSituacaoFinal($matricula, $disciplina, $periodo);

        $mediaEtapa1 = collect($resultado['medias_etapas'])->firstWhere('etapa_id', $etapa1->id)['media'];
        $mediaEtapa2 = collect($resultado['medias_etapas'])->firstWhere('etapa_id', $etapa2->id)['media'];

        // No modo por etapa, cada etapa é recuperada com a sua própria nota de recuperação
        $this->assertEquals(9.0, $mediaEtapa1);
        $this->assertEquals(8.0, $mediaEtapa2);
        $this->assertEquals(8.5, $resultado['media_final']);
        $this->assertSame(SituacaoFinal::APROVADO, $resultado['situacao']);
        $this->assertTrue($resultado['recuperacao']['aplicada']);
        $this->assertCount(2, $resultado['recuperacao']['por_etapa']);
        $this->assertTrue(collect($resultado['recuperacao']['por_etapa'])->firstWhere('etapa_id', $etapa1->id)['aplicada']);
        $this->assertTrue(collect($resultado['recuperacao']['por_etapa'])->firstWhere('etapa_id', $etapa2->id)['aplicada']);
    }

    public function test_recuperacao_por_etapa_nao_substitui_quando_recuperacao_e_pior(): void
    {
        [
            'periodo' => $periodo,
            'turma' => $turma,
            'etapa1' => $etapa1,
            'etapa2' => $etapa2,
            'disciplina' => $disciplina,
            'matricula' => $matricula,
            'categoriaNormal' => $categoriaNormal,
            'categoriaRecuperacao' => $categoriaRecuperacao,
        ] = $this->criarCenario(recuperacaoPorEtapa: true);

        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa1, $categoriaNormal, $matricula, 8.0);
        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa2, $categoriaNormal, $matricula, 8.0);

        // Recuperação pior que a média original: não deve substituir
        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa1, $categoriaRecuperacao, $matricula, 6.0);

        $resultado = app(FechamentoCicloService::class)->calcularSituacaoFinal($matricula, $disciplina, $periodo);

        $mediaEtapa1 = collect($resultado['medias_etapas'])->firstWhere('etapa_id', $etapa1->id)['media'];

        $this->assertEquals(8.0, $mediaEtapa1);
        $this->assertFalse(collect($resultado['recuperacao']['por_etapa'])->firstWhere('etapa_id', $etapa1->id)['aplicada']);
        $this->assertFalse($resultado['recuperacao']['aplicada']);
    }

    public function test_fechar_periodo_letivo_persiste_situacao_final(): void
    {
        [
            'periodo' => $periodo,
            'turma' => $turma,
            'etapa1' => $etapa1,
            'disciplina' => $disciplina,
            'matricula' => $matricula,
            'categoriaNormal' => $categoriaNormal,
        ] = $this->criarCenario(recuperacaoPorEtapa: false);

        $turma->disciplinas()->attach($disciplina->id);

        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa1, $categoriaNormal, $matricula, 8.0);

        $resultados = app(FechamentoCicloService::class)->fecharPeriodoLetivo($periodo);

        $this->assertCount(1, $resultados);
        $this->assertDatabaseHas('situacao_final_disciplina', [
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'aprovado',
        ]);
    }

    public function test_registrar_exame_final_calcula_media_e_situacao_definitiva(): void
    {
        [
            'periodo' => $periodo,
            'matricula' => $matricula,
            'disciplina' => $disciplina,
        ] = $this->criarCenario(recuperacaoPorEtapa: false, exameFinalHabilitado: true);

        $registro = SituacaoFinalDisciplina::create([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodo->id,
            'media_final' => 6.0,
            'situacao' => SituacaoFinal::RECUPERACAO,
            'calculado_em' => now(),
        ]);

        $this->assertTrue(app(FechamentoCicloService::class)->elegivelExameFinal($registro));

        $atualizado = app(FechamentoCicloService::class)->registrarExameFinal($registro, 8.0);

        // média final pós exame = (6.0 + 8.0) / 2 = 7.0 >= nota_aprovacao_pos_exame (5.0)
        $this->assertEquals(7.0, (float) $atualizado->media_final_pos_exame);
        $this->assertSame(SituacaoFinal::APROVADO, $atualizado->situacao_final_pos_exame);
        $this->assertFalse(app(FechamentoCicloService::class)->elegivelExameFinal($atualizado->fresh()));
    }

    public function test_registrar_exame_final_pode_resultar_em_reprovado(): void
    {
        [
            'periodo' => $periodo,
            'matricula' => $matricula,
            'disciplina' => $disciplina,
        ] = $this->criarCenario(recuperacaoPorEtapa: false, exameFinalHabilitado: true);

        $registro = SituacaoFinalDisciplina::create([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodo->id,
            'media_final' => 5.0,
            'situacao' => SituacaoFinal::RECUPERACAO,
            'calculado_em' => now(),
        ]);

        $atualizado = app(FechamentoCicloService::class)->registrarExameFinal($registro, 2.0);

        // (5.0 + 2.0) / 2 = 3.5 < 5.0
        $this->assertEquals(3.5, (float) $atualizado->media_final_pos_exame);
        $this->assertSame(SituacaoFinal::REPROVADO, $atualizado->situacao_final_pos_exame);
    }

    public function test_registrar_exame_final_falha_se_periodo_nao_habilita_exame_final(): void
    {
        [
            'periodo' => $periodo,
            'matricula' => $matricula,
            'disciplina' => $disciplina,
        ] = $this->criarCenario(recuperacaoPorEtapa: false, exameFinalHabilitado: false);

        $registro = SituacaoFinalDisciplina::create([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodo->id,
            'media_final' => 6.0,
            'situacao' => SituacaoFinal::RECUPERACAO,
            'calculado_em' => now(),
        ]);

        $this->assertFalse(app(FechamentoCicloService::class)->elegivelExameFinal($registro));

        $this->expectException(InvalidArgumentException::class);
        app(FechamentoCicloService::class)->registrarExameFinal($registro, 8.0);
    }

    public function test_registrar_exame_final_falha_se_situacao_nao_e_recuperacao(): void
    {
        [
            'periodo' => $periodo,
            'matricula' => $matricula,
            'disciplina' => $disciplina,
        ] = $this->criarCenario(recuperacaoPorEtapa: false, exameFinalHabilitado: true);

        $registro = SituacaoFinalDisciplina::create([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodo->id,
            'media_final' => 8.0,
            'situacao' => SituacaoFinal::APROVADO,
            'calculado_em' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(FechamentoCicloService::class)->registrarExameFinal($registro, 9.0);
    }

    public function test_recalculo_limpa_exame_final_quando_situacao_deixa_de_ser_recuperacao(): void
    {
        [
            'periodo' => $periodo,
            'turma' => $turma,
            'etapa1' => $etapa1,
            'disciplina' => $disciplina,
            'matricula' => $matricula,
            'categoriaNormal' => $categoriaNormal,
        ] = $this->criarCenario(recuperacaoPorEtapa: false, exameFinalHabilitado: true);

        $turma->disciplinas()->attach($disciplina->id);

        $avaliacao = $this->criarAvaliacaoComNota($turma, $disciplina, $etapa1, $categoriaNormal, $matricula, 6.0);

        app(FechamentoCicloService::class)->fecharPeriodoLetivo($periodo);

        $registro = SituacaoFinalDisciplina::where([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodo->id,
        ])->firstOrFail();

        $this->assertSame(SituacaoFinal::RECUPERACAO, $registro->situacao);

        app(FechamentoCicloService::class)->registrarExameFinal($registro, 9.0);
        $this->assertNotNull($registro->fresh()->situacao_final_pos_exame);

        // Nota subiu o suficiente para aprovar direto, sem precisar de exame final
        Nota::where('avaliacao_id', $avaliacao->id)->update(['valor' => 9.0]);

        app(FechamentoCicloService::class)->fecharPeriodoLetivo($periodo);

        $registroRecalculado = $registro->fresh();
        $this->assertSame(SituacaoFinal::APROVADO, $registroRecalculado->situacao);
        $this->assertNull($registroRecalculado->nota_exame_final);
        $this->assertNull($registroRecalculado->media_final_pos_exame);
        $this->assertNull($registroRecalculado->situacao_final_pos_exame);
    }

    public function test_recalculo_preserva_exame_final_quando_situacao_continua_recuperacao(): void
    {
        [
            'periodo' => $periodo,
            'turma' => $turma,
            'etapa1' => $etapa1,
            'disciplina' => $disciplina,
            'matricula' => $matricula,
            'categoriaNormal' => $categoriaNormal,
        ] = $this->criarCenario(recuperacaoPorEtapa: false, exameFinalHabilitado: true);

        $turma->disciplinas()->attach($disciplina->id);

        $this->criarAvaliacaoComNota($turma, $disciplina, $etapa1, $categoriaNormal, $matricula, 6.0);

        app(FechamentoCicloService::class)->fecharPeriodoLetivo($periodo);

        $registro = SituacaoFinalDisciplina::where([
            'matricula_id' => $matricula->id,
            'disciplina_id' => $disciplina->id,
            'periodo_letivo_id' => $periodo->id,
        ])->firstOrFail();

        app(FechamentoCicloService::class)->registrarExameFinal($registro, 9.0);

        // Recalcula de novo sem mudar nada: continua em recuperação, o exame final não deve ser apagado
        app(FechamentoCicloService::class)->fecharPeriodoLetivo($periodo);

        $registroRecalculado = $registro->fresh();
        $this->assertSame(SituacaoFinal::RECUPERACAO, $registroRecalculado->situacao);
        $this->assertNotNull($registroRecalculado->nota_exame_final);
        $this->assertNotNull($registroRecalculado->situacao_final_pos_exame);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\SituacaoFinal;
use App\Models\CronogramaAula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\FrequenciaEscolar;
use App\Models\HistoricoEscolar;
use App\Models\HistoricoEscolarAno;
use App\Models\HistoricoEscolarDisciplina;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\SituacaoFinalDisciplina;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\HistoricoEscolarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HistoricoEscolarTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_criar_historico_escolar_com_anos_e_disciplinas(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Carlos Eduardo de Oliveira',
            'cpf' => '11122233344',
            'data_nascimento' => '2010-03-15',
        ]);

        $historico = HistoricoEscolar::create([
            'pessoa_id' => $aluno->id,
            'codigo_autenticidade' => HistoricoEscolar::gerarCodigoAutenticidade(),
            'situacao' => 'concluido',
            'data_emissao' => now(),
            'data_conclusao' => now(),
        ]);

        $this->assertDatabaseHas('historico_escolars', [
            'id' => $historico->id,
            'pessoa_id' => $aluno->id,
            'situacao' => 'concluido',
        ]);

        $ano1 = HistoricoEscolarAno::create([
            'historico_escolar_id' => $historico->id,
            'ano_letivo' => 2024,
            'serie_nome' => '8º Ano',
            'ordem' => 1,
            'tipo' => 'interno',
            'escola_nome' => 'Torre de Marfim',
            'carga_horaria_total' => 800,
            'dias_letivos' => 200,
            'frequencia_percentual' => 98.00,
            'situacao_ano' => 'Aprovado',
        ]);

        $disc1 = HistoricoEscolarDisciplina::create([
            'historico_escolar_ano_id' => $ano1->id,
            'disciplina_nome' => 'Língua Portuguesa',
            'area_conhecimento' => 'Linguagens',
            'carga_horaria' => 160,
            'nota_final' => 8.5,
            'situacao' => 'Aprovado',
        ]);

        $this->assertCount(1, $historico->anos);
        $this->assertCount(1, $ano1->disciplinas);
        $this->assertEquals('Língua Portuguesa', $disc1->disciplina_nome);
    }

    public function test_servico_monta_matriz_tabular_multi_ano_corretamente(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Mariana Silveira',
            'cpf' => '22233344455',
            'data_nascimento' => '2009-07-22',
        ]);

        $historico = HistoricoEscolar::create([
            'pessoa_id' => $aluno->id,
            'codigo_autenticidade' => 'HIST-2026-TEST-0001',
            'situacao' => 'concluido',
            'data_emissao' => now(),
        ]);

        // Ano 1 (2024 - 8º Ano)
        $ano1 = HistoricoEscolarAno::create([
            'historico_escolar_id' => $historico->id,
            'ano_letivo' => 2024,
            'serie_nome' => '8º Ano',
            'ordem' => 1,
            'tipo' => 'interno',
            'escola_nome' => 'Torre de Marfim',
            'escola_cidade' => 'São Paulo',
            'escola_uf' => 'SP',
            'carga_horaria_total' => 800,
            'dias_letivos' => 200,
            'frequencia_percentual' => 97.5,
            'situacao_ano' => 'Aprovado',
        ]);

        HistoricoEscolarDisciplina::create([
            'historico_escolar_ano_id' => $ano1->id,
            'disciplina_nome' => 'Matemática',
            'area_conhecimento' => 'Matemática',
            'carga_horaria' => 160,
            'nota_final' => 9.0,
            'situacao' => 'Aprovado',
        ]);

        // Ano 2 (2025 - 9º Ano)
        $ano2 = HistoricoEscolarAno::create([
            'historico_escolar_id' => $historico->id,
            'ano_letivo' => 2025,
            'serie_nome' => '9º Ano',
            'ordem' => 2,
            'tipo' => 'interno',
            'escola_nome' => 'Torre de Marfim',
            'escola_cidade' => 'São Paulo',
            'escola_uf' => 'SP',
            'carga_horaria_total' => 800,
            'dias_letivos' => 200,
            'frequencia_percentual' => 99.0,
            'situacao_ano' => 'Aprovado',
        ]);

        HistoricoEscolarDisciplina::create([
            'historico_escolar_ano_id' => $ano2->id,
            'disciplina_nome' => 'Matemática',
            'area_conhecimento' => 'Matemática',
            'carga_horaria' => 160,
            'nota_final' => 9.5,
            'situacao' => 'Aprovado',
        ]);

        $service = app(HistoricoEscolarService::class);
        $matriz = $service->montarMatrizTabular($historico);

        $this->assertCount(2, $matriz['anos']);
        $this->assertArrayHasKey('Matemática', $matriz['areas']);
        $this->assertArrayHasKey('Matemática', $matriz['areas']['Matemática']);

        // Valores por ano na disciplina
        $valores = $matriz['areas']['Matemática']['Matemática'];
        $this->assertEquals(9.0, $valores[$ano1->id]['nota']);
        $this->assertEquals(9.5, $valores[$ano2->id]['nota']);

        // Totais
        $this->assertEquals(800, $matriz['totais_anos'][$ano1->id]['ch_total']);
        $this->assertEquals(97.5, $matriz['totais_anos'][$ano1->id]['freq']);
    }

    public function test_sincronizacao_de_matriculas_internas_popula_historico(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Beatriz Mendes',
            'cpf' => '33344455566',
            'data_nascimento' => '2011-11-10',
        ]);

        $unidade = Unidade::create(['nome' => 'Torre de Marfim']);
        $curso = Curso::create([
            'nome_interno' => 'Ensino Fundamental',
            'nome_externo' => 'Ensino Fundamental',
            'unidade_id' => $unidade->id,
        ]);
        $serie = Serie::create([
            'nome' => '6º Ano',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
        $periodo = PeriodoLetivo::create([
            'nome' => '2025',
            'data_inicio' => '2025-02-01',
            'data_fim' => '2025-12-15',
        ]);

        $turma = Turma::create([
            'nome' => '6º Ano A',
            'serie_id' => $serie->id,
            'periodo_letivo_id' => $periodo->id,
            'carga_horaria_total' => 840,
        ]);

        $disc = Disciplina::create([
            'nome' => 'História',
            'carga_horaria_semanal' => 3,
        ]);

        $turma->disciplinas()->attach($disc->id);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'concluido',
            'data_ativacao' => '2025-02-05',
        ]);

        $historico = HistoricoEscolar::create([
            'pessoa_id' => $aluno->id,
            'curso_id' => $curso->id,
            'codigo_autenticidade' => 'HIST-2026-TEST-0002',
            'situacao' => 'concluido',
            'data_emissao' => now(),
        ]);

        $service = app(HistoricoEscolarService::class);
        $res = $service->sincronizarMatriculasInternas($historico);

        $this->assertEquals(1, $res['anos_sincronizados']);
        $this->assertEquals(1, $res['disciplinas_sincronizadas']);

        $this->assertDatabaseHas('historico_escolar_anos', [
            'historico_escolar_id' => $historico->id,
            'matricula_id' => $matricula->id,
            'ano_letivo' => 2025,
            'serie_nome' => '6º Ano',
        ]);

        $this->assertDatabaseHas('historico_escolar_disciplinas', [
            'disciplina_nome' => 'História',
            'disciplina_id' => $disc->id,
        ]);
    }

    // --- Apuração automática da sincronização (LDB, art. 24) --------------------------------------------------------------

    /**
     * Monta uma matrícula já com situação lançada, pronta para sincronizar.
     *
     * @param  array<int, float>  $medias  média final de cada disciplina da turma (uma disciplina por item)
     * @param  array<string, mixed>  $periodo  atributos do período letivo (nota de corte etc.)
     * @param  array<string, mixed>  $exame  atributos extras do SituacaoFinalDisciplina da primeira disciplina
     * @return array{historico: HistoricoEscolar, matricula: Matricula, disciplinas: array<int, Disciplina>}
     */
    private function cenarioDeApuracao(array $medias, int $presencas = 10, int $faltas = 0, array $periodo = [], string $situacaoMatricula = 'concluido', array $exame = []): array
    {
        $aluno = Pessoa::create(['nome' => 'Aluno Apuração', 'data_nascimento' => '2011-05-10']);
        $unidade = Unidade::create(['nome' => 'Torre de Marfim']);
        $curso = Curso::create(['nome_interno' => 'Ensino Fundamental', 'nome_externo' => 'Ensino Fundamental', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '7º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $periodoLetivo = PeriodoLetivo::create(array_merge(['nome' => '2025', 'data_inicio' => '2025-02-01', 'data_fim' => '2025-12-15'], $periodo));
        $turma = Turma::create(['nome' => '7º Ano A', 'serie_id' => $serie->id, 'periodo_letivo_id' => $periodoLetivo->id, 'carga_horaria_total' => 800]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => $situacaoMatricula,
            'data_ativacao' => '2025-02-05',
        ]);

        $disciplinas = [];
        foreach (array_values($medias) as $i => $media) {
            $disciplina = Disciplina::create(['nome' => 'Disciplina '.($i + 1), 'carga_horaria_semanal' => 3]);
            $turma->disciplinas()->attach($disciplina->id);
            $disciplinas[] = $disciplina;

            SituacaoFinalDisciplina::create(array_merge([
                'matricula_id' => $matricula->id,
                'disciplina_id' => $disciplina->id,
                'periodo_letivo_id' => $periodoLetivo->id,
                'media_final' => $media,
                'calculado_em' => now(),
            ], $i === 0 ? $exame : []));
        }

        // Frequência: $presencas aulas presentes e $faltas ausentes (frequência = presenças / total).
        $disciplinaDaAula = $disciplinas[0];
        foreach (array_merge(array_fill(0, $presencas, 'presente'), array_fill(0, $faltas, 'ausente')) as $i => $situacao) {
            $aula = CronogramaAula::factory()->create([
                'turma_id' => $turma->id,
                'disciplina_id' => $disciplinaDaAula->id,
                'data' => '2025-03-03',
            ]);

            FrequenciaEscolar::create(['matricula_id' => $matricula->id, 'cronograma_aula_id' => $aula->id, 'situacao' => $situacao]);
        }

        $historico = HistoricoEscolar::create([
            'pessoa_id' => $aluno->id,
            'curso_id' => $curso->id,
            'codigo_autenticidade' => HistoricoEscolar::gerarCodigoAutenticidade(),
            'situacao' => 'concluido',
            'data_emissao' => now(),
        ]);

        return ['historico' => $historico, 'matricula' => $matricula, 'disciplinas' => $disciplinas];
    }

    public function test_sincronizacao_utiliza_nota_de_aprovacao_do_periodo_letivo(): void
    {
        ['historico' => $historico, 'disciplinas' => [$disciplina]] = $this->cenarioDeApuracao([7.5], periodo: ['nota_aprovacao' => 8.0]);

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        // 7,5 passaria no corte antigo (6,0) e no padrão do sistema (7,0), mas não no corte de 8,0 deste período.
        $this->assertDatabaseHas('historico_escolar_disciplinas', [
            'disciplina_id' => $disciplina->id,
            'nota_final' => 7.5,
            'situacao' => 'Reprovado',
        ]);
    }

    public function test_sincronizacao_aprova_nota_entre_o_corte_antigo_e_o_do_periodo_apenas_se_o_periodo_permitir(): void
    {
        ['historico' => $historico, 'disciplinas' => [$disciplina]] = $this->cenarioDeApuracao([6.5], periodo: ['nota_aprovacao' => 6.0]);

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        // Com corte 6,0 no período, 6,5 aprova; o mesmo 6,5 reprovaria no corte padrão de 7,0.
        $this->assertDatabaseHas('historico_escolar_disciplinas', ['disciplina_id' => $disciplina->id, 'situacao' => 'Aprovado']);
    }

    public function test_sincronizacao_considera_media_e_situacao_pos_exame_final(): void
    {
        ['historico' => $historico, 'disciplinas' => [$disciplina]] = $this->cenarioDeApuracao(
            [4.0],
            exame: [
                'situacao' => SituacaoFinal::RECUPERACAO,
                'nota_exame_final' => 8.0,
                'media_final_pos_exame' => 6.0,
                'situacao_final_pos_exame' => SituacaoFinal::APROVADO,
            ],
        );

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        $this->assertDatabaseHas('historico_escolar_disciplinas', [
            'disciplina_id' => $disciplina->id,
            'nota_final' => 6.0,
            'situacao' => 'Aprovado',
        ]);
        $this->assertDatabaseHas('historico_escolar_anos', ['historico_escolar_id' => $historico->id, 'situacao_ano' => 'Aprovado']);
    }

    public function test_sincronizacao_reprova_a_disciplina_quando_o_exame_final_nao_aprova(): void
    {
        ['historico' => $historico, 'disciplinas' => [$disciplina]] = $this->cenarioDeApuracao(
            [6.0],
            exame: [
                'situacao' => SituacaoFinal::RECUPERACAO,
                'nota_exame_final' => 2.0,
                'media_final_pos_exame' => 4.0,
                'situacao_final_pos_exame' => SituacaoFinal::REPROVADO,
            ],
        );

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        $this->assertDatabaseHas('historico_escolar_disciplinas', [
            'disciplina_id' => $disciplina->id,
            'nota_final' => 4.0,
            'situacao' => 'Reprovado',
        ]);
    }

    public function test_sincronizacao_mantem_disciplina_sem_nota_como_pendente(): void
    {
        ['historico' => $historico, 'matricula' => $matricula, 'disciplinas' => [$disciplina]] = $this->cenarioDeApuracao([7.0]);
        SituacaoFinalDisciplina::where('matricula_id', $matricula->id)->update(['media_final' => null]);

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        $this->assertDatabaseHas('historico_escolar_disciplinas', [
            'disciplina_id' => $disciplina->id,
            'nota_final' => null,
            'situacao' => 'Pendente',
        ]);
    }

    public function test_situacao_ano_aprovado_quando_nenhuma_disciplina_reprova(): void
    {
        ['historico' => $historico] = $this->cenarioDeApuracao([8.0, 9.0, 7.0], presencas: 9, faltas: 1);

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        $this->assertDatabaseHas('historico_escolar_anos', [
            'historico_escolar_id' => $historico->id,
            'frequencia_percentual' => 90.00,
            'situacao_ano' => 'Aprovado',
        ]);
    }

    public function test_situacao_ano_aprovado_com_dependencia_quando_reprova_em_ate_duas_disciplinas(): void
    {
        ['historico' => $historico] = $this->cenarioDeApuracao([4.0, 5.0, 8.0, 9.0], presencas: 8, faltas: 2);

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        $this->assertDatabaseHas('historico_escolar_anos', [
            'historico_escolar_id' => $historico->id,
            'frequencia_percentual' => 80.00,
            'situacao_ano' => 'Aprovado com Dependência',
        ]);
    }

    public function test_situacao_ano_reprovado_quando_reprova_em_tres_ou_mais_disciplinas(): void
    {
        ['historico' => $historico] = $this->cenarioDeApuracao([4.0, 5.0, 6.0, 9.0], presencas: 8, faltas: 2);

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        $this->assertDatabaseHas('historico_escolar_anos', [
            'historico_escolar_id' => $historico->id,
            'frequencia_percentual' => 80.00,
            'situacao_ano' => 'Reprovado',
        ]);
    }

    public function test_situacao_ano_reprovado_por_faltas_quando_frequencia_inferior_a_75_porcento(): void
    {
        ['historico' => $historico] = $this->cenarioDeApuracao([10.0, 10.0], presencas: 7, faltas: 3);

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        $this->assertDatabaseHas('historico_escolar_anos', [
            'historico_escolar_id' => $historico->id,
            'frequencia_percentual' => 70.00,
            'situacao_ano' => 'Reprovado por Faltas',
        ]);
    }

    public function test_frequencia_de_exatos_75_porcento_nao_reprova_por_faltas(): void
    {
        ['historico' => $historico] = $this->cenarioDeApuracao([10.0], presencas: 3, faltas: 1);

        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        $this->assertDatabaseHas('historico_escolar_anos', [
            'historico_escolar_id' => $historico->id,
            'frequencia_percentual' => 75.00,
            'situacao_ano' => 'Aprovado',
        ]);
    }

    public function test_matricula_nao_concluida_nao_recebe_resultado_final(): void
    {
        ['historico' => $ativa] = $this->cenarioDeApuracao([2.0, 2.0, 2.0], presencas: 1, faltas: 9, situacaoMatricula: 'ativa');
        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($ativa);
        $this->assertDatabaseHas('historico_escolar_anos', ['historico_escolar_id' => $ativa->id, 'situacao_ano' => 'Cursando']);

        ['historico' => $cancelada] = $this->cenarioDeApuracao([2.0], situacaoMatricula: 'cancelada');
        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($cancelada);
        $this->assertDatabaseHas('historico_escolar_anos', ['historico_escolar_id' => $cancelada->id, 'situacao_ano' => 'Transferido']);
    }

    public function test_matricula_pendente_ou_em_reserva_nao_entra_no_historico(): void
    {
        foreach (['pendente', 'reserva'] as $situacao) {
            ['historico' => $historico] = $this->cenarioDeApuracao([9.0], situacaoMatricula: $situacao);

            $res = app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

            $this->assertSame(0, $res['anos_sincronizados'], "Matrícula {$situacao} não deveria virar ano do histórico.");
            $this->assertSame(0, $historico->anos()->count());
        }
    }

    public function test_ressincronizar_atualiza_a_situacao_do_ano_sem_duplicar(): void
    {
        ['historico' => $historico, 'matricula' => $matricula] = $this->cenarioDeApuracao([4.0, 4.0, 4.0]);
        $service = app(HistoricoEscolarService::class);

        $service->sincronizarMatriculasInternas($historico);
        $this->assertDatabaseHas('historico_escolar_anos', ['historico_escolar_id' => $historico->id, 'situacao_ano' => 'Reprovado']);

        // A secretaria corrige duas notas e sincroniza de novo: o mesmo ano passa a ter dependência.
        SituacaoFinalDisciplina::where('matricula_id', $matricula->id)->orderBy('id')->limit(2)->update(['media_final' => 8.0]);
        $service->sincronizarMatriculasInternas($historico);

        $this->assertSame(1, $historico->anos()->count());
        $this->assertSame('Aprovado com Dependência', $historico->anos()->first()->situacao_ano);
    }

    public function test_matriz_tabular_preserva_dependencia_e_reprovacao_por_faltas(): void
    {
        $aluno = Pessoa::create(['nome' => 'Aluno Matriz', 'data_nascimento' => '2010-01-01']);
        $historico = HistoricoEscolar::create([
            'pessoa_id' => $aluno->id,
            'codigo_autenticidade' => HistoricoEscolar::gerarCodigoAutenticidade(),
            'situacao' => 'concluido',
            'data_emissao' => now(),
        ]);

        $anoDependencia = HistoricoEscolarAno::create([
            'historico_escolar_id' => $historico->id, 'ano_letivo' => 2024, 'serie_nome' => '8º Ano', 'ordem' => 1,
            'tipo' => 'interno', 'escola_nome' => 'Torre de Marfim', 'frequencia_percentual' => 80,
            'situacao_ano' => 'Aprovado com Dependência',
        ]);
        $anoFaltas = HistoricoEscolarAno::create([
            'historico_escolar_id' => $historico->id, 'ano_letivo' => 2025, 'serie_nome' => '9º Ano', 'ordem' => 2,
            'tipo' => 'interno', 'escola_nome' => 'Torre de Marfim', 'frequencia_percentual' => 70,
            'situacao_ano' => 'Reprovado por Faltas',
        ]);

        $matriz = app(HistoricoEscolarService::class)->montarMatrizTabular($historico);

        $this->assertSame('Aprovado com Dependência', $matriz['totais_anos'][$anoDependencia->id]['situacao']);
        $this->assertSame('Reprovado por Faltas', $matriz['totais_anos'][$anoFaltas->id]['situacao']);
    }

    public function test_pdf_exibe_resultado_do_ano_com_dependencia_e_por_faltas(): void
    {
        ['historico' => $historico] = $this->cenarioDeApuracao([4.0, 9.0], presencas: 6, faltas: 4);
        app(HistoricoEscolarService::class)->sincronizarMatriculasInternas($historico);

        $html = view('pdfs.historico_escolar', $this->dadosMinimosDaView($historico))->render();

        $this->assertStringContainsString('REPROVADO POR FALTAS', $html);
        $this->assertStringContainsString('color: #c53030', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosMinimosDaView(HistoricoEscolar $historico): array
    {
        return [
            'historico' => $historico,
            'aluno' => $historico->pessoa,
            'unidade' => null,
            'instituicao' => null,
            'nomePai' => 'Não declarado',
            'nomeMae' => 'Não declarada',
            'diretorNome' => 'Diretor(a) Escolar',
            'secretarioNome' => 'Secretário(a) Escolar',
            'matriz' => app(HistoricoEscolarService::class)->montarMatrizTabular($historico),
            'qrCodeDataUri' => null,
            'urlValidacao' => url('/validar-documento/'.$historico->codigo_autenticidade),
            'logoBase64' => null,
            'dataEmissaoFormatada' => '01 de janeiro de 2026',
        ];
    }

    public function test_servico_gera_pdf_com_sucesso(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Gabriel Henrique',
            'cpf' => '44455566677',
            'data_nascimento' => '2008-01-20',
        ]);

        $historico = HistoricoEscolar::create([
            'pessoa_id' => $aluno->id,
            'codigo_autenticidade' => 'HIST-2026-PDF-0001',
            'situacao' => 'em_curso',
            'data_emissao' => now(),
        ]);

        $service = app(HistoricoEscolarService::class);
        $pdf = $service->gerarPdf($historico);

        $this->assertNotNull($pdf);
        $output = $pdf->output();
        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_validacao_publica_de_historico_escolar(): void
    {
        $aluno = Pessoa::create([
            'nome' => 'Thiago Ferreira Silva',
            'cpf' => '55566677788',
        ]);

        $historico = HistoricoEscolar::create([
            'pessoa_id' => $aluno->id,
            'codigo_autenticidade' => 'HIST-2026-VAL-9999',
            'situacao' => 'concluido',
            'data_emissao' => now(),
        ]);

        $response = $this->get('/validar-documento/HIST-2026-VAL-9999');

        $response->assertStatus(200);
        $response->assertSee('Histórico Escolar Autêntico e Válido');
        $response->assertSee('HISTÓRICO ESCOLAR OFICIAL MULTI-ANO');
        $response->assertSee('HIST-2026-VAL-9999');
        // Nome mascarado (LGPD)
        $response->assertSee('T***** F****** S****');
    }

    public function test_usuario_staff_pode_visualizar_pdf_do_historico(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $aluno = Pessoa::create([
            'nome' => 'Larissa Souza',
            'cpf' => '66677788899',
        ]);

        $historico = HistoricoEscolar::create([
            'pessoa_id' => $aluno->id,
            'codigo_autenticidade' => 'HIST-2026-TEST-STREAM',
            'situacao' => 'concluido',
            'data_emissao' => now(),
        ]);

        $response = $this->actingAs($admin)->get("/historicos-escolares/{$historico->id}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}

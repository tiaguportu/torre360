<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\HistoricoEscolar;
use App\Models\HistoricoEscolarAno;
use App\Models\HistoricoEscolarDisciplina;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
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
        $admin = User::factory()->create();
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

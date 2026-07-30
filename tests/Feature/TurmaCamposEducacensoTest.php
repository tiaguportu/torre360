<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurmaCamposEducacensoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_criar_turma_com_campos_do_educacenso(): void
    {
        $unidade = Unidade::create(['nome' => 'Unidade Sede']);
        $curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'EF',
        ]);
        $serie = Serie::create([
            'nome' => '1º Ano',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'nota',
        ]);
        $turno = Turno::create([
            'nome' => 'Matutino',
            'hora_inicio' => '07:00:00',
            'hora_fim' => '12:00:00',
        ]);

        $turma = Turma::create([
            'nome' => 'Turma 101 Especial',
            'codigo' => 'TURMA-2026-01',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'carga_horaria_total' => 800,
            'tipo_mediacao_didatico_pedagogica' => 1, // Presencial
            'tipo_turma' => 6, // Curricular
            'local_funcionamento_diferenciado' => 0, // Não diferenciado
            'turma_educacao_especial' => true,
        ]);

        $this->assertDatabaseHas('turma', [
            'id' => $turma->id,
            'codigo' => 'TURMA-2026-01',
            'carga_horaria_total' => 800,
            'tipo_mediacao_didatico_pedagogica' => 1,
            'tipo_turma' => 6,
            'local_funcionamento_diferenciado' => 0,
            'turma_educacao_especial' => 1,
        ]);
    }

    public function test_pode_cadastrar_horarios_de_funcionamento_para_dias_da_semana(): void
    {
        $unidade = Unidade::create(['nome' => 'Unidade Sede']);
        $curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'EF',
        ]);
        $serie = Serie::create([
            'nome' => '1º Ano',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'nota',
        ]);
        $turno = Turno::create([
            'nome' => 'Matutino',
            'hora_inicio' => '07:00:00',
            'hora_fim' => '12:00:00',
        ]);

        $turma = Turma::create([
            'nome' => 'Turma 102 Horarios',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
        ]);

        // Cadastro de horarios de funcionamento para Segunda (1) e Quarta (3)
        $turma->horariosFuncionamento()->create([
            'dia_semana' => 1, // Segunda
            'hora_inicio' => '07:30:00',
            'hora_fim' => '11:45:00',
        ]);

        $turma->horariosFuncionamento()->create([
            'dia_semana' => 3, // Quarta
            'hora_inicio' => '07:30:00',
            'hora_fim' => '11:45:00',
        ]);

        $this->assertCount(2, $turma->horariosFuncionamento);

        $this->assertDatabaseHas('turma_horario', [
            'turma_id' => $turma->id,
            'dia_semana' => 1,
            'hora_inicio' => '07:30:00',
            'hora_fim' => '11:45:00',
        ]);

        $this->assertDatabaseHas('turma_horario', [
            'turma_id' => $turma->id,
            'dia_semana' => 3,
            'hora_inicio' => '07:30:00',
            'hora_fim' => '11:45:00',
        ]);
    }
}

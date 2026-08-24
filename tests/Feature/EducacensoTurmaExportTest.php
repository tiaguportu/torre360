<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\EtapaEnsino;
use App\Models\EtapaEnsinoAgregada;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\TurmaHorario;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Educacenso\EducacensoTurmaExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EducacensoTurmaExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_exportar_turma_no_formato_registro_20_do_educacenso(): void
    {
        $unidade = Unidade::create([
            'nome' => 'Unidade Central',
            'codigo_inep' => '12345678',
        ]);
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

        $etapaAgregada = EtapaEnsinoAgregada::create([
            'codigo' => '302',
            'nome' => 'Ensino Fundamental',
        ]);

        $etapaEnsino = EtapaEnsino::create([
            'etapa_ensino_agregada_id' => $etapaAgregada->id,
            'codigo' => '14',
            'nome' => 'Ensino fundamental de 9 anos - 1º Ano',
        ]);

        $turma = Turma::create([
            'nome' => 'Turma 101',
            'codigo' => 'T101',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'etapa_ensino_agregada_id' => $etapaAgregada->id,
            'etapa_ensino_id' => $etapaEnsino->id,
            'carga_horaria_total' => 800,
            'tipo_mediacao_didatico_pedagogica' => 1,
            'tipo_turma' => 6,
            'local_funcionamento_diferenciado' => 0,
            'turma_educacao_especial' => false,
            'forma_organizacao' => 1,
            'modalidade_ensino' => 1,
            'tipo_lingua_ministrada' => 1,
            'flag_aee_ensino_libras' => true,
        ]);

        TurmaHorario::create([
            'turma_id' => $turma->id,
            'dia_semana' => 1, // Segunda
            'hora_inicio' => '07:00:00',
            'hora_fim' => '12:00:00',
        ]);

        $exporter = new EducacensoTurmaExporter;
        $line = $exporter->buildRegistro20Line($turma);

        $fields = explode('|', $line);

        $this->assertCount(66, $fields);
        $this->assertEquals('20', $fields[0]); // 1. Registro
        $this->assertEquals('12345678', $fields[1]); // 2. Código INEP Escola
        $this->assertEquals('T101', $fields[2]); // 3. Código Turma
        $this->assertEquals('', $fields[3]); // 4. INEP Turma
        $this->assertEquals('TURMA 101', $fields[4]); // 5. Nome Turma (Sanitizado A-Z 0-9 ª º -)
        $this->assertEquals('1', $fields[5]); // 6. Mediação
        $this->assertEquals('', $fields[6]); // 7. Domingo
        $this->assertEquals('07:00-12:00', $fields[7]); // 8. Segunda (do mock)
        $this->assertEquals('08:00-16:00', $fields[8]); // 9. Terça (fallback)
        $this->assertEquals('6', $fields[13]); // 14. Tipo Turma
        $this->assertEquals('0', $fields[20]); // 21. Local Funcionamento Diferenciado
        $this->assertEquals('0', $fields[21]); // 22. Classe Especial
        $this->assertEquals('302', $fields[22]); // 23. Etapa Agregada
        $this->assertEquals('14', $fields[23]); // 24. Etapa
        $this->assertEquals('1', $fields[27]); // 28. Forma de Organização da Turma
        $this->assertEquals('0', $fields[28]); // 29. Alternância
    }

    public function test_acao_de_exportacao_em_lote_gera_arquivo_txt(): void
    {
        Permission::findOrCreate('ViewAny:Turma', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('ViewAny:Turma');

        $turma = Turma::factory()->create([
            'codigo' => 'TB',
            'tipo_mediacao_didatico_pedagogica' => 1,
            'tipo_turma' => 6,
        ]);

        $exporter = new EducacensoTurmaExporter;
        $output = $exporter->export(collect([$turma]));

        $this->assertStringStartsWith('20|', $output);
        $this->assertStringContainsString('|TB|', $output);
    }
}

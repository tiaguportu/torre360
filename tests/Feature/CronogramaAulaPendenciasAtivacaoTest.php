<?php

namespace Tests\Feature;

use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronogramaAulaPendenciasAtivacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_matricula_nao_gera_pendencia_em_aula_anterior_a_sua_data_de_ativacao(): void
    {
        $turma = Turma::factory()->create();
        $aluno = Pessoa::factory()->create();
        $disciplina = Disciplina::factory()->create();

        // Aluno ativado no dia 10
        $matricula = Matricula::factory()->create([
            'turma_id' => $turma->id,
            'pessoa_id' => $aluno->id,
            'data_ativacao' => '2026-05-10',
            'data_desativacao' => null,
        ]);

        // Aula no dia 05 (antes da ativação)
        $aulaAnterior = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => '2026-05-05',
        ]);

        // Aula no dia 12 (depois da ativação)
        $aulaPosterior = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => '2026-05-12',
        ]);

        // Aula anterior não deve ter pendência para este aluno
        $this->assertFalse($aulaAnterior->hasPendingFrequencies());

        // Aula posterior deve ter pendência
        $this->assertTrue($aulaPosterior->hasPendingFrequencies());
    }

    public function test_matricula_desativada_nao_gera_pendencia_apos_data_de_desativacao(): void
    {
        $turma = Turma::factory()->create();
        $aluno = Pessoa::factory()->create();
        $disciplina = Disciplina::factory()->create();

        // Aluno ativado no dia 01 e desativado no dia 10
        $matricula = Matricula::factory()->create([
            'turma_id' => $turma->id,
            'pessoa_id' => $aluno->id,
            'data_ativacao' => '2026-05-01',
            'data_desativacao' => '2026-05-10',
        ]);

        // Aula no dia 05 (durante o período ativo)
        $aulaAtiva = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => '2026-05-05',
        ]);

        // Aula no dia 15 (após a desativação)
        $aulaDesativada = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => '2026-05-15',
        ]);

        $this->assertTrue($aulaAtiva->hasPendingFrequencies());
        $this->assertFalse($aulaDesativada->hasPendingFrequencies());
    }
}

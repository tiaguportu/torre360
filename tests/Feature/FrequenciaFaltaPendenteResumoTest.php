<?php

namespace Tests\Feature;

use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A falta lançada não avisa a família na hora: o observer só a marca como pendente de resumo
 * (o envio é do `frequencia:enviar-resumo-diario`, coberto em ResumoFaltasDiarioTest).
 */
class FrequenciaFaltaPendenteResumoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{matricula: Matricula, aula: CronogramaAula}
     */
    private function alunoComAula(array $matriculaAttrs = []): array
    {
        $turma = Turma::factory()->create();
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Faltoso']);
        $matricula = Matricula::factory()->create(array_merge([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
        ], $matriculaAttrs));

        $aula = CronogramaAula::factory()->create(['turma_id' => $turma->id, 'data' => now()->toDateString()]);

        return compact('matricula', 'aula');
    }

    private function lancar(array $dados, string $situacao): FrequenciaEscolar
    {
        return FrequenciaEscolar::create([
            'matricula_id' => $dados['matricula']->id,
            'cronograma_aula_id' => $dados['aula']->id,
            'situacao' => $situacao,
        ]);
    }

    public function test_registrar_falta_marca_pendente_e_nao_avisa_na_hora(): void
    {
        Notification::fake();
        $dados = $this->alunoComAula();

        $frequencia = $this->lancar($dados, 'ausente');

        $this->assertTrue($frequencia->fresh()->aviso_resumo_pendente);
        Notification::assertNothingSent();
    }

    public function test_presenca_nao_marca_pendente(): void
    {
        $dados = $this->alunoComAula();

        $frequencia = $this->lancar($dados, 'presente');

        $this->assertFalse($frequencia->fresh()->aviso_resumo_pendente);
    }

    public function test_alterar_presenca_para_falta_marca_pendente(): void
    {
        $dados = $this->alunoComAula();
        $frequencia = $this->lancar($dados, 'presente');

        $frequencia->update(['situacao' => 'ausente']);

        $this->assertTrue($frequencia->fresh()->aviso_resumo_pendente);
    }

    public function test_corrigir_falta_para_presenca_antes_do_resumo_tira_da_fila(): void
    {
        $dados = $this->alunoComAula();
        $frequencia = $this->lancar($dados, 'ausente');

        $frequencia->update(['situacao' => 'presente']);

        $this->assertFalse($frequencia->fresh()->aviso_resumo_pendente);
    }

    public function test_manter_falta_ja_avisada_nao_volta_para_a_fila(): void
    {
        $dados = $this->alunoComAula();
        $frequencia = $this->lancar($dados, 'ausente');
        $frequencia->forceFill(['aviso_resumo_pendente' => false, 'aviso_resumo_enviado_em' => now()])->saveQuietly();

        $frequencia->update(['situacao' => 'ausente', 'motivo_justificativa' => 'Consulta médica']);

        $this->assertFalse($frequencia->fresh()->aviso_resumo_pendente);
    }

    public function test_voltar_a_ser_falta_depois_de_corrigida_marca_de_novo(): void
    {
        $dados = $this->alunoComAula();
        $frequencia = $this->lancar($dados, 'ausente');
        $frequencia->update(['situacao' => 'presente']);

        $frequencia->update(['situacao' => 'ausente']);

        $this->assertTrue($frequencia->fresh()->aviso_resumo_pendente);
    }

    public function test_nao_marca_falta_de_aula_anterior_a_ativacao_da_matricula(): void
    {
        $dados = $this->alunoComAula(['data_ativacao' => now()->addDays(5)]);

        $frequencia = $this->lancar($dados, 'ausente');

        $this->assertFalse($frequencia->fresh()->aviso_resumo_pendente);
    }

    public function test_nao_marca_falta_apos_desativacao_da_matricula(): void
    {
        $dados = $this->alunoComAula(['data_desativacao' => now()->subDays(5)]);

        $frequencia = $this->lancar($dados, 'ausente');

        $this->assertFalse($frequencia->fresh()->aviso_resumo_pendente);
    }

    public function test_marca_mesmo_sem_responsavel_cadastrado_ainda(): void
    {
        // Os responsáveis só são resolvidos na hora do envio: quem for cadastrado antes do resumo do dia também recebe.
        $dados = $this->alunoComAula();

        $frequencia = $this->lancar($dados, 'ausente');

        $this->assertTrue($frequencia->fresh()->aviso_resumo_pendente);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModoAnulacaoQuestao;
use App\Enums\StatusOmrCorrecao;
use App\Models\AvaliacaoGabarito;
use App\Models\AvaliacaoGabaritoQuestao;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\OmrCorrecao;
use App\Models\Turma;
use App\Models\User;
use App\Services\Omr\OmrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CriaAvaliacaoTestTrait;
use Tests\TestCase;

class OmrLancamentoNotaTest extends TestCase
{
    use CriaAvaliacaoTestTrait;
    use RefreshDatabase;

    public function test_calculo_ponderado_com_questao_anulada_pontua_todos(): void
    {
        $avaliacao = $this->criarAvaliacao(['nota_maxima' => 10.00]);
        $gabarito = AvaliacaoGabarito::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'anulacao_modo' => ModoAnulacaoQuestao::PontuaTodos,
        ]);

        // Questão 1 (peso 5): Correta A.
        AvaliacaoGabaritoQuestao::factory()->create([
            'avaliacao_gabarito_id' => $gabarito->id,
            'numero' => 1,
            'alternativa_correta' => 'A',
            'peso' => 5.00,
            'anulada' => false,
        ]);

        // Questão 2 (peso 5): Anulada.
        AvaliacaoGabaritoQuestao::factory()->create([
            'avaliacao_gabarito_id' => $gabarito->id,
            'numero' => 2,
            'alternativa_correta' => 'B',
            'peso' => 5.00,
            'anulada' => true,
        ]);

        $omrService = app(OmrService::class);

        // Aluno acertou Q1 (marcou A) e errou Q2 (marcou D).
        // Como Q2 é anulada em PontuaTodos, pontua as duas: (5 + 5) / (5 + 5) * 10 = 10.00
        $respostasAluno = [
            1 => ['alternativa' => 'A'],
            2 => ['alternativa' => 'D'],
        ];

        $calculo = $omrService->calcularNotaEAcertos($gabarito, 10.00, $respostasAluno);

        $this->assertEquals(2, $calculo['acertos']);
        $this->assertEquals(10.00, $calculo['nota_final']);
    }

    public function test_calculo_ponderado_com_questao_anulada_desconsidera(): void
    {
        $avaliacao = $this->criarAvaliacao(['nota_maxima' => 10.00]);
        $gabarito = AvaliacaoGabarito::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'anulacao_modo' => ModoAnulacaoQuestao::Desconsidera,
        ]);

        // Q1 (peso 4)
        AvaliacaoGabaritoQuestao::factory()->create([
            'avaliacao_gabarito_id' => $gabarito->id,
            'numero' => 1,
            'alternativa_correta' => 'A',
            'peso' => 4.00,
            'anulada' => false,
        ]);
        // Q2 (peso 6) anulada
        AvaliacaoGabaritoQuestao::factory()->create([
            'avaliacao_gabarito_id' => $gabarito->id,
            'numero' => 2,
            'alternativa_correta' => 'B',
            'peso' => 6.00,
            'anulada' => true,
        ]);

        $omrService = app(OmrService::class);

        // Aluno acertou Q1 (peso 4). Q2 anulada é desconsiderada (peso válido = 4).
        // Nota: (4 / 4) * 10.00 = 10.00
        $respostasAluno = [
            1 => ['alternativa' => 'A'],
            2 => ['alternativa' => 'C'],
        ];

        $calculo = $omrService->calcularNotaEAcertos($gabarito, 10.00, $respostasAluno);

        $this->assertEquals(1, $calculo['acertos']);
        $this->assertEquals(1, $calculo['total_validas']);
        $this->assertEquals(10.00, $calculo['nota_final']);
    }

    public function test_lancamento_de_nota_persiste_via_service_e_atualiza_status(): void
    {
        $turma = Turma::factory()->create();
        $avaliacao = $this->criarAvaliacao([
            'turma_id' => $turma->id,
            'nota_maxima' => 10.00,
        ]);
        $matricula = Matricula::factory()->create(['turma_id' => $turma->id]);
        $user = User::factory()->create();

        $correcao = OmrCorrecao::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'nota_calculada' => 8.50,
            'status' => StatusOmrCorrecao::AguardandoConferencia,
        ]);

        $omrService = app(OmrService::class);
        $resultado = $omrService->lancarNota($correcao, $user, true);

        $this->assertTrue($resultado['sucesso']);

        // Verifica que a nota foi persistida no diário oficial
        $this->assertDatabaseHas('nota', [
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'valor' => 8.50,
        ]);

        // Verifica que o status da correção mudou para Confirmada
        $this->assertSame(StatusOmrCorrecao::Confirmada, $correcao->fresh()->status);
        $this->assertSame($user->id, $correcao->fresh()->confirmada_por_user_id);
    }

    public function test_conflito_com_nota_existente_exige_confirmacao_explicita(): void
    {
        $turma = Turma::factory()->create();
        $avaliacao = $this->criarAvaliacao([
            'turma_id' => $turma->id,
            'nota_maxima' => 10.00,
        ]);
        $matricula = Matricula::factory()->create(['turma_id' => $turma->id]);
        $user = User::factory()->create();

        // Aluno já tinha nota 5.00 lançada anteriormente
        Nota::create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'valor' => 5.00,
        ]);

        $correcao = OmrCorrecao::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'nota_calculada' => 9.00,
            'status' => StatusOmrCorrecao::AguardandoConferencia,
        ]);

        $omrService = app(OmrService::class);

        // Sem forçar sobrescrita, deve acusar conflito
        $resultadoSemForcar = $omrService->lancarNota($correcao, $user, false);
        $this->assertFalse($resultadoSemForcar['sucesso']);
        $this->assertTrue($resultadoSemForcar['conflito']);

        // A nota no banco continua 5.00
        $this->assertEquals(5.00, (float) Nota::where('avaliacao_id', $avaliacao->id)->where('matricula_id', $matricula->id)->value('valor'));

        // Com confirmação forçada, atualiza para 9.00
        $resultadoComForcar = $omrService->lancarNota($correcao, $user, true);
        $this->assertTrue($resultadoComForcar['sucesso']);
        $this->assertEquals(9.00, (float) Nota::where('avaliacao_id', $avaliacao->id)->where('matricula_id', $matricula->id)->value('valor'));
    }

    public function test_lancamento_registra_auditoria_no_activity_log(): void
    {
        $turma = Turma::factory()->create();
        $avaliacao = $this->criarAvaliacao([
            'turma_id' => $turma->id,
            'nota_maxima' => 10.00,
        ]);
        $matricula = Matricula::factory()->create(['turma_id' => $turma->id]);
        $user = User::factory()->create();

        $correcao = OmrCorrecao::factory()->create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'nota_calculada' => 7.50,
            'status' => StatusOmrCorrecao::AguardandoConferencia,
        ]);

        $omrService = app(OmrService::class);
        $omrService->lancarNota($correcao, $user, true);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'avaliacao',
            'subject_id' => $avaliacao->id,
            'causer_id' => $user->id,
        ]);
    }
}

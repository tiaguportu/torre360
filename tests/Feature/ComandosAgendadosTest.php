<?php

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusContaPagar;
use App\Enums\StatusEmprestimo;
use App\Models\ContaPagar;
use App\Models\Emprestimo;
use App\Models\Livro;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComandosAgendadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_atualizar_contas_pagar_atrasadas(): void
    {
        $contaVencida = ContaPagar::create([
            'descricao' => 'Internet Fibra',
            'valor' => 250.00,
            'vencimento' => now()->subDays(2)->toDateString(),
            'status' => StatusContaPagar::Pendente,
        ]);

        $contaNoPrazo = ContaPagar::create([
            'descricao' => 'Energia Eletrica',
            'valor' => 600.00,
            'vencimento' => now()->addDays(5)->toDateString(),
            'status' => StatusContaPagar::Pendente,
        ]);

        $contaPaga = ContaPagar::create([
            'descricao' => 'Material de Limpeza',
            'valor' => 180.00,
            'vencimento' => now()->subDays(3)->toDateString(),
            'status' => StatusContaPagar::Pago,
            'data_pagamento' => now()->subDays(3)->toDateString(),
        ]);

        $this->artisan('financeiro:atualizar-contas-pagar-atrasadas')
            ->expectsOutputToContain('1 conta(s) a pagar marcada(s) como atrasada(s).')
            ->assertSuccessful();

        $this->assertEquals(StatusContaPagar::Atrasado, $contaVencida->fresh()->status);
        $this->assertEquals(StatusContaPagar::Pendente, $contaNoPrazo->fresh()->status);
        $this->assertEquals(StatusContaPagar::Pago, $contaPaga->fresh()->status);
    }

    public function test_comando_atualizar_emprestimos_atrasados(): void
    {
        $livro = Livro::factory()->create(['titulo' => 'Dom Casmurro']);
        $aluno = Pessoa::factory()->create();
        $turma = Turma::factory()->create();

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $emprestimoAtrasado = Emprestimo::create([
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'data_emprestimo' => now()->subDays(15)->toDateString(),
            'data_prevista_devolucao' => now()->subDays(2)->toDateString(),
            'status' => StatusEmprestimo::Emprestado,
        ]);

        $emprestimoNoPrazo = Emprestimo::create([
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'data_emprestimo' => now()->subDays(2)->toDateString(),
            'data_prevista_devolucao' => now()->addDays(5)->toDateString(),
            'status' => StatusEmprestimo::Emprestado,
        ]);

        $emprestimoDevolvido = Emprestimo::create([
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'data_emprestimo' => now()->subDays(20)->toDateString(),
            'data_prevista_devolucao' => now()->subDays(5)->toDateString(),
            'data_devolucao' => now()->subDays(6)->toDateString(),
            'status' => StatusEmprestimo::Devolvido,
        ]);

        $this->artisan('biblioteca:atualizar-emprestimos-atrasados')
            ->expectsOutputToContain('1 empréstimo(s) marcado(s) como atrasado(s).')
            ->assertSuccessful();

        $this->assertEquals(StatusEmprestimo::Atrasado, $emprestimoAtrasado->fresh()->status);
        $this->assertEquals(StatusEmprestimo::Emprestado, $emprestimoNoPrazo->fresh()->status);
        $this->assertEquals(StatusEmprestimo::Devolvido, $emprestimoDevolvido->fresh()->status);
    }

    public function test_comando_recalcular_risco_evasao_com_matriculas_ativas(): void
    {
        $aluno = Pessoa::factory()->create();
        $periodo = PeriodoLetivo::factory()->create();
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);

        Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $this->artisan('academico:recalcular-risco-evasao')
            ->expectsOutputToContain('Risco de Evasão recalculado para 1 matrícula(s) ativa(s).')
            ->assertSuccessful();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Toda matrícula tem turma; período letivo e série da matrícula são os da turma (não há mais colunas próprias).
 */
class MatriculaTurmaObrigatoriaTest extends TestCase
{
    use RefreshDatabase;

    private function turma(string $nome, ?PeriodoLetivo $periodo = null, ?Serie $serie = null): Turma
    {
        $periodo ??= PeriodoLetivo::factory()->create(['nome' => $nome]);

        return Turma::create([
            'nome' => $nome,
            'periodo_letivo_id' => $periodo->id,
            'serie_id' => $serie?->id,
        ]);
    }

    public function test_matricula_sem_turma_nao_pode_ser_gravada(): void
    {
        $this->expectException(QueryException::class);

        DB::table('matricula')->insert([
            'pessoa_id' => Pessoa::create(['nome' => 'Aluno'])->id,
            'turma_id' => null,
            'situacao' => 'ativa',
        ]);
    }

    public function test_as_colunas_periodo_e_serie_deixaram_de_existir_na_matricula(): void
    {
        $this->assertFalse(Schema::hasColumn('matricula', 'periodo_letivo_id'));
        $this->assertFalse(Schema::hasColumn('matricula', 'serie_id'));
        $this->assertTrue(Schema::hasColumn('matricula', 'turma_id'));
    }

    public function test_periodo_e_serie_da_matricula_sao_os_da_turma(): void
    {
        $periodo = PeriodoLetivo::factory()->create(['nome' => '2027']);
        $serie = Serie::create(['nome' => '3º Ano', 'curso_id' => $this->cursoId(), 'sistema_avaliacao' => 'Nota']);
        $turma = $this->turma('3º Ano A', $periodo, $serie);
        $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'Aluno'])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        $matricula = Matricula::with(['periodoLetivo', 'serie'])->findOrFail($matricula->id);

        $this->assertSame($periodo->id, $matricula->periodo_letivo_id);
        $this->assertSame($serie->id, $matricula->serie_id);
        $this->assertSame('2027', $matricula->periodoLetivo->nome);
        $this->assertSame('3º Ano', $matricula->serie->nome);
        $this->assertSame('3º Ano', $matricula->serie_nome);
    }

    public function test_mudar_a_turma_muda_o_periodo_e_a_serie_da_matricula(): void
    {
        $turma2026 = $this->turma('Turma 2026');
        $turma2027 = $this->turma('Turma 2027');
        $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'Aluno'])->id, 'turma_id' => $turma2026->id, 'situacao' => 'ativa']);

        $matricula->update(['turma_id' => $turma2027->id]);
        $matricula = Matricula::findOrFail($matricula->id);

        $this->assertSame($turma2027->periodo_letivo_id, $matricula->periodo_letivo_id);
    }

    public function test_scopes_do_periodo_e_da_serie(): void
    {
        $serieA = Serie::create(['nome' => '1º Ano', 'curso_id' => $this->cursoId(), 'sistema_avaliacao' => 'Nota']);
        $serieB = Serie::create(['nome' => '2º Ano', 'curso_id' => $this->cursoId(), 'sistema_avaliacao' => 'Nota']);
        $p2026 = PeriodoLetivo::factory()->create(['nome' => '2026']);
        $p2027 = PeriodoLetivo::factory()->create(['nome' => '2027']);

        $m26a = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'A'])->id, 'turma_id' => $this->turma('T1', $p2026, $serieA)->id, 'situacao' => 'ativa']);
        $m26b = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'B'])->id, 'turma_id' => $this->turma('T2', $p2026, $serieB)->id, 'situacao' => 'ativa']);
        $m27a = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'C'])->id, 'turma_id' => $this->turma('T3', $p2027, $serieA)->id, 'situacao' => 'ativa']);

        $this->assertEqualsCanonicalizing([$m26a->id, $m26b->id], Matricula::doPeriodo($p2026->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$m27a->id], Matricula::doPeriodo($p2027->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$m26a->id, $m27a->id], Matricula::daSerie($serieA->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$m26a->id], Matricula::doPeriodo($p2026->id)->daSerie($serieA->id)->pluck('id')->all());
    }

    public function test_passar_periodo_ou_serie_ao_criar_matricula_falha_alto_nos_testes(): void
    {
        $turma = $this->turma('Turma');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('eles vêm da turma');

        Matricula::create([
            'pessoa_id' => Pessoa::create(['nome' => 'Aluno'])->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $turma->periodo_letivo_id,
            'situacao' => 'ativa',
        ]);
    }

    public function test_matricula_carrega_a_turma_por_padrao_e_ler_periodo_nao_dispara_lazy_loading(): void
    {
        $turma = $this->turma('Turma');
        Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'Aluno'])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        // O projeto proíbe lazy loading em testes: se a turma não viesse junto, esta leitura lançaria exceção.
        $matricula = Matricula::query()->firstOrFail();

        $this->assertTrue($matricula->relationLoaded('turma'));
        $this->assertSame($turma->periodo_letivo_id, $matricula->periodo_letivo_id);
    }

    public function test_migration_aborta_quando_existe_matricula_sem_turma_e_nao_altera_nada(): void
    {
        $migration = require base_path('database/migrations/2026_10_08_120000_require_turma_and_drop_periodo_serie_from_matricula_table.php');
        $turma = $this->turma('Turma');
        $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => 'Aluno'])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        // Estado "antigo": colunas de volta, valores copiados da turma e turma opcional.
        $migration->down();
        $this->assertTrue(Schema::hasColumn('matricula', 'periodo_letivo_id'));
        $this->assertSame($turma->periodo_letivo_id, (int) DB::table('matricula')->where('id', $matricula->id)->value('periodo_letivo_id'), 'down() preenche o período a partir da turma.');

        DB::table('matricula')->where('id', $matricula->id)->update(['turma_id' => null]);

        try {
            $migration->up();
            $this->fail('A migration deveria abortar com matrícula sem turma.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('1 matrícula(s) sem turma', $e->getMessage());
        }

        // Nada foi alterado: as colunas continuam lá até todas as matrículas terem turma.
        $this->assertTrue(Schema::hasColumn('matricula', 'periodo_letivo_id'));
        $this->assertTrue(Schema::hasColumn('matricula', 'serie_id'));

        DB::table('matricula')->where('id', $matricula->id)->update(['turma_id' => $turma->id]);
        $migration->up();

        $this->assertFalse(Schema::hasColumn('matricula', 'periodo_letivo_id'));
        $this->assertFalse(Schema::hasColumn('matricula', 'serie_id'));
    }

    private function cursoId(): int
    {
        return Curso::firstOrCreate(
            ['nome_interno' => 'EF'],
            ['nome_externo' => 'Ensino Fundamental', 'unidade_id' => Unidade::firstOrCreate(['nome' => 'Sede'])->id]
        )->id;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coordenador;
use App\Models\Curso;
use App\Models\Pessoa;
use App\Models\TributacaoCurso;
use App\Models\Unidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordenadorETributacaoCursoTest extends TestCase
{
    use RefreshDatabase;

    private Curso $curso;

    private Pessoa $pessoa;

    protected function setUp(): void
    {
        parent::setUp();

        $unidade = Unidade::create(['nome' => 'Unidade Central']);
        $this->curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => 'Ensino Fundamental II',
            'nome_interno' => 'EF II',
        ]);

        $this->pessoa = Pessoa::factory()->create(['nome' => 'Prof. Coordenador']);
    }

    /**
     * Testa criação, persistência e relacionamentos do modelo Coordenador.
     */
    public function test_pode_criar_coordenador_e_acessar_relacionamentos(): void
    {
        $coordenador = Coordenador::create([
            'curso_id' => $this->curso->id,
            'pessoa_id' => $this->pessoa->id,
            'cargo' => 'Coordenador Pedagógico',
            'data_inicio' => '2026-02-01',
            'flag_somente_leitura' => false,
        ]);

        $this->assertDatabaseHas('coordenador', [
            'id' => $coordenador->id,
            'curso_id' => $this->curso->id,
            'pessoa_id' => $this->pessoa->id,
            'cargo' => 'Coordenador Pedagógico',
        ]);

        $this->assertTrue($coordenador->curso->is($this->curso));
        $this->assertTrue($coordenador->pessoa->is($this->pessoa));
    }

    /**
     * Testa atualização e exclusão do modelo Coordenador.
     */
    public function test_pode_atualizar_e_excluir_coordenador(): void
    {
        $coordenador = Coordenador::create([
            'curso_id' => $this->curso->id,
            'pessoa_id' => $this->pessoa->id,
            'cargo' => 'Coordenador Geral',
            'data_inicio' => '2026-01-10',
            'flag_somente_leitura' => true,
        ]);

        $coordenador->update(['cargo' => 'Diretor Pedagógico']);

        $this->assertDatabaseHas('coordenador', [
            'id' => $coordenador->id,
            'cargo' => 'Diretor Pedagógico',
        ]);

        $coordenador->delete();

        $this->assertDatabaseMissing('coordenador', [
            'id' => $coordenador->id,
        ]);
    }

    /**
     * Testa criação, persistência e relacionamentos do modelo TributacaoCurso.
     */
    public function test_pode_criar_tributacao_curso_e_acessar_relacionamentos(): void
    {
        $tributacao = TributacaoCurso::create([
            'curso_id' => $this->curso->id,
            'cnae' => '8512-1/00',
            'iss' => 2.50,
            'pis' => 0.65,
            'cofins' => 3.00,
            'item_servico' => '08.01',
        ]);

        $this->assertDatabaseHas('tributacao_curso', [
            'id' => $tributacao->id,
            'curso_id' => $this->curso->id,
            'cnae' => '8512-1/00',
            'item_servico' => '08.01',
        ]);

        $this->assertTrue($tributacao->curso->is($this->curso));
    }

    /**
     * Testa atualização e exclusão do modelo TributacaoCurso.
     */
    public function test_pode_atualizar_e_excluir_tributacao_curso(): void
    {
        $tributacao = TributacaoCurso::create([
            'curso_id' => $this->curso->id,
            'cnae' => '8512-1/00',
            'iss' => 2.00,
            'pis' => 0.50,
            'cofins' => 2.00,
            'item_servico' => '08.01',
        ]);

        $tributacao->update([
            'iss' => 5.00,
            'cofins' => 4.00,
        ]);

        $this->assertDatabaseHas('tributacao_curso', [
            'id' => $tributacao->id,
            'iss' => 5.00,
            'cofins' => 4.00,
        ]);

        $tributacao->delete();

        $this->assertDatabaseMissing('tributacao_curso', [
            'id' => $tributacao->id,
        ]);
    }
}

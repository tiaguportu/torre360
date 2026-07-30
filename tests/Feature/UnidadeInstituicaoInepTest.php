<?php

namespace Tests\Feature;

use App\Models\InstituicaoEnsino;
use App\Models\Unidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnidadeInstituicaoInepTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_criar_instituicao_ensino_com_codigo_inep(): void
    {
        $instituicao = InstituicaoEnsino::create([
            'nome' => 'Instituição Teste INEP',
            'cnpj' => '12.345.678/0001-99',
            'codigo_inep' => '12345678',
            'flag_ativo' => true,
        ]);

        $this->assertDatabaseHas('instituicao_ensinos', [
            'id' => $instituicao->id,
            'codigo_inep' => '12345678',
        ]);
    }

    public function test_pode_criar_unidade_com_campos_censo_e_inep(): void
    {
        $instituicao = InstituicaoEnsino::create([
            'nome' => 'Instituição Teste',
            'flag_ativo' => true,
        ]);

        $unidade = Unidade::create([
            'nome' => 'Unidade Escolar Centro',
            'cnpj' => '12.345.678/0002-00',
            'instituicao_ensino_id' => $instituicao->id,
            'codigo_inep' => '87654321',
            'situacao_funcionamento' => '1',
            'telefone' => '(81)99999-999',
            'email' => 'unidade@escola.gov.br',
            'codigo_orgao_regional_ensino' => 'REG-01',
            'localizacao_zona' => '1',
            'localizacao_diferenciada' => '7',
            'dependencia_administrativa' => '3',
            'orgao_vinculado_escola_publica' => 'Secretaria Municipal de Educação',
            'flag_secretaria_educacao_mec' => true,
            'flag_seguranca_publica_forcas_armadas' => false,
            'flag_secretaria_saude' => false,
            'flag_outro_orgao_publico' => false,
        ]);

        $this->assertDatabaseHas('unidade', [
            'id' => $unidade->id,
            'codigo_inep' => '87654321',
            'situacao_funcionamento' => '1',
            'telefone' => '(81)99999-999',
            'email' => 'unidade@escola.gov.br',
            'codigo_orgao_regional_ensino' => 'REG-01',
            'localizacao_zona' => '1',
            'localizacao_diferenciada' => '7',
            'dependencia_administrativa' => '3',
            'flag_secretaria_educacao_mec' => true,
        ]);
    }
}

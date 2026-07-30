<?php

namespace Tests\Feature;

use App\Models\Cidade;
use App\Models\Estado;
use App\Models\Pais;
use App\Models\Pessoa;
use App\Services\Educacenso\EducacensoPessoaExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EducacensoPessoaExportTest extends TestCase
{
    use RefreshDatabase;

    private function createCidadeMock(): Cidade
    {
        $pais = Pais::create([
            'nome' => 'Brasil',
            'sigla' => 'BR',
        ]);

        $estado = Estado::create([
            'pais_id' => $pais->id,
            'nome' => 'São Paulo',
            'sigla' => 'SP',
        ]);

        return Cidade::create([
            'nome' => 'São Paulo',
            'estado_id' => $estado->id,
            'codigo_ibge' => '3550308',
        ]);
    }

    public function test_pode_exportar_pessoa_com_todos_os_campos_educacenso_preenchidos(): void
    {
        $cidade = $this->createCidadeMock();

        $pessoa = Pessoa::create([
            'codigo' => 'ALU12345',
            'cpf' => '123.456.789-01',
            'certidao_nascimento' => '12345678901234567890123456789012',
            'nome' => 'João da Silva Sauro',
            'data_nascimento' => '2010-05-15',
            'filiacao_1' => 'Maria da Silva',
            'filiacao_2' => 'José Sauro',
            'naturalidade_id' => $cidade->id,
            'codigo_inep' => '123456789012',
        ]);

        $exporter = new EducacensoPessoaExporter;
        $line = $exporter->buildPessoaLine($pessoa);

        $fields = explode('|', $line);

        $this->assertCount(9, $fields);
        $this->assertEquals('ALU12345', $fields[0]); // 1. Código Escola
        $this->assertEquals('12345678901', $fields[1]); // 2. CPF
        $this->assertEquals('12345678901234567890123456789012', $fields[2]); // 3. Certidão
        $this->assertEquals('JOAO DA SILVA SAURO', $fields[3]); // 4. Nome
        $this->assertEquals('15/05/2010', $fields[4]); // 5. Data Nascimento
        $this->assertEquals('MARIA DA SILVA', $fields[5]); // 6. Filiação 1
        $this->assertEquals('JOSE SAURO', $fields[6]); // 7. Filiação 2
        $this->assertEquals('3550308', $fields[7]); // 8. Código IBGE
        $this->assertEquals('123456789012', $fields[8]); // 9. INEP
    }

    public function test_exporta_pessoa_com_campos_opcionais_vazios_gerando_pipes_consecutivos(): void
    {
        $cidade = $this->createCidadeMock();

        $pessoa = Pessoa::create([
            'nome' => 'Ana Clara',
            'data_nascimento' => '2015-10-20',
            'naturalidade_id' => $cidade->id,
        ]);

        $exporter = new EducacensoPessoaExporter;
        $line = $exporter->buildPessoaLine($pessoa);

        $fields = explode('|', $line);

        $this->assertCount(9, $fields);
        $this->assertEquals((string) $pessoa->id, $fields[0]); // 1. ID como código fallback
        $this->assertEquals('', $fields[1]); // 2. CPF vazio
        $this->assertEquals('', $fields[2]); // 3. Certidão vazia
        $this->assertEquals('ANA CLARA', $fields[3]); // 4. Nome
        $this->assertEquals('20/10/2015', $fields[4]); // 5. Data Nascimento
        $this->assertEquals('', $fields[5]); // 6. Filiação 1 vazia
        $this->assertEquals('', $fields[6]); // 7. Filiação 2 vazia
        $this->assertEquals('3550308', $fields[7]); // 8. Código IBGE
        $this->assertEquals('', $fields[8]); // 9. INEP vazio

        // Verifica presença de pipes duplos para campos ausentes (ex: id|||ANA CLARA|...)
        $this->assertStringContainsString((string) $pessoa->id.'|||ANA CLARA|20/10/2015|||3550308|', $line);
    }

    public function test_busca_filiacao_em_responsaveis_caso_campos_filiacao_estejam_vazios(): void
    {
        $cidade = $this->createCidadeMock();

        $aluno = Pessoa::create([
            'nome' => 'Carlos Eduardo',
            'data_nascimento' => '2012-03-08',
            'naturalidade_id' => $cidade->id,
        ]);

        $mae = Pessoa::create([
            'nome' => 'Fernanda Oliveira',
        ]);

        $pai = Pessoa::create([
            'nome' => 'Roberto Oliveira',
        ]);

        $aluno->responsaveis()->attach($mae->id);
        $aluno->responsaveis()->attach($pai->id);

        $exporter = new EducacensoPessoaExporter;
        $line = $exporter->buildPessoaLine($aluno);

        $fields = explode('|', $line);

        $this->assertEquals('FERNANDA OLIVEIRA', $fields[5]); // 6. Filiação 1 via responsável 1
        $this->assertEquals('ROBERTO OLIVEIRA', $fields[6]); // 7. Filiação 2 via responsável 2
    }

    public function test_exportacao_colecao_de_pessoas_formata_linhas_separadas_por_quebra_de_linha(): void
    {
        $cidade = $this->createCidadeMock();

        $p1 = Pessoa::create([
            'nome' => 'Pedro Álvares',
            'data_nascimento' => '2008-01-01',
            'naturalidade_id' => $cidade->id,
        ]);

        $p2 = Pessoa::create([
            'nome' => 'Lucas Santos',
            'data_nascimento' => '2009-02-02',
            'naturalidade_id' => $cidade->id,
        ]);

        $exporter = new EducacensoPessoaExporter;
        $output = $exporter->export(collect([$p1, $p2]));

        $lines = explode("\r\n", $output);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('PEDRO ALVARES', $lines[0]);
        $this->assertStringContainsString('LUCAS SANTOS', $lines[1]);
    }
}

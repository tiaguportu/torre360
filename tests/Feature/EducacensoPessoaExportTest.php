<?php

namespace Tests\Feature;

use App\Enums\CorRaca;
use App\Enums\Nacionalidade;
use App\Enums\Sexo;
use App\Models\Cidade;
use App\Models\Endereco;
use App\Models\Estado;
use App\Models\NecessidadeEducacaoEspecial;
use App\Models\Pais;
use App\Models\Pessoa;
use App\Models\RecursoAcessibilidade;
use App\Models\TipoVinculo;
use App\Services\Educacenso\EducacensoPessoaExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EducacensoPessoaExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_exportar_pessoa_no_formato_registro_30_do_educacenso(): void
    {
        $pais = new Pais([
            'nome' => 'Brasil',
            'sigla' => 'BR',
            'codigo' => '76',
        ]);

        $estado = new Estado([
            'nome' => 'São Paulo',
            'sigla' => 'SP',
        ]);

        $cidade = new Cidade([
            'nome' => 'Campinas',
            'codigo_ibge' => '3509502',
        ]);
        $cidade->setRelation('estado', $estado);

        $pessoa = new Pessoa([
            'id' => 101,
            'nome' => 'João da Silva Ção',
            'cpf' => '123.456.789-00',
            'data_nascimento' => '2010-05-15',
            'sexo' => Sexo::MASCULINO,
            'cor_raca' => CorRaca::PARDA,
            'email' => 'joao@example.com',
        ]);

        $pessoa->setRelation('nacionalidade', $pais);
        $pessoa->setRelation('naturalidade', $cidade);

        $pai = new Pessoa(['nome' => 'José da Silva']);
        $mae = new Pessoa(['nome' => 'Maria da Silva']);

        $tipoPai = new TipoVinculo(['nome' => 'Pai']);
        $tipoMae = new TipoVinculo(['nome' => 'Mãe']);

        $pai->pivot = (object) ['tipoVinculo' => $tipoPai];
        $mae->pivot = (object) ['tipoVinculo' => $tipoMae];

        $pessoa->setRelation('responsaveis', collect([$pai, $mae]));

        $nec = new NecessidadeEducacaoEspecial(['nome' => 'Cegueira']);
        $rec = new RecursoAcessibilidade(['nome' => 'Prova em Braille']);

        $pessoa->setRelation('necessidadesEducacaoEspecial', collect([$nec]));
        $pessoa->setRelation('transtornosAprendizagem', collect());
        $pessoa->setRelation('recursosAcessibilidade', collect([$rec]));

        $endereco = new Endereco([
            'logradouro' => 'Rua das Flores',
            'numero' => '123',
            'bairro' => 'Centro',
            'cep' => '13000-000',
        ]);
        $endereco->setRelation('cidade', $cidade);

        $pessoa->setRelation('enderecos', collect([$endereco]));

        $exporter = new EducacensoPessoaExporter;
        $line = $exporter->buildRegistro30Line($pessoa);

        $fields = explode('|', $line);

        $this->assertCount(110, $fields);
        $this->assertEquals('30', $fields[0]); // 1. Registro
        $this->assertEquals('101', $fields[2]); // 3. Código no sistema próprio
        $this->assertEquals('', $fields[3]); // 4. Identificação única (INEP)
        $this->assertEquals('12345678900', $fields[4]); // 5. CPF
        $this->assertEquals('JOAO DA SILVA CAO', $fields[5]); // 6. Nome Sanitizado
        $this->assertEquals('15/05/2010', $fields[6]); // 7. Data Nascimento
        $this->assertEquals('1', $fields[7]); // 8. Filiação Declarada
        $this->assertEquals('JOSE DA SILVA', $fields[8]); // 9. Pai
        $this->assertEquals('MARIA DA SILVA', $fields[9]); // 10. Mãe
        $this->assertEquals('1', $fields[10]); // 11. Sexo (1 = Masculino)
        $this->assertEquals('3', $fields[11]); // 12. Cor/Raça (3 = Parda)
        $this->assertEquals('76', $fields[12]); // 13. Código País (76)
        $this->assertEquals('1', $fields[13]); // 14. Nacionalidade (1 = Brasileira)
        $this->assertEquals('SP', $fields[14]); // 15. UF Nascimento
        $this->assertEquals('3509502', $fields[15]); // 16. IBGE Cidade Nascimento
        $this->assertEquals('1', $fields[16]); // 17. Tem Deficiência
        $this->assertEquals('1', $fields[17]); // 18. Cegueira
        $this->assertEquals('1', $fields[35]); // 36. Prova Braille
        $this->assertEquals('13000000', $fields[37]); // 38. CEP
        $this->assertEquals('RUA DAS FLORES', $fields[38]); // 39. Logradouro
        $this->assertEquals('123', $fields[39]); // 40. Número
        $this->assertEquals('CENTRO', $fields[41]); // 42. Bairro
        $this->assertEquals('3509502', $fields[42]); // 43. IBGE Cidade Endereço
        $this->assertEquals('SP', $fields[43]); // 44. UF Endereço
        $this->assertEquals('joao@example.com', $fields[44]); // 45. Email
    }

    public function test_exportacao_com_campos_opcionais_vazios_gera_pipes_duplos(): void
    {
        $pessoa = Pessoa::create([
            'nome' => 'Pedro Sem Documentos',
        ]);

        $exporter = new EducacensoPessoaExporter;
        $line = $exporter->buildRegistro30Line($pessoa);

        $fields = explode('|', $line);

        $this->assertCount(110, $fields);
        $this->assertEquals('30', $fields[0]);
        $this->assertEquals((string) $pessoa->id, $fields[2]); // Campo 3
        $this->assertEquals('', $fields[3]); // Campo 4
        $this->assertEquals('PEDRO SEM DOCUMENTOS', $fields[5]);
        $this->assertEquals('', $fields[4]); // CPF vazio
        $this->assertEquals('', $fields[6]); // Data nasc vazia
        $this->assertStringContainsString('||', $line);
    }

    public function test_enums_cor_raca_e_nacionalidade_possuem_valores_e_labels_corretos(): void
    {
        $this->assertEquals('0', CorRaca::NAO_DECLARADA->value);
        $this->assertEquals('Não Declarada', CorRaca::NAO_DECLARADA->getLabel());

        $this->assertEquals('1', CorRaca::BRANCA->value);
        $this->assertEquals('Branca', CorRaca::BRANCA->getLabel());

        $this->assertEquals('3', CorRaca::PARDA->value);
        $this->assertEquals('Parda', CorRaca::PARDA->getLabel());

        $this->assertEquals('5', CorRaca::INDIGENA->value);
        $this->assertEquals('Indígena', CorRaca::INDIGENA->getLabel());

        $this->assertEquals('1', Nacionalidade::BRASILEIRA->value);
        $this->assertEquals('Brasileira', Nacionalidade::BRASILEIRA->getLabel());

        $this->assertEquals('2', Nacionalidade::BRASILEIRA_EXTERIOR_OU_NATURALIZADO->value);
        $this->assertEquals('Brasileira - nascido no Exterior ou Naturalizado', Nacionalidade::BRASILEIRA_EXTERIOR_OU_NATURALIZADO->getLabel());

        $this->assertEquals('3', Nacionalidade::ESTRANGEIRA->value);
        $this->assertEquals('Estrangeira', Nacionalidade::ESTRANGEIRA->getLabel());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use App\Models\Cidade;
use App\Models\Contrato;
use App\Models\Endereco;
use App\Models\Estado;
use App\Models\Matricula;
use App\Models\Pais;
use App\Models\Pessoa;
use App\Models\TipoVinculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatriculaPendenciaCadastroTest extends TestCase
{
    use RefreshDatabase;

    private function criarEndereco(Cidade $cidade): Endereco
    {
        return Endereco::create([
            'logradouro' => 'Rua Central',
            'numero' => '100',
            'bairro' => 'Centro',
            'cidade_id' => $cidade->id,
            'cep' => '12345000',
            'tipo' => 'residencial',
        ]);
    }

    private function criarPessoaCompleta(Pais $pais, Cidade $cidade, string $cpf, string $nome = 'Pessoa Completa'): Pessoa
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => $nome,
            'data_nascimento' => '2005-05-15',
            'cpf' => $cpf,
            'email' => strtolower(str_replace(' ', '', $nome)).'@teste.com',
            'telefone' => '11988887777',
            'sexo' => Sexo::FEMININO,
            'cor_raca' => CorRaca::PARDA,
            'nacionalidade_id' => $pais->id,
            'naturalidade_id' => $cidade->id,
        ]);

        $endereco = $this->criarEndereco($cidade);
        $pessoa->enderecos()->attach($endereco->id);

        return $pessoa;
    }

    /** @test */
    public function pessoa_has_incomplete_cadastro_identifica_campos_e_endereco()
    {
        $pais = Pais::create(['nome' => 'Brasil', 'sigla' => 'BRA']);
        $estado = Estado::create(['pais_id' => $pais->id, 'nome' => 'São Paulo', 'sigla' => 'SP']);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'nome' => 'São Paulo']);

        $pessoa = Pessoa::factory()->create([
            'nome' => 'Maria Silva',
            'data_nascimento' => null,
            'cpf' => null,
            'email' => null,
            'telefone' => null,
            'sexo' => Sexo::FEMININO,
            'cor_raca' => null,
            'nacionalidade_id' => $pais->id,
            'naturalidade_id' => null,
        ]);

        $this->assertTrue($pessoa->hasIncompleteCadastro());

        $camposFaltantes = $pessoa->getMissingCadastroFields();
        $this->assertContains('Data de Nascimento', $camposFaltantes);
        $this->assertContains('CPF', $camposFaltantes);
        $this->assertContains('Cor/Raça', $camposFaltantes);
        $this->assertContains('Naturalidade', $camposFaltantes);
        $this->assertContains('Endereço', $camposFaltantes);

        // Telefone e E-mail não devem ser considerados pendências
        $this->assertNotContains('Telefone', $camposFaltantes);
        $this->assertNotContains('E-mail', $camposFaltantes);
    }

    /** @test */
    public function pessoa_sem_email_e_telefone_mas_com_dados_civeis_e_endereco_esta_completa()
    {
        $pais = Pais::create(['nome' => 'Brasil', 'sigla' => 'BRA']);
        $estado = Estado::create(['pais_id' => $pais->id, 'nome' => 'São Paulo', 'sigla' => 'SP']);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'nome' => 'São Paulo']);

        $pessoa = Pessoa::factory()->create([
            'nome' => 'Pessoa Sem Contato',
            'data_nascimento' => '1990-01-01',
            'cpf' => '12345678900',
            'email' => null,
            'telefone' => null,
            'sexo' => Sexo::MASCULINO,
            'cor_raca' => CorRaca::BRANCA,
            'nacionalidade_id' => $pais->id,
            'naturalidade_id' => $cidade->id,
        ]);

        $endereco = $this->criarEndereco($cidade);
        $pessoa->enderecos()->attach($endereco->id);

        $this->assertFalse($pessoa->hasIncompleteCadastro());
        $this->assertEmpty($pessoa->getMissingCadastroFields());
    }

    /** @test */
    public function matricula_com_pessoas_completas_nao_possui_pendencia_cadastral()
    {
        $pais = Pais::create(['nome' => 'Brasil', 'sigla' => 'BRA']);
        $estado = Estado::create(['pais_id' => $pais->id, 'nome' => 'São Paulo', 'sigla' => 'SP']);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'nome' => 'São Paulo']);

        $aluno = $this->criarPessoaCompleta($pais, $cidade, '11111111111', 'Aluno');
        $pai = $this->criarPessoaCompleta($pais, $cidade, '22222222222', 'Pai');
        $tipoPai = TipoVinculo::create(['nome' => 'Pai']);

        $aluno->responsaveis()->attach($pai->id, ['tipo_vinculo_id' => $tipoPai->id]);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'situacao' => 'ativa',
        ]);

        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => 1200,
        ]);

        $rf = $this->criarPessoaCompleta($pais, $cidade, '33333333333', 'Responsável Financeiro');
        $contrato->responsaveisFinanceiros()->create(['pessoa_id' => $rf->id]);

        $this->assertFalse($matricula->hasIncompleteCadastro());
        $this->assertEmpty($matricula->getPessoasComCadastroIncompleto());
        $this->assertEquals(1, Matricula::comCadastroCompleto()->count());
        $this->assertEquals(0, Matricula::comCadastroIncompleto()->count());
    }

    /** @test */
    public function matricula_detecta_pendencia_detalhada_de_cada_pessoa_envolvida()
    {
        $pais = Pais::create(['nome' => 'Brasil', 'sigla' => 'BRA']);
        $estado = Estado::create(['pais_id' => $pais->id, 'nome' => 'São Paulo', 'sigla' => 'SP']);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'nome' => 'São Paulo']);

        // Aluno completo
        $aluno = $this->criarPessoaCompleta($pais, $cidade, '11111111111', 'Aluno');

        // Pai sem endereço (e sem CPF)
        $pai = Pessoa::factory()->create([
            'nome' => 'Carlos Pai',
            'data_nascimento' => '1980-01-01',
            'cpf' => null,
            'email' => null,
            'telefone' => null,
            'sexo' => Sexo::MASCULINO,
            'cor_raca' => CorRaca::BRANCA,
            'nacionalidade_id' => $pais->id,
            'naturalidade_id' => $cidade->id,
        ]);
        $tipoPai = TipoVinculo::create(['nome' => 'Pai']);
        $aluno->responsaveis()->attach($pai->id, ['tipo_vinculo_id' => $tipoPai->id]);

        // Matrícula
        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'situacao' => 'ativa',
        ]);

        // Contrato com RF sem CPF
        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => 1200,
        ]);
        $rf = $this->criarPessoaCompleta($pais, $cidade, '33333333333', 'Tio Financeiro');
        $rf->update(['cpf' => null]);
        $contrato->responsaveisFinanceiros()->create(['pessoa_id' => $rf->id]);

        $this->assertTrue($matricula->hasIncompleteCadastro());

        $incompletas = $matricula->getPessoasComCadastroIncompleto();
        $this->assertCount(2, $incompletas);

        $tipos = $incompletas->pluck('tipo')->toArray();
        $this->assertContains('Pai', $tipos);
        $this->assertContains('Responsável Financeiro', $tipos);

        $this->assertEquals(1, Matricula::comCadastroIncompleto()->count());
        $this->assertEquals(0, Matricula::comCadastroCompleto()->count());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\CorRaca;
use App\Enums\Sexo;
use App\Filament\Widgets\MatriculasPendentesWidget;
use App\Models\Cidade;
use App\Models\Contrato;
use App\Models\Endereco;
use App\Models\Estado;
use App\Models\Matricula;
use App\Models\Pais;
use App\Models\Pessoa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatriculasPendentesWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function criarEndereco(Cidade $cidade): Endereco
    {
        return Endereco::create([
            'logradouro' => 'Rua das Flores',
            'numero' => '123',
            'bairro' => 'Centro',
            'cidade_id' => $cidade->id,
            'cep' => '01001000',
            'tipo' => 'residencial',
        ]);
    }

    private function criarPessoaCompleta(Pais $pais, Cidade $cidade, string $cpf, string $nome = 'Pessoa Completa'): Pessoa
    {
        $pessoa = Pessoa::factory()->create([
            'nome' => $nome,
            'data_nascimento' => '2010-01-01',
            'cpf' => $cpf,
            'email' => strtolower(str_replace(' ', '', $nome)).'@teste.com',
            'telefone' => '11999999999',
            'sexo' => Sexo::MASCULINO,
            'cor_raca' => CorRaca::BRANCA,
            'nacionalidade_id' => $pais->id,
            'naturalidade_id' => $cidade->id,
        ]);

        $endereco = $this->criarEndereco($cidade);
        $pessoa->enderecos()->attach($endereco->id);

        return $pessoa;
    }

    /** @test */
    public function deve_contar_corretamente_pendencias_das_matriculas()
    {
        $pais = Pais::create(['nome' => 'Brasil', 'sigla' => 'BRA']);
        $estado = Estado::create(['pais_id' => $pais->id, 'nome' => 'São Paulo', 'sigla' => 'SP']);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'nome' => 'São Paulo']);

        // 1. Aluno completo e com endereço, mas sem responsável (pendência apenas de responsável)
        $aluno1 = $this->criarPessoaCompleta($pais, $cidade, '12345678901', 'Aluno 1');
        Matricula::factory()->create([
            'pessoa_id' => $aluno1->id,
            'situacao' => 'ativa',
        ]);

        // 2. Aluno completo com responsável completo (ambos com endereço) -> Sem nenhuma pendência de cadastro
        $aluno2 = $this->criarPessoaCompleta($pais, $cidade, '98765432109', 'Aluno 2');
        $responsavel2 = $this->criarPessoaCompleta($pais, $cidade, '11122233344', 'Responsavel 2');
        $aluno2->responsaveis()->attach($responsavel2->id);
        Matricula::factory()->create([
            'pessoa_id' => $aluno2->id,
            'situacao' => 'ativa',
        ]);

        // 3. Aluno sem CPF (pendência de cadastro no aluno)
        $alunoIncompleto = $this->criarPessoaCompleta($pais, $cidade, '22233344455', 'Aluno Incompleto');
        $alunoIncompleto->update(['cpf' => null]);
        $responsavel3 = $this->criarPessoaCompleta($pais, $cidade, '33344455566', 'Responsavel 3');
        $alunoIncompleto->responsaveis()->attach($responsavel3->id);
        Matricula::factory()->create([
            'pessoa_id' => $alunoIncompleto->id,
            'situacao' => 'ativa',
        ]);

        $widget = new MatriculasPendentesWidget;

        $reflection = new \ReflectionClass(MatriculasPendentesWidget::class);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // A primeira estatística deve ser "Pendência de Responsáveis" com valor 1
        $this->assertEquals(1, $stats[0]->getValue());

        // A terceira estatística deve ser "Pendência de Cadastro" com valor 1
        $this->assertEquals(1, $stats[2]->getValue());
    }

    /** @test */
    public function deve_detectar_pendencia_de_cadastro_quando_responsavel_estiver_incompleto()
    {
        $pais = Pais::create(['nome' => 'Brasil', 'sigla' => 'BRA']);
        $estado = Estado::create(['pais_id' => $pais->id, 'nome' => 'São Paulo', 'sigla' => 'SP']);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'nome' => 'São Paulo']);

        $aluno = $this->criarPessoaCompleta($pais, $cidade, '12345678901', 'Aluno');
        // Responsável sem endereço
        $responsavelSemEndereco = Pessoa::factory()->create([
            'nome' => 'Pai Sem Endereco',
            'data_nascimento' => '1980-01-01',
            'cpf' => '99988877766',
            'email' => 'pai@teste.com',
            'telefone' => '11988888888',
            'sexo' => Sexo::MASCULINO,
            'cor_raca' => CorRaca::BRANCA,
            'nacionalidade_id' => $pais->id,
            'naturalidade_id' => $cidade->id,
        ]);
        $aluno->responsaveis()->attach($responsavelSemEndereco->id);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'situacao' => 'ativa',
        ]);

        $widget = new MatriculasPendentesWidget;
        $reflection = new \ReflectionClass(MatriculasPendentesWidget::class);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // Deve contar 1 na pendência de cadastro por causa do responsável sem endereço
        $this->assertEquals(1, $stats[2]->getValue());
        $this->assertTrue($matricula->hasIncompleteCadastro());
    }

    /** @test */
    public function deve_detectar_pendencia_de_cadastro_quando_responsavel_financeiro_estiver_incompleto()
    {
        $pais = Pais::create(['nome' => 'Brasil', 'sigla' => 'BRA']);
        $estado = Estado::create(['pais_id' => $pais->id, 'nome' => 'São Paulo', 'sigla' => 'SP']);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'nome' => 'São Paulo']);

        $aluno = $this->criarPessoaCompleta($pais, $cidade, '12345678901', 'Aluno');
        $responsavel = $this->criarPessoaCompleta($pais, $cidade, '99988877766', 'Responsavel');
        $aluno->responsaveis()->attach($responsavel->id);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'situacao' => 'ativa',
        ]);

        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => 1000,
        ]);

        // Responsável financeiro com CPF nulo
        $rfPessoa = $this->criarPessoaCompleta($pais, $cidade, '55566677788', 'Resp Financeiro');
        $rfPessoa->update(['cpf' => null]);

        $contrato->responsaveisFinanceiros()->create([
            'pessoa_id' => $rfPessoa->id,
        ]);

        $widget = new MatriculasPendentesWidget;
        $reflection = new \ReflectionClass(MatriculasPendentesWidget::class);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // Deve contar 1 na pendência de cadastro por causa do responsável financeiro incompleto
        $this->assertEquals(1, $stats[2]->getValue());
        $this->assertTrue($matricula->hasIncompleteCadastro());
    }

    /** @test */
    public function deve_detectar_pendencia_quando_aluno_nao_tem_endereco()
    {
        $pais = Pais::create(['nome' => 'Brasil', 'sigla' => 'BRA']);
        $estado = Estado::create(['pais_id' => $pais->id, 'nome' => 'São Paulo', 'sigla' => 'SP']);
        $cidade = Cidade::create(['estado_id' => $estado->id, 'nome' => 'São Paulo']);

        // Aluno sem endereço
        $alunoSemEndereco = Pessoa::factory()->create([
            'nome' => 'Aluno Sem Endereço',
            'data_nascimento' => '2012-05-10',
            'cpf' => '11144477788',
            'email' => 'aluno.sem.end@teste.com',
            'telefone' => '11977777777',
            'sexo' => Sexo::MASCULINO,
            'cor_raca' => CorRaca::BRANCA,
            'nacionalidade_id' => $pais->id,
            'naturalidade_id' => $cidade->id,
        ]);

        $responsavel = $this->criarPessoaCompleta($pais, $cidade, '99988877766', 'Responsavel');
        $alunoSemEndereco->responsaveis()->attach($responsavel->id);

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $alunoSemEndereco->id,
            'situacao' => 'ativa',
        ]);

        $this->assertTrue($alunoSemEndereco->hasIncompleteCadastro());
        $this->assertTrue($matricula->hasIncompleteCadastro());
        $this->assertContains('Endereço', $alunoSemEndereco->getMissingCadastroFields());
    }

    /** @test */
    public function deve_gerar_url_com_filtro_correto_no_widget()
    {
        $widget = new MatriculasPendentesWidget;

        $reflection = new \ReflectionClass(MatriculasPendentesWidget::class);
        $method = $reflection->getMethod('getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        // Verifica que a URL do primeiro card contém o filtro 'sem_responsavel' e o de situação ativa
        $url0 = urldecode($stats[0]->getUrl());
        $this->assertStringContainsString('sem_responsavel', $url0);
        $this->assertStringContainsString('filters[situacao][value]=ativa', $url0);

        // Verifica que a URL do segundo card contém o filtro 'documentos_pendentes' e o de situação ativa
        $url1 = urldecode($stats[1]->getUrl());
        $this->assertStringContainsString('documentos_pendentes', $url1);
        $this->assertStringContainsString('filters[situacao][value]=ativa', $url1);

        // Verifica que a URL do terceiro card contém o filtro 'dados_pendentes' e o de situação ativa
        $url2 = urldecode($stats[2]->getUrl());
        $this->assertStringContainsString('dados_pendentes', $url2);
        $this->assertStringContainsString('filters[situacao][value]=ativa', $url2);
    }
}

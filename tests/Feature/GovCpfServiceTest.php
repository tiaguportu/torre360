<?php

namespace Tests\Feature;

use App\Enums\Sexo;
use App\Models\Cidade;
use App\Models\Estado;
use App\Models\Pais;
use App\Services\GovCpfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GovCpfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.gov_cpf', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'user_cpf' => '00000000191',
            'environment' => 'homologacao',
        ]);
    }

    public function test_obtem_access_token_com_sucesso(): void
    {
        Http::fake([
            'https://h-apigateway.conectagov.np.estaleiro.serpro.gov.br/oauth2/jwt-token' => Http::response([
                'access_token' => 'mock-jwt-token-123',
                'expires_in' => 3600,
            ], 200),
        ]);

        $service = new GovCpfService;
        $token = $service->getAccessToken();

        $this->assertEquals('mock-jwt-token-123', $token);
    }

    public function test_consulta_cpf_e_mapeia_dados_da_pessoa(): void
    {
        $brasil = Pais::firstOrCreate(['nome' => 'Brasil'], ['sigla' => 'BR']);
        $ceara = Estado::firstOrCreate(['sigla' => 'CE'], ['pais_id' => $brasil->id, 'nome' => 'Ceará']);
        $fortaleza = Cidade::firstOrCreate(['nome' => 'Fortaleza'], ['estado_id' => $ceara->id, 'codigo_ibge' => '2304400']);

        Http::fake([
            'https://h-apigateway.conectagov.np.estaleiro.serpro.gov.br/oauth2/jwt-token' => Http::response([
                'access_token' => 'mock-jwt-token-123',
                'expires_in' => 3600,
            ], 200),
            'https://h-apigateway.conectagov.np.estaleiro.serpro.gov.br/api-cpf-light/v2/consulta/cpf' => Http::response([
                'listaCpfCompleto' => [
                    [
                        'CPF' => '77689062768',
                        'Nome' => 'MARIA SILVA SANTOS',
                        'DataNascimento' => '19950520',
                        'Sexo' => 2,
                        'NomePaisNacionalidade' => 'BRASIL',
                        'NomeMunicipioNaturalidade' => 'FORTALEZA',
                        'UFMunicipioNaturalidade' => 'CE',
                    ],
                ],
            ], 200),
        ]);

        $service = new GovCpfService;
        $resultado = $service->consultarEPopularPessoa('776.890.627-68');

        $this->assertTrue($resultado['sucesso']);
        $this->assertEquals('77689062768', $resultado['cpf']);
        $this->assertEquals('MARIA SILVA SANTOS', $resultado['nome']);
        $this->assertEquals('1995-05-20', $resultado['data_nascimento']);
        $this->assertEquals(Sexo::FEMININO->value, $resultado['sexo']);
        $this->assertEquals($brasil->id, $resultado['nacionalidade_id']);
        $this->assertEquals($fortaleza->id, $resultado['naturalidade_id']);
    }

    public function test_lanca_excecao_para_cpf_invalido(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('O CPF informado deve conter exatamente 11 dígitos.');

        $service = new GovCpfService;
        $service->consultarCpfRaw('123');
    }
}

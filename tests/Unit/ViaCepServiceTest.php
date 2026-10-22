<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Cidade;
use App\Models\Estado;
use App\Models\Pais;
use App\Services\ViaCepService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ViaCepServiceTest extends TestCase
{
    use RefreshDatabase;

    private ViaCepService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new ViaCepService;
    }

    public function test_retorna_null_quando_cep_invalido_ou_tamanho_incorreto(): void
    {
        $this->assertNull($this->service->buscar(''));
        $this->assertNull($this->service->buscar('12345'));
        $this->assertNull($this->service->buscar('12345-6789'));
        $this->assertNull($this->service->buscar('ABCDE-FGH'));
    }

    public function test_busca_com_sucesso_e_armazena_em_cache(): void
    {
        Http::fake([
            'https://viacep.com.br/ws/01001000/json/' => Http::response([
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'complemento' => 'lado ímpar',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
                'ibge' => '3550308',
            ], 200),
        ]);

        $resultado1 = $this->service->buscar('01001-000');

        $this->assertNotNull($resultado1);
        $this->assertSame('Praça da Sé', $resultado1['logradouro']);
        $this->assertSame('Sé', $resultado1['bairro']);
        $this->assertSame('São Paulo', $resultado1['localidade']);
        $this->assertSame('SP', $resultado1['uf']);
        $this->assertSame('3550308', $resultado1['ibge']);

        // Segunda chamada para o mesmo CEP deve utilizar o cache
        $resultado2 = $this->service->buscar('01001000');
        $this->assertSame($resultado1, $resultado2);

        // Apenas 1 requisição HTTP deve ter sido disparada
        Http::assertSentCount(1);
    }

    public function test_retorna_null_quando_viacep_retorna_erro_de_cep_nao_encontrado(): void
    {
        Http::fake([
            'https://viacep.com.br/ws/99999999/json/' => Http::response([
                'erro' => true,
            ], 200),
        ]);

        $resultado = $this->service->buscar('99999-999');

        $this->assertNull($resultado);
    }

    public function test_trata_timeout_ou_falha_de_rede_graciosamente_sem_lancar_excecao(): void
    {
        Http::fake([
            'https://viacep.com.br/ws/01311000/json/' => fn () => throw new ConnectionException('Connection timed out after 3000 milliseconds'),
        ]);

        $resultado = $this->service->buscar('01311-000');

        $this->assertNull($resultado);
    }

    public function test_buscar_com_cidade_localiza_registro_local_por_codigo_ibge(): void
    {
        $pais = Pais::firstOrCreate(['sigla' => 'BR'], ['nome' => 'Brasil']);
        $estado = Estado::firstOrCreate(['sigla' => 'SP'], ['nome' => 'São Paulo', 'pais_id' => $pais->id]);
        $cidade = Cidade::create([
            'nome' => 'Cidade Teste IBGE',
            'estado_id' => $estado->id,
            'codigo_ibge' => '9998887',
        ]);

        Http::fake([
            'https://viacep.com.br/ws/12345000/json/' => Http::response([
                'cep' => '12345-000',
                'logradouro' => 'Rua das Flores',
                'complemento' => '',
                'bairro' => 'Centro',
                'localidade' => 'Cidade Teste IBGE',
                'uf' => 'SP',
                'ibge' => '9998887',
            ], 200),
        ]);

        $resultado = $this->service->buscarComCidade('12345-000');

        $this->assertNotNull($resultado);
        $this->assertSame($cidade->id, $resultado['cidade_id']);
        $this->assertSame('Cidade Teste IBGE', $resultado['cidade']?->nome);
    }
}

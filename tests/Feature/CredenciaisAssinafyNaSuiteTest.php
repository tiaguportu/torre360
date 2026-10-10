<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contrato;
use App\Services\AssinafyService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O `.env` local pode trazer credenciais reais (inclusive de produção) do Assinafy. O `phpunit.xml` as
 * esvazia para toda a suíte; estes testes quebram se alguém remover essas linhas, antes que algum teste
 * que passe por `enviarContrato()` chame a API de verdade.
 */
class CredenciaisAssinafyNaSuiteTest extends TestCase
{
    public function test_a_suite_nao_herda_as_credenciais_do_assinafy_do_env(): void
    {
        $this->assertEmpty(config('services.assinafy.key'), 'ASSINAFY_API_KEY precisa estar vazia no phpunit.xml.');
        $this->assertEmpty(config('services.assinafy.account_id'), 'ASSINAFY_ACCOUNT_ID precisa estar vazio no phpunit.xml.');
    }

    public function test_enviar_contrato_para_antes_de_qualquer_chamada_de_rede_sem_credenciais(): void
    {
        // Rede bloqueada: se as credenciais vazassem para cá, o primeiro request lançaria exceção.
        Http::preventStrayRequests();

        $resultado = (new AssinafyService)->enviarContrato(new Contrato);

        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('Configuração do Assinafy pendente', $resultado['message']);
        Http::assertNothingSent();
    }
}

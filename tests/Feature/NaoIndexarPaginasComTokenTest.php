<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Páginas abertas por link com token (dívida, proposta, visita, ouvidoria, admissão) pedem ao buscador que não as indexe, em qualquer
 * resposta (200, 404 ou redirecionamento): `X-Robots-Tag`. As páginas de captação e de matrícula continuam indexáveis.
 */
class NaoIndexarPaginasComTokenTest extends TestCase
{
    use RefreshDatabase;

    private const ESPERADO = 'noindex, nofollow, noarchive';

    /**
     * @return array<string, array{0: string}>
     */
    public static function caminhosSensiveis(): array
    {
        return [
            'ouvidoria' => ['/ouvidoria'],
            'acompanhar a ouvidoria' => ['/ouvidoria/acompanhar'],
            'agendamento de visita' => ['/visita'],
            'visita por token' => ['/visita/token-qualquer'],
            'proposta por token' => ['/proposta/token-qualquer'],
            'acordo de inadimplência por token' => ['/acordo/token-qualquer'],
            'pesquisa de visita por token' => ['/pesquisa-visita/token-qualquer'],
            'portal de admissão por token' => ['/admissao/token-qualquer'],
            'convite de matrícula por token' => ['/quero-matricular/convite/token-qualquer'],
            'descadastro' => ['/comunicacao/descadastrar/1'],
            'validação de documento' => ['/validar-documento'],
        ];
    }

    /**
     * @dataProvider caminhosSensiveis
     */
    public function test_pagina_sensivel_pede_para_nao_ser_indexada(string $caminho): void
    {
        $this->get($caminho)->assertHeader('X-Robots-Tag', self::ESPERADO);
    }

    public function test_o_cabecalho_vale_tambem_na_resposta_de_erro(): void
    {
        $this->get('/proposta/token-que-nao-existe')->assertNotFound()->assertHeader('X-Robots-Tag', self::ESPERADO);
    }

    public function test_paginas_de_captacao_e_a_home_continuam_indexaveis(): void
    {
        foreach (['/', '/quero-matricular', '/matricular-online'] as $caminho) {
            $this->assertFalse($this->get($caminho)->headers->has('X-Robots-Tag'), "{$caminho} não deveria pedir noindex.");
        }
    }

    public function test_a_lista_vem_da_configuracao(): void
    {
        config(['seguranca.noindex' => []]);

        $this->assertFalse($this->get('/ouvidoria')->headers->has('X-Robots-Tag'));
    }
}

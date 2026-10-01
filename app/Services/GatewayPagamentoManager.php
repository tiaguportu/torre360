<?php

namespace App\Services;

use App\Contracts\GatewayPagamento;
use App\Services\Gateways\FakeGatewayPagamento;
use InvalidArgumentException;

/**
 * Registro dos drivers de gateway de pagamento disponíveis. Mesmo padrão de
 * `CanalMensagemManager`: um driver real entra aqui sem alterar quem o consome.
 */
class GatewayPagamentoManager
{
    /**
     * @var array<string, class-string<GatewayPagamento>>
     */
    private const DRIVERS = [
        'fake' => FakeGatewayPagamento::class,
    ];

    /**
     * Resolve o driver configurado em `config('pagamentos.driver')`, ou o informado
     * explicitamente (usado no webhook, que recebe a chave gravada em `Fatura.gateway`).
     */
    public static function resolver(?string $chave = null): GatewayPagamento
    {
        $chave ??= (string) config('pagamentos.driver', 'fake');

        $classe = self::DRIVERS[$chave] ?? null;

        if ($classe === null) {
            throw new InvalidArgumentException("Gateway de pagamento desconhecido: {$chave}");
        }

        return app($classe);
    }

    /**
     * @return array<string, string> chave => rótulo, para uso em Select de formulários.
     */
    public static function opcoes(): array
    {
        $opcoes = [];

        foreach (array_keys(self::DRIVERS) as $chave) {
            $opcoes[$chave] = self::resolver($chave)->rotulo();
        }

        return $opcoes;
    }
}

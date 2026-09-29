<?php

namespace App\Services;

use App\Contracts\CanalMensagem;
use App\Services\Canais\EmailCanal;
use App\Services\Canais\FcmCanal;
use InvalidArgumentException;

/**
 * Registro dos canais de mensagem disponíveis. Novos canais (WhatsApp, SMS)
 * entram aqui sem alterar quem os consome (comunicação em massa, régua de
 * cobrança da Onda 6, convite de matrícula da Onda 7).
 */
class CanalMensagemManager
{
    /**
     * @var array<string, class-string<CanalMensagem>>
     */
    private const CANAIS = [
        'email' => EmailCanal::class,
        'fcm' => FcmCanal::class,
    ];

    public static function resolver(string $chave): CanalMensagem
    {
        $classe = self::CANAIS[$chave] ?? null;

        if ($classe === null) {
            throw new InvalidArgumentException("Canal de mensagem desconhecido: {$chave}");
        }

        return app($classe);
    }

    /**
     * @return array<string, string> chave => rótulo, para uso em Select de formulários.
     */
    public static function opcoes(): array
    {
        $opcoes = [];

        foreach (array_keys(self::CANAIS) as $chave) {
            $opcoes[$chave] = self::resolver($chave)->rotulo();
        }

        return $opcoes;
    }
}

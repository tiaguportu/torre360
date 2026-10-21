<?php

declare(strict_types=1);

namespace App\Services\Ponto;

/**
 * Padrão: não assina. O comprovante sai com a faixa "NÃO ASSINADO" e o modo REP-P fica bloqueado.
 */
class AssinadorNaoAssinado implements AssinadorPonto
{
    public function nome(): string
    {
        return 'Sem assinatura eletrônica';
    }

    public function assina(): bool
    {
        return false;
    }

    public function assinar(string $conteudo): ?string
    {
        return null;
    }
}

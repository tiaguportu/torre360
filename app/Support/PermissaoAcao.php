<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

/**
 * Autorização de ações personalizadas do Filament.
 *
 * Ações customizadas (`Action::make(...)`) NÃO têm autorização automática por policy — o padrão é "liberado para
 * todos" (ver Filament\Actions\Concerns\CanBeAuthorized). Só as ações nativas (Create/Edit/Delete) herdam a policy
 * do recurso. Toda ação com efeito colateral (gravar, gerar link, disparar IA paga) precisa declarar quem pode:
 *
 *     Action::make('aprovar')->authorize(PermissaoAcao::qualquer('Update:Interessado'))
 *
 * Retorna um Closure (e não uma string de habilidade) para aceitar permissões Shield avulsas, que não existem como
 * método de policy, e porque a policy do Shield é regenerável.
 */
final class PermissaoAcao
{
    /**
     * Autorizado se o usuário logado tiver ao menos uma das permissões informadas (formato `Acao:Modelo`).
     */
    public static function qualquer(string ...$permissoes): Closure
    {
        return static function () use ($permissoes): bool {
            $usuario = auth()->user();

            if (! $usuario) {
                return false;
            }

            foreach ($permissoes as $permissao) {
                if ($usuario->can($permissao)) {
                    return true;
                }
            }

            return false;
        };
    }
}

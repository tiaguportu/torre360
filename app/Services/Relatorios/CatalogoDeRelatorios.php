<?php

declare(strict_types=1);

namespace App\Services\Relatorios;

use App\Contracts\FonteDeRelatorio;
use App\Models\User;

/**
 * As fontes de dados que os relatórios configuráveis oferecem (Onda 72), na ordem de `config/relatorios.php`, e quem pode usar
 * cada uma. A permissão é a da tela correspondente: o relatório não dá acesso a dado que a pessoa não veria na tela.
 */
class CatalogoDeRelatorios
{
    /** @var array<string, FonteDeRelatorio>|null */
    private ?array $fontes = null;

    /**
     * @return array<string, FonteDeRelatorio> chave => fonte
     */
    public function todas(): array
    {
        if ($this->fontes === null) {
            $this->fontes = [];

            foreach ((array) config('relatorios.fontes', []) as $classe) {
                $fonte = app($classe);

                if ($fonte instanceof FonteDeRelatorio) {
                    $this->fontes[$fonte->chave()] = $fonte;
                }
            }
        }

        return $this->fontes;
    }

    public function da(?string $chave): ?FonteDeRelatorio
    {
        return $chave === null ? null : ($this->todas()[$chave] ?? null);
    }

    /**
     * @return array<string, FonteDeRelatorio>
     */
    public function permitidasPara(User $usuario): array
    {
        return array_filter($this->todas(), fn (FonteDeRelatorio $fonte): bool => $this->usuarioPode($usuario, $fonte));
    }

    /**
     * Na tela vale o papel ativo da sessão; no agendador (sem sessão), o conjunto dos papéis da pessoa.
     */
    public function usuarioPode(User $usuario, FonteDeRelatorio $fonte): bool
    {
        if (! $usuario->is_active) {
            return false;
        }

        try {
            return (bool) $usuario->can($fonte->permissao());
        } catch (\Throwable) {
            return false;
        }
    }
}

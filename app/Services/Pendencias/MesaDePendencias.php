<?php

declare(strict_types=1);

namespace App\Services\Pendencias;

use App\Contracts\FontePendencia;
use App\Contracts\GuardaPendenciasEmCache;
use App\Models\User;
use App\Support\Pendencias\FilaDePendencias;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Mesa de pendências por papel (Onda 71): junta as filas dos módulos (cada uma uma {@see FontePendencia}) e entrega a
 * cada usuário só as que o papel ativo dele pode ver. Não guarda nada no banco: cada fila conta direto da fonte, então a
 * Mesa nunca contradiz a tela onde a pendência se resolve.
 */
class MesaDePendencias
{
    /** Quantos itens a Mesa lista por fila (o resto vira "e mais N"). */
    public const LIMITE_ITENS = 5;

    /**
     * Todas as filas cadastradas em `config/mesa_pendencias.php`.
     *
     * @return list<FontePendencia>
     */
    public function fontes(): array
    {
        return array_values(array_map(
            fn (string $classe): FontePendencia => app($classe),
            (array) config('mesa_pendencias.fontes', []),
        ));
    }

    /**
     * As filas que o usuário pode ver, pelo papel ativo dele.
     *
     * @return list<FontePendencia>
     */
    public function fontesDe(User $usuario): array
    {
        return array_values(array_filter(
            $this->fontes(),
            fn (FontePendencia $fonte): bool => $usuario->can($fonte->permissao()),
        ));
    }

    public function temAlgumaFila(User $usuario): bool
    {
        return $this->fontesDe($usuario) !== [];
    }

    /**
     * As filas do usuário, com contagem e os primeiros itens. Uma fila que falha ao carregar vira uma fila com `erro`: a
     * Mesa segue com as outras em vez de cair inteira.
     *
     * @return Collection<int, FilaDePendencias>
     */
    public function filas(User $usuario, int $limite = self::LIMITE_ITENS): Collection
    {
        return collect($this->fontesDe($usuario))->map(function (FontePendencia $fonte) use ($usuario, $limite): FilaDePendencias {
            try {
                $total = $fonte->contar($usuario);

                return new FilaDePendencias(
                    fonte: $fonte,
                    total: $total,
                    atrasados: $total > 0 ? min($total, $fonte->atrasados($usuario)) : 0,
                    itens: $total > 0 ? $fonte->itens($usuario, max(1, $limite)) : [],
                );
            } catch (\Throwable $e) {
                Log::warning('Mesa de pendências: a fila não carregou.', ['fila' => $fonte->chave(), 'erro' => $e->getMessage()]);

                return new FilaDePendencias(fonte: $fonte, erro: true);
            }
        })->values();
    }

    /**
     * Totais para o selo do menu, guardados por alguns minutos por usuário e papel: o selo aparece em todas as páginas e
     * não precisa ser instantâneo.
     *
     * @return array{total: int, atrasados: int}
     */
    public function resumo(User $usuario): array
    {
        return Cache::remember($this->chaveDoResumo($usuario), max(1, (int) config('mesa_pendencias.selo_cache_segundos', 300)), function () use ($usuario): array {
            $filas = $this->filas($usuario, 1);

            return [
                'total' => (int) $filas->sum('total'),
                'atrasados' => (int) $filas->sum('atrasados'),
            ];
        });
    }

    /**
     * Esquece a contagem guardada do usuário (o botão "Atualizar" da Mesa).
     */
    public function esquecer(User $usuario): void
    {
        Cache::forget($this->chaveDoResumo($usuario));

        foreach ($this->fontes() as $fonte) {
            if ($fonte instanceof GuardaPendenciasEmCache) {
                $fonte->esquecer();
            }
        }
    }

    private function chaveDoResumo(User $usuario): string
    {
        return 'mesa-pendencias:'.$usuario->getKey().':'.(session('active_role') ?: '-');
    }
}

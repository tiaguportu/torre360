<?php

namespace App\Services;

use App\Models\Interessado;
use App\Models\StatusInteressado;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Contadores do CRM que aparecem em toda carga de tela: as seis abas da listagem de Interessados e o
 * selo "Novo" do menu. Antes eram 11 `count()` (vários com `whereHas` aninhado) a cada render e uma
 * consulta extra em toda página do painel só para o selo.
 *
 * Os valores ficam em cache por `crm.contadores.cache_segundos` e são descartados na hora por
 * `invalidar()` — chamado quando um lead, um contato do histórico ou uma etapa do funil é gravado ou
 * excluído. O recálculo de score em lote usa `DB::table` e não passa por aqui: o contador "Quentes"
 * pode levar até o tempo do cache para refletir uma mudança só de score.
 */
class ContadoresCrm
{
    private const CHAVE_VERSAO = 'crm:contadores:versao';

    /**
     * Descarta todos os contadores guardados (a próxima leitura recalcula).
     */
    public static function invalidar(): void
    {
        Cache::forever(self::CHAVE_VERSAO, (string) microtime(true));
    }

    /**
     * Contagem de cada aba da listagem, indexada pela chave da aba.
     *
     * @param  Closure(): Builder  $base  consulta-base da listagem (escopo do usuário já aplicado)
     * @return array{todos: int, precisa_contato: int, estagnados: int, quentes: int, ativos: int, finalizados: int}
     */
    public static function abas(Closure $base, int|string|null $usuarioId): array
    {
        return Cache::remember(
            self::chave("abas:{$usuarioId}"),
            self::segundos(),
            function () use ($base): array {
                $corteQuente = (int) config('lead_score.faixas_cor.quente', 70);

                return [
                    'todos' => $base()->count(),
                    'precisa_contato' => $base()->ativos()->precisaContato()->count(),
                    'estagnados' => $base()->ativos()->estagnados()->count(),
                    'quentes' => $base()->ativos()
                        ->where(fn (Builder $q) => $q->where('temperatura', 'quente')->orWhere('lead_score', '>=', $corteQuente))
                        ->count(),
                    'ativos' => $base()->ativos()->count(),
                    'finalizados' => $base()->whereHas('status', fn (Builder $q) => $q->where('is_final', true))->count(),
                ];
            }
        );
    }

    /**
     * Leads na etapa inicial ("Novo"), exibidos como selo no menu.
     */
    public static function novos(): int
    {
        return Cache::remember(
            self::chave('novos'),
            self::segundos(),
            function (): int {
                $status = StatusInteressado::inicial();

                return $status ? Interessado::query()->where('status_interessado_id', $status->id)->count() : 0;
            }
        );
    }

    private static function chave(string $sufixo): string
    {
        return 'crm:contadores:'.Cache::get(self::CHAVE_VERSAO, '0').':'.$sufixo;
    }

    private static function segundos(): int
    {
        return max(1, (int) config('crm.contadores.cache_segundos', 60));
    }
}

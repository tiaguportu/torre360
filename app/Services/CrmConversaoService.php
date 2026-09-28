<?php

namespace App\Services;

use App\Models\CampanhaMarketing;
use App\Models\OrigemInteressado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Indicadores de conversão do funil de captação (lead → matrícula) por
 * campanha de marketing e por origem. "Matriculado" = lead com `data_conversao`.
 */
class CrmConversaoService
{
    /**
     * @return array<int, array{id: int, nome: string, canal: ?string, leads: int, matriculados: int, taxa: float, custo: float, custo_por_lead: ?float, custo_por_matricula: ?float}>
     */
    public static function porCampanha(): array
    {
        return CampanhaMarketing::query()
            ->withCount([
                'interessados as leads',
                'interessados as matriculados' => fn (Builder $query) => $query->whereNotNull('data_conversao'),
            ])
            ->orderByDesc('leads')
            ->get()
            ->map(fn (CampanhaMarketing $campanha): array => [
                'id' => $campanha->id,
                'nome' => $campanha->nome,
                'canal' => $campanha->canal_label,
                'leads' => (int) $campanha->leads,
                'matriculados' => (int) $campanha->matriculados,
                'taxa' => self::taxa((int) $campanha->matriculados, (int) $campanha->leads),
                'custo' => (float) $campanha->custo,
                'custo_por_lead' => self::razao((float) $campanha->custo, (int) $campanha->leads),
                'custo_por_matricula' => self::razao((float) $campanha->custo, (int) $campanha->matriculados),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, nome: string, leads: int, matriculados: int, taxa: float}>
     */
    public static function porOrigem(): array
    {
        return OrigemInteressado::query()
            ->withCount([
                'interessados as leads',
                'interessados as matriculados' => fn (Builder $query) => $query->whereNotNull('data_conversao'),
            ])
            ->orderByDesc('leads')
            ->get()
            ->map(fn (OrigemInteressado $origem): array => [
                'id' => $origem->id,
                'nome' => $origem->nome,
                'leads' => (int) $origem->leads,
                'matriculados' => (int) $origem->matriculados,
                'taxa' => self::taxa((int) $origem->matriculados, (int) $origem->leads),
            ])
            ->all();
    }

    /**
     * Resumo agregado das campanhas, para cabeçalhos e cartões.
     *
     * @return array{leads: int, matriculados: int, taxa: float, custo: float}
     */
    public static function resumoCampanhas(): array
    {
        $linhas = Collection::make(self::porCampanha());

        $leads = (int) $linhas->sum('leads');
        $matriculados = (int) $linhas->sum('matriculados');

        return [
            'leads' => $leads,
            'matriculados' => $matriculados,
            'taxa' => self::taxa($matriculados, $leads),
            'custo' => (float) $linhas->sum('custo'),
        ];
    }

    private static function taxa(int $parte, int $total): float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : 0.0;
    }

    private static function razao(float $valor, int $divisor): ?float
    {
        return $divisor > 0 ? round($valor / $divisor, 2) : null;
    }
}

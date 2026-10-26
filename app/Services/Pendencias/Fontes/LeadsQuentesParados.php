<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Models\Interessado;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Leads quentes (temperatura *quente* ou score acima do corte da faixa quente) sem nenhuma interação humana há alguns dias
 * (Onda 84). É a aba *Quentes* cruzada com *Estagnados* da lista: o lead que mais converte e está esfriando. Passado o dobro
 * do prazo de estagnação, a pendência passa a atrasada.
 */
final class LeadsQuentesParados extends FilaDeLeads
{
    public function chave(): string
    {
        return 'leads_quentes_parados';
    }

    public function titulo(): string
    {
        return 'Leads quentes sem contato';
    }

    public function icone(): string
    {
        return 'heroicon-o-fire';
    }

    public function orientacao(): string
    {
        return 'Procure a família hoje. Lead quente parado é a matrícula que mais escapa: registre o atendimento e marque a próxima ação.';
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario, $this->dias())->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario, $this->dias() * 2)->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $corteAtraso = now()->subDays($this->dias() * 2);

        return $this->comRelacoes($this->consulta($usuario, $this->dias()))
            ->with('ultimoHistorico')
            ->orderByDesc('interessado.lead_score')
            ->limit($limite)
            ->get()
            ->map(function (Interessado $lead) use ($corteAtraso) {
                $ultimo = $lead->ultimoHistorico?->data_contato ?? $lead->created_at;

                return $this->item(
                    $lead,
                    'Score '.($lead->lead_score ?? '—').' · última interação '.$ultimo->diffForHumans(),
                    $ultimo->lte($corteAtraso),
                    $ultimo,
                );
            })
            ->all();
    }

    private function dias(): int
    {
        return max(1, $this->configuracao('quente_parado_dias', 3));
    }

    /**
     * @return Builder<Interessado>
     */
    private function consulta(User $usuario, int $diasSemInteracao): Builder
    {
        $corteQuente = (int) config('lead_score.faixas_cor.quente', 70);

        return $this->base($usuario)
            ->where(fn (Builder $quente) => $quente->where('interessado.temperatura', 'quente')->orWhere('interessado.lead_score', '>=', $corteQuente))
            ->estagnados($diasSemInteracao);
    }
}

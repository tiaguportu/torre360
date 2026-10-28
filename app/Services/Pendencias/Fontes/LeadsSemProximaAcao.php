<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Models\Interessado;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lead em andamento sem próximo contato marcado (Onda 84): ninguém sabe quando ele volta a ser procurado, então ele só reaparece
 * quando alguém lembra. Lead recém-cadastrado tem uma carência (dá tempo de o consultor fazer o primeiro contato); depois de alguns dias
 * sem próxima ação a pendência passa a atrasada.
 */
final class LeadsSemProximaAcao extends FilaDeLeads
{
    public function chave(): string
    {
        return 'leads_sem_proxima_acao';
    }

    public function titulo(): string
    {
        return 'Leads sem próxima ação';
    }

    public function icone(): string
    {
        return 'heroicon-o-flag';
    }

    public function orientacao(): string
    {
        return 'Abra o lead e marque o que fazer e quando (botão "Próxima ação"). Lead sem data não aparece em "Precisa de contato" nem nos alertas.';
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)->where('interessado.created_at', '<=', now()->subDays($this->configuracao('sem_proxima_acao_atrasado_dias', 2)))->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $corte = now()->subDays($this->configuracao('sem_proxima_acao_atrasado_dias', 2));

        return $this->comRelacoes($this->consulta($usuario))
            ->orderBy('interessado.created_at')
            ->limit($limite)
            ->get()
            ->map(fn (Interessado $lead) => $this->item(
                $lead,
                ($lead->status?->nome ?? 'Em andamento').' · cadastrado '.$lead->created_at->diffForHumans(),
                $lead->created_at->lte($corte),
                $lead->created_at,
            ))
            ->all();
    }

    /**
     * @return Builder<Interessado>
     */
    private function consulta(User $usuario): Builder
    {
        return $this->base($usuario)
            ->whereNull('interessado.data_proximo_contato')
            ->where('interessado.created_at', '<=', now()->subHours($this->configuracao('carencia_sem_proxima_acao_horas', 2)));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Models\Interessado;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Leads em andamento sem consultor responsável (Onda 84): quem pode assumir lead (`Update:Interessado`) vê a fila; qualquer
 * consultor pode assumir pelo botão da lista. Passadas as horas do alerta de gestão (`crm.alertas.sem_consultor_apos_horas`),
 * a pendência é atrasada.
 */
final class LeadsSemConsultor extends FilaDeLeads
{
    public function chave(): string
    {
        return 'leads_sem_consultor';
    }

    public function titulo(): string
    {
        return 'Leads sem consultor';
    }

    public function icone(): string
    {
        return 'heroicon-o-user-plus';
    }

    public function orientacao(): string
    {
        return 'Assuma o lead (botão "Assumir Lead" da lista) ou distribua pelo lote "Atribuir consultor".';
    }

    public function permissao(): string
    {
        return 'Update:Interessado';
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)->where('interessado.created_at', '<=', $this->corte())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $corte = $this->corte();

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

    private function corte(): CarbonInterface
    {
        return now()->subHours($this->configuracao('sem_consultor_apos_horas', (int) config('crm.alertas.sem_consultor_apos_horas', 24)));
    }

    /**
     * @return Builder<Interessado>
     */
    private function consulta(User $usuario): Builder
    {
        return $this->base($usuario)->naoAtribuidos();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Models\Interessado;
use App\Models\User;
use App\Services\LeadSlaService;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Support\Collection;

/**
 * Leads cuja primeira resposta da escola passou do prazo de SLA (Onda 84), em horário comercial: a mesma conta do selo *SLA 1ª
 * Resposta* da lista e do alerta `crm:verificar-sla-estourado`. Toda a fila é atrasada. O limite de dias evita varrer leads
 * antigos que nunca foram atendidos e que a gestão já recebeu em outros alertas.
 */
final class PrimeiraRespostaEstourada extends FilaDeLeads
{
    /** @var Collection<int, Interessado>|null */
    private ?Collection $estourados = null;

    private ?int $usuarioDoCache = null;

    public function chave(): string
    {
        return 'primeira_resposta_estourada';
    }

    public function titulo(): string
    {
        return 'Primeira resposta fora do prazo';
    }

    public function icone(): string
    {
        return 'heroicon-o-clock';
    }

    public function orientacao(): string
    {
        return 'Fale com a família agora e registre o atendimento: o primeiro contato encerra o SLA.';
    }

    public function contar(User $usuario): int
    {
        return $this->estourados($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->estourados($usuario)->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $sla = app(LeadSlaService::class);

        return $this->estourados($usuario)
            ->take($limite)
            ->map(function (Interessado $lead) use ($sla): ItemPendencia {
                $limiteSla = $sla->calcularDataLimite($lead);

                return $this->item($lead, 'Prazo era '.$limiteSla->format('d/m H:i').' · '.$limiteSla->diffForHumans(), true, $limiteSla);
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Interessado>
     */
    private function estourados(User $usuario): Collection
    {
        if ($this->estourados !== null && $this->usuarioDoCache === $usuario->getKey()) {
            return $this->estourados;
        }

        $sla = app(LeadSlaService::class);

        $this->usuarioDoCache = $usuario->getKey();

        return $this->estourados = $this->comRelacoes($this->base($usuario))
            ->whereNull('interessado.data_primeiro_contato')
            ->where('interessado.created_at', '>=', now()->subDays($this->configuracao('sla_janela_dias', 30)))
            ->orderBy('interessado.created_at')
            ->get()
            ->filter(fn (Interessado $lead): bool => $sla->slaEstourado($lead))
            ->values();
    }
}

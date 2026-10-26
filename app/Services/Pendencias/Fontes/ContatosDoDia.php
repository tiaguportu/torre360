<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Models\Interessado;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contatos que o consultor marcou para hoje ou que já passaram da hora (Onda 84). É a mesma regra da aba *Precisa de contato*
 * da lista (`data_proximo_contato` no passado) mais o que vence até o fim do dia: só o que já passou da hora é atrasado.
 */
final class ContatosDoDia extends FilaDeLeads
{
    public function chave(): string
    {
        return 'contatos_do_dia';
    }

    public function titulo(): string
    {
        return 'Contatos do dia';
    }

    public function icone(): string
    {
        return 'heroicon-o-phone';
    }

    public function orientacao(): string
    {
        return 'Fale com a família e registre o atendimento (botão "Atendimento"): o registro pede a próxima ação e tira o lead desta fila.';
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->base($usuario)->precisaContato()->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->comRelacoes($this->consulta($usuario))
            ->orderBy('interessado.data_proximo_contato')
            ->limit($limite)
            ->get()
            ->map(fn (Interessado $lead) => $this->item(
                $lead,
                ($lead->proxima_acao ?: 'Retomar o contato').' · '.$lead->data_proximo_contato->format('d/m H:i'),
                $lead->data_proximo_contato->isPast(),
                $lead->data_proximo_contato,
            ))
            ->all();
    }

    /**
     * @return Builder<Interessado>
     */
    private function consulta(User $usuario): Builder
    {
        return $this->base($usuario)
            ->whereNotNull('interessado.data_proximo_contato')
            ->where('interessado.data_proximo_contato', '<=', now()->endOfDay());
    }
}

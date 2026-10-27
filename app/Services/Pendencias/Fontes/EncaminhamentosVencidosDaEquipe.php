<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusReuniao;
use App\Enums\TipoReuniao;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Models\ReuniaoEncaminhamento;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Painel da coordenação (Onda 32): os encaminhamentos de reunião da equipe que passaram do prazo. Não entram os de reuniões
 * sigilosas, nem os do próprio usuário (esses estão na fila "Meus encaminhamentos de reunião").
 */
final class EncaminhamentosVencidosDaEquipe implements FontePendencia
{
    public function chave(): string
    {
        return 'encaminhamentos_equipe_vencidos';
    }

    public function titulo(): string
    {
        return 'Encaminhamentos vencidos da equipe';
    }

    public function area(): string
    {
        return 'Acadêmico';
    }

    public function icone(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public function orientacao(): string
    {
        return 'Fale com o responsável: o encaminhamento precisa ser concluído, cancelado com um motivo ou ter novo combinado.';
    }

    public function permissao(): string
    {
        return 'VerTodas:Reuniao';
    }

    public function url(): string
    {
        return ReuniaoResource::getUrl('index');
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    /**
     * Todo encaminhamento desta fila já passou do prazo.
     */
    public function atrasados(User $usuario): int
    {
        return $this->contar($usuario);
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta($usuario)
            ->with(['reuniao', 'responsavel'])
            ->orderBy('prazo')
            ->limit($limite)
            ->get()
            ->map(function (ReuniaoEncaminhamento $encaminhamento): ItemPendencia {
                $dias = (int) $encaminhamento->prazo->diffInDays(today());

                return new ItemPendencia(
                    titulo: $encaminhamento->descricao,
                    detalhe: ($encaminhamento->responsavel?->name ?? 'Sem responsável').' · '.$encaminhamento->reuniao->titulo.' · '.$dias.' '.($dias === 1 ? 'dia' : 'dias').' de atraso',
                    desde: $encaminhamento->prazo,
                    url: ReuniaoResource::getUrl('view', ['record' => $encaminhamento->reuniao]),
                    atrasado: true,
                );
            })
            ->all();
    }

    /**
     * @return Builder<ReuniaoEncaminhamento>
     */
    private function consulta(User $usuario): Builder
    {
        return ReuniaoEncaminhamento::query()
            ->vencidos()
            ->where(fn (Builder $q) => $q->whereNull('responsavel_user_id')->orWhere('responsavel_user_id', '!=', $usuario->id))
            ->whereHas('reuniao', fn (Builder $q) => $q
                ->where('status', '!=', StatusReuniao::Cancelada->value)
                ->where('confidencial', false)
                ->where('tipo', '!=', TipoReuniao::Devolutiva->value));
    }
}

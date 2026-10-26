<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base das filas da Mesa do consultor (Onda 84): leads em andamento que o usuário pode ver. A visibilidade é a mesma da lista de
 * Interessados (`visivelPara`): o consultor vê a própria carteira e os leads sem dono; quem tem `ViewAll:Interessado` vê todos.
 * Cada fila usa os mesmos escopos das abas da lista, para a Mesa nunca contradizer a tela onde o lead se resolve.
 */
abstract class FilaDeLeads implements FontePendencia
{
    public function area(): string
    {
        return 'CRM / Comercial';
    }

    public function permissao(): string
    {
        return 'ViewAny:Interessado';
    }

    public function url(): string
    {
        return InteressadoResource::getUrl('index');
    }

    /**
     * Leads em andamento visíveis ao usuário.
     *
     * @return Builder<Interessado>
     */
    protected function base(User $usuario): Builder
    {
        return Interessado::query()->ativos()->visivelPara($usuario);
    }

    /**
     * @param  Builder<Interessado>  $consulta
     * @return Builder<Interessado>
     */
    protected function comRelacoes(Builder $consulta): Builder
    {
        return $consulta->with(['pessoa', 'status', 'usuario']);
    }

    protected function item(Interessado $lead, string $detalhe, bool $atrasado, ?CarbonInterface $desde = null): ItemPendencia
    {
        return new ItemPendencia(
            titulo: $lead->pessoa?->nome ?? "Lead #{$lead->id}",
            detalhe: trim($detalhe.($lead->usuario ? ' · '.$lead->usuario->name : '')),
            desde: $desde,
            url: InteressadoResource::getUrl('edit', ['record' => $lead]),
            atrasado: $atrasado,
        );
    }

    protected function configuracao(string $chave, int $padrao): int
    {
        return (int) config("crm.mesa.{$chave}", $padrao);
    }
}

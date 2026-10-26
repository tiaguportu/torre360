<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visitas à escola marcadas para hoje, ou que já passaram sem o resultado registrado (Onda 84). Visita sem resultado trava a
 * régua (o agradecimento e a pesquisa de satisfação saem na "visita realizada") e deixa o lead sem próxima ação: depois de
 * algumas horas a pendência passa a atrasada.
 */
final class VisitasDoDia implements FontePendencia
{
    public function chave(): string
    {
        return 'visitas_do_dia';
    }

    public function titulo(): string
    {
        return 'Visitas de hoje e sem resultado';
    }

    public function area(): string
    {
        return 'CRM / Comercial';
    }

    public function icone(): string
    {
        return 'heroicon-o-calendar-days';
    }

    public function orientacao(): string
    {
        return 'Na aba "Visitas" do lead, marque a visita como realizada (ou que a família faltou). Isso dispara a pesquisa de satisfação e libera a próxima ação.';
    }

    public function permissao(): string
    {
        return 'ViewAny:Interessado';
    }

    public function url(): string
    {
        return InteressadoResource::getUrl('index');
    }

    public function contar(User $usuario): int
    {
        return $this->consulta($usuario)->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta($usuario)->where('visita_interessado.data_hora', '<=', now()->subHours($this->horasSemResultado()))->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $corte = now()->subHours($this->horasSemResultado());

        return $this->consulta($usuario)
            ->with('interessado.pessoa', 'interessado.usuario')
            ->orderBy('visita_interessado.data_hora')
            ->limit($limite)
            ->get()
            ->map(fn (VisitaInteressado $visita): ItemPendencia => new ItemPendencia(
                titulo: $visita->interessado?->pessoa?->nome ?? 'Visita',
                detalhe: ($visita->data_hora->isPast() ? 'Passou sem resultado' : 'Hoje').' · '.$visita->data_hora->format('d/m H:i').($visita->interessado?->usuario ? ' · '.$visita->interessado->usuario->name : ''),
                desde: $visita->data_hora,
                url: $visita->interessado ? InteressadoResource::getUrl('edit', ['record' => $visita->interessado]) : $this->url(),
                atrasado: $visita->data_hora->lte($corte),
            ))
            ->all();
    }

    private function horasSemResultado(): int
    {
        return (int) config('crm.mesa.visita_sem_resultado_horas', 4);
    }

    /**
     * @return Builder<VisitaInteressado>
     */
    private function consulta(User $usuario): Builder
    {
        return VisitaInteressado::query()
            ->agendadas()
            ->where('visita_interessado.data_hora', '<=', now()->endOfDay())
            ->whereHas('interessado', fn (Builder $lead) => $lead->ativos()->visivelPara($usuario));
    }
}

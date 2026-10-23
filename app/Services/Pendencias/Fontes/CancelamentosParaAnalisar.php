<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusCancelamentoMatricula;
use App\Filament\Resources\CancelamentosMatricula\CancelamentoMatriculaResource;
use App\Models\CancelamentoMatricula;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pedidos de cancelamento de matrícula (Onda 61) que a família fez e a secretaria ainda não aprovou nem recusou.
 */
final class CancelamentosParaAnalisar implements FontePendencia
{
    public function chave(): string
    {
        return 'cancelamentos_para_analisar';
    }

    public function titulo(): string
    {
        return 'Pedidos de cancelamento para analisar';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-document-minus';
    }

    public function orientacao(): string
    {
        return 'Confira a situação financeira e aprove ou recuse: a família espera o retorno.';
    }

    public function permissao(): string
    {
        return 'Aprovar:CancelamentoMatricula';
    }

    public function url(): string
    {
        return CancelamentoMatriculaResource::getUrl('index', ['filters[status][values][0]' => StatusCancelamentoMatricula::Solicitado->value]);
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta()->where('created_at', '<=', $this->limite())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $limiteDeAtraso = $this->limite();

        return $this->consulta()
            ->with('matricula.pessoa')
            ->orderBy('created_at')
            ->limit($limite)
            ->get()
            ->map(fn (CancelamentoMatricula $pedido): ItemPendencia => new ItemPendencia(
                titulo: $pedido->matricula?->pessoa?->nome ?? 'Aluno',
                detalhe: 'Pediu em '.$pedido->created_at->format('d/m/Y').' · saída em '.$pedido->data_efetiva->format('d/m/Y').' · '.$pedido->motivo->getLabel(),
                desde: $pedido->created_at,
                url: $this->url(),
                atrasado: $pedido->created_at->lte($limiteDeAtraso),
            ))
            ->all();
    }

    /**
     * @return Builder<CancelamentoMatricula>
     */
    private function consulta(): Builder
    {
        return CancelamentoMatricula::query()->where('status', StatusCancelamentoMatricula::Solicitado->value);
    }

    private function limite(): CarbonInterface
    {
        return now()->subDays((int) config('mesa_pendencias.cancelamento_resposta_dias', 3));
    }
}

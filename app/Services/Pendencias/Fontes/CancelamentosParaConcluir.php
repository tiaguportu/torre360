<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusCancelamentoMatricula;
use App\Filament\Resources\CancelamentosMatricula\CancelamentoMatriculaResource;
use App\Models\CancelamentoMatricula;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cancelamentos aprovados cuja data de saída chegou: falta anexar o distrato assinado e concluir.
 */
final class CancelamentosParaConcluir implements FontePendencia
{
    public function chave(): string
    {
        return 'cancelamentos_para_concluir';
    }

    public function titulo(): string
    {
        return 'Cancelamentos prontos para concluir';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-flag';
    }

    public function orientacao(): string
    {
        return 'A data de saída chegou: anexe o distrato assinado (se faltar) e conclua para cancelar a matrícula e as faturas.';
    }

    public function permissao(): string
    {
        return 'Concluir:CancelamentoMatricula';
    }

    public function url(): string
    {
        return CancelamentoMatriculaResource::getUrl('index', ['filters[status][values][0]' => StatusCancelamentoMatricula::Aprovado->value]);
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    /**
     * Já passou do último dia do aluno.
     */
    public function atrasados(User $usuario): int
    {
        return $this->consulta()->whereDate('data_efetiva', '<', today()->toDateString())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta()
            ->with('matricula.pessoa')
            ->orderBy('data_efetiva')
            ->limit($limite)
            ->get()
            ->map(fn (CancelamentoMatricula $pedido): ItemPendencia => new ItemPendencia(
                titulo: $pedido->matricula?->pessoa?->nome ?? 'Aluno',
                detalhe: 'Saída em '.$pedido->data_efetiva->format('d/m/Y').' · '.($pedido->temDistratoAssinado() ? 'distrato assinado anexado' : 'falta o distrato assinado'),
                desde: $pedido->data_efetiva,
                url: $this->url(),
                atrasado: $pedido->data_efetiva->lt(today()),
            ))
            ->all();
    }

    /**
     * @return Builder<CancelamentoMatricula>
     */
    private function consulta(): Builder
    {
        return CancelamentoMatricula::query()
            ->where('status', StatusCancelamentoMatricula::Aprovado->value)
            ->whereDate('data_efetiva', '<=', today()->toDateString());
    }
}

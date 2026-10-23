<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Resources\CancelamentosMatricula\CancelamentoMatriculaResource;
use App\Models\CancelamentoMatricula;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cancelamentos concluídos em que a escola ainda deve devolver o que a família pagou adiantado.
 */
final class DevolucoesPendentes implements FontePendencia
{
    public function chave(): string
    {
        return 'devolucoes_pendentes';
    }

    public function titulo(): string
    {
        return 'Devoluções a fazer';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-arrow-uturn-left';
    }

    public function orientacao(): string
    {
        return 'Devolva o valor à família, lance a saída em Movimentação Bancária e registre a devolução no cancelamento.';
    }

    public function permissao(): string
    {
        return 'Concluir:CancelamentoMatricula';
    }

    public function url(): string
    {
        return CancelamentoMatriculaResource::getUrl('index');
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta()->where('concluido_em', '<=', $this->limite())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $limiteDeAtraso = $this->limite();

        return $this->consulta()
            ->with('matricula.pessoa')
            ->orderBy('concluido_em')
            ->limit($limite)
            ->get()
            ->map(fn (CancelamentoMatricula $pedido): ItemPendencia => new ItemPendencia(
                titulo: $pedido->matricula?->pessoa?->nome ?? 'Aluno',
                detalhe: 'Devolver R$ '.number_format((float) $pedido->valor_a_devolver, 2, ',', '.').' · concluído em '.$pedido->concluido_em?->format('d/m/Y'),
                desde: $pedido->concluido_em,
                url: $this->url(),
                atrasado: $pedido->concluido_em?->lte($limiteDeAtraso) ?? false,
            ))
            ->all();
    }

    /**
     * @return Builder<CancelamentoMatricula>
     */
    private function consulta(): Builder
    {
        return CancelamentoMatricula::query()->comDevolucaoPendente();
    }

    private function limite(): CarbonInterface
    {
        return now()->subDays((int) config('mesa_pendencias.devolucao_dias', 10));
    }
}

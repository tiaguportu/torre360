<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Resources\ContasRecorrentes\ContaRecorrenteResource;
use App\Models\ContaRecorrente;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Despesas recorrentes com o reajuste do contrato chegando (Onda 78): aluguel e serviços têm data para negociar ou aplicar o índice.
 * Passou da data sem o reajuste registrado, fica atrasado.
 */
final class ReajustesDeContrato implements FontePendencia
{
    public function chave(): string
    {
        return 'reajustes_de_contrato';
    }

    public function titulo(): string
    {
        return 'Reajustes de contrato';
    }

    public function area(): string
    {
        return 'Financeiro';
    }

    public function icone(): string
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public function orientacao(): string
    {
        return 'Negocie ou aplique o índice e registre o reajuste na despesa; o valor novo passa a valer nas próximas contas.';
    }

    public function permissao(): string
    {
        return 'Update:ContaRecorrente';
    }

    public function url(): string
    {
        return ContaRecorrenteResource::getUrl('index');
    }

    /**
     * @return Builder<ContaRecorrente>
     */
    private function consulta(): Builder
    {
        $dias = max(0, (int) config('financeiro.recorrentes.aviso_reajuste_dias', 30));

        return ContaRecorrente::query()
            ->ativas()
            ->whereNotNull('proximo_reajuste_em')
            ->whereDate('proximo_reajuste_em', '<=', today()->addDays($dias)->toDateString());
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta()->whereDate('proximo_reajuste_em', '<', today()->toDateString())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta()
            ->with('fornecedor')
            ->orderBy('proximo_reajuste_em')
            ->limit($limite)
            ->get()
            ->map(fn (ContaRecorrente $modelo): ItemPendencia => new ItemPendencia(
                titulo: $modelo->descricao,
                detalhe: 'Reajuste em '.$modelo->proximo_reajuste_em->format('d/m/Y').' · hoje R$ '.number_format((float) $modelo->valor, 2, ',', '.').($modelo->fornecedor ? ' · '.$modelo->fornecedor->razao_social : ''),
                url: ContaRecorrenteResource::getUrl('edit', ['record' => $modelo]),
                atrasado: $modelo->proximo_reajuste_em->lt(today()),
            ))
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusContaPagar;
use App\Filament\Resources\ContaPagars\ContaPagarResource;
use App\Models\ContaPagar;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contas a pagar geradas de despesas de valor estimado (energia, água) que ainda esperam a conferência do valor da fatura (Onda 78).
 * Entram na fila a poucos dias do vencimento; vencida e sem revisão é atrasada.
 */
final class RecorrentesParaRevisar implements FontePendencia
{
    public function chave(): string
    {
        return 'recorrentes_para_revisar';
    }

    public function titulo(): string
    {
        return 'Contas recorrentes a revisar';
    }

    public function area(): string
    {
        return 'Financeiro';
    }

    public function icone(): string
    {
        return 'heroicon-o-pencil-square';
    }

    public function orientacao(): string
    {
        return 'Confira o valor da fatura que chegou e confirme em "Revisar valor"; só depois dê a baixa.';
    }

    public function permissao(): string
    {
        return 'Update:ContaPagar';
    }

    public function url(): string
    {
        return ContaPagarResource::getUrl('index', ['filters[a_revisar][isActive]' => 'true']);
    }

    /**
     * @return Builder<ContaPagar>
     */
    private function consulta(): Builder
    {
        $dias = max(0, (int) config('financeiro.recorrentes.revisao_valor_dias', 7));

        return ContaPagar::query()
            ->whereNotNull('conta_recorrente_id')
            ->where('valor_revisado', false)
            ->whereIn('status', [StatusContaPagar::Pendente->value, StatusContaPagar::Atrasado->value])
            ->whereDate('vencimento', '<=', today()->addDays($dias)->toDateString());
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta()->whereDate('vencimento', '<', today()->toDateString())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta()
            ->orderBy('vencimento')
            ->limit($limite)
            ->get()
            ->map(fn (ContaPagar $conta): ItemPendencia => new ItemPendencia(
                titulo: $conta->descricao,
                detalhe: 'Vence em '.$conta->vencimento->format('d/m/Y').' · estimado R$ '.number_format((float) $conta->valor, 2, ',', '.'),
                url: ContaPagarResource::getUrl('index', ['filters[a_revisar][isActive]' => 'true']),
                atrasado: $conta->vencimento->lt(today()),
            ))
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Enums\StatusTransferencia;
use App\Enums\TipoTransferencia;
use App\Filament\Resources\TransferenciasEscolares\TransferenciaEscolarResource;
use App\Models\TransferenciaEscolar;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Transferências escolares (entrada e saída) ainda em andamento. A que já passou da data prevista está atrasada.
 */
final class TransferenciasEmAndamento implements FontePendencia
{
    public function chave(): string
    {
        return 'transferencias_em_andamento';
    }

    public function titulo(): string
    {
        return 'Transferências em andamento';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-arrows-right-left';
    }

    public function orientacao(): string
    {
        return 'Na saída, emita a declaração e conclua; na entrada, cobre o histórico da escola de origem e conclua quando chegar.';
    }

    public function permissao(): string
    {
        return 'Update:TransferenciaEscolar';
    }

    public function url(): string
    {
        return TransferenciaEscolarResource::getUrl('index', ['filters[status][value]' => StatusTransferencia::EmAndamento->value]);
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta()->whereDate('data', '<', today()->toDateString())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta()
            ->with('matricula.pessoa')
            ->orderBy('data')
            ->limit($limite)
            ->get()
            ->map(function (TransferenciaEscolar $transferencia): ItemPendencia {
                $entrada = $transferencia->tipo === TipoTransferencia::Entrada;
                $escola = $transferencia->escola_externa_nome ?: 'outra escola';

                return new ItemPendencia(
                    titulo: $transferencia->matricula?->pessoa?->nome ?? 'Aluno',
                    detalhe: ($entrada ? "Entrada vinda de {$escola}" : "Saída para {$escola}")
                        .' · prevista para '.$transferencia->data->format('d/m/Y')
                        .($entrada && ! $transferencia->historico_recebido ? ' · falta o histórico' : ''),
                    desde: $transferencia->data,
                    url: $this->url(),
                    atrasado: $transferencia->data->lt(today()),
                );
            })
            ->all();
    }

    /**
     * @return Builder<TransferenciaEscolar>
     */
    private function consulta(): Builder
    {
        return TransferenciaEscolar::query()->where('status', StatusTransferencia::EmAndamento->value);
    }
}

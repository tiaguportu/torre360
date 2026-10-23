<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Resources\ProcessosFuncionario\ProcessoFuncionarioResource;
use App\Models\ItemProcessoFuncionario;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Providências de admissão e desligamento (Onda 65) com o prazo vencido, em processos ainda em andamento. É a mesma
 * contagem do selo do menu de Admissão e Desligamento.
 */
final class ProvidenciasRhVencidas implements FontePendencia
{
    public function chave(): string
    {
        return 'providencias_rh_vencidas';
    }

    public function titulo(): string
    {
        return 'Providências de RH vencidas';
    }

    public function area(): string
    {
        return 'RH';
    }

    public function icone(): string
    {
        return 'heroicon-o-arrow-right-on-rectangle';
    }

    public function orientacao(): string
    {
        return 'Abra o processo, faça a providência e marque como feita (com o comprovante) ou como "não se aplica".';
    }

    public function permissao(): string
    {
        return 'ViewAny:ProcessoFuncionario';
    }

    public function url(): string
    {
        return ProcessoFuncionarioResource::getUrl('index');
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    /**
     * Toda providência desta fila já passou do prazo.
     */
    public function atrasados(User $usuario): int
    {
        return $this->contar($usuario);
    }

    public function itens(User $usuario, int $limite): array
    {
        return $this->consulta()
            ->with('processo.funcionario.pessoa')
            ->orderBy('prazo')
            ->limit($limite)
            ->get()
            ->map(function (ItemProcessoFuncionario $item): ItemPendencia {
                $dias = (int) $item->prazo->diffInDays(today());

                return new ItemPendencia(
                    titulo: $item->titulo,
                    detalhe: ($item->processo?->funcionario?->pessoa?->nome ?? 'Funcionário').' · prazo '.$item->prazo->format('d/m/Y').' ('.$dias.' '.($dias === 1 ? 'dia' : 'dias').' de atraso)',
                    desde: $item->prazo,
                    url: $item->processo ? ProcessoFuncionarioResource::getUrl('view', ['record' => $item->processo]) : $this->url(),
                    atrasado: true,
                );
            })
            ->all();
    }

    /**
     * @return Builder<ItemProcessoFuncionario>
     */
    private function consulta(): Builder
    {
        return ItemProcessoFuncionario::query()->atrasados();
    }
}

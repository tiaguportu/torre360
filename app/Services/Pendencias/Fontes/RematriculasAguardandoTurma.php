<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Resources\Rematriculas\RematriculaResource;
use App\Models\Rematricula;
use App\Models\User;
use App\Support\Pendencias\ItemPendencia;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rematrículas em que a família já confirmou os dados pelo Portal e a secretaria ainda não escolheu a turma (a mesma
 * contagem do selo do menu de Rematrículas).
 */
final class RematriculasAguardandoTurma implements FontePendencia
{
    public function chave(): string
    {
        return 'rematriculas_aguardando_turma';
    }

    public function titulo(): string
    {
        return 'Rematrículas aguardando a turma';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-arrow-path';
    }

    public function orientacao(): string
    {
        return 'Escolha a turma de destino e efetive a rematrícula: só então o contrato vai para a família assinar.';
    }

    public function permissao(): string
    {
        return 'Update:Rematricula';
    }

    public function url(): string
    {
        return RematriculaResource::getUrl('index', ['filters[aguardando_turma][isActive]' => 'true']);
    }

    public function contar(User $usuario): int
    {
        return $this->consulta()->count();
    }

    public function atrasados(User $usuario): int
    {
        return $this->consulta()->where('updated_at', '<=', $this->limite())->count();
    }

    public function itens(User $usuario, int $limite): array
    {
        $limiteDeAtraso = $this->limite();

        return $this->consulta()
            ->with(['matriculaOrigem.pessoa', 'periodoRematricula'])
            ->orderBy('updated_at')
            ->limit($limite)
            ->get()
            ->map(fn (Rematricula $rematricula): ItemPendencia => new ItemPendencia(
                titulo: $rematricula->matriculaOrigem?->pessoa?->nome ?? 'Aluno',
                detalhe: 'Dados confirmados em '.$rematricula->updated_at->format('d/m/Y').($rematricula->periodoRematricula ? ' · '.$rematricula->periodoRematricula->nome : ''),
                desde: $rematricula->updated_at,
                url: $this->url(),
                atrasado: $rematricula->updated_at->lte($limiteDeAtraso),
            ))
            ->all();
    }

    /**
     * @return Builder<Rematricula>
     */
    private function consulta(): Builder
    {
        return Rematricula::query()->aguardandoTurma();
    }

    private function limite(): CarbonInterface
    {
        return now()->subDays((int) config('mesa_pendencias.rematricula_turma_dias', 5));
    }
}

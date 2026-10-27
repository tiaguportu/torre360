<?php

declare(strict_types=1);

namespace App\Services\Pendencias\Fontes;

use App\Contracts\FontePendencia;
use App\Filament\Pages\SimuladorProximoAno;
use App\Models\User;
use App\Services\SimuladorProximoAnoService;
use App\Support\Pendencias\ItemPendencia;

/**
 * Séries com problema na projeção do próximo ano (Onda 76), com os parâmetros padrão do simulador: demanda sem turma, acima da
 * capacidade, abaixo do ponto de equilíbrio ou lista de espera maior que as vagas que sobram. Quando a campanha de rematrícula está
 * perto do fim, a decisão de abrir ou fechar turma tem prazo e a fila passa a atrasada.
 */
final class ProjecaoDoProximoAno implements FontePendencia
{
    /** @var list<array{serie: string, problema: string, detalhe: string, urgente: bool}>|null */
    private ?array $alertas = null;

    public function chave(): string
    {
        return 'projecao_do_proximo_ano';
    }

    public function titulo(): string
    {
        return 'Projeção do próximo ano';
    }

    public function area(): string
    {
        return 'Secretaria';
    }

    public function icone(): string
    {
        return 'heroicon-o-calculator';
    }

    public function orientacao(): string
    {
        return 'Abra o simulador, teste abrir ou fechar turma e decida antes do fim da campanha de rematrícula.';
    }

    public function permissao(): string
    {
        return 'View:SimuladorProximoAno';
    }

    public function url(): string
    {
        return SimuladorProximoAno::getUrl();
    }

    /**
     * @return list<array{serie: string, problema: string, detalhe: string, urgente: bool}>
     */
    private function alertas(): array
    {
        return $this->alertas ??= app(SimuladorProximoAnoService::class)->alertas();
    }

    public function contar(User $usuario): int
    {
        return count($this->alertas());
    }

    public function atrasados(User $usuario): int
    {
        return count(array_filter($this->alertas(), fn (array $a): bool => $a['urgente']));
    }

    public function itens(User $usuario, int $limite): array
    {
        return collect($this->alertas())
            ->take($limite)
            ->map(fn (array $a): ItemPendencia => new ItemPendencia(
                titulo: $a['serie'].' · '.$a['problema'],
                detalhe: $a['detalhe'],
                url: SimuladorProximoAno::getUrl(),
                atrasado: $a['urgente'],
            ))
            ->values()
            ->all();
    }
}

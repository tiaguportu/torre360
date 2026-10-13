<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico;

final readonly class GerarPeiInput
{
    /**
     * @param  list<string>  $categoriasNecessidades
     * @param  list<string>  $categoriasTranstornos
     * @param  list<string>  $categoriasRecursos
     * @param  list<array{codigo: string, nome: string}>  $habilidadesBncc
     */
    public function __construct(
        public int $planoAulaId,
        public int $matriculaId,
        public string $etapaEnsino,
        public int $faixaEtariaAnos,
        public string $disciplinaNome,
        public string $temaOuObjetivoAula,
        public array $categoriasNecessidades,
        public array $categoriasTranstornos,
        public array $categoriasRecursos,
        public array $habilidadesBncc,
        public ?string $observacaoPedagogicaHigienizada = null,
    ) {
        if ($this->planoAulaId <= 0) {
            throw new \InvalidArgumentException('ID do plano de aula inválido.');
        }

        if ($this->matriculaId <= 0) {
            throw new \InvalidArgumentException('ID da matrícula inválido.');
        }
    }
}

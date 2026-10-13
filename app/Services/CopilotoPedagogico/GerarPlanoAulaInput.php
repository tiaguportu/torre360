<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico;

final readonly class GerarPlanoAulaInput
{
    /**
     * @param  list<array{id: int, codigo: string, nome: string, descricao: string}>  $habilidades
     */
    public function __construct(
        public int $turmaId,
        public int $disciplinaId,
        public string $turmaNome,
        public string $serieNome,
        public string $disciplinaNome,
        public int $numeroAulas,
        public int $duracaoMinutosPorAula,
        public array $habilidades,
        public ?string $instrucoesExtras = null,
    ) {
        if ($this->numeroAulas < 1) {
            throw new \InvalidArgumentException('O número de aulas deve ser de no mínimo 1.');
        }

        if ($this->duracaoMinutosPorAula < 10) {
            throw new \InvalidArgumentException('A duração de cada aula deve ser de no mínimo 10 minutos.');
        }

        if (empty($this->habilidades)) {
            throw new \InvalidArgumentException('Selecione pelo menos uma habilidade da BNCC para o planejamento.');
        }
    }

    public function duracaoTotalMinutos(): int
    {
        return $this->numeroAulas * $this->duracaoMinutosPorAula;
    }
}

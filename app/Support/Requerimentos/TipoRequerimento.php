<?php

declare(strict_types=1);

namespace App\Support\Requerimentos;

/**
 * Um tipo de requerimento da família (Onda 80): o formulário que ela preenche, o prazo de resposta e, quando a escola pode
 * aplicar o pedido com um clique depois de conferir, o tratador que faz isso.
 *
 * Campos: `nome` (chave em `dados`), `rotulo`, `tipo` (`text`, `textarea`, `date`, `select` ou `file`), `obrigatorio` e, no `select`,
 * `opcoes` (valor => rótulo).
 */
final class TipoRequerimento
{
    /**
     * @param  list<array{nome: string, rotulo: string, tipo: string, obrigatorio?: bool, opcoes?: array<string, string>}>  $campos
     * @param  class-string<AplicaRequerimento>|null  $tratador
     */
    public function __construct(
        public readonly string $chave,
        public readonly string $nome,
        public readonly string $orientacao,
        public readonly array $campos,
        public readonly int $prazoDias,
        public readonly bool $diasUteis = true,
        public readonly bool $restrito = false,
        public readonly bool $exigeMatricula = false,
        public readonly ?string $tratador = null,
    ) {}

    public function tratador(): ?AplicaRequerimento
    {
        return $this->tratador ? app($this->tratador) : null;
    }

    /**
     * @return list<array{nome: string, rotulo: string, tipo: string, obrigatorio?: bool, opcoes?: array<string, string>}>
     */
    public function camposDeArquivo(): array
    {
        return array_values(array_filter($this->campos, fn (array $campo): bool => $campo['tipo'] === 'file'));
    }

    public function rotuloDe(string $campo): string
    {
        foreach ($this->campos as $definicao) {
            if ($definicao['nome'] === $campo) {
                return $definicao['rotulo'];
            }
        }

        return $campo;
    }
}

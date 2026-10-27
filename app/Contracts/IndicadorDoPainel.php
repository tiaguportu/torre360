<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Painel\ContextoPainel;

/**
 * Um indicador do Painel da Direção (Onda 45). Cada indicador tem UMA definição, aqui: a fórmula fica no código e na descrição, e o
 * painel, as fotografias e as metas leem dela. Quando já existe um serviço ou relatório com o mesmo número, o indicador chama esse
 * serviço, para a escola não ter dois valores diferentes para a mesma coisa.
 *
 * Um módulo novo que queira aparecer no painel implementa esta interface e entra em `config/painel_direcao.php`.
 */
interface IndicadorDoPainel
{
    public function chave(): string;

    public function titulo(): string;

    /**
     * Seção do painel: Alunos e vagas, Financeiro, Captação, Pedagógico.
     */
    public function grupo(): string;

    /**
     * Como o valor é mostrado: `inteiro`, `dinheiro` ou `percentual`.
     */
    public function formato(): string;

    /**
     * `maior` quando valor mais alto é melhor (matrículas); `menor` quando é pior (inadimplência). Define o semáforo e a cor da
     * variação.
     */
    public function melhor(): string;

    /**
     * Permissão de quem vê o indicador: a mesma da tela onde o número nasce. Financeiro não aparece para quem não vê financeiro.
     */
    public function permissao(): string;

    /**
     * A fórmula em português, mostrada no cartão.
     */
    public function descricao(): string;

    /**
     * Se o valor pode ser calculado para uma unidade só. Quem não suporta mostra o número da escola toda quando a unidade é filtrada.
     */
    public function suportaUnidade(): bool;

    /**
     * Para onde o clique no cartão leva (a tela de detalhe), ou null.
     */
    public function url(): ?string;

    /**
     * O valor agora, ou null quando ainda não há dado para calcular (sem orçamento aprovado, sem leads).
     */
    public function valor(ContextoPainel $contexto): ?float;
}

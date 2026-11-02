<?php

declare(strict_types=1);

namespace App\Support\Requerimentos;

use App\Models\AtendimentoChamado;
use App\Models\User;

/**
 * Tratador de um tipo de requerimento que muda um dado do sistema (Onda 80). Nenhuma alteração é aplicada sozinha: a secretaria
 * vê `diferencas()` ao lado do dado atual e só então chama `aplicar()`, que registra quem aplicou.
 */
interface AplicaRequerimento
{
    /**
     * O que mudaria, para a conferência.
     *
     * @return list<array{rotulo: string, antes: ?string, depois: ?string}>
     */
    public function diferencas(AtendimentoChamado $chamado): array;

    /**
     * Opções que a secretaria decide na hora de aplicar (nome => rótulo; todas são caixas de seleção).
     *
     * @return array<string, string>
     */
    public function opcoes(): array;

    /**
     * Texto curto e sem dado sensível, que vai à família na resposta de encerramento.
     *
     * @param  array<string, mixed>  $opcoes
     *
     * @throws \DomainException quando o pedido não pode ser aplicado (a mensagem vai à tela)
     */
    public function aplicar(AtendimentoChamado $chamado, User $por, array $opcoes = []): string;
}

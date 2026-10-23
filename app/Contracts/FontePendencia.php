<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;
use App\Support\Pendencias\ItemPendencia;

/**
 * Uma fila da Mesa de pendências (Onda 71): um tipo de item que espera ação de alguém da escola (um pedido para analisar,
 * uma providência vencida, uma grade incompleta...). Cada módulo que tem uma fila acrescenta a sua classe em
 * `config/mesa_pendencias.php`; a Mesa mostra só as filas que o papel ativo do usuário pode ver.
 *
 * A fila não decide nada nem muda dados: ela conta, lista os mais antigos e leva à tela onde a pendência se resolve. A mesma
 * lista de filas pode alimentar um resumo diário por e-mail (Onda 47) sem mexer nas telas.
 */
interface FontePendencia
{
    /**
     * Identificador estável da fila (letras minúsculas e sublinhado).
     */
    public function chave(): string;

    public function titulo(): string;

    /**
     * Assunto em que a fila aparece agrupada na Mesa (Secretaria, RH, Calendário...).
     */
    public function area(): string;

    public function icone(): string;

    /**
     * O que fazer com um item da fila, em uma frase.
     */
    public function orientacao(): string;

    /**
     * Permissão necessária para ver a fila. Vale a do papel ativo do usuário.
     */
    public function permissao(): string;

    /**
     * Tela onde a fila inteira se resolve.
     */
    public function url(): string;

    /**
     * Quantos itens esperam ação.
     */
    public function contar(User $usuario): int;

    /**
     * Quantos desses já passaram do prazo (0 quando a fila não tem prazo).
     */
    public function atrasados(User $usuario): int;

    /**
     * Os itens mais antigos ou mais urgentes primeiro.
     *
     * @return list<ItemPendencia>
     */
    public function itens(User $usuario, int $limite): array;
}

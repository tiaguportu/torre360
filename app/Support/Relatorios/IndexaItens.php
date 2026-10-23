<?php

declare(strict_types=1);

namespace App\Support\Relatorios;

/**
 * Transforma a lista de colunas ou de filtros de uma fonte de relatório (Onda 72) no mapa chave => item que o contrato pede.
 */
trait IndexaItens
{
    /**
     * @template T of ColunaRelatorio|FiltroRelatorio
     *
     * @param  list<T>  $itens
     * @return array<string, T>
     */
    private function indexar(array $itens): array
    {
        $mapa = [];

        foreach ($itens as $item) {
            $mapa[$item->chave] = $item;
        }

        return $mapa;
    }
}

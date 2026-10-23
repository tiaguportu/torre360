<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Relatorios\ColunaRelatorio;
use App\Support\Relatorios\FiltroRelatorio;

/**
 * Uma fonte de dados dos relatórios configuráveis (Onda 72). O relatório nunca monta SQL a partir do que a pessoa escolhe: a
 * pessoa escolhe entre colunas e filtros que a fonte declara, e a fonte é quem consulta o banco.
 *
 * Um módulo novo que queira aparecer nos relatórios implementa esta interface e entra em `config/relatorios.php`.
 */
interface FonteDeRelatorio
{
    public function chave(): string;

    public function titulo(): string;

    public function descricao(): string;

    /**
     * Permissão de quem pode ver estes dados, a mesma da tela correspondente. É conferida ao montar o relatório, a cada
     * execução e para cada destinatário do agendamento.
     */
    public function permissao(): string;

    /**
     * @return array<string, ColunaRelatorio> chave => coluna
     */
    public function colunas(): array;

    /**
     * @return array<string, FiltroRelatorio> chave => filtro
     */
    public function filtros(): array;

    /**
     * No máximo `$limite` linhas (a fonte pede uma a mais para o relatório saber que cortou), cada uma com todas as colunas da
     * fonte pela chave. Valores: texto, número, `\DateTimeInterface` ou nulo.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, scalar|\DateTimeInterface|null>>
     */
    public function linhas(array $filtros, int $limite): array;
}

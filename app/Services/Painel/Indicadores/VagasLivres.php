<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Support\Painel\ContextoPainel;

final class VagasLivres extends OcupacaoDasTurmas
{
    public function chave(): string
    {
        return 'vagas_livres';
    }

    public function titulo(): string
    {
        return 'Vagas livres';
    }

    public function formato(): string
    {
        return 'inteiro';
    }

    public function melhor(): string
    {
        return 'menor';
    }

    public function descricao(): string
    {
        return 'Soma, por turma em andamento, das vagas máximas que ainda não têm aluno (turma acima do limite não conta negativo).';
    }

    public function url(): ?string
    {
        return null;
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        $ocupacao = $this->ocupacao($contexto);

        return $ocupacao['vagas'] > 0 ? (float) $ocupacao['livres'] : null;
    }
}

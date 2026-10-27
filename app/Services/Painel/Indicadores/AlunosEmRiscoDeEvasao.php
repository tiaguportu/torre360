<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Support\Painel\ContextoPainel;

final class AlunosEmRiscoDeEvasao extends IndicadorBase
{
    public function chave(): string
    {
        return 'alunos_em_risco_de_evasao';
    }

    public function titulo(): string
    {
        return 'Alunos em risco alto de evasão';
    }

    public function grupo(): string
    {
        return 'Alunos e vagas';
    }

    public function formato(): string
    {
        return 'inteiro';
    }

    public function melhor(): string
    {
        return 'menor';
    }

    public function permissao(): string
    {
        return 'ViewAny:Matricula';
    }

    public function descricao(): string
    {
        return 'Matrículas ativas cujo risco de evasão calculado está na faixa "alto" da configuração (a mesma cor vermelha da ficha do aluno).';
    }

    public function suportaUnidade(): bool
    {
        return true;
    }

    public function url(): ?string
    {
        return MatriculaResource::getUrl('index');
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        $corte = (int) config('risco_evasao.faixas_cor.alto', 60);

        return (float) $this->matriculasAtivas($contexto)->where('risco_evasao_score', '>=', $corte)->count();
    }
}

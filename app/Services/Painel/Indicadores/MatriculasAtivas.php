<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Support\Painel\ContextoPainel;

final class MatriculasAtivas extends IndicadorBase
{
    public function chave(): string
    {
        return 'matriculas_ativas';
    }

    public function titulo(): string
    {
        return 'Alunos matriculados';
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
        return 'maior';
    }

    public function permissao(): string
    {
        return 'ViewAny:Matricula';
    }

    public function descricao(): string
    {
        return 'Matrículas com situação ativa, ativadas até hoje e ainda não desativadas.';
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
        return (float) $this->matriculasAtivas($contexto)->count();
    }
}

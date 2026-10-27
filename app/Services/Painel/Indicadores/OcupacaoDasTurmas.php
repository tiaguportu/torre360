<?php

declare(strict_types=1);

namespace App\Services\Painel\Indicadores;

use App\Filament\Pages\ControladoriaTurmas;
use App\Models\Turma;
use App\Support\Painel\ContextoPainel;

class OcupacaoDasTurmas extends IndicadorBase
{
    public function chave(): string
    {
        return 'ocupacao_das_turmas';
    }

    public function titulo(): string
    {
        return 'Ocupação das turmas';
    }

    public function grupo(): string
    {
        return 'Alunos e vagas';
    }

    public function formato(): string
    {
        return 'percentual';
    }

    public function melhor(): string
    {
        return 'maior';
    }

    public function permissao(): string
    {
        return 'ViewAny:Turma';
    }

    public function descricao(): string
    {
        return 'Alunos matriculados nas turmas em andamento dividido pelas vagas máximas dessas turmas.';
    }

    public function suportaUnidade(): bool
    {
        return true;
    }

    public function url(): ?string
    {
        return ControladoriaTurmas::getUrl();
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        $ocupacao = $this->ocupacao($contexto);

        return $ocupacao['vagas'] > 0 ? round($ocupacao['alunos'] / $ocupacao['vagas'] * 100, 1) : null;
    }

    /**
     * Vagas, alunos e vagas livres das turmas em andamento. A turma lotada ou acima do limite não "empresta" vaga para a outra.
     *
     * @return array{vagas: int, alunos: int, livres: int}
     */
    protected function ocupacao(ContextoPainel $contexto): array
    {
        $hoje = $contexto->hoje->toDateString();

        $turmas = Turma::query()
            ->ativas()
            ->when($contexto->daUnidade(), fn ($q, int $unidade) => $q->whereHas('serie.curso', fn ($c) => $c->where('unidade_id', $unidade)))
            ->withCount(['matriculas as alunos_ativos' => fn ($q) => $q
                ->where('situacao', 'ativa')
                ->where(fn ($w) => $w->whereNull('data_ativacao')->orWhereDate('data_ativacao', '<=', $hoje))
                ->where(fn ($w) => $w->whereNull('data_desativacao')->orWhereDate('data_desativacao', '>', $hoje))])
            ->get(['id', 'vagas_maximas']);

        $vagas = 0;
        $alunos = 0;
        $livres = 0;

        foreach ($turmas as $turma) {
            $capacidade = (int) ($turma->vagas_maximas ?? 0);
            $vagas += $capacidade;
            $alunos += (int) $turma->alunos_ativos;
            $livres += max(0, $capacidade - (int) $turma->alunos_ativos);
        }

        return ['vagas' => $vagas, 'alunos' => $alunos, 'livres' => $livres];
    }
}

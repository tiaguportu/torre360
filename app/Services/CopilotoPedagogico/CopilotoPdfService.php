<?php

declare(strict_types=1);

namespace App\Services\CopilotoPedagogico;

use App\Models\InstituicaoEnsino;
use App\Models\PlanoAula;
use App\Models\PlanoAulaAdaptacao;
use App\Models\Unidade;
use Barryvdh\DomPDF\Facade\Pdf;

class CopilotoPdfService
{
    /**
     * Gera o conteúdo binário do PDF do Plano de Aula completo.
     */
    public function gerarPdfPlanoAula(PlanoAula $planoAula): string
    {
        $planoAula->loadMissing(['turma', 'disciplina', 'professor', 'habilidades']);

        $escolaNome = $this->obterNomeEscola();

        $pdf = Pdf::loadView('pdfs.plano-aula', [
            'planoAula' => $planoAula,
            'escolaNome' => $escolaNome,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        return $pdf->output();
    }

    /**
     * Gera o conteúdo binário do PDF confidencial da Adaptação PEI de um estudante.
     */
    public function gerarPdfPei(PlanoAulaAdaptacao $adaptacao): string
    {
        $adaptacao->loadMissing(['planoAula.turma', 'planoAula.disciplina', 'matricula.pessoa', 'geradoPor']);

        $escolaNome = $this->obterNomeEscola();
        $conteudo = is_array($adaptacao->conteudo) ? $adaptacao->conteudo : [];

        $pdf = Pdf::loadView('pdfs.plano-aula-pei', [
            'adaptacao' => $adaptacao,
            'conteudo' => $conteudo,
            'escolaNome' => $escolaNome,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        return $pdf->output();
    }

    protected function obterNomeEscola(): string
    {
        return Unidade::first()?->nome
            ?? InstituicaoEnsino::first()?->nome
            ?? config('app.name', 'Torre360');
    }
}

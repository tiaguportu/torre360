<?php

declare(strict_types=1);

namespace App\Services\Omr;

use App\Models\Avaliacao;
use App\Models\GabaritoFolha;
use App\Models\InstituicaoEnsino;
use App\Models\Unidade;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FolhaGabaritoPdfService
{
    public function __construct(
        protected QrCodeService $qrCodeService,
    ) {}

    /**
     * Retorna o nome oficial da escola para o cabeçalho.
     */
    protected function nomeEscola(): string
    {
        return Unidade::first()?->nome
            ?? InstituicaoEnsino::first()?->nome
            ?? (string) config('app.name', 'Torre 360');
    }

    /**
     * Gera o PDF binário de uma única folha de respostas.
     */
    public function gerarPdfFolha(GabaritoFolha $folha): string
    {
        return $this->gerarPdfColecao(collect([$folha]));
    }

    /**
     * Gera o PDF binário de uma folha avulsa / em branco para testes.
     */
    public function gerarPdfFolhaEmBranco(Avaliacao $avaliacao): string
    {
        $gabarito = $avaliacao->gabarito;
        if (! $gabarito) {
            throw new \InvalidArgumentException('A avaliação ainda não possui gabarito cadastrado.');
        }

        // Folha temporária em memória sem matrícula para impressão de teste
        $folha = new GabaritoFolha([
            'uuid' => (string) Str::uuid(),
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => null,
            'layout_versao' => $gabarito->layout_versao,
        ]);
        $folha->setRelation('avaliacao', $avaliacao);

        return $this->gerarPdfColecao(collect([$folha]));
    }

    /**
     * Gera o PDF de todas as folhas dos alunos ativos da turma de uma avaliação.
     */
    public function gerarPdfTurma(Avaliacao $avaliacao): string
    {
        $avaliacao->loadMissing(['turma.matriculas.pessoa', 'gabarito']);

        if (! $avaliacao->gabarito) {
            throw new \InvalidArgumentException('Cadastre e salve o gabarito oficial antes de gerar as folhas da turma.');
        }

        if ($avaliacao->nota_maxima === null || (float) $avaliacao->nota_maxima <= 0) {
            throw new \InvalidArgumentException('A avaliação precisa ter uma Nota Máxima definida e maior que zero para gerar o gabarito.');
        }

        $matriculasAtivas = $avaliacao->turma?->matriculas()
            ->where('situacao', 'ativa')
            ->with('pessoa')
            ->get() ?? collect();

        if ($matriculasAtivas->isEmpty()) {
            throw new \InvalidArgumentException('A turma não possui alunos com matrícula ativa.');
        }

        $folhas = collect();
        $user = auth()->user();

        foreach ($matriculasAtivas as $matricula) {
            // Reutiliza folha existente ou cria nova folha persistida com UUID
            $folha = GabaritoFolha::firstOrCreate(
                [
                    'avaliacao_id' => $avaliacao->id,
                    'matricula_id' => $matricula->id,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'layout_versao' => $avaliacao->gabarito->layout_versao,
                    'gerada_por_user_id' => $user?->id,
                ]
            );

            $folha->setRelation('avaliacao', $avaliacao);
            $folha->setRelation('matricula', $matricula);
            $folhas->push($folha);
        }

        return $this->gerarPdfColecao($folhas);
    }

    /**
     * Renderiza a coleção de folhas em um único arquivo PDF (uma folha por página A4).
     *
     * @param  Collection<int, GabaritoFolha>  $folhas
     */
    public function gerarPdfColecao(Collection $folhas): string
    {
        $nomeEscola = $this->nomeEscola();

        $itens = $folhas->map(function (GabaritoFolha $folha) use ($nomeEscola): array {
            $avaliacao = $folha->avaliacao ?? Avaliacao::findOrFail($folha->avaliacao_id);
            $avaliacao->loadMissing(['disciplina', 'turma', 'etapaAvaliativa', 'categoria', 'professor', 'gabarito']);

            $gabarito = $avaliacao->gabarito;
            $totalQuestoes = $gabarito?->total_questoes ?? 10;
            $alternativas = $gabarito?->alternativas ?? 5;
            $versao = $gabarito?->layout_versao ?? $folha->layout_versao;

            $layout = new GabaritoLayout(
                totalQuestoes: $totalQuestoes,
                alternativas: $alternativas,
                versao: $versao,
            );

            $qrDataUri = $this->qrCodeService->renderDataUri($folha->uuid);

            $matricula = $folha->matricula;
            $alunoNome = $matricula?->pessoa?->nome;
            $matriculaCodigo = $matricula?->numero_matricula ?? ($matricula ? 'MAT-'.$matricula->id : null);

            return [
                'folha' => $folha,
                'avaliacao' => $avaliacao,
                'layout' => $layout,
                'qrDataUri' => $qrDataUri,
                'escola_nome' => $nomeEscola,
                'aluno_nome' => $alunoNome,
                'matricula_codigo' => $matriculaCodigo,
            ];
        });

        $pdf = Pdf::loadView('pdfs.gabarito-folha', ['folhas' => $itens])
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        return (string) $pdf->output();
    }
}

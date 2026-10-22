<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CancelamentoMatricula;
use App\Models\Fatura;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * PDF do distrato de uma matrícula, para imprimir e assinar. Existe só depois da aprovação (é quando as decisões e os
 * valores ficam definidos) e abre para a equipe com `View:CancelamentoMatricula` e para a família do aluno. Depois de
 * concluído continua igual: lê as faturas pelas decisões gravadas, mesmo as já canceladas.
 */
class DistratoMatriculaPdfController extends Controller
{
    public function show(CancelamentoMatricula $cancelamento): Response
    {
        /** @var User $user */
        $user = auth()->user();

        $cancelamento->load(['matricula.pessoa', 'matricula.turma.serie.curso.unidade.instituicaoEnsino', 'matricula.turma.periodoLetivo', 'matricula.contrato.responsaveisFinanceiros.pessoa']);

        abort_unless($cancelamento->isAccessibleBy($user), 403);
        abort_unless($cancelamento->temDistrato(), 404, 'O distrato só existe depois que a escola aprova o pedido.');

        $matricula = $cancelamento->matricula;
        $decisoes = $cancelamento->decisoes ?? [];

        $faturas = $decisoes === []
            ? collect()
            : Fatura::query()
                ->whereIn('id', array_keys($decisoes))
                ->with(['itens', 'transacoes'])
                ->orderBy('vencimento')
                ->get();

        $unidade = $matricula->turma?->serie?->curso?->unidade;

        return Pdf::loadView('pdfs.distrato-matricula', [
            'cancelamento' => $cancelamento,
            'matricula' => $matricula,
            'contrato' => $matricula->contrato,
            'escola' => $unidade?->instituicaoEnsino?->nome ?? $unidade?->nome ?? config('app.name'),
            'faturas' => $faturas,
            'decisoes' => $decisoes,
            'emissao' => today(),
            'prazoMulta' => today()->addDays((int) config('cancelamento.multa_vencimento_dias', 10)),
        ])
            ->setPaper('a4')
            ->stream("distrato-matricula-{$cancelamento->id}.pdf");
    }
}

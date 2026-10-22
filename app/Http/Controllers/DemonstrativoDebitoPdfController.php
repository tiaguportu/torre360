<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Matricula;
use App\Models\User;
use App\Services\EncargosAtrasoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * PDF do demonstrativo de débito de uma matrícula: as faturas em atraso com a multa e os juros de cada uma até hoje.
 * Abre para a família do aluno (ou o responsável financeiro) e para quem tem `View:Fatura`; o professor não entra só por
 * ser da equipe. A emissão por terceiros fica no log (sem os valores).
 */
class DemonstrativoDebitoPdfController extends Controller
{
    public function show(Matricula $matricula, EncargosAtrasoService $encargos): Response
    {
        /** @var User $user */
        $user = auth()->user();

        $equipe = $user->can('View:Fatura');

        abort_unless($equipe || $matricula->isAccessibleByFamilia($user), 403);

        $matricula->load(['pessoa', 'turma.serie.curso.unidade.instituicaoEnsino', 'contrato.responsaveisFinanceiros.pessoa']);

        abort_unless($matricula->contrato, 404, 'Esta matrícula não tem contrato.');

        $hoje = today();
        $linhas = $encargos->linhasDoContrato($matricula->contrato, $hoje);

        if ($equipe && ! $matricula->isAccessibleByFamilia($user)) {
            activity('financeiro')
                ->causedBy($user)
                ->performedOn($matricula)
                ->withProperties(['faturas' => $linhas->count()])
                ->log('Demonstrativo de débito emitido.');
        }

        $unidade = $matricula->turma?->serie?->curso?->unidade;

        return Pdf::loadView('pdfs.demonstrativo-debito', [
            'matricula' => $matricula,
            'escola' => $unidade?->instituicaoEnsino?->nome ?? $unidade?->nome ?? config('app.name'),
            'linhas' => $linhas,
            'totais' => $encargos->totais($linhas),
            'parametros' => $encargos->parametros($matricula->contrato),
            'emissao' => $hoje,
        ])
            ->setPaper('a4')
            ->stream("demonstrativo-debito-{$matricula->id}.pdf");
    }
}

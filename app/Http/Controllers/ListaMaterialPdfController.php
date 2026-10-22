<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ListaMaterialEscolar;
use App\Models\User;
use App\Services\ListaMaterialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * PDF da lista de material escolar. A equipe com `View:ListaMaterialEscolar` abre qualquer lista (inclusive rascunho,
 * marcado como tal); a família só abre a lista publicada da série e do período de algum dos seus alunos.
 */
class ListaMaterialPdfController extends Controller
{
    public function show(ListaMaterialEscolar $lista, ListaMaterialService $servico): Response
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($user->can('View:ListaMaterialEscolar') || $servico->familiaPodeVer($user, $lista), 403);

        $lista->load(['itens', 'serie.curso.unidade.instituicaoEnsino', 'periodoLetivo']);

        $unidade = $lista->serie?->curso?->unidade;

        return Pdf::loadView('pdfs.lista-material', [
            'lista' => $lista,
            'grupos' => $servico->itensPorCategoria($lista),
            'escola' => $unidade?->instituicaoEnsino?->nome ?? $unidade?->nome ?? config('app.name'),
        ])
            ->setPaper('a4')
            ->stream("lista-material-{$lista->id}.pdf");
    }
}

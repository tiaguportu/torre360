<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PortfolioItem;
use App\Models\User;
use App\Support\TiposArquivo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega os anexos do portfólio, guardados no disco privado. Cada acesso é autorizado:
 * - equipe: pela `PortfolioItemPolicy` (recorte do professor incluído);
 * - família: só o item COMPARTILHADO de um aluno acessível ao usuário (ele mesmo ou dependente).
 * O tipo é o detectado no arquivo; só imagem e PDF abrem no navegador, o resto baixa.
 */
class PortfolioAnexoController extends Controller
{
    public function show(PortfolioItem $item, int $indice): StreamedResponse
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($this->podeAcessar($user, $item), 403);

        $caminho = $item->caminhoDoAnexo($indice);
        abort_if($caminho === null || ! Storage::disk('local')->exists($caminho), 404);

        $mime = (string) (Storage::disk('local')->mimeType($caminho) ?: 'application/octet-stream');
        $inline = in_array($mime, TiposArquivo::exibiveisNoNavegador(), true);

        return Storage::disk('local')->response($caminho, basename($caminho), [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.basename($caminho).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function podeAcessar(User $user, PortfolioItem $item): bool
    {
        if (Gate::forUser($user)->allows('view', $item)) {
            return true;
        }

        if (! $item->visivel_familia || $item->mostra_outros_alunos) {
            return false;
        }

        return $user->pessoasAcessiveis()->pluck('id')->contains($item->matricula?->pessoa_id);
    }
}

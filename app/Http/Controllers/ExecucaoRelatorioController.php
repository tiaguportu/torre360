<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExecucaoRelatorio;
use App\Services\Relatorios\CatalogoDeRelatorios;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baixa o arquivo de uma execução de relatório (Onda 72). Quem baixa precisa ver o relatório (é o dono, está entre os
 * destinatários ou tem `VerTodos`) e ainda ter a permissão da fonte de dados: perder o acesso aos dados fecha o arquivo,
 * mesmo para o dono. O conteúdo é conferido contra a impressão digital guardada.
 */
class ExecucaoRelatorioController extends Controller
{
    public function download(ExecucaoRelatorio $execucao, CatalogoDeRelatorios $catalogo): Response
    {
        $usuario = auth()->user();
        $relatorio = $execucao->relatorio;

        abort_unless($usuario && $relatorio, 403, 'Sem permissão para este relatório.');

        $fonte = $catalogo->da($relatorio->fonte);

        $veRelatorio = $relatorio->criado_por_user_id === $usuario->id
            || in_array($usuario->id, array_map('intval', (array) $relatorio->destinatarios), true)
            || $usuario->can('VerTodos:RelatorioSalvo');

        abort_unless($veRelatorio && $fonte && $catalogo->usuarioPode($usuario, $fonte), 403, 'Sem permissão para este relatório.');
        abort_unless($execucao->temArquivo(), 404, 'O arquivo desta execução não está mais disponível. Gere o relatório de novo.');

        $disco = Storage::disk('local');

        abort_unless($disco->exists((string) $execucao->arquivo_path), 404, 'O arquivo desta execução não está mais disponível. Gere o relatório de novo.');

        $conteudo = $disco->get((string) $execucao->arquivo_path);

        abort_unless(hash_equals((string) $execucao->arquivo_hash, hash('sha256', $conteudo)), 409, 'O arquivo guardado foi alterado depois de gerado. Gere o relatório de novo.');

        activity('relatorios')->performedOn($relatorio)->causedBy($usuario)->withProperties(['execucao' => $execucao->id])->log('Arquivo do relatório baixado.');

        $nome = str($relatorio->nome)->slug()->limit(60, '')->toString() ?: 'relatorio';

        return response($conteudo, 200, [
            'Content-Type' => $execucao->formato->mime(),
            'Content-Disposition' => 'attachment; filename="'.$nome.'-'.$execucao->created_at->format('Ymd-Hi').'.'.$execucao->formato->value.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

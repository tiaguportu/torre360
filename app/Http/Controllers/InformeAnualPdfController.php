<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Matricula;
use App\Models\User;
use App\Services\InformeAnualMensalidadesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * PDF do informe anual de mensalidades pagas. Por aluno: a família do aluno ou quem tem `GerarInformeAnual:Fatura`.
 * Em lote (por turma): só a equipe com a permissão. Cada emissão por terceiros fica no log (sem os valores).
 */
class InformeAnualPdfController extends Controller
{
    public function aluno(int $ano, Matricula $matricula, InformeAnualMensalidadesService $informes): Response
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($this->anoValido($ano), 404);
        abort_unless($informes->podeVer($user, $matricula), 403);

        $linhas = $informes->gerar($ano, [$matricula->id]);

        $this->registrar($user, $ano, ['matricula_id' => $matricula->id]);

        return Pdf::loadView('informes.mensalidades-anual', ['informes' => $linhas, 'ano' => $ano, 'alunoSemPagamento' => $linhas->isEmpty() ? $matricula->loadMissing('pessoa') : null])
            ->setPaper('a4')
            ->stream("informe-mensalidades-{$ano}-{$matricula->id}.pdf");
    }

    public function lote(int $ano, Request $request, InformeAnualMensalidadesService $informes): Response
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($this->anoValido($ano), 404);
        abort_unless($user->can('GerarInformeAnual:Fatura'), 403);

        $turmaId = $request->integer('turma') ?: null;
        $busca = $request->string('busca')->toString() ?: null;
        $limite = (int) config('financeiro.informe_anual.limite_lote', 300);

        $linhas = $informes->gerar($ano, null, $turmaId, $busca);

        abort_if($linhas->count() > $limite, 422, "O lote tem {$linhas->count()} alunos; o limite é {$limite}. Filtre por turma.");

        $this->registrar($user, $ano, ['turma_id' => $turmaId, 'busca' => $busca, 'alunos' => $linhas->count()]);

        return Pdf::loadView('informes.mensalidades-anual', ['informes' => $linhas, 'ano' => $ano, 'alunoSemPagamento' => null])
            ->setPaper('a4')
            ->stream("informes-mensalidades-{$ano}.pdf");
    }

    private function anoValido(int $ano): bool
    {
        return $ano >= 2000 && $ano <= (int) today()->format('Y');
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private function registrar(User $user, int $ano, array $propriedades): void
    {
        if (! $user->can('GerarInformeAnual:Fatura')) {
            return; // a própria família emitindo o seu informe não precisa de trilha
        }

        activity('informe_anual')
            ->causedBy($user)
            ->withProperties(['ano' => $ano] + $propriedades)
            ->log('Informe anual de mensalidades emitido.');
    }
}

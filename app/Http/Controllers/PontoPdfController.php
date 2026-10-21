<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Funcionario;
use App\Models\PontoConfiguracao;
use App\Models\PontoRegistro;
use App\Models\User;
use App\Services\Ponto\ComprovantePontoService;
use App\Services\Ponto\PontoApuracaoService;
use App\Support\FuncionarioDoUsuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDFs do ponto: comprovante de uma marcação e espelho mensal. Ponto é dado pessoal do trabalhador, então cada PDF só
 * sai para o próprio trabalhador ou para quem tem permissão sobre o de todos; o acesso a dados de outra pessoa fica no
 * log de atividades (sem o conteúdo).
 */
class PontoPdfController extends Controller
{
    public function comprovante(PontoRegistro $registro, ComprovantePontoService $comprovantes): Response
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless(Gate::forUser($user)->allows('view', $registro), 403);

        $registro->loadMissing('funcionario');
        $this->registrarAcessoDeTerceiro($user, $registro->funcionario, 'comprovante');

        return Pdf::loadView('ponto.comprovante', ['comprovante' => $comprovantes->dados($registro)])
            ->setPaper('a5')
            ->stream("comprovante-ponto-nsr-{$registro->nsr}.pdf");
    }

    public function espelho(Funcionario $funcionario, int $ano, int $mes, PontoApuracaoService $apuracao): Response
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless(Gate::forUser($user)->allows('verEspelho', [PontoRegistro::class, $funcionario]), 403);
        abort_unless($mes >= 1 && $mes <= 12 && $ano >= 2000 && $ano <= 2100, 404);

        $this->registrarAcessoDeTerceiro($user, $funcionario, 'espelho');

        $funcionario->loadMissing(['pessoa', 'unidade']);

        return Pdf::loadView('ponto.espelho', [
            'funcionario' => $funcionario,
            'espelho' => $apuracao->espelho($funcionario, $ano, $mes),
        ])->setPaper('a4', 'landscape')->stream(sprintf('espelho-ponto-%d-%02d-%d.pdf', $funcionario->id, $mes, $ano));
    }

    /**
     * Atestado Técnico e Termo de Responsabilidade (PDF no disco privado), só para quem pode ver a configuração.
     */
    public function atestado(PontoConfiguracao $configuracao): StreamedResponse
    {
        abort_unless(Gate::forUser(auth()->user())->allows('view', $configuracao), 403);
        abort_if(blank($configuracao->atestado_tecnico_path) || ! Storage::disk('local')->exists($configuracao->atestado_tecnico_path), 404);

        return Storage::disk('local')->response($configuracao->atestado_tecnico_path, 'atestado-tecnico-ponto.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="atestado-tecnico-ponto.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function registrarAcessoDeTerceiro(User $user, Funcionario $funcionario, string $documento): void
    {
        if (FuncionarioDoUsuario::ehOProprio($user, $funcionario)) {
            return;
        }

        activity('ponto')
            ->causedBy($user)
            ->withProperties(['funcionario_id' => $funcionario->id, 'documento' => $documento])
            ->log("Consulta ao {$documento} de ponto de outro funcionário.");
    }
}

<?php

namespace App\Http\Controllers\Financeiro;

use App\Enums\StatusAcordoInadimplencia;
use App\Http\Controllers\Controller;
use App\Models\AcordoInadimplencia;
use App\Services\AcordoInadimplenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AcordoPublicoController extends Controller
{
    public function show(string $token, AcordoInadimplenciaService $service): View
    {
        $acordo = AcordoInadimplencia::with(['matricula.pessoa', 'responsavelPessoa', 'parcelas'])
            ->where('token_publico', $token)
            ->firstOrFail();

        return view('financeiro.acordo-publico', [
            'acordo' => $acordo,
            'termo' => $service->termoParaExibicao($acordo),
        ]);
    }

    public function aceitar(Request $request, string $token, AcordoInadimplenciaService $service): RedirectResponse
    {
        $request->validate([
            'concordo' => ['required'],
            'cpf' => ['required', 'string'],
        ], [
            'concordo.required' => 'É necessário concordar expressamente com os termos da confissão de dívida.',
            'cpf.required' => 'Informe o CPF do titular para autenticação da assinatura digital.',
        ]);

        return DB::transaction(function () use ($request, $token, $service) {
            $acordo = AcordoInadimplencia::with('responsavelPessoa')
                ->where('token_publico', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if ($acordo->estaExpirado()) {
                return redirect()->route('acordo.publico.show', ['token' => $token])
                    ->with('mensagem_erro', 'Esta proposta de renegociação expirou o prazo limite de validade. Entre em contato com a secretaria da escola.');
            }

            if ($acordo->status !== StatusAcordoInadimplencia::AguardandoAceite && $acordo->status !== StatusAcordoInadimplencia::Simulado) {
                return redirect()->route('acordo.publico.show', ['token' => $token])
                    ->with('mensagem_erro', 'Este acordo já foi homologado ou não está mais disponível para aceite online.');
            }

            // Validação de Identidade do Responsável Financeiro (Lei 14.063/2020)
            $cpfDigitado = preg_replace('/\D/', '', (string) $request->input('cpf'));
            $cpfResponsavel = preg_replace('/\D/', '', (string) ($acordo->responsavelPessoa?->cpf ?? ''));

            if ($cpfResponsavel !== '' && $cpfDigitado !== $cpfResponsavel) {
                return redirect()->route('acordo.publico.show', ['token' => $token])
                    ->withInput()
                    ->with('mensagem_erro', 'O CPF informado não confere com o cadastro do responsável financeiro desta proposta.');
            }

            $service->confirmarAceite(
                $acordo,
                $request->ip(),
                $request->userAgent(),
                $cpfDigitado ?: null
            );

            return redirect()->route('acordo.publico.show', ['token' => $token])
                ->with('mensagem_sucesso', 'Acordo aceito com sucesso! O cronograma de pagamentos foi confirmado e o termo de confissão de dívida foi lavrado.');
        });
    }
}

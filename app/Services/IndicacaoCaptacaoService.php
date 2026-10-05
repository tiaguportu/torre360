<?php

namespace App\Services;

use App\Models\IndicacaoInteressado;
use App\Models\Interessado;
use App\Models\Pessoa;
use Illuminate\Http\Request;

/**
 * Programa "Família Indica Família" no formulário público de captação: o link de indicação
 * (`/quero-matricular?indicacao=CODIGO`, gerado por `Pessoa::linkIndicacao()`) fica guardado na sessão
 * e, ao enviar o formulário, vincula o novo lead à família que indicou (status "pendente", como a
 * secretaria faria manualmente). Segue a mesma ideia do `UtmTracker`.
 */
class IndicacaoCaptacaoService
{
    private const SESSION_KEY = 'captacao_indicacao';

    /**
     * Guarda na sessão o código de indicação presente na query string. Código com formato inválido
     * é descartado sem erro: a página pública continua funcionando normalmente.
     */
    public function capturar(Request $request): void
    {
        $codigo = self::normalizarCodigo($request->query('indicacao'));

        if ($codigo !== null) {
            $request->session()->put(self::SESSION_KEY, $codigo);
        }
    }

    /**
     * Código de indicação do envio: o enviado no próprio POST tem prioridade sobre o da sessão.
     */
    public function codigoDaRequisicao(Request $request): ?string
    {
        return self::normalizarCodigo($request->input('indicacao'))
            ?? self::normalizarCodigo($request->session()->get(self::SESSION_KEY));
    }

    /**
     * Família que indicou, a partir do código — ou null se o código não existe ou se for uma
     * auto-indicação (mesma pessoa, e-mail, CPF ou telefone), que não pode render recompensa.
     */
    public function localizarIndicador(?string $codigo, Pessoa $leadPessoa): ?Pessoa
    {
        if ($codigo === null) {
            return null;
        }

        $indicador = Pessoa::query()->where('codigo_indicacao', $codigo)->first();

        if (! $indicador || $this->ehAutoIndicacao($indicador, $leadPessoa)) {
            return null;
        }

        return $indicador;
    }

    /**
     * Vincula o lead à indicação (uma por lead: a primeira vale). Idempotente: reenviar o formulário
     * não duplica o registro nem troca quem indicou.
     */
    public function registrar(Interessado $interessado, Pessoa $indicador, string $codigo): ?IndicacaoInteressado
    {
        if ($interessado->indicacao()->exists()) {
            return null;
        }

        return IndicacaoInteressado::create([
            'indicador_pessoa_id' => $indicador->id,
            'interessado_id' => $interessado->id,
            'codigo_indicacao' => $codigo,
            'status' => IndicacaoInteressado::STATUS_PENDENTE,
        ]);
    }

    public function esquecer(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Códigos gerados por `IndicacaoInteressado::gerarCodigoParaPessoa()` seguem `PREFIXO-XXXX`
     * (maiúsculas e dígitos). Aceita só esse alfabeto, para a query nunca carregar texto arbitrário.
     */
    public static function normalizarCodigo(mixed $codigo): ?string
    {
        if (! is_string($codigo)) {
            return null;
        }

        $codigo = strtoupper(trim($codigo));

        return preg_match('/^[A-Z0-9][A-Z0-9-]{2,29}$/', $codigo) === 1 ? $codigo : null;
    }

    private function ehAutoIndicacao(Pessoa $indicador, Pessoa $lead): bool
    {
        if ($indicador->is($lead)) {
            return true;
        }

        if (filled($indicador->email) && filled($lead->email) && mb_strtolower($indicador->email) === mb_strtolower($lead->email)) {
            return true;
        }

        if (filled($indicador->cpf) && filled($lead->cpf) && $indicador->cpf === $lead->cpf) {
            return true;
        }

        $telefoneIndicador = $this->ultimosDigitos($indicador->telefone);
        $telefoneLead = $this->ultimosDigitos($lead->telefone);

        return $telefoneIndicador !== null && $telefoneIndicador === $telefoneLead;
    }

    /**
     * Últimos 11 dígitos (DDD + celular), para comparar telefones com ou sem máscara/DDI.
     */
    private function ultimosDigitos(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone) ?? '';

        return strlen($digitos) >= 10 ? substr($digitos, -11) : null;
    }
}

<?php

namespace App\Services;

use App\Models\CampanhaMarketing;
use Illuminate\Http\Request;

/**
 * Guarda os parâmetros UTM da visita na sessão (para sobreviver à navegação
 * entre páginas públicas) e os converte em atribuição de campanha ao criar o lead.
 */
class UtmTracker
{
    private const SESSION_KEY = 'captacao_utm';

    private const PARAMETROS = ['utm_source', 'utm_medium', 'utm_campaign'];

    /**
     * Registra na sessão os UTM presentes na query string. Uma nova visita com UTM
     * sobrescreve a anterior (last touch dentro da mesma sessão).
     */
    public static function capturar(Request $request): void
    {
        $utm = self::extrair($request->query());

        if ($utm !== []) {
            $request->session()->put(self::SESSION_KEY, $utm);
        }
    }

    /**
     * Atribuição do lead: UTM enviados no próprio request têm prioridade sobre os da sessão.
     * Inclui `campanha_marketing_id` quando `utm_campaign` corresponde a uma campanha ativa.
     *
     * @return array<string, mixed>
     */
    public static function atribuicao(Request $request): array
    {
        $utm = array_merge(
            (array) $request->session()->get(self::SESSION_KEY, []),
            self::extrair($request->input()),
        );

        if ($utm === []) {
            return [];
        }

        $campanha = CampanhaMarketing::porCodigoUtm($utm['utm_campaign'] ?? null);

        if ($campanha) {
            $utm['campanha_marketing_id'] = $campanha->id;
        }

        return $utm;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, string>
     */
    private static function extrair(array $dados): array
    {
        $utm = [];

        foreach (self::PARAMETROS as $parametro) {
            $valor = $dados[$parametro] ?? null;

            if (is_string($valor) && trim($valor) !== '') {
                $utm[$parametro] = mb_substr(mb_strtolower(trim($valor)), 0, 191);
            }
        }

        return $utm;
    }
}

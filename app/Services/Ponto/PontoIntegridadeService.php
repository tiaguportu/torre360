<?php

declare(strict_types=1);

namespace App\Services\Ponto;

use App\Models\PontoConfiguracao;
use App\Models\PontoRegistro;

/**
 * Percorre a cadeia de registros de uma unidade e aponta qualquer quebra: número (NSR) faltando ou repetido, encadeamento
 * rompido, conteúdo que não bate com o hash gravado e registros removidos do fim da cadeia.
 */
class PontoIntegridadeService
{
    /**
     * @return array{ok: bool, total: int, quebras: list<array{nsr: ?int, tipo: string, detalhe: string}>}
     */
    public function verificar(int $unidadeId): array
    {
        $quebras = [];
        $total = 0;
        $nsrEsperado = 1;
        $hashAnterior = null;

        PontoRegistro::query()
            ->where('unidade_id', $unidadeId)
            ->orderBy('nsr')
            ->chunk(500, function ($registros) use (&$quebras, &$total, &$nsrEsperado, &$hashAnterior): void {
                foreach ($registros as $registro) {
                    $total++;

                    if ($registro->nsr !== $nsrEsperado) {
                        $quebras[] = ['nsr' => $registro->nsr, 'tipo' => 'lacuna_nsr', 'detalhe' => "Esperado o NSR {$nsrEsperado}, encontrado {$registro->nsr}."];
                    }

                    if ($registro->hash_anterior !== $hashAnterior) {
                        $quebras[] = ['nsr' => $registro->nsr, 'tipo' => 'encadeamento', 'detalhe' => 'O registro não aponta para o hash do registro anterior.'];
                    }

                    if (! $registro->hashConfere()) {
                        $quebras[] = ['nsr' => $registro->nsr, 'tipo' => 'hash_invalido', 'detalhe' => 'O conteúdo do registro não corresponde ao hash gravado.'];
                    }

                    $nsrEsperado = $registro->nsr + 1;
                    $hashAnterior = $registro->hash;
                }
            });

        // Registros apagados do fim da cadeia só aparecem comparando com a ponta guardada na configuração.
        $config = PontoConfiguracao::query()->where('unidade_id', $unidadeId)->first();

        if ($config && ($config->ultimo_nsr !== $nsrEsperado - 1 || $config->hash_ultimo_registro !== $hashAnterior)) {
            $quebras[] = [
                'nsr' => null,
                'tipo' => 'cauda',
                'detalhe' => "A configuração indica o NSR {$config->ultimo_nsr} como último, mas a cadeia termina em ".($nsrEsperado - 1).'. Pode haver registros removidos.',
            ];
        }

        return ['ok' => $quebras === [], 'total' => $total, 'quebras' => $quebras];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\NivelAssinatura;

/**
 * Lê num PDF o que ele diz sobre assinatura digital (Onda 74): quantas assinaturas (dicionários `/Type /Sig` com
 * `/ByteRange`), de que formato (PAdES e variantes) e se o certificado embutido tem marcas da ICP-Brasil (o nome
 * "ICP-Brasil", a "Autoridade Certificadora Raiz Brasileira" ou o OID 2.16.76.1).
 *
 * É uma LEITURA do arquivo, não uma validação: o inspetor **não confere** a integridade do conteúdo assinado, a cadeia de
 * certificação, a revogação nem a data de validade do certificado. Quem precisa dessa garantia usa o Verificador de
 * Conformidade do ITI (validar.iti.gov.br) ou o validador do próprio assinador. O sistema só registra o que encontrou, e
 * diz isso na tela.
 */
class AssinaturaPdfInspector
{
    /** Quantas assinaturas no máximo o inspetor lê num arquivo (cada uma traz alguns KB de certificados). */
    private const LIMITE_ASSINATURAS = 20;

    /**
     * @return array{assinaturas: int, formatos: list<string>, icp_brasil: bool, nivel: ?NivelAssinatura}
     */
    public function inspecionar(string $pdf): array
    {
        if (! str_starts_with(ltrim($pdf), '%PDF')) {
            return ['assinaturas' => 0, 'formatos' => [], 'icp_brasil' => false, 'nivel' => null];
        }

        $quantidade = preg_match_all('#/ByteRange\s*\[\s*\d+\s+\d+\s+\d+\s+\d+\s*\]#', $pdf);
        $quantidade = $quantidade === false ? 0 : $quantidade;

        if ($quantidade === 0) {
            return ['assinaturas' => 0, 'formatos' => [], 'icp_brasil' => false, 'nivel' => null];
        }

        preg_match_all('#/SubFilter\s*/([A-Za-z0-9_.\-]+)#', $pdf, $formatos);

        $icp = false;

        if (preg_match_all('#/Contents\s*<([0-9A-Fa-f\s]+)>#', $pdf, $conteudos)) {
            foreach (array_slice($conteudos[1], 0, self::LIMITE_ASSINATURAS) as $hex) {
                $binario = @hex2bin((string) preg_replace('/\s+/', '', $hex));

                if ($binario !== false && $this->temMarcaDaIcp($binario)) {
                    $icp = true;

                    break;
                }
            }
        }

        return [
            'assinaturas' => $quantidade,
            'formatos' => array_values(array_unique($formatos[1] ?? [])),
            'icp_brasil' => $icp,
            'nivel' => $icp ? NivelAssinatura::QualificadaIcp : NivelAssinatura::Digital,
        ];
    }

    public function nivelDe(string $pdf): ?NivelAssinatura
    {
        return $this->inspecionar($pdf)['nivel'];
    }

    /**
     * O PDF assinado começa com o PDF que o sistema emitiu? As ferramentas de assinatura costumam acrescentar a assinatura
     * ao fim do arquivo (atualização incremental), deixando os bytes originais intactos. Quando não começa, pode ser que a
     * ferramenta tenha regravado o arquivo (e o documento continua o mesmo) ou que seja outro documento: o sistema avisa
     * para a pessoa conferir, sem recusar.
     */
    public function contemOriginal(string $assinado, string $original): bool
    {
        $original = rtrim($original);

        return $original !== '' && strncmp($assinado, $original, strlen($original)) === 0;
    }

    private function temMarcaDaIcp(string $binario): bool
    {
        return stripos($binario, 'ICP-Brasil') !== false
            || stripos($binario, 'Autoridade Certificadora Raiz Brasileira') !== false
            // OID 2.16.76.1 (ICP-Brasil) em DER: tag 0x06, tamanho, 60 4C 01.
            || preg_match('/\x06[\x03-\x10]\x60\x4C\x01/', $binario) === 1;
    }
}

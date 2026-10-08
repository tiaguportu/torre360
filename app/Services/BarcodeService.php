<?php

namespace App\Services;

class BarcodeService
{
    /**
     * Tabela de padrões Code 128B (valores de 0 a 106).
     * Cada sequência representa as larguras das 6 barras/espaços alternados.
     */
    protected static array $patterns = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '233111',
    ];

    /**
     * Gera um código de barras no formato SVG (Code 128B).
     */
    public function gerarSvg(string $texto, int $altura = 50, float $larguraBarra = 2.0): string
    {
        $limpo = trim($texto);
        if ($limpo === '') {
            $limpo = '00000';
        }

        $valores = [104]; // Start Code B (104)
        $len = strlen($limpo);
        for ($i = 0; $i < $len; $i++) {
            $ascii = ord($limpo[$i]);
            $valores[] = $ascii - 32;
        }

        // Calcula checksum: (start + sum(valor * index)) % 103
        $soma = $valores[0];
        for ($i = 1; $i <= $len; $i++) {
            $soma += $valores[$i] * $i;
        }
        $check = $soma % 103;
        $valores[] = $check;
        $valores[] = 106; // Stop Code (106)

        // Converte valores para padrão de larguras
        $barras = '';
        foreach ($valores as $v) {
            $pattern = self::$patterns[$v] ?? self::$patterns[0];
            $barras .= $pattern;
        }
        $barras .= '2'; // Barra de terminação do stop code

        // Renderiza o SVG
        $x = 0.0;
        $rects = '';
        $barraLen = strlen($barras);
        for ($i = 0; $i < $barraLen; $i++) {
            $w = ((int) $barras[$i]) * $larguraBarra;
            $isBarra = ($i % 2 === 0);
            if ($isBarra) {
                $rects .= sprintf('<rect x="%.2f" y="0" width="%.2f" height="%d" fill="#000000" />', $x, $w, $altura);
            }
            $x += $w;
        }

        $larguraTotal = $x;

        return sprintf(
            '<svg viewBox="0 0 %.2f %d" width="%.2f" height="%d" xmlns="http://www.w3.org/2000/svg" style="display:block;margin:0 auto;max-width:100%%;">%s</svg>',
            $larguraTotal,
            $altura,
            $larguraTotal,
            $altura,
            $rects
        );
    }
}

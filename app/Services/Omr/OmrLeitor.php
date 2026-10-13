<?php

declare(strict_types=1);

namespace App\Services\Omr;

use App\Enums\EstadoLeituraQuestao;
use GdImage;

class OmrLeitor
{
    /**
     * Executa a medição de preenchimento de cada bolha e decide as respostas.
     *
     * @return array<int, array{
     *     numero: int,
     *     alternativa: ?string,
     *     estado: EstadoLeituraQuestao,
     *     densidades: array<string, float>,
     *     confianca: float
     * }>
     */
    public function lerRespostas(
        GdImage $imagem,
        HomografiaService $homografia,
        GabaritoLayout $layout,
    ): array {
        $limiarMarcada = (float) config('omr.limiar_marcada', 0.42);
        $limiarVazia = (float) config('omr.limiar_vazia', 0.18);

        $grade = $layout->coordenadasBolhas();
        $imgW = imagesx($imagem);
        $imgH = imagesy($imagem);

        $respostas = [];

        foreach ($grade as $numero => $alternativas) {
            $densidades = [];

            foreach ($alternativas as $letra => $coordMm) {
                $pontoPx = $homografia->mapearPonto($coordMm['x'], $coordMm['y']);
                $raioPx = $homografia->mapearRaio($coordMm['raio']);

                $densidade = $this->medirDensidadeBolha(
                    $imagem,
                    $pontoPx['x'],
                    $pontoPx['y'],
                    $raioPx,
                    $imgW,
                    $imgH
                );

                $densidades[$letra] = round($densidade, 3);
            }

            // Análise das densidades de cada alternativa
            $marcadas = [];
            $ambiguas = [];

            foreach ($densidades as $letra => $densidade) {
                if ($densidade >= $limiarMarcada) {
                    $marcadas[] = $letra;
                } elseif ($densidade >= $limiarVazia) {
                    $ambiguas[] = $letra;
                }
            }

            $totalMarcadas = count($marcadas);
            $totalAmbiguas = count($ambiguas);

            if ($totalMarcadas === 1 && $totalAmbiguas === 0) {
                $altEscolhida = $marcadas[0];
                $estado = EstadoLeituraQuestao::Valida;
                $confianca = min(1.0, ($densidades[$altEscolhida] - $limiarVazia) / ($limiarMarcada - $limiarVazia));
            } elseif ($totalMarcadas === 0 && $totalAmbiguas === 0) {
                $altEscolhida = null;
                $estado = EstadoLeituraQuestao::EmBranco;
                $confianca = 1.0;
            } elseif ($totalMarcadas >= 2) {
                $altEscolhida = null;
                $estado = EstadoLeituraQuestao::Dupla;
                $confianca = 0.9;
            } elseif ($totalMarcadas === 1 && $totalAmbiguas >= 1) {
                $altMarcada = $marcadas[0];
                // Se a marcada for muito mais densa que a ambígua (ex: rasura leve), considera a marcada
                $maiorAmbigua = max(array_map(fn ($l) => $densidades[$l], $ambiguas));
                if (($densidades[$altMarcada] - $maiorAmbigua) > 0.20) {
                    $altEscolhida = $altMarcada;
                    $estado = EstadoLeituraQuestao::Valida;
                    $confianca = 0.75;
                } else {
                    $altEscolhida = null;
                    $estado = EstadoLeituraQuestao::Dupla;
                    $confianca = 0.5;
                }
            } elseif ($totalMarcadas === 0 && $totalAmbiguas === 1) {
                $altEscolhida = $ambiguas[0];
                $estado = EstadoLeituraQuestao::Ambigua;
                $confianca = 0.5;
            } else {
                $altEscolhida = null;
                $estado = EstadoLeituraQuestao::Ambigua;
                $confianca = 0.3;
            }

            $respostas[$numero] = [
                'numero' => $numero,
                'alternativa' => $altEscolhida,
                'estado' => $estado,
                'densidades' => $densidades,
                'confianca' => round($confianca, 2),
            ];
        }

        return $respostas;
    }

    /**
     * Mede a fração de pixels escuros preenchidos no interior da bolha circular.
     */
    protected function medirDensidadeBolha(
        GdImage $img,
        float $cx,
        float $cy,
        float $raioPx,
        int $imgW,
        int $imgH,
    ): float {
        // Amostra o fundo de controle local (logo acima da bolha)
        $fundoLuminancia = $this->amostrarFundoLocal($img, $cx, $cy - ($raioPx * 1.6), $imgW, $imgH);

        // Raio interno de amostragem (80% do raio para ignorar a linha de contorno impressa)
        $raioAmostragem = max(1.5, $raioPx * 0.80);
        $raioQuadrado = $raioAmostragem * $raioAmostragem;

        $totalAmostras = 0;
        $pixelsEscuros = 0;

        $minX = (int) floor($cx - $raioAmostragem);
        $maxX = (int) ceil($cx + $raioAmostragem);
        $minY = (int) floor($cy - $raioAmostragem);
        $maxY = (int) ceil($cy + $raioAmostragem);

        // Limiar dinâmico adaptado ao fundo da folha
        $limiarEscuro = min(130.0, $fundoLuminancia * 0.70);

        for ($y = $minY; $y <= $maxY; $y++) {
            if ($y < 0 || $y >= $imgH) {
                continue;
            }

            $dy = $y - $cy;
            $dy2 = $dy * $dy;

            for ($x = $minX; $x <= $maxX; $x++) {
                if ($x < 0 || $x >= $imgW) {
                    continue;
                }

                $dx = $x - $cx;
                if (($dx * $dx) + $dy2 <= $raioQuadrado) {
                    $rgb = imagecolorat($img, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $luminancia = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);

                    if ($luminancia < $limiarEscuro) {
                        $pixelsEscuros++;
                    }
                    $totalAmostras++;
                }
            }
        }

        if ($totalAmostras === 0) {
            return 0.0;
        }

        return $pixelsEscuros / $totalAmostras;
    }

    /**
     * Mede a luminância média do papel na vizinhança da bolha para compensação de luz/sombra.
     */
    protected function amostrarFundoLocal(GdImage $img, float $x, float $y, int $w, int $h): float
    {
        $ix = (int) max(0, min($w - 1, round($x)));
        $iy = (int) max(0, min($h - 1, round($y)));

        $rgb = imagecolorat($img, $ix, $iy);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
    }
}

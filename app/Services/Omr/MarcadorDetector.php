<?php

declare(strict_types=1);

namespace App\Services\Omr;

use GdImage;

/**
 * Detecta os 4 marcadores de calibração nos cantos da folha e resolve a orientação.
 */
class MarcadorDetector
{
    /**
     * Localiza os 4 marcadores nos quadrantes da imagem.
     * Retorna array com ['TL' => ['x', 'y'], 'TR' => ..., 'BR' => ..., 'BL' => ...] e imagem rotacionada se necessário.
     *
     * @return array{
     *     sucesso: bool,
     *     marcadores: array<string, array{x: float, y: float}>,
     *     rotacao_aplicada: int,
     *     imagem: GdImage,
     *     mensagem: ?string
     * }
     */
    public function detectar(GdImage $imagem): array
    {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);

        // Busca candidatos a marcadores nos 4 cantos
        $cantos = $this->localizarCentrosNosQuadrantes($imagem, $largura, $altura);

        if (! $cantos['sucesso']) {
            return [
                'sucesso' => false,
                'marcadores' => [],
                'rotacao_aplicada' => 0,
                'imagem' => $imagem,
                'mensagem' => 'Não foi possível localizar os 4 marcadores de canto da folha. Certifique-se de que a folha inteira está visível e bem iluminada.',
            ];
        }

        // Verifica assimetria para identificar qual canto é o TL (marcador com furo central)
        $identificacao = $this->determinarOrientacao($imagem, $cantos['pontos']);

        $rotacao = $identificacao['rotacao'];
        if ($rotacao !== 0) {
            // Rotaciona a imagem para a orientação correta
            $imagemRotacionada = imagerotate($imagem, $rotacao, 0);
            if ($imagemRotacionada instanceof GdImage) {
                $imagem = $imagemRotacionada;
                $largura = imagesx($imagem);
                $altura = imagesy($imagem);
                $cantos = $this->localizarCentrosNosQuadrantes($imagem, $largura, $altura);
            }
        }

        return [
            'sucesso' => true,
            'marcadores' => $cantos['pontos'],
            'rotacao_aplicada' => $rotacao,
            'imagem' => $imagem,
            'mensagem' => null,
        ];
    }

    /**
     * Localiza o ponto central aproximado do marcador escuro em cada um dos 4 cantos.
     *
     * @return array{sucesso: bool, pontos: array<string, array{x: float, y: float}>}
     */
    protected function localizarCentrosNosQuadrantes(GdImage $img, int $w, int $h): array
    {
        // Janelas de busca nos 4 cantos calibradas para a margem dos marcadores (12 a 22 mm)
        // Evita capturar o QR Code (que inicia em 28 mm) ou textos da folha
        $janelaW = (int) round($w * 0.13);
        $janelaH = (int) round($h * 0.10);

        $tl = $this->encontrarCentroQuadrante($img, 0, 0, $janelaW, $janelaH, 'TL');
        $tr = $this->encontrarCentroQuadrante($img, $w - $janelaW, 0, $w, $janelaH, 'TR');
        $br = $this->encontrarCentroQuadrante($img, $w - $janelaW, $h - $janelaH, $w, $h, 'BR');
        $bl = $this->encontrarCentroQuadrante($img, 0, $h - $janelaH, $janelaW, $h, 'BL');

        if (! $tl || ! $tr || ! $br || ! $bl) {
            return [
                'sucesso' => false,
                'pontos' => [],
            ];
        }

        return [
            'sucesso' => true,
            'pontos' => [
                'TL' => $tl,
                'TR' => $tr,
                'BR' => $br,
                'BL' => $bl,
            ],
        ];
    }

    /**
     * Encontra o centroide de intensidade escura na região especificada.
     *
     * @return array{x: float, y: float}|null
     */
    protected function encontrarCentroQuadrante(GdImage $img, int $x1, int $y1, int $x2, int $y2, string $canto): ?array
    {
        $somaX = 0.0;
        $somaY = 0.0;
        $totalEscuros = 0;

        // Amostragem com passo de 2 pixels para agilidade
        $passo = 2;

        for ($y = $y1; $y < $y2; $y += $passo) {
            for ($x = $x1; $x < $x2; $x += $passo) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $luminancia = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);

                // Pixel escuro do marcador (limiar rígido)
                if ($luminancia < 90) {
                    $somaX += $x;
                    $somaY += $y;
                    $totalEscuros++;
                }
            }
        }

        if ($totalEscuros < 30) {
            return null;
        }

        return [
            'x' => round($somaX / $totalEscuros, 2),
            'y' => round($somaY / $totalEscuros, 2),
        ];
    }

    /**
     * Determina a rotação necessária com base no marcador vazado (TL).
     *
     * @param  array<string, array{x: float, y: float}>  $pontos
     * @return array{rotacao: int}
     */
    protected function determinarOrientacao(GdImage $img, array $pontos): array
    {
        // Mede a luminância no centro exato de cada um dos 4 cantos
        $luminancias = [];
        foreach (['TL', 'TR', 'BR', 'BL'] as $chave) {
            $cx = (int) round($pontos[$chave]['x']);
            $cy = (int) round($pontos[$chave]['y']);

            $rgb = imagecolorat($img, max(0, min(imagesx($img) - 1, $cx)), max(0, min(imagesy($img) - 1, $cy)));
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $luminancias[$chave] = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
        }

        // O marcador TL tem centro vazado (branco, alta luminância). `imagerotate` gira no sentido anti-horário.
        // Se TL for o mais claro, rotação = 0°.
        // Se TR for o mais claro, a foto foi girada 90° no sentido horário (o TL foi para a direita): gira 90°.
        // Se BR for o mais claro, rotação = 180°.
        // Se BL for o mais claro, a foto foi girada 90° no sentido anti-horário (o TL foi para baixo): gira 270°.
        $maiorLuminancia = max($luminancias);
        $cantoMaisClaro = array_search($maiorLuminancia, $luminancias, true);

        // Se a diferença for pequena, mantém 0°
        $menorLuminancia = min($luminancias);
        if (($maiorLuminancia - $menorLuminancia) < 40) {
            return ['rotacao' => 0];
        }

        $rotacao = match ($cantoMaisClaro) {
            'TR' => 90,
            'BR' => 180,
            'BL' => 270,
            default => 0,
        };

        return ['rotacao' => $rotacao];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Omr;

/**
 * Serviço matemático para transformação geométrica e interpolação bilinear de 4 pontos.
 * Mapeia coordenadas milimétricas canônicas da folha A4 (210x297mm) para as coordenadas reais de pixels na foto.
 */
class HomografiaService
{
    /**
     * @param  array{x: float, y: float}  $tl  Canto Superior Esquerdo na imagem
     * @param  array{x: float, y: float}  $tr  Canto Superior Direito na imagem
     * @param  array{x: float, y: float}  $br  Canto Inferior Direito na imagem
     * @param  array{x: float, y: float}  $bl  Canto Inferior Esquerdo na imagem
     * @param  float  $marcadorTlXMm  Posição canônica X do centro do marcador TL (padrão 17.0 mm)
     * @param  float  $marcadorTlYMm  Posição canônica Y do centro do marcador TL (padrão 17.0 mm)
     * @param  float  $marcadorTrXMm  Posição canônica X do centro do marcador TR (padrão 193.0 mm)
     * @param  float  $marcadorBrYMm  Posição canônica Y do centro do marcador BR (padrão 280.0 mm)
     */
    public function __construct(
        protected array $tl,
        protected array $tr,
        protected array $br,
        protected array $bl,
        protected float $marcadorTlXMm = 17.0,
        protected float $marcadorTlYMm = 17.0,
        protected float $marcadorTrXMm = 193.0,
        protected float $marcadorBrYMm = 280.0,
    ) {}

    /**
     * Mapeia um ponto (xMm, yMm) da folha A4 para o ponto correspondente (px, py) na imagem real
     * utilizando interpolação bilinear calibrada pelos centros dos 4 marcadores de canto.
     *
     * @return array{x: float, y: float}
     */
    public function mapearPonto(float $xMm, float $yMm): array
    {
        $larguraMarcadoresMm = $this->marcadorTrXMm - $this->marcadorTlXMm;
        $alturaMarcadoresMm = $this->marcadorBrYMm - $this->marcadorTlYMm;

        // Coordenadas normalizadas relativas aos marcadores
        $u = ($xMm - $this->marcadorTlXMm) / $larguraMarcadoresMm;
        $v = ($yMm - $this->marcadorTlYMm) / $alturaMarcadoresMm;

        // Interpolação bilinear entre os 4 vértices
        $wTL = (1.0 - $u) * (1.0 - $v);
        $wTR = $u * (1.0 - $v);
        $wBR = $u * $v;
        $wBL = (1.0 - $u) * $v;

        $px = ($wTL * $this->tl['x']) + ($wTR * $this->tr['x']) + ($wBR * $this->br['x']) + ($wBL * $this->bl['x']);
        $py = ($wTL * $this->tl['y']) + ($wTR * $this->tr['y']) + ($wBR * $this->br['y']) + ($wBL * $this->bl['y']);

        return [
            'x' => $px,
            'y' => $py,
        ];
    }

    /**
     * Calcula o raio aproximado em pixels a partir do raio em milímetros.
     */
    public function mapearRaio(float $raioMm): float
    {
        $larguraMarcadoresMm = $this->marcadorTrXMm - $this->marcadorTlXMm;
        $distTopPx = hypot($this->tr['x'] - $this->tl['x'], $this->tr['y'] - $this->tl['y']);
        $escalaPxPorMm = $distTopPx / $larguraMarcadoresMm;

        return max(2.0, $raioMm * $escalaPxPorMm);
    }
}

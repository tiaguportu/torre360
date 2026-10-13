<?php

declare(strict_types=1);

namespace App\Services\Omr;

use chillerlan\QRCode\QRCode;
use GdImage;

class QrCodeLeitor
{
    /**
     * Tenta recortar e ler o QR Code da folha a partir da transformação de homografia.
     */
    public function ler(GdImage $imagem, HomografiaService $homografia, GabaritoLayout $layout): ?string
    {
        $area = $layout->areaQrCode();

        // Mapeia os 4 cantos da área do QR Code para pixels
        $pTL = $homografia->mapearPonto($area['x'], $area['y']);
        $pBR = $homografia->mapearPonto($area['x'] + $area['w'], $area['y'] + $area['h']);

        $minX = (int) max(0, min($pTL['x'], $pBR['x']) - 4);
        $minY = (int) max(0, min($pTL['y'], $pBR['y']) - 4);
        $maxX = (int) min(imagesx($imagem) - 1, max($pTL['x'], $pBR['x']) + 4);
        $maxY = (int) min(imagesy($imagem) - 1, max($pTL['y'], $pBR['y']) + 4);

        $cropW = $maxX - $minX;
        $cropH = $maxY - $minY;

        if ($cropW < 20 || $cropH < 20) {
            return null;
        }

        $recorte = imagecreatetruecolor($cropW, $cropH);
        if (! $recorte) {
            return null;
        }

        imagecopy($recorte, $imagem, 0, 0, $minX, $minY, $cropW, $cropH);

        // Salva recorte em stream de memória temporário PNG
        ob_start();
        imagepng($recorte);
        $pngData = (string) ob_get_clean();
        imagedestroy($recorte);

        if (empty($pngData)) {
            return null;
        }

        try {
            $qr = new QRCode;
            $result = $qr->readFromBlob($pngData);
            $texto = trim((string) $result);

            // Verifica se é um UUID v4 válido
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $texto)) {
                return strtolower($texto);
            }

            return ! empty($texto) ? $texto : null;
        } catch (\Throwable) {
            // Em caso de falha de leitura do QR, o fallback é seleção manual ou reprocessamento
            return null;
        }
    }
}

<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeService
{
    /**
     * Gera a string SVG direta do QR Code (com tag <svg...).
     */
    public function renderSvg(string $data): string
    {
        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'addQuietzone' => true,
            'svgUseFill' => true,
            'outputBase64' => false,
        ]);

        return (new QRCode($options))->render($data);
    }

    /**
     * Gera o Data URI base64 (data:image/svg+xml;base64,...) para renderização em tags <img> no HTML/PDF.
     */
    public function renderDataUri(string $data): string
    {
        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'addQuietzone' => true,
            'svgUseFill' => true,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($data);
    }
}

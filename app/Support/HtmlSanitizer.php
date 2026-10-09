<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sanitizador de HTML defensivo para neutralizar vetores de Stored XSS
 * em modelos de contratos, relatórios pedagógicos e templates do TinyMCE.
 */
class HtmlSanitizer
{
    /**
     * Remove tags de execução de código, eventos inline e protocolos perigosos
     * preservando a formatação visual e estrutural necessária para impressão e PDF.
     */
    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $clean = (string) $html;

        // 1. Remove blocos inteiros de script, iframe, object, embed e applet
        $clean = preg_replace('/<\s*(script|iframe|object|embed|applet)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $clean) ?? $clean;

        // 2. Remove tags perigosas autolimpantes ou órfãs
        $clean = preg_replace('/<\s*\/?\s*(script|iframe|object|embed|applet|meta|link|base|form|input|button)\b[^>]*>/i', '', $clean) ?? $clean;

        // 3. Remove manipuladores de evento inline (onload, onerror, onclick, onmouseover, onfocus, etc.)
        $clean = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;

        // 4. Remove protocolos pseudo-URL perigosos em atributos (javascript:, vbscript:, data:text/html)
        $clean = preg_replace_callback('/\b(href|src|action)\s*=\s*(["\'])(.*?)\2/is', function ($matches) {
            $attribute = $matches[1];
            $quote = $matches[2];
            $url = trim($matches[3]);

            $urlSemEspacos = preg_replace('/[\x00-\x20\s]+/', '', strtolower($url));

            if (str_starts_with($urlSemEspacos, 'javascript:') ||
                str_starts_with($urlSemEspacos, 'vbscript:') ||
                (str_starts_with($urlSemEspacos, 'data:') && ! str_starts_with($urlSemEspacos, 'data:image/'))
            ) {
                return "{$attribute}={$quote}#{$quote}";
            }

            return "{$attribute}={$quote}{$url}{$quote}";
        }, $clean) ?? $clean;

        return $clean;
    }
}

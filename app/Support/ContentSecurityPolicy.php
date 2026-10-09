<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Monta a Content-Security-Policy em duas camadas.
 *
 * "Base": diretivas que não dependem de lista de origens e não quebram nenhuma tela (sem plugins, sem `<base>` forjado,
 * sem ser embutido em site de terceiros). É aplicada sempre.
 *
 * "Estrita": limita de onde scripts, estilos, fontes, imagens, conexões e quadros podem vir, e para onde formulários
 * podem enviar. Ainda precisa de 'unsafe-inline' e 'unsafe-eval' em scripts porque Filament/Livewire/Alpine e o
 * Tailwind CDN dos portais públicos usam script inline e avaliação dinâmica; o ganho real é impedir que um script
 * injetado carregue código de, ou envie dados para, um domínio fora da lista. Uma política com nonce (sem
 * 'unsafe-inline') é a evolução natural, mas exige o build CSP do Alpine e testes em todas as telas.
 *
 * Por isso a estrita sai primeiro em modo `report-only` (config/seguranca.php): o navegador só relata o que bloquearia.
 */
class ContentSecurityPolicy
{
    public const ROTA_RELATORIO = '/api/csp-report';

    /**
     * @return array<string, list<string>>
     */
    private static function diretivasBase(): array
    {
        return [
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function diretivasEstritas(): array
    {
        $recaptcha = ['https://www.google.com/recaptcha/', 'https://www.gstatic.com/recaptcha/'];
        $fontes = ['https://fonts.googleapis.com', 'https://fonts.bunny.net'];

        return [
            'default-src' => ["'self'"],
            'script-src' => [
                "'self'", "'unsafe-inline'", "'unsafe-eval'",
                ...$recaptcha,
                'https://cdn.tailwindcss.com', 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com',
                'https://www.googletagmanager.com',
            ],
            'style-src' => ["'self'", "'unsafe-inline'", ...$fontes, 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://maxcdn.bootstrapcdn.com'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com', 'https://fonts.bunny.net', 'https://cdn.jsdelivr.net'],
            'img-src' => [
                "'self'", 'data:', 'blob:',
                'https://ui-avatars.com', 'https://www.gstatic.com', 'https://www.google.com',
                'https://www.google-analytics.com', 'https://www.googletagmanager.com',
                'https://books.google.com', 'https://*.googleusercontent.com',
            ],
            'media-src' => ["'self'", 'blob:'],
            'connect-src' => [
                "'self'", 'https://viacep.com.br', 'https://www.google.com',
                'https://www.google-analytics.com', 'https://*.google-analytics.com', 'https://*.analytics.google.com',
                'https://www.googletagmanager.com',
            ],
            'frame-src' => [
                "'self'", ...$recaptcha, 'https://recaptcha.google.com',
                'https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://player.vimeo.com', 'https://drive.google.com',
            ],
            'worker-src' => ["'self'", 'blob:'],
            'manifest-src' => ["'self'"],
            'form-action' => ["'self'"],
            ...self::diretivasBase(),
        ];
    }

    public static function base(): string
    {
        return self::montar(self::diretivasBase());
    }

    public static function estrita(): string
    {
        return self::montar(self::comExtras(self::diretivasEstritas()));
    }

    /**
     * A estrita acrescida da URL que recebe os relatos de violação (usada no modo report-only).
     */
    public static function estritaComRelatorio(): string
    {
        return self::estrita().'; report-uri '.self::ROTA_RELATORIO;
    }

    /**
     * @param  array<string, list<string>>  $diretivas
     * @return array<string, list<string>>
     */
    private static function comExtras(array $diretivas): array
    {
        foreach ((array) config('seguranca.csp.extra', []) as $diretiva => $lista) {
            $origens = array_values(array_filter(array_map('trim', explode(',', (string) $lista))));

            if ($origens !== [] && isset($diretivas[$diretiva])) {
                $diretivas[$diretiva] = array_values(array_unique([...$diretivas[$diretiva], ...$origens]));
            }
        }

        return $diretivas;
    }

    /**
     * @param  array<string, list<string>>  $diretivas
     */
    private static function montar(array $diretivas): string
    {
        return collect($diretivas)
            ->map(fn (array $origens, string $diretiva): string => $diretiva.' '.implode(' ', $origens))
            ->implode('; ');
    }
}

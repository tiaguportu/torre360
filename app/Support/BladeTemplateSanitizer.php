<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sanitizador defensivo de templates Blade contra Server-Side Template Injection (SSTI).
 * Neutraliza injeções arbitrárias de código PHP, comandos de sistema operacional (RCE)
 * e leitura não autorizada de arquivos locais antes de submeter ao Blade::render().
 */
class BladeTemplateSanitizer
{
    /**
     * Lista de funções nativas e auxiliares de alta periculosidade no PHP/Laravel.
     *
     * @var list<string>
     */
    private const DANGEROUS_FUNCTIONS = [
        'exec',
        'system',
        'passthru',
        'shell_exec',
        'proc_open',
        'popen',
        'pcntl_exec',
        'eval',
        'assert',
        'call_user_func',
        'call_user_func_array',
        'create_function',
        'file_get_contents',
        'file_put_contents',
        'readfile',
        'unlink',
        'rmdir',
        'mkdir',
        'fopen',
        'file',
        'copy',
        'rename',
        'chmod',
        'chown',
        'touch',
        'symlink',
        'link',
        'scandir',
        'glob',
        'tempnam',
        'phpinfo',
        'getenv',
        'putenv',
        'extract',
        'compact',
        'unserialize',
        'var_dump',
        'print_r',
        'debug_backtrace',
        'app',
        'resolve',
        'abort',
        'dispatch',
        'event',
        'base64_decode',
    ];

    /**
     * Classes e fachadas sensíveis cujo acesso estático em templates deve ser bloqueado.
     *
     * @var list<string>
     */
    private const DANGEROUS_CLASSES = [
        'Artisan',
        'DB',
        'Storage',
        'File',
        'Http',
        'Process',
        'Auth',
        'Session',
        'App',
        'Route',
        'Request',
        'Schema',
        'Blade',
        'Config',
        'Env',
        'ReflectionClass',
        'ReflectionFunction',
        'ReflectionMethod',
    ];

    /**
     * Sanitiza uma string contendo template Blade, removendo código PHP ativo e expressões maliciosas.
     */
    public static function clean(?string $template): string
    {
        if (blank($template)) {
            return '';
        }

        $clean = (string) $template;

        // 1. Neutraliza tags PHP puras (<?php e <?=)
        $clean = preg_replace('/<\?(php|=)?/i', '&lt;?$1', $clean) ?? $clean;

        // 2. Remove blocos @php ... @endphp
        $clean = preg_replace('/@php\b.*?@endphp/is', '<!-- [BLOCO PHP BLOQUEADO] -->', $clean) ?? $clean;

        // 3. Remove diretivas @php(...) isoladas
        $clean = preg_replace('/@php\s*\(.*?\)/is', '<!-- [BLOCO PHP BLOQUEADO] -->', $clean) ?? $clean;

        // 4. Neutraliza diretivas Blade estruturais de inclusão e injeção externa
        $clean = preg_replace(
            '/@(include(If|When|Unless|First)?|each|inject|use|component|extends|section|yield|stack|push|prepend|verbatim|endverbatim|eval)\b[^;()]*(\([^)]*\))?/is',
            '<!-- [DIRETIVA BLADE BLOQUEADA] -->',
            $clean
        ) ?? $clean;

        // 5. Sanitiza expressões de interpolação {!! ... !!} e {{ ... }}
        $clean = self::sanitizeInterpolations($clean);

        return $clean;
    }

    /**
     * Inspeciona e sanitiza blocos de interpolação {!! ... !!} e {{ ... }}.
     */
    private static function sanitizeInterpolations(string $template): string
    {
        return preg_replace_callback('/(\{\{|\{!!)\s*(.*?)\s*(\}\}|\!!\})/', function (array $matches): string {
            $open = $matches[1];
            $expression = trim($matches[2]);
            $close = $matches[3];

            if (self::isDangerousExpression($expression)) {
                return '<!-- [EXPRESSAO BLOQUEADA POR SEGURANCA] -->';
            }

            return "{$open} {$expression} {$close}";
        }, $template) ?? $template;
    }

    /**
     * Avalia se uma expressão dentro de interpolação do Blade possui potenciais vetores de RCE / SSTI.
     */
    public static function isDangerousExpression(string $expression): bool
    {
        // Bloqueia operador de execução shell por crases (backticks `whoami`)
        if (str_contains($expression, '`')) {
            return true;
        }

        // Bloqueia tentativas de execução de múltiplos comandos separados por ponto e vírgula
        if (str_contains($expression, ';')) {
            return true;
        }

        // Bloqueia variáveis variáveis dinâmicas ($$)
        if (str_contains($expression, '$$')) {
            return true;
        }

        // Bloqueia instanciação arbitrária com 'new ClassName'
        if (preg_match('/\bnew\s+[a-zA-Z0-9_\\\\]+/i', $expression)) {
            return true;
        }

        // Bloqueia acesso estático a classes ou fachadas perigosas (ex: Artisan::, DB::, etc.)
        $classesRegex = implode('|', array_map('preg_quote', self::DANGEROUS_CLASSES));
        if (preg_match('/(?:^|[^a-zA-Z0-9_\\\\])(?:\\\\)?(?:'.$classesRegex.')\s*::/i', $expression)) {
            return true;
        }

        // Bloqueia invocação de funções nativas/perigosas (ex: system(, exec(, etc.)
        $functionsRegex = implode('|', array_map('preg_quote', self::DANGEROUS_FUNCTIONS));
        if (preg_match('/(?:^|[^a-zA-Z0-9_\\\\])(?:\\\\)?(?:'.$functionsRegex.')\s*\(/i', $expression)) {
            return true;
        }

        // Bloqueia funções dinâmicas invocadas via variável ou parênteses, ex: ($fn)() ou $fn()
        if (preg_match('/\$\w+\s*\(/', $expression) || preg_match('/\)\s*\(/', $expression)) {
            return true;
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validador e mitigador de Server-Side Request Forgery (SSRF).
 * Impede conexões do backend para endereços de loopback, redes privadas (RFC 1918),
 * serviços de metadados em nuvem (RFC 3927) e esquemas de protocolo arbitrários.
 */
class SsrfProtection
{
    /**
     * Esquemas de protocolo web estritamente permitidos.
     *
     * @var list<string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Hostnames e IPs expressamente bloqueados (loopbacks, cloud metadata).
     *
     * @var list<string>
     */
    private const BLOCKED_HOSTS = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        '169.254.169.254', // AWS, GCP, Azure, OpenStack Metadata API
        'metadata.google.internal',
        'instance-data',
    ];

    /**
     * Avalia se uma URL remota é segura contra SSRF para ser acessada pelo backend.
     */
    public static function isSafeUrl(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $url = trim((string) $url);

        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parsed['scheme']);
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return false;
        }

        $host = strtolower((string) $parsed['host']);

        // Bloqueia hosts explicitamente proibidos
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            return false;
        }

        // Bloqueia domínios internos ou locais
        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')) {
            return false;
        }

        // Se o host já for um IP literal (IPv4 ou IPv6)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        // Resolve os endereços IP vinculados ao hostname para mitigar DNS Rebinding
        $ips = @gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            // Em ambientes de teste locais, hostnames simulados sem DNS público podem ser tratados defensivamente
            return false;
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Avalia se um endereço IP é público e não pertence a faixas reservadas ou privadas.
     */
    public static function isPublicIp(string $ip): bool
    {
        $isValid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($isValid === false) {
            return false;
        }

        // Checagem defensiva adicional para Link-Local / Cloud Metadata (169.254.0.0/16)
        if (str_starts_with($ip, '169.254.')) {
            return false;
        }

        // Checagem para loopback (127.0.0.0/8)
        if (str_starts_with($ip, '127.')) {
            return false;
        }

        return true;
    }
}

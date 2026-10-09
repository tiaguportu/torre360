<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Remove do corpo registrado em `email_logs` o que dá acesso a uma conta ou a um portal: links de redefinição e de
 * verificação de e-mail, tokens de assinatura e os links públicos por token (admissão, acordo, pesquisa, convite).
 *
 * O log existe para a equipe saber o que foi enviado a quem, não para guardar chaves de acesso: quem pudesse ler o
 * log (ou um backup do banco) poderia usar um link ainda válido para entrar na conta de outra pessoa.
 */
class EmailLogSanitizer
{
    public const MARCADOR = '[oculto]';

    /** Notificações cujo corpo é, por natureza, uma credencial: o conteúdo inteiro é omitido. */
    private const PREFIXOS_SENSIVEIS = [
        'App\\Notifications\\WelcomeUserMail',
        'App\\Notifications\\UserUpdatedMail',
        'Filament\\Auth\\Notifications\\',
        'Illuminate\\Auth\\Notifications\\',
    ];

    public static function corpo(string $corpo, ?string $notificacao = null): string
    {
        if ($notificacao !== null && self::ehNotificacaoSensivel($notificacao)) {
            return '[Conteúdo omitido: este e-mail contém um link ou credencial de acesso e não é guardado no log.]';
        }

        // Parâmetros de query com segredo (o separador pode vir como "&" ou "&amp;" no HTML).
        $corpo = preg_replace(
            '~(?<=[?&;])(token|signature|hash|key|password|senha|code)=[^&"\'\s<>]+~i',
            '$1='.self::MARCADOR,
            $corpo,
        ) ?? $corpo;

        // Links públicos com o token no caminho: /admissao/{token}, /acordo/{token}, /pesquisa-visita/{token}, /convite/{token}.
        return preg_replace(
            '~(/(?:admissao|acordo|pesquisa-visita|convite|reset-password)/)[A-Za-z0-9_\-]{16,}~',
            '$1'.self::MARCADOR,
            $corpo,
        ) ?? $corpo;
    }

    private static function ehNotificacaoSensivel(string $classe): bool
    {
        foreach (self::PREFIXOS_SENSIVEIS as $prefixo) {
            if (str_starts_with($classe, $prefixo)) {
                return true;
            }
        }

        return false;
    }
}

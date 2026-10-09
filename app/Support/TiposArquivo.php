<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tipos MIME aceitos nos uploads do sistema (lista branca).
 *
 * Todo `FileUpload` deve declarar o que aceita, usando uma destas listas, em vez de `image/*` ou nada: `image/*`
 * inclui SVG (XML que pode carregar `<script>`) e um upload sem restrição aceita HTML. Arquivos de usuário são
 * servidos na mesma origem do painel, então um HTML/SVG enviado por um perfil de baixo privilégio e aberto por um
 * administrador executaria script com a sessão dele. O Filament valida estes tipos no servidor (regra `mimetypes`).
 */
final class TiposArquivo
{
    /**
     * Imagens raster. Sem SVG de propósito.
     *
     * @return list<string>
     */
    public static function imagens(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];
    }

    /**
     * PDF e imagens: documentos pessoais, comprovantes e anexos de atendimento.
     *
     * @return list<string>
     */
    public static function documentos(): array
    {
        return ['application/pdf', ...self::imagens()];
    }

    /**
     * Materiais de aula: documentos mais Office/OpenDocument, texto, compactados e mídia comum.
     * Nada de HTML, SVG, XML ou scripts.
     *
     * @return list<string>
     */
    public static function materiaisDeAula(): array
    {
        return [
            ...self::documentos(),
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
            'text/plain',
            'text/csv',
            // Alguns servidores detectam .docx/.xlsx/.pptx como zip pelo conteúdo.
            'application/zip',
            'video/mp4',
            'audio/mpeg',
        ];
    }

    /**
     * Tipos que o navegador pode exibir na própria página sem risco de executar código no domínio do sistema.
     *
     * @return list<string>
     */
    public static function exibiveisNoNavegador(): array
    {
        return ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    }
}

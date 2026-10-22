<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda um documento (PDF ou foto) no disco privado. O tipo vem do CONTEÚDO do arquivo, nunca do nome nem do tipo que o
 * navegador informa, e a extensão gravada sai do tipo detectado: um HTML enviado como "contrato.pdf" é recusado. Quem
 * serve o arquivo é o `VisualizarDocumentoController`, que confere a autorização pela pasta.
 */
final class ArquivoPrivado
{
    /**
     * Extensão gravada para cada tipo aceito.
     *
     * @var array<string, string>
     */
    private const EXTENSOES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];

    /**
     * @param  string  $pasta  sem barra no fim (ex.: "processos_funcionario/12")
     * @param  string  $nome  sem extensão (ex.: "item-7-20261021090000")
     * @return string caminho gravado no disco `local`
     *
     * @throws \DomainException quando o arquivo está vazio ou não é PDF/imagem
     */
    public static function guardarDocumento(UploadedFile $arquivo, string $pasta, string $nome): string
    {
        $conteudo = $arquivo->get();

        if (! is_string($conteudo) || $conteudo === '') {
            throw new \DomainException('Arquivo vazio ou ilegível.');
        }

        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->buffer($conteudo) ?: '';

        if (! in_array($tipo, TiposArquivo::documentos(), true) || ! isset(self::EXTENSOES[$tipo])) {
            throw new \DomainException('Envie o arquivo em PDF ou em foto (JPG, PNG ou WEBP).');
        }

        $caminho = trim($pasta, '/').'/'.$nome.'.'.self::EXTENSOES[$tipo];

        Storage::disk('local')->put($caminho, $conteudo);

        return $caminho;
    }
}

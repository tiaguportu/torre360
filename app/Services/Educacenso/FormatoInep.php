<?php

declare(strict_types=1);

namespace App\Services\Educacenso;

use Illuminate\Support\Str;

/**
 * Regras de texto dos arquivos de migração do Educacenso: maiúsculas, sem acento nem cê-cedilha, campos separados por
 * "|" e codificação ISO-8859-1.
 */
final class FormatoInep
{
    public static function digitos(mixed $valor): string
    {
        return preg_replace('/\D/', '', (string) ($valor ?? '')) ?? '';
    }

    /**
     * Só letras A-Z e espaço (nomes nos arquivos de identificação e do gestor).
     */
    public static function letras(?string $valor, int $maximo = 100): string
    {
        $texto = mb_strtoupper(Str::ascii((string) $valor));
        $texto = preg_replace('/[^A-Z ]/', ' ', $texto) ?? '';
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');

        return mb_substr($texto, 0, $maximo);
    }

    /**
     * Código próprio da escola (aluno ou turma): até 20 caracteres alfanuméricos, sem espaço nem símbolo.
     */
    public static function codigoLocal(mixed $valor, int $maximo = 20): string
    {
        $texto = mb_strtoupper(Str::ascii((string) ($valor ?? '')));

        return mb_substr(preg_replace('/[^A-Z0-9]/', '', $texto) ?? '', 0, $maximo);
    }

    /**
     * @param  list<string>  $linhas
     */
    public static function arquivo(array $linhas): string
    {
        return mb_convert_encoding(implode("\r\n", $linhas)."\r\n", 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Linhas de um arquivo recebido do Educacenso, já em UTF-8 e sem linhas vazias.
     *
     * @return list<string>
     */
    public static function linhasDoArquivo(string $conteudo): array
    {
        if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
            $conteudo = substr($conteudo, 3);
        }

        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
        }

        $linhas = preg_split('/\r\n|\n|\r/', $conteudo) ?: [];

        return array_values(array_filter(array_map('trim', $linhas), fn (string $linha): bool => $linha !== ''));
    }
}

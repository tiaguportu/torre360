<?php

namespace App\Services\Educacenso;

use App\Models\Pessoa;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EducacensoPessoaExporter
{
    /**
     * Exporta uma coleção de pessoas no formato delimitado por Pipe (|) para o Educacenso (INEP).
     *
     * @param  Collection<int, Pessoa>  $pessoas
     */
    public function export(Collection $pessoas): string
    {
        $pessoas->each(function (Pessoa $pessoa) {
            $pessoa->loadMissing([
                'naturalidade',
                'responsaveis',
            ]);
        });

        $lines = [];

        foreach ($pessoas as $pessoa) {
            $lines[] = $this->buildPessoaLine($pessoa);
        }

        return implode("\r\n", $lines);
    }

    /**
     * Sanitiza textos removendo acentos e mantendo apenas caracteres alfanuméricos padrão Educacenso (A-Z, 0-9, ª, º, -, espaço).
     */
    private function sanitizeText(?string $text, int $maxLength = 100): string
    {
        if (blank($text)) {
            return '';
        }

        $str = mb_strtoupper($text);

        $transliteration = [
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ];

        $str = strtr($str, $transliteration);
        $str = preg_replace('/[^A-Z0-9 ªº\-]/u', '', $str);
        $str = preg_replace('/\s+/', ' ', $str);

        return mb_substr(trim($str), 0, $maxLength);
    }

    /**
     * Constrói a linha formatada em 9 campos separados por Pipe (|) para uma pessoa.
     */
    public function buildPessoaLine(Pessoa $pessoa): string
    {
        // 1. Código do aluno na Entidade/Escola (Alfanumérico, max 20, Obrigatório)
        $f1 = $this->sanitizeText((string) ($pessoa->codigo ?? $pessoa->id), 20);

        // 2. Número do CPF (11 dígitos numéricos, Opcional)
        $cpfDigits = preg_replace('/[^0-9]/', '', (string) ($pessoa->cpf ?? ''));
        $f2 = strlen($cpfDigits) === 11 ? $cpfDigits : '';

        // 3. Número da Matrícula (Registro Civil - Certidão de nascimento) (Alfanumérico, max 32, Opcional)
        $f3 = $this->sanitizeText((string) ($pessoa->certidao_nascimento ?? ''), 32);

        // 4. Nome completo (Alfanumérico, max 100, Obrigatório)
        $f4 = $this->sanitizeText((string) ($pessoa->nome ?? ''), 100);

        // 5. Data de nascimento (Data DD/MM/AAAA, max 10, Obrigatório)
        $f5 = '';
        if ($pessoa->data_nascimento) {
            $dataNascto = $pessoa->data_nascimento instanceof Carbon
                ? $pessoa->data_nascimento
                : Carbon::parse($pessoa->data_nascimento);
            $f5 = $dataNascto->format('d/m/Y');
        }

        // 6. Filiação 1 (Alfanumérico, max 100, Opcional)
        $filiacao1Raw = $pessoa->filiacao_1;
        if (blank($filiacao1Raw) && $pessoa->responsaveis->isNotEmpty()) {
            $filiacao1Raw = $pessoa->responsaveis->first()?->nome;
        }
        $f6 = $this->sanitizeText($filiacao1Raw, 100);

        // 7. Filiação 2 (Alfanumérico, max 100, Opcional)
        $filiacao2Raw = $pessoa->filiacao_2;
        if (blank($filiacao2Raw) && $pessoa->responsaveis->count() > 1) {
            $filiacao2Raw = $pessoa->responsaveis->skip(1)->first()?->nome;
        }
        $f7 = $this->sanitizeText($filiacao2Raw, 100);

        // 8. Município de nascimento (Numérico - Código IBGE, max 7, Obrigatório)
        $ibgeDigits = preg_replace('/[^0-9]/', '', (string) ($pessoa->naturalidade?->codigo_ibge ?? ''));
        $f8 = mb_substr($ibgeDigits, 0, 7);

        // 9. Identificação única do aluno (Inep) (Numérico, max 12, Opcional)
        $inepDigits = preg_replace('/[^0-9]/', '', (string) ($pessoa->codigo_inep ?? ''));
        $f9 = mb_substr($inepDigits, 0, 12);

        $fields = [$f1, $f2, $f3, $f4, $f5, $f6, $f7, $f8, $f9];

        return implode('|', $fields);
    }
}

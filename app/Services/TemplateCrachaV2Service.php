<?php

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Models\Pessoa;
use App\Models\TemplateCrachaV2;
use App\Models\Turma;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfObject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class TemplateCrachaV2Service
{
    /**
     * Processa o SVG do template substituindo as variáveis de texto e injetando a foto em base64 da Pessoa.
     */
    public static function processarSvg(string $svgContent, Pessoa $pessoa, ?Turma $turma = null): string
    {
        // Se a turma for nula, tentamos obter a turma da matrícula ativa da pessoa
        if (! $turma) {
            $matriculaAtiva = $pessoa->matriculas()
                ->where('situacao', SituacaoMatricula::ATIVA)
                ->first();
            $turma = $matriculaAtiva?->turma;
        }

        // Substituição das variáveis textuais padrão
        $svgContent = TemplateCrachaService::substituirVariaveis($svgContent, $pessoa, $turma);

        // Processamento da Foto (Base64)
        $fotoUrl = null;
        if ($pessoa->foto && Storage::exists($pessoa->foto)) {
            try {
                $mimeType = Storage::mimeType($pessoa->foto) ?? 'image/jpeg';
                $fileContent = Storage::get($pessoa->foto);
                $fotoUrl = 'data:'.$mimeType.';base64,'.base64_encode($fileContent);
            } catch (\Exception $e) {
                // Fallback em caso de falha de leitura
            }
        }

        // Caso a pessoa não tenha foto ou ocorra erro, gera o avatar padrão
        if (! $fotoUrl) {
            $fotoUrl = 'https://ui-avatars.com/api/?name='.urlencode($pessoa->nome).'&color=7F9CF5&background=EBF4FF';
        }

        // Regex para localizar a tag <image> com id="foto-aluno-v2" e substituir o seu href (ou xlink:href)
        $pattern = '/(<image[^>]*?id="foto-aluno-v2"[^>]*?(?:href|xlink:href)=")(.*?)("[^>]*?>)/is';

        if (preg_match($pattern, $svgContent)) {
            $svgContent = preg_replace($pattern, '${1}'.$fotoUrl.'${3}', $svgContent);
        }

        return $svgContent;
    }

    /**
     * Gera o PDF consolidado dos crachás V2.
     *
     * @param  Collection<int, object>  $pessoasComTurma  Coleção de objetos com 'pessoa' e 'turma'
     */
    public static function gerarPdf(TemplateCrachaV2 $template, Collection $pessoasComTurma): DomPdfObject
    {
        $svgsProcessados = collect();

        foreach ($pessoasComTurma as $item) {
            $pessoa = $item->pessoa;
            $turma = $item->turma;

            $svgOriginal = $template->svg_content ?: '';

            // Processa o SVG injetando variáveis e foto correspondente
            $svgProcessado = self::processarSvg($svgOriginal, $pessoa, $turma);

            $svgsProcessados->push((object) [
                'pessoa' => $pessoa,
                'turma' => $turma,
                'svg' => $svgProcessado,
            ]);
        }

        // Passa as dimensões em pontos (px * 0.75) para controle da grade no PDF
        $crachaLargura = $template->largura * 0.75;
        $crachaAltura = $template->altura * 0.75;

        return Pdf::loadView('pdf.cracha-lote-v2', [
            'svgs' => $svgsProcessados,
            'crachaLargura' => $crachaLargura,
            'crachaAltura' => $crachaAltura,
        ])->setPaper('a4', 'portrait');
    }
}

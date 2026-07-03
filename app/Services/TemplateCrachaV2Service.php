<?php

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Models\Pessoa;
use App\Models\TemplateCrachaV2;
use App\Models\Turma;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfObject;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class TemplateCrachaV2Service
{
    /**
     * Retorna o valor de uma variável a partir do nome da classe associada.
     */
    public static function getValorVariavelPorNome(string $nome, Pessoa $pessoa, ?Turma $turma = null): ?string
    {
        $nome = strtolower(trim($nome));

        return match ($nome) {
            'nome' => $pessoa->nome ?? '',
            'cpf' => $pessoa->cpf ?? '',
            'email' => $pessoa->email ?? '',
            'telefone' => $pessoa->telefone ?? '',
            'profissao' => $pessoa->profissao ?? '',
            'identidade' => $pessoa->identidade ?? '',
            'data_nascimento' => $pessoa->data_nascimento ? Carbon::parse($pessoa->data_nascimento)->format('d/m/Y') : '',
            'sexo' => $pessoa->sexo?->value ?? $pessoa->sexo ?? '',
            'cor_raca' => $pessoa->cor_raca?->value ?? $pessoa->cor_raca ?? '',

            // Dados de Turma
            'turma_nome' => $turma?->nome ?? '',
            'turma_periodo' => $turma?->periodoLetivo?->nome ?? '',
            'turma_serie' => $turma?->serie?->nome ?? '',
            'turma_curso' => $turma?->serie?->curso?->nome ?? '',
            default => null,
        };
    }

    /**
     * Retorna a string Base64 da foto da pessoa ou o avatar padrão.
     */
    public static function getFotoBase64(Pessoa $pessoa): string
    {
        $fotoUrl = null;
        if ($pessoa->foto && Storage::exists($pessoa->foto)) {
            try {
                $mimeType = Storage::mimeType($pessoa->foto) ?? 'image/jpeg';
                $fileContent = Storage::get($pessoa->foto);
                $fotoUrl = 'data:'.$mimeType.';base64,'.base64_encode($fileContent);
            } catch (\Exception $e) {
                // Fallback em caso de erro
            }
        }

        if (! $fotoUrl) {
            $fotoUrl = 'https://ui-avatars.com/api/?name='.urlencode($pessoa->nome).'&color=7F9CF5&background=EBF4FF';
        }

        return $fotoUrl;
    }

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

        // Habilita manipulação segura do XML
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);

        $xmlContent = $svgContent;
        if (strpos($xmlContent, '<?xml') === false) {
            $xmlContent = '<?xml version="1.0" encoding="utf-8"?>'.$xmlContent;
        }

        $dom->loadXML($xmlContent, LIBXML_NOENT | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // 1. Processar os textos que contêm classes mapeadas (text e tspan)
        $textTags = ['text', 'tspan'];
        foreach ($textTags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            foreach ($elements as $el) {
                if ($el->hasAttribute('class')) {
                    $classAttr = $el->getAttribute('class');
                    $classes = array_map('trim', explode(' ', $classAttr));

                    foreach ($classes as $c) {
                        $valor = self::getValorVariavelPorNome($c, $pessoa, $turma);
                        if ($valor !== null) {
                            if ($tag === 'text') {
                                // Se a classe estiver no <text>, tenta atualizar o <tspan> filho se houver
                                $tspan = $el->getElementsByTagName('tspan')->item(0);
                                if ($tspan) {
                                    $tspan->nodeValue = htmlspecialchars($valor, ENT_XML1, 'UTF-8');
                                } else {
                                    $el->nodeValue = htmlspecialchars($valor, ENT_XML1, 'UTF-8');
                                }
                            } else {
                                // Se a classe estiver diretamente no <tspan>, atualiza o tspan diretamente
                                $el->nodeValue = htmlspecialchars($valor, ENT_XML1, 'UTF-8');
                            }
                            break; // Para após achar a primeira correspondência
                        }
                    }
                }
            }
        }

        // Cria a tag <defs> se ela não existir
        $defs = $dom->getElementsByTagName('defs')->item(0);
        if (! $defs) {
            $defs = $dom->createElementNS('http://www.w3.org/2000/svg', 'defs');
            $dom->documentElement->insertBefore($defs, $dom->documentElement->firstChild);
        }

        // Gera a URL da foto (Base64 ou avatar padrão)
        $fotoUrl = self::getFotoBase64($pessoa);

        // Cria o elemento <pattern> para a foto
        $patternId = 'pattern-foto-aluno-'.$pessoa->id;

        // Remove pattern antigo se existir
        $oldPattern = $dom->getElementById($patternId);
        if ($oldPattern) {
            $oldPattern->parentNode->removeChild($oldPattern);
        }

        $pattern = $dom->createElementNS('http://www.w3.org/2000/svg', 'pattern');
        $pattern->setAttribute('id', $patternId);
        $pattern->setAttribute('x', '0');
        $pattern->setAttribute('y', '0');
        $pattern->setAttribute('width', '1');
        $pattern->setAttribute('height', '1');
        $pattern->setAttribute('patternContentUnits', 'objectBoundingBox');

        // Cria a tag <image> interna do pattern preenchendo proporcionalmente (cover/slice)
        $image = $dom->createElementNS('http://www.w3.org/2000/svg', 'image');
        $image->setAttribute('href', $fotoUrl);
        $image->setAttribute('x', '0');
        $image->setAttribute('y', '0');
        $image->setAttribute('width', '1');
        $image->setAttribute('height', '1');
        $image->setAttribute('preserveAspectRatio', 'xMidYMid slice');

        $pattern->appendChild($image);
        $defs->appendChild($pattern);

        // 2. Processar a imagem da foto ou formas geométricas com a classe 'foto'
        $geometricTags = ['rect', 'circle', 'ellipse', 'polygon', 'path', 'image'];
        foreach ($geometricTags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            foreach ($elements as $el) {
                $deveSubstituir = false;

                if ($el->hasAttribute('class')) {
                    $classAttr = $el->getAttribute('class');
                    $classes = array_map('trim', explode(' ', $classAttr));
                    if (in_array('foto', $classes)) {
                        $deveSubstituir = true;
                    }
                }

                if ($el->hasAttribute('id') && $el->getAttribute('id') === 'foto-aluno-v2') {
                    $deveSubstituir = true;
                }

                if ($deveSubstituir) {
                    if ($tag === 'image') {
                        $el->setAttribute('href', $fotoUrl);
                        if ($el->hasAttribute('xlink:href')) {
                            $el->setAttribute('xlink:href', $fotoUrl);
                        }
                    } else {
                        // Aplica o pattern de preenchimento na forma geométrica
                        $el->setAttribute('fill', 'url(#'.$patternId.')');
                    }
                }
            }
        }

        // Salva o SVG resultante limpando a tag xml criada no loadXML
        $resultSvg = $dom->documentElement ? $dom->saveXML($dom->documentElement) : $dom->saveXML();

        // Mantém a compatibilidade com placeholders V1 caso o usuário prefira misturar
        return TemplateCrachaService::substituirVariaveis($resultSvg, $pessoa, $turma);
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

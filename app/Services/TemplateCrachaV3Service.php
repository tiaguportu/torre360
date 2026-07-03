<?php

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Models\Pessoa;
use App\Models\TemplateCrachaV3;
use App\Models\Turma;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfObject;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class TemplateCrachaV3Service
{
    /**
     * Retorna o valor de uma variável a partir do nome.
     */
    public static function getValorVariavelPorNome(string $nome, Pessoa $pessoa, ?Turma $turma = null): ?string
    {
        $nome = strtolower(trim(str_replace(['{', '}'], '', $nome)));

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
     * Gera o PDF consolidado dos crachás V3.
     */
    public static function gerarPdf(TemplateCrachaV3 $template, Collection $pessoasComTurma): DomPdfObject
    {
        $crachasProcessados = collect();
        $dadosJson = $template->dados_json ?? [];
        $elementosOriginais = $dadosJson['elementos'] ?? [];
        $fundo = $dadosJson['fundo'] ?? '#ffffff';

        foreach ($pessoasComTurma as $item) {
            $pessoa = $item->pessoa;
            $turma = $item->turma;

            // Se a turma for nula, tenta obter a turma da matrícula ativa
            if (! $turma) {
                $matriculaAtiva = $pessoa->matriculas()
                    ->where('situacao', SituacaoMatricula::ATIVA)
                    ->first();
                $turma = $matriculaAtiva?->turma;
            }

            $elementosProcessados = [];

            foreach ($elementosOriginais as $el) {
                $novoEl = $el;

                // Processar valores de variáveis
                if ($el['tipo'] === 'variavel') {
                    if ($el['variavel'] === '{foto}') {
                        $novoEl['foto_url'] = self::getFotoBase64($pessoa);
                    } else {
                        $novoEl['conteudo'] = self::getValorVariavelPorNome($el['variavel'], $pessoa, $turma) ?? '';
                    }
                }

                // Ajustar posições de pixel para pontos (pt = px * 0.75)
                $novoEl['x_pt'] = $el['x'] * 0.75;
                $novoEl['y_pt'] = $el['y'] * 0.75;
                $novoEl['w_pt'] = $el['largura'] * 0.75;
                $novoEl['h_pt'] = $el['altura'] * 0.75;

                // Ajustar estilos CSS
                $estilos = $el['estilos'] ?? [];

                // Conversão de tamanho de fonte
                if (isset($estilos['fontSize'])) {
                    $fontSizePx = (float) str_replace('px', '', $estilos['fontSize']);
                    $estilos['fontSizePt'] = ($fontSizePx * 0.75).'pt';
                }

                // Conversão de borderRadius
                if (isset($estilos['borderRadius']) && strpos($estilos['borderRadius'], '%') === false) {
                    $borderRadiusPx = (float) str_replace('px', '', $estilos['borderRadius']);
                    $estilos['borderRadiusPt'] = ($borderRadiusPx * 0.75).'pt';
                } else {
                    $estilos['borderRadiusPt'] = $estilos['borderRadius'] ?? '0px';
                }

                // Conversão de borderWidth
                if (isset($estilos['borderWidth'])) {
                    $borderWidthPx = (float) str_replace('px', '', $estilos['borderWidth']);
                    $estilos['borderWidthPt'] = ($borderWidthPx * 0.75).'pt';
                }

                $novoEl['estilos_processados'] = $estilos;
                $elementosProcessados[] = $novoEl;
            }

            $crachasProcessados->push((object) [
                'pessoa' => $pessoa,
                'turma' => $turma,
                'elementos' => $elementosProcessados,
            ]);
        }

        // Dimensões do crachá em pontos
        $crachaLargura = $template->largura * 0.75;
        $crachaAltura = $template->altura * 0.75;

        return Pdf::loadView('pdf.cracha-lote-v3', [
            'crachas' => $crachasProcessados,
            'fundo' => $fundo,
            'crachaLargura' => $crachaLargura,
            'crachaAltura' => $crachaAltura,
        ])->setPaper('a4', 'portrait');
    }
}

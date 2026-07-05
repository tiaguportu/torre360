<?php

namespace App\Services;

use App\Models\Contrato;
use Illuminate\Support\Facades\Blade;

class ContractTemplateService
{
    public function process(Contrato $contrato, string $html): string
    {
        // Carrega relações necessárias caso não estejam presentes
        $contrato->loadMissing([
            'matricula.pessoa.responsaveis',
            'matricula.turma.serie.curso.unidade.representantesLegais',
            'responsaveisFinanceiros.pessoa.enderecos',
            'faturas',
        ]);

        $unidade = $contrato->matricula?->turma?->serie?->curso?->unidade;
        $aluno = $contrato->matricula?->pessoa;

        $html = $this->preprocessBlade($html);

        try {
            return Blade::render($html, [
                'contrato' => $contrato,
                'unidade' => $unidade,
                'aluno' => $aluno,
                'responsaveis' => $contrato->responsaveisFinanceiros,
                'faturas' => $contrato->faturas,
            ]);
        } catch (\Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }
            logger()->error('Erro ao renderizar template de contrato com Blade: '.$e->getMessage());

            return $html;
        }
    }

    protected function preprocessBlade(string $content): string
    {
        // Remove macros legadas do tipo {{MACRO}} ou {{MACRO.SUB}}
        $content = preg_replace('/\{\{[A-Z_]+(?:\.[A-Z_]+)*\}\}/', '', $content);

        // Decodifica entidades HTML apenas dentro de tags de expressão {{ ... }}
        $content = preg_replace_callback('/\{\{(.*?)\}\}/s', function ($matches) {
            $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            $decoded = str_replace(chr(194).chr(160), ' ', $decoded); // Substitui non-breaking spaces
            $decoded = str_replace('&nbsp;', ' ', $decoded);

            return '{{'.$decoded.'}}';
        }, $content);

        // Decodifica entidades HTML apenas dentro de diretivas com parênteses, ex: @if(...), @foreach(...)
        $content = preg_replace_callback('/@(\w+)\s*\((.*?)\)/s', function ($matches) {
            $decoded = html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');
            $decoded = str_replace(chr(194).chr(160), ' ', $decoded);
            $decoded = str_replace('&nbsp;', ' ', $decoded);

            return '@'.$matches[1].'('.$decoded.')';
        }, $content);

        // Também decodifica diretivas de fechamento comuns coladas ou com entidades
        $content = preg_replace_callback('/@(\w+)/', function ($matches) {
            $directive = $matches[1];
            // Se for diretiva de fechamento comum do blade, garante que esteja limpa
            $closers = ['endif', 'endforeach', 'endwhile', 'empty', 'else'];
            if (in_array(strtolower($directive), $closers)) {
                return '@'.strtolower($directive);
            }

            return $matches[0];
        }, $content);

        return $content;
    }
}

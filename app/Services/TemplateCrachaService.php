<?php

namespace App\Services;

use App\Enums\SituacaoMatricula;
use App\Enums\TemplateCrachaEntidade;
use App\Models\Pessoa;
use App\Models\Turma;
use Carbon\Carbon;

class TemplateCrachaService
{
    /**
     * Retorna a lista de variáveis estruturadas por grupo e seus labels de exibição.
     */
    public static function getVariaveisPorEntidade(TemplateCrachaEntidade $entidade): array
    {
        $variaveisPessoa = [
            '{foto}' => 'Foto da Pessoa',
            '{nome}' => 'Nome Completo',
            '{profissao}' => 'Profissão',
            '{cpf}' => 'CPF',
            '{identidade}' => 'Identidade (RG)',
            '{email}' => 'E-mail',
            '{telefone}' => 'Telefone',
            '{data_nascimento}' => 'Nascimento',
            '{sexo}' => 'Sexo',
            '{cor_raca}' => 'Cor / Raça',
        ];

        return match ($entidade) {
            TemplateCrachaEntidade::PESSOA => [
                'Variáveis de Pessoa' => $variaveisPessoa,
            ],
            TemplateCrachaEntidade::TURMA => [
                'Variáveis de Turma' => [
                    '{turma_nome}' => 'Nome da Turma',
                    '{turma_periodo}' => 'Período Escolar',
                    '{turma_serie}' => 'Série / Ano',
                    '{turma_curso}' => 'Curso',
                ],
                'Variáveis do Aluno (Pessoa)' => $variaveisPessoa,
            ],
        };
    }

    /**
     * Substitui os marcadores no texto com as informações reais da pessoa e turma correspondentes.
     */
    public static function substituirVariaveis(string $texto, Pessoa $pessoa, ?Turma $turma = null): string
    {
        // Se a turma for nula, tentamos obter a turma da matrícula ativa da pessoa
        if (! $turma) {
            $matriculaAtiva = $pessoa->matriculas()
                ->where('situacao', SituacaoMatricula::ATIVA)
                ->first();
            $turma = $matriculaAtiva?->turma;
        }

        $valores = [
            '{nome}' => $pessoa->nome ?? '',
            '{cpf}' => $pessoa->cpf ?? '',
            '{email}' => $pessoa->email ?? '',
            '{telefone}' => $pessoa->telefone ?? '',
            '{profissao}' => $pessoa->profissao ?? '',
            '{identidade}' => $pessoa->identidade ?? '',
            '{data_nascimento}' => $pessoa->data_nascimento ? Carbon::parse($pessoa->data_nascimento)->format('d/m/Y') : '',
            '{sexo}' => $pessoa->sexo?->value ?? $pessoa->sexo ?? '',
            '{cor_raca}' => $pessoa->cor_raca?->value ?? $pessoa->cor_raca ?? '',

            // Dados de Turma
            '{turma_nome}' => $turma?->nome ?? '',
            '{turma_periodo}' => $turma?->periodoLetivo?->nome ?? '',
            '{turma_serie}' => $turma?->serie?->nome ?? '',
            '{turma_curso}' => $turma?->serie?->curso?->nome ?? '',
        ];

        return str_replace(array_keys($valores), array_values($valores), $texto);
    }
}

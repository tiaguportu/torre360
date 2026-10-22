<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Situação do aluno na 2ª etapa do Censo Escolar (campo 8 do registro 90 e 10 do registro 91), conforme o leiaute de
 * importação da Situação do Aluno do Inep (Censo 2025, versão 3, que trocou os códigos 7 e 8).
 */
enum SituacaoAlunoCenso: int implements HasColor, HasLabel
{
    case Transferido = 1;
    case DeixouDeFrequentar = 2;
    case Falecido = 3;
    case Reprovado = 4;
    case Aprovado = 5;
    case AprovadoConcluinte = 6;
    case SemMovimentacao = 7;
    case EmAndamento = 8;

    /** Etapas de Educação Infantil (creche, pré-escola e a turma unificada). */
    public const ETAPAS_EDUCACAO_INFANTIL = ['1', '2', '3'];

    /** Etapas em que o aluno pode ser "aprovado concluinte" (regra 4 do campo 8 do registro 90). */
    public const ETAPAS_FINAIS = ['27', '28', '29', '37', '38', '39', '40', '41', '67', '68', '70', '71', '73', '74'];

    /** Etapas em que o curso pode estar "em andamento" (regra 5). */
    public const ETAPAS_EM_ANDAMENTO = ['39', '40', '67', '68', '69', '70', '71', '73', '74'];

    /** Turmas com mais de uma etapa: no registro 91 o Inep pede a etapa do aluno. */
    public const ETAPAS_MULTI = ['3', '22', '23', '56', '64', '72'];

    public function getLabel(): string
    {
        return match ($this) {
            self::Transferido => 'Transferido',
            self::DeixouDeFrequentar => 'Deixou de frequentar',
            self::Falecido => 'Falecido',
            self::Reprovado => 'Reprovado',
            self::Aprovado => 'Aprovado',
            self::AprovadoConcluinte => 'Aprovado concluinte',
            self::SemMovimentacao => 'Sem movimentação',
            self::EmAndamento => 'Em andamento',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aprovado, self::AprovadoConcluinte => 'success',
            self::Reprovado => 'danger',
            self::Transferido, self::DeixouDeFrequentar => 'warning',
            self::Falecido => 'gray',
            self::SemMovimentacao, self::EmAndamento => 'info',
        };
    }

    /**
     * Motivo pelo qual a situação não cabe na etapa da matrícula, ou null se cabe. Etapa vazia (turma sem etapa do
     * Educacenso) não é validada aqui.
     */
    public function restricaoParaEtapa(string $etapa): ?string
    {
        if ($etapa === '') {
            return null;
        }

        $infantil = in_array($etapa, self::ETAPAS_EDUCACAO_INFANTIL, true);

        if ($infantil && ! in_array($this, [self::Transferido, self::DeixouDeFrequentar, self::Falecido, self::SemMovimentacao], true)) {
            return 'Na Educação Infantil só cabem: transferido, deixou de frequentar, falecido ou sem movimentação.';
        }

        return match ($this) {
            self::SemMovimentacao => $infantil ? null : '"Sem movimentação" só vale para a Educação Infantil.',
            self::AprovadoConcluinte => in_array($etapa, self::ETAPAS_FINAIS, true) ? null : '"Aprovado concluinte" só vale para a etapa final do curso (por exemplo, 9º ano ou 3ª série do Ensino Médio).',
            self::EmAndamento => in_array($etapa, self::ETAPAS_EM_ANDAMENTO, true) ? null : '"Em andamento" só vale para EJA e cursos técnicos em etapas modulares.',
            default => null,
        };
    }
}

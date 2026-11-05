<?php

declare(strict_types=1);

namespace App\Support\Qualificacao;

use App\Models\InteressadoDependente;
use App\Models\InteressadoQualificacao;

/**
 * Roteiro de qualificação do lead (SDR): a lista fechada de campos, na ordem em que a conversa deve percorrê-los.
 *
 * Ordem: encaixe (série, nascimento, turno, bairro) → descoberta (situação atual, escola atual, motivo, escolas em
 * comparação) → decisão (quem decide, se vai à visita, prazo) → dinheiro por último e fora da pergunta automática.
 * Os campos marcados com `gate` são os que bastam para marcar a visita; a mensalidade nunca é exigida.
 *
 * A lista é de propósito fechada e em código: renda exata, saúde e necessidades especiais da criança não são campos de
 * qualificação (dado sensível, LGPD art. 11), e quem preenche o roteiro (consultor, IA) só pode gravar o que está aqui.
 */
final class Roteiro
{
    public const NIVEL_FAMILIA = 'familia';

    public const NIVEL_ALUNO = 'aluno';

    public const ESTADO_NAO_PERGUNTADO = 'nao_perguntado';

    public const ESTADO_PERGUNTADO = 'perguntado';

    public const ESTADO_RESPONDIDO = 'respondido';

    public const ESTADO_RECUSOU = 'recusou';

    /** Campo que não se aplica a este lead (ex.: escola atual de quem ainda não estuda em escola). */
    public const ESTADO_NAO_APLICAVEL = 'nao_aplicavel';

    public const DIMENSOES = [
        'encaixe' => 'Encaixe',
        'necessidade' => 'Necessidade',
        'autoridade' => 'Autoridade',
        'urgencia' => 'Urgência',
        'dinheiro' => 'Dinheiro',
    ];

    /** @var list<CampoQualificacao>|null */
    private static ?array $campos = null;

    /**
     * @return list<CampoQualificacao>
     */
    public static function campos(): array
    {
        return self::$campos ??= [
            new CampoQualificacao('serie_id', self::NIVEL_ALUNO, 'encaixe', 'Série pretendida', 'Para qual série (ano) vocês procuram vaga?', gate: true),
            new CampoQualificacao('data_nascimento', self::NIVEL_ALUNO, 'encaixe', 'Data de nascimento', 'Qual a data de nascimento do(a) aluno(a)?', gate: true),
            new CampoQualificacao('turno_preferencia', self::NIVEL_ALUNO, 'encaixe', 'Turno', 'Vocês preferem o turno da manhã, o da tarde ou o período integral?', gate: true),
            new CampoQualificacao('bairro', self::NIVEL_FAMILIA, 'encaixe', 'Bairro', 'Em qual bairro vocês moram?', gate: true),
            new CampoQualificacao('situacao_atual', self::NIVEL_ALUNO, 'necessidade', 'Situação atual do aluno', 'O(a) aluno(a) já estuda em alguma escola hoje ou será a primeira escola?', gate: true),
            new CampoQualificacao('escola_atual', self::NIVEL_ALUNO, 'necessidade', 'Escola atual', 'Em qual escola ele(a) estuda hoje?'),
            new CampoQualificacao('motivo_busca', self::NIVEL_FAMILIA, 'necessidade', 'Motivo da busca', 'O que está motivando a procura por uma escola nova?', gate: true),
            new CampoQualificacao('escolas_em_comparacao', self::NIVEL_FAMILIA, 'necessidade', 'Escolas em comparação', 'Vocês estão conhecendo outras escolas também?'),
            new CampoQualificacao('decisor_tipo', self::NIVEL_FAMILIA, 'autoridade', 'Quem decide', 'A decisão é sua ou vocês conversam com mais alguém da família?', gate: true),
            new CampoQualificacao('decisor_vai_visita', self::NIVEL_FAMILIA, 'autoridade', 'Decisor na visita', 'Quem participa da decisão poderá vir conhecer a escola com você?', gate: true),
            new CampoQualificacao('prazo_decisao', self::NIVEL_FAMILIA, 'urgencia', 'Prazo de decisão', 'Para quando vocês precisam definir a escola?', gate: true),
            new CampoQualificacao('faixa_mensalidade', self::NIVEL_FAMILIA, 'dinheiro', 'Faixa de mensalidade', 'Qual faixa de mensalidade cabe no orçamento da família?', perguntarAutomaticamente: false),
        ];
    }

    public static function campo(string $chave): ?CampoQualificacao
    {
        foreach (self::campos() as $campo) {
            if ($campo->chave === $chave) {
                return $campo;
            }
        }

        return null;
    }

    /**
     * Campos que bastam para marcar a visita.
     *
     * @return list<CampoQualificacao>
     */
    public static function gate(): array
    {
        return array_values(array_filter(self::campos(), fn (CampoQualificacao $campo): bool => $campo->gate));
    }

    /**
     * Colunas de `interessado_qualificacao` que são campos do roteiro.
     *
     * @return list<string>
     */
    public static function colunasDaFamilia(): array
    {
        return array_values(array_map(
            fn (CampoQualificacao $campo): string => $campo->chave,
            array_filter(self::campos(), fn (CampoQualificacao $campo): bool => $campo->ehDaFamilia()),
        ));
    }

    /**
     * Opções fechadas do campo (valor => rótulo), ou null quando o campo é livre.
     *
     * @return array<string, string>|null
     */
    public static function opcoes(string $chave): ?array
    {
        return match ($chave) {
            'decisor_tipo' => InteressadoQualificacao::DECISOR_TIPOS,
            'decisor_vai_visita' => InteressadoQualificacao::DECISOR_VAI_VISITA,
            'motivo_busca' => InteressadoQualificacao::MOTIVOS_BUSCA,
            'prazo_decisao' => InteressadoQualificacao::PRAZOS_DECISAO,
            'faixa_mensalidade' => InteressadoQualificacao::faixasMensalidade(),
            'situacao_atual' => InteressadoDependente::SITUACOES_ATUAIS,
            'turno_preferencia' => array_combine(InteressadoDependente::TURNOS_PREFERENCIA, InteressadoDependente::TURNOS_PREFERENCIA),
            default => null,
        };
    }
}

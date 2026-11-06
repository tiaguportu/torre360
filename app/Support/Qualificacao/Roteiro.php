<?php

declare(strict_types=1);

namespace App\Support\Qualificacao;

use App\Models\InteressadoDependente;
use App\Models\InteressadoQualificacao;
use App\Models\QualificacaoRoteiroConfiguracao;
use WeakMap;

/**
 * Roteiro de qualificação do lead (SDR): a lista fechada de campos, na ordem em que a conversa deve percorrê-los.
 *
 * Ordem de fábrica: encaixe (série, nascimento, turno, bairro) → descoberta (situação atual, escola atual, motivo,
 * escolas em comparação) → decisão (quem decide, se vai à visita, prazo) → dinheiro por último e fora da pergunta
 * automática. Os campos marcados com `gate` são os que bastam para marcar a visita; a mensalidade nunca é exigida.
 *
 * A lista de **campos** é de propósito fechada e em código: renda exata, saúde e necessidades especiais da criança não são
 * campos de qualificação (dado sensível, LGPD art. 11), e quem preenche o roteiro (consultor, IA) só pode gravar o que
 * está aqui. A secretaria personaliza, em "Roteiro de qualificação" (CRM / Comercial), só o **texto sugerido da pergunta**,
 * a **ordem**, se o campo é **necessário para marcar a visita** e se ele está **ativo**; ver {@see QualificacaoRoteiroConfiguracao}.
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

    /** Campos que nunca entram no `gate`, qualquer que seja a personalização: a mensalidade nunca é exigida. */
    public const FORA_DO_GATE = ['faixa_mensalidade'];

    /** Tamanho máximo do texto personalizado de uma pergunta. */
    public const MAX_PERGUNTA = 300;

    /** @var WeakMap<object, list<CampoQualificacao>>|null */
    private static ?WeakMap $memoria = null;

    /**
     * Os campos que estão no roteiro (ativos), na ordem configurada.
     *
     * @return list<CampoQualificacao>
     */
    public static function campos(): array
    {
        return array_values(array_filter(self::todos(), fn (CampoQualificacao $campo): bool => $campo->ativo));
    }

    /**
     * Todos os campos, ativos ou não, na ordem configurada, já com a personalização da secretaria aplicada. O resultado
     * fica guardado por aplicação (uma requisição, ou um teste), então chamar isto em laço é barato.
     *
     * @return list<CampoQualificacao>
     */
    public static function todos(): array
    {
        self::$memoria ??= new WeakMap;

        return self::$memoria[app()] ??= self::aplicar(self::padroes(), QualificacaoRoteiroConfiguracao::sobrescritas());
    }

    /**
     * Descarta o que ficou guardado: chamar depois de salvar ou restaurar a personalização.
     */
    public static function esquecer(): void
    {
        self::$memoria = null;
    }

    public static function campo(string $chave): ?CampoQualificacao
    {
        foreach (self::todos() as $campo) {
            if ($campo->chave === $chave) {
                return $campo;
            }
        }

        return null;
    }

    /** O campo existe e está ativo no roteiro (as telas escondem os campos desligados). */
    public static function estaAtivo(string $chave): bool
    {
        return self::campo($chave)?->ativo ?? false;
    }

    /**
     * Campos ativos que bastam para marcar a visita.
     *
     * @return list<CampoQualificacao>
     */
    public static function gate(): array
    {
        return array_values(array_filter(self::campos(), fn (CampoQualificacao $campo): bool => $campo->gate));
    }

    /**
     * Colunas de `interessado_qualificacao` que são campos do roteiro (todas, ativas ou não: a procedência de uma
     * resposta é registrada mesmo quando o campo foi desligado depois).
     *
     * @return list<string>
     */
    public static function colunasDaFamilia(): array
    {
        return array_values(array_map(
            fn (CampoQualificacao $campo): string => $campo->chave,
            array_filter(self::padroes(), fn (CampoQualificacao $campo): bool => $campo->ehDaFamilia()),
        ));
    }

    /**
     * Os campos como saem de fábrica, na ordem de fábrica (espaçada de 10 em 10 para a personalização intercalar).
     *
     * @return list<CampoQualificacao>
     */
    public static function padroes(): array
    {
        return [
            new CampoQualificacao('serie_id', self::NIVEL_ALUNO, 'encaixe', 'Série pretendida', 'Para qual série (ano) vocês procuram vaga?', gate: true, ordem: 10),
            new CampoQualificacao('data_nascimento', self::NIVEL_ALUNO, 'encaixe', 'Data de nascimento', 'Qual a data de nascimento do(a) aluno(a)?', gate: true, ordem: 20),
            new CampoQualificacao('turno_preferencia', self::NIVEL_ALUNO, 'encaixe', 'Turno', 'Vocês preferem o turno da manhã, o da tarde ou o período integral?', gate: true, ordem: 30),
            new CampoQualificacao('bairro', self::NIVEL_FAMILIA, 'encaixe', 'Bairro', 'Em qual bairro vocês moram?', gate: true, ordem: 40),
            new CampoQualificacao('situacao_atual', self::NIVEL_ALUNO, 'necessidade', 'Situação atual do aluno', 'O(a) aluno(a) já estuda em alguma escola hoje ou será a primeira escola?', gate: true, ordem: 50),
            new CampoQualificacao('escola_atual', self::NIVEL_ALUNO, 'necessidade', 'Escola atual', 'Em qual escola ele(a) estuda hoje?', ordem: 60),
            new CampoQualificacao('motivo_busca', self::NIVEL_FAMILIA, 'necessidade', 'Motivo da busca', 'O que está motivando a procura por uma escola nova?', gate: true, ordem: 70),
            new CampoQualificacao('escolas_em_comparacao', self::NIVEL_FAMILIA, 'necessidade', 'Escolas em comparação', 'Vocês estão conhecendo outras escolas também?', ordem: 80),
            new CampoQualificacao('decisor_tipo', self::NIVEL_FAMILIA, 'autoridade', 'Quem decide', 'A decisão é sua ou vocês conversam com mais alguém da família?', gate: true, ordem: 90),
            new CampoQualificacao('decisor_vai_visita', self::NIVEL_FAMILIA, 'autoridade', 'Decisor na visita', 'Quem participa da decisão poderá vir conhecer a escola com você?', gate: true, ordem: 100),
            new CampoQualificacao('prazo_decisao', self::NIVEL_FAMILIA, 'urgencia', 'Prazo de decisão', 'Para quando vocês precisam definir a escola?', gate: true, ordem: 110),
            new CampoQualificacao('faixa_mensalidade', self::NIVEL_FAMILIA, 'dinheiro', 'Faixa de mensalidade', 'Qual faixa de mensalidade cabe no orçamento da família?', perguntarAutomaticamente: false, ordem: 120),
        ];
    }

    /**
     * Aplica a personalização salva aos campos de fábrica. Valor ausente ou inválido cai no padrão do campo.
     *
     * @param  list<CampoQualificacao>  $padroes
     * @param  array<string, mixed>  $sobrescritas  No formato de {@see QualificacaoRoteiroConfiguracao}: `['campos' => [chave => [...]]]`.
     * @return list<CampoQualificacao>
     */
    public static function aplicar(array $padroes, array $sobrescritas): array
    {
        $campos = [];

        foreach ($padroes as $padrao) {
            $ajuste = is_array($sobrescritas['campos'][$padrao->chave] ?? null) ? $sobrescritas['campos'][$padrao->chave] : [];

            $pergunta = is_string($ajuste['pergunta'] ?? null) && trim($ajuste['pergunta']) !== ''
                ? mb_substr(trim($ajuste['pergunta']), 0, self::MAX_PERGUNTA)
                : $padrao->pergunta;

            $gate = in_array($padrao->chave, self::FORA_DO_GATE, true)
                ? false
                : (array_key_exists('gate', $ajuste) ? (bool) $ajuste['gate'] : $padrao->gate);

            $campos[] = new CampoQualificacao(
                chave: $padrao->chave,
                nivel: $padrao->nivel,
                dimensao: $padrao->dimensao,
                rotulo: $padrao->rotulo,
                pergunta: $pergunta,
                gate: $gate,
                perguntarAutomaticamente: $padrao->perguntarAutomaticamente,
                ordem: is_numeric($ajuste['ordem'] ?? null) ? (int) $ajuste['ordem'] : $padrao->ordem,
                ativo: array_key_exists('ativo', $ajuste) ? (bool) $ajuste['ativo'] : true,
            );
        }

        // usort é estável: campos com a mesma ordem mantêm a ordem de fábrica.
        usort($campos, fn (CampoQualificacao $a, CampoQualificacao $b): int => $a->ordem <=> $b->ordem);

        return $campos;
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

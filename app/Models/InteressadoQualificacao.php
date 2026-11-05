<?php

namespace App\Models;

use App\Models\Concerns\InvalidaDossieIaDoLead;
use App\Support\Qualificacao\Roteiro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O que a família disse ao lead sobre quem decide, o motivo da busca, o prazo, o bairro e a mensalidade (roteiro de
 * qualificação do SDR; ver {@see Roteiro}). O que é de cada aluno fica em {@see InteressadoDependente}.
 *
 * `resultado` e `campos` (procedência de cada resposta) ficam fora do `$fillable`: quem grava é o QualificacaoService.
 */
class InteressadoQualificacao extends Model
{
    use InvalidaDossieIaDoLead;

    protected $table = 'interessado_qualificacao';

    public const DECISOR_TIPOS = [
        'sozinho' => 'Decide sozinho(a)',
        'com_conjuge' => 'Decide com o cônjuge ou companheiro(a)',
        'outro_familiar' => 'Outro familiar decide',
        'empresa_convenio' => 'Empresa ou convênio',
    ];

    public const DECISOR_VAI_VISITA = [
        'sim' => 'Sim',
        'nao' => 'Não',
        'a_definir' => 'A definir',
    ];

    public const MOTIVOS_BUSCA = [
        'primeira_escola' => 'Primeira escola',
        'insatisfacao_pedagogica' => 'Insatisfação pedagógica',
        'insatisfacao_atendimento' => 'Insatisfação com o atendimento',
        'preco_escola_atual' => 'Preço da escola atual',
        'mudanca' => 'Mudança de cidade ou bairro',
        'horario_jornada' => 'Horário e jornada (período integral)',
        'seguranca_estrutura' => 'Segurança ou estrutura',
        'serie_seguinte_indisponivel' => 'A escola atual não oferece a série seguinte',
        'indicacao' => 'Indicação',
        'outro' => 'Outro motivo',
    ];

    public const PRAZOS_DECISAO = [
        'esta_semana' => 'Esta semana',
        'este_mes' => 'Este mês',
        'antes_do_inicio_das_aulas' => 'Antes do início das aulas',
        'sem_prazo' => 'Sem prazo definido',
    ];

    public const RESULTADO_NAO_INICIADA = 'nao_iniciada';

    public const RESULTADO_EM_QUALIFICACAO = 'em_qualificacao';

    public const RESULTADO_QUALIFICADO = 'qualificado';

    public const RESULTADOS = [
        self::RESULTADO_NAO_INICIADA => 'Não iniciada',
        self::RESULTADO_EM_QUALIFICACAO => 'Em qualificação',
        self::RESULTADO_QUALIFICADO => 'Qualificado para a visita',
    ];

    public const FONTE_FORMULARIO = 'formulario';

    public const FONTE_CONSULTOR = 'consultor';

    public const FONTE_IA_CONFIRMADA = 'ia_confirmada';

    public const FONTE_IMPORTACAO = 'importacao';

    public const FONTES = [
        self::FONTE_FORMULARIO => 'Formulário do site',
        self::FONTE_CONSULTOR => 'Consultor',
        self::FONTE_IA_CONFIRMADA => 'IA, confirmada pelo consultor',
        self::FONTE_IMPORTACAO => 'Importação',
    ];

    protected $fillable = [
        'decisor_tipo',
        'decisor_vai_visita',
        'motivo_busca',
        'prazo_decisao',
        'bairro',
        'escolas_em_comparacao',
        'faixa_mensalidade',
    ];

    protected function casts(): array
    {
        return [
            'escolas_em_comparacao' => 'array',
            'campos' => 'array',
            'qualificado_em' => 'datetime',
        ];
    }

    /**
     * Faixas de mensalidade (chave => rótulo), configuráveis em `crm.qualificacao.faixas_mensalidade` conforme a
     * tabela de preços da escola.
     *
     * @return array<string, string>
     */
    public static function faixasMensalidade(): array
    {
        return (array) config('crm.qualificacao.faixas_mensalidade', []);
    }

    /**
     * Quem editou o campo pela ficha (e não pelo serviço) vira a procedência "consultor": o serviço, quando grava,
     * já preenche `campos` e este gancho não interfere.
     */
    protected static function booted(): void
    {
        static::saving(function (self $qualificacao): void {
            if ($qualificacao->isDirty('campos')) {
                return;
            }

            $campos = $qualificacao->campos ?? [];

            foreach (Roteiro::colunasDaFamilia() as $coluna) {
                if (! $qualificacao->isDirty($coluna)) {
                    continue;
                }

                if (blank($qualificacao->getAttribute($coluna))) {
                    // Limpar o campo o devolve a "não perguntado".
                    unset($campos[$coluna]);

                    continue;
                }

                $campos[$coluna] = [
                    'estado' => Roteiro::ESTADO_RESPONDIDO,
                    'fonte' => self::FONTE_CONSULTOR,
                    'evidencia' => null,
                    'atualizado_em' => now()->toIso8601String(),
                    'atualizado_por' => auth()->id(),
                ];
            }

            $qualificacao->campos = $campos === [] ? null : $campos;
        });
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }
}

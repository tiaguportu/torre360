<?php

namespace App\Models;

use App\Models\Concerns\InvalidaDossieIaDoLead;
use App\Services\Qualificacao\QualificacaoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Throwable;

class InteressadoDependente extends Model
{
    use InvalidaDossieIaDoLead;

    protected $table = 'interessado_dependente';

    /** Turnos aceitos como preferência no formulário público (`Sem preferência` inclusive). */
    public const TURNOS_PREFERENCIA = ['Manhã', 'Tarde', 'Integral', 'Sem preferência'];

    /** Onde o aluno estuda hoje (roteiro de qualificação). */
    public const SITUACOES_ATUAIS = [
        'primeira_escola' => 'Primeira escola',
        'em_outra_escola' => 'Estuda em outra escola',
        'em_casa_ou_cuidador' => 'Está em casa ou com cuidador',
    ];

    // `qualificacao_meta` (procedência de cada resposta) fica fora: quem grava é o QualificacaoService.
    protected $fillable = ['interessado_id', 'nome_crianca', 'serie_id', 'unidade_id', 'turno_preferencia', 'vinculo', 'data_nascimento', 'situacao_atual', 'escola_atual', 'escola_atual_concorrente_id'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'qualificacao_meta' => 'array',
        ];
    }

    /**
     * Mexer num aluno muda o que o roteiro de qualificação do lead sabe (série, turno, nascimento, escola atual): o
     * resultado é recalculado. É um cálculo derivado, então uma falha aqui nunca pode impedir o cadastro do aluno.
     */
    protected static function booted(): void
    {
        $reavaliar = static function (self $dependente): void {
            try {
                $lead = Interessado::query()->find($dependente->interessado_id);

                if ($lead) {
                    app(QualificacaoService::class)->reavaliar($lead);
                }
            } catch (Throwable $e) {
                report($e);
            }
        };

        static::saved($reavaliar);
        static::deleted($reavaliar);
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }

    public function escolaAtualConcorrente(): BelongsTo
    {
        return $this->belongsTo(Concorrente::class, 'escola_atual_concorrente_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * Nome sem caixa, acentos e espaços repetidos: base para reconhecer o mesmo aluno digitado
     * de formas diferentes ("Lucas  Silva" / "lucas silva").
     */
    public static function nomeNormalizado(?string $nome): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim(Str::ascii((string) $nome)))) ?? '';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InteressadoDependente extends Model
{
    protected $table = 'interessado_dependente';

    /** Turnos aceitos como preferência no formulário público (`Sem preferência` inclusive). */
    public const TURNOS_PREFERENCIA = ['Manhã', 'Tarde', 'Integral', 'Sem preferência'];

    protected $fillable = ['interessado_id', 'nome_crianca', 'serie_id', 'unidade_id', 'turno_preferencia', 'vinculo', 'data_nascimento'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
        ];
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
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

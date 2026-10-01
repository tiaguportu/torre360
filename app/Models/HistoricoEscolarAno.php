<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoricoEscolarAno extends Model
{
    use HasFactory;

    protected $table = 'historico_escolar_anos';

    protected $fillable = [
        'historico_escolar_id',
        'matricula_id',
        'ano_letivo',
        'serie_id',
        'serie_nome',
        'ordem',
        'tipo',
        'escola_nome',
        'escola_cidade',
        'escola_uf',
        'dias_letivos',
        'carga_horaria_total',
        'frequencia_percentual',
        'situacao_ano',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'ano_letivo' => 'integer',
            'ordem' => 'integer',
            'dias_letivos' => 'integer',
            'carga_horaria_total' => 'integer',
            'frequencia_percentual' => 'decimal:2',
        ];
    }

    public function historicoEscolar(): BelongsTo
    {
        return $this->belongsTo(HistoricoEscolar::class, 'historico_escolar_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }

    public function disciplinas(): HasMany
    {
        return $this->hasMany(HistoricoEscolarDisciplina::class, 'historico_escolar_ano_id')->orderBy('ordem');
    }
}

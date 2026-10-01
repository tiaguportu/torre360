<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoEscolarDisciplina extends Model
{
    use HasFactory;

    protected $table = 'historico_escolar_disciplinas';

    protected $fillable = [
        'historico_escolar_ano_id',
        'disciplina_id',
        'disciplina_nome',
        'area_conhecimento',
        'carga_horaria',
        'nota_final',
        'conceito',
        'situacao',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'carga_horaria' => 'integer',
            'nota_final' => 'decimal:2',
            'ordem' => 'integer',
        ];
    }

    public function ano(): BelongsTo
    {
        return $this->belongsTo(HistoricoEscolarAno::class, 'historico_escolar_ano_id');
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class, 'disciplina_id');
    }
}

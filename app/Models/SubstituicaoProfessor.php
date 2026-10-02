<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubstituicaoProfessor extends Model
{
    use HasFactory;

    protected $table = 'substituicoes_professor';

    protected $fillable = [
        'turma_id',
        'disciplina_id',
        'professor_titular_id',
        'professor_substituto_id',
        'data_inicio',
        'data_fim',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function professorTitular(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_titular_id');
    }

    public function professorSubstituto(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_substituto_id');
    }

    public function isAtiva(): bool
    {
        return $this->data_fim === null || $this->data_fim->isFuture() || $this->data_fim->isToday();
    }
}

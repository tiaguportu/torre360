<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habilidade extends Model
{
    use HasFactory;

    protected $table = 'habilidades';

    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'disciplina_id',
        'tipo',
    ];

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'turma_habilidade');
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(AvaliacaoHabilidade::class);
    }
}

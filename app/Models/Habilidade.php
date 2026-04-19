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
        'campo_experiencia_id',
        'tipo',
    ];

    public function campoExperiencia(): BelongsTo
    {
        return $this->belongsTo(CampoExperiencia::class);
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'turma_habilidade');
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(AvaliacaoHabilidade::class);
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'turma_habilidade')->withPivot('professor_id')->withTimestamps();
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }
}

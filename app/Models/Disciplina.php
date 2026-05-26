<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Disciplina extends Model
{
    use HasFactory;

    protected $table = 'disciplina';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ordem_boletim' => 'integer',
        ];
    }

    public function areaConhecimento(): BelongsTo
    {
        return $this->belongsTo(AreaConhecimento::class, 'area_id');
    }

    public function habilidades(): HasMany
    {
        return $this->hasMany(Habilidade::class);
    }

    public function cronogramasAula(): HasMany
    {
        return $this->hasMany(CronogramaAula::class);
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'turma_disciplina')->withPivot('professor_id')->withTimestamps();
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }
}

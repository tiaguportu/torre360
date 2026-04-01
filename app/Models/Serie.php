<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serie extends Model
{
    protected $table = 'serie';
    protected $guarded = [];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function habilidades(): HasMany
    {
        return $this->hasMany(Habilidade::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SituacaoMatricula extends Model
{
    protected $table = 'situacao_matricula';
    protected $guarded = [];

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }
}

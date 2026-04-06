<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turno extends Model
{
    protected $table = 'turno';

    protected $guarded = [];

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }
}

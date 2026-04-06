<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pais extends Model
{
    protected $table = 'pais';

    protected $guarded = [];

    public function estados(): HasMany
    {
        return $this->hasMany(Estado::class);
    }

    public function pessoas(): HasMany
    {
        return $this->hasMany(Pessoa::class, 'nacionalidade_id');
    }
}

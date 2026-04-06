<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sexo extends Model
{
    protected $table = 'sexo';

    protected $guarded = [];

    public function pessoas(): HasMany
    {
        return $this->hasMany(Pessoa::class);
    }
}

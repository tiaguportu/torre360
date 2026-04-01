<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Perfil extends Model
{
    protected $table = 'perfil';
    protected $guarded = [];

    public function pessoas(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'pessoa_perfil');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaRecursoAcessibilidade extends Model
{
    use HasFactory;

    protected $table = 'categoria_recurso_acessabilidades';

    protected $guarded = [];

    public function recursosAcessibilidade(): HasMany
    {
        return $this->hasMany(RecursoAcessibilidade::class, 'categoria_recurso_acessabilidade_id');
    }
}

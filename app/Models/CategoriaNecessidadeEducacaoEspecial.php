<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaNecessidadeEducacaoEspecial extends Model
{
    use HasFactory;

    protected $table = 'categoria_necessidade_educacao_especiais';

    protected $guarded = [];

    public function necessidadesEducacaoEspecial(): HasMany
    {
        return $this->hasMany(NecessidadeEducacaoEspecial::class, 'categoria_necessidade_educacao_especial_id');
    }
}

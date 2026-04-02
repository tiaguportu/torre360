<?php

namespace App\Models;

use Database\Factories\CategoriaAvaliacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaAvaliacao extends Model
{
    /** @use HasFactory<CategoriaAvaliacaoFactory> */
    use HasFactory;

    protected $table = 'categoria_avaliacao';

    protected $guarded = [];

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class, 'categoria_avaliacao_id');
    }
}

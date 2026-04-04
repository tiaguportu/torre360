<?php

namespace App\Models;

use Database\Factories\CategoriaAvaliacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * A categoria que esta categoria substitui.
     * Ex: "Prova Substitutiva" substitui "Prova 1".
     */
    public function substituicao(): BelongsTo
    {
        return $this->belongsTo(CategoriaAvaliacao::class, 'categoria_avaliacao_substituicao_id');
    }

    /**
     * Categorias que são substituídas por esta categoria.
     */
    public function substituidas(): HasMany
    {
        return $this->hasMany(CategoriaAvaliacao::class, 'categoria_avaliacao_substituicao_id');
    }
}

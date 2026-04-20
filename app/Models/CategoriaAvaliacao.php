<?php

namespace App\Models;

use Database\Factories\CategoriaAvaliacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaAvaliacao extends Model
{
    /** @use HasFactory<CategoriaAvaliacaoFactory> */
    use HasFactory;

    protected $table = 'categoria_avaliacao';

    protected $guarded = [];

    public function getNomeComDescricaoAttribute(): string
    {
        return $this->descricao ? "{$this->nome} - {$this->descricao}" : $this->nome;
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class, 'categoria_avaliacao_id');
    }

    /**
     * Categorias que SÃO SUBSTITUÍDAS por esta categoria.
     * Ex: "Prova Substitutiva" substitui ["Prova 1", "Prova 2"].
     */
    public function substituidas(): BelongsToMany
    {
        return $this->belongsToMany(
            CategoriaAvaliacao::class,
            'categoria_avaliacao_substituicao',
            'categoria_id',
            'substituida_id'
        )->withTimestamps();
    }

    /**
     * Categorias que SUBSTITUEM esta categoria.
     * Ex: "Prova 1" é substituída por ["Prova Substitutiva"].
     */
    public function substitutas(): BelongsToMany
    {
        return $this->belongsToMany(
            CategoriaAvaliacao::class,
            'categoria_avaliacao_substituicao',
            'substituida_id',
            'categoria_id'
        )->withTimestamps();
    }
}

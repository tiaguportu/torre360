<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NecessidadeEducacaoEspecial extends Model
{
    use HasFactory;

    protected $table = 'necessidade_educacao_especiais';

    protected $guarded = [];

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaNecessidadeEducacaoEspecial::class, 'categoria_necessidade_educacao_especial_id');
    }
}

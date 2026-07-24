<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecursoAcessibilidade extends Model
{
    use HasFactory;

    protected $table = 'recurso_acessabilidades';

    protected $guarded = [];

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaRecursoAcessibilidade::class, 'categoria_recurso_acessabilidade_id');
    }
}

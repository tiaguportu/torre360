<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranstornoAprendizagem extends Model
{
    use HasFactory;

    protected $table = 'transtorno_aprendizagens';

    protected $guarded = [];

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaTranstornoAprendizagem::class, 'categoria_transtorno_aprendizagem_id');
    }
}

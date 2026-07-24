<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaTranstornoAprendizagem extends Model
{
    use HasFactory;

    protected $table = 'categoria_transtorno_aprendizagens';

    protected $guarded = [];

    public function transtornosAprendizagem(): HasMany
    {
        return $this->hasMany(TranstornoAprendizagem::class, 'categoria_transtorno_aprendizagem_id');
    }
}

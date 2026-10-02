<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Livro extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'autor',
        'isbn',
        'categoria',
        'editora',
        'quantidade_total',
        'quantidade_disponivel',
    ];

    public function emprestimos(): HasMany
    {
        return $this->hasMany(Emprestimo::class);
    }

    public function temExemplarDisponivel(): bool
    {
        return $this->quantidade_disponivel > 0;
    }
}

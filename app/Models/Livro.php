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
        'capa',
    ];

    public function getCapaUrlAttribute(): ?string
    {
        if (! $this->capa) {
            return null;
        }

        return str_starts_with($this->capa, 'http')
            ? $this->capa
            : asset('storage/'.$this->capa);
    }

    public function emprestimos(): HasMany
    {
        return $this->hasMany(Emprestimo::class);
    }

    public function temExemplarDisponivel(): bool
    {
        return $this->quantidade_disponivel > 0;
    }
}

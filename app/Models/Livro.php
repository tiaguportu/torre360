<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Livro extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'titulo',
        'autor',
        'isbn',
        'categoria',
        'editora',
        'faixa_etaria',
        'segmentos',
        'quantidade_total',
        'quantidade_disponivel',
        'capa',
    ];

    protected function casts(): array
    {
        return [
            'segmentos' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Livro $livro) {
            if (empty($livro->codigo)) {
                $livro->updateQuietly([
                    'codigo' => 'LIV-'.str_pad((string) $livro->id, 5, '0', STR_PAD_LEFT),
                ]);
            }
        });
    }

    public function getIdentificadorLeitorAttribute(): string
    {
        return $this->codigo ?: ($this->isbn ?: 'LIV-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT));
    }

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

    public function inventarioItens(): HasMany
    {
        return $this->hasMany(InventarioItem::class);
    }

    public function temExemplarDisponivel(): bool
    {
        return $this->quantidade_disponivel > 0;
    }
}

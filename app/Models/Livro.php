<?php

namespace App\Models;

use App\Enums\StatusEmprestimo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

        // Excluir a obra apagaria em cascata (FK) os empréstimos, inclusive os ainda em aberto, e perderia o
        // controle de quem está com o exemplar. Retornar false cancela a exclusão (o Filament mostra a falha).
        static::deleting(function (Livro $livro): bool {
            return ! $livro->temEmprestimosEmAberto();
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

    /**
     * Empréstimos que ainda mantêm um exemplar fora do acervo (emprestados ou atrasados).
     */
    public function emprestimosEmAberto(): HasMany
    {
        return $this->emprestimos()->whereIn('status', [StatusEmprestimo::Emprestado, StatusEmprestimo::Atrasado]);
    }

    public function temEmprestimosEmAberto(): bool
    {
        return $this->emprestimosEmAberto()->exists();
    }

    /**
     * Exemplares disponíveis = total − empréstimos em aberto. É a fonte da verdade: o campo gravado
     * `quantidade_disponivel` é apenas o cache desse cálculo (e não deve ser digitado à mão).
     */
    public function disponibilidadeCalculada(?int $total = null): int
    {
        return max(0, ($total ?? (int) $this->quantidade_total) - $this->emprestimosEmAberto()->count());
    }

    /**
     * Regrava `quantidade_disponivel` a partir do total e dos empréstimos em aberto. Retorna true se mudou.
     */
    public function recalcularDisponibilidade(): bool
    {
        $correta = $this->disponibilidadeCalculada();

        if ((int) $this->quantidade_disponivel === $correta) {
            return false;
        }

        $this->forceFill(['quantidade_disponivel' => $correta])->saveQuietly();

        return true;
    }

    /**
     * Devolve um exemplar ao acervo disponível, sem passar do total cadastrado (atômico, num único UPDATE).
     */
    public static function devolverExemplar(int $livroId): void
    {
        static::query()->whereKey($livroId)->update([
            'quantidade_disponivel' => DB::raw('CASE WHEN quantidade_disponivel < quantidade_total THEN quantidade_disponivel + 1 ELSE quantidade_disponivel END'),
        ]);
    }
}

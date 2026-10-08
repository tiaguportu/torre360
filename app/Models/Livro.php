<?php

namespace App\Models;

use App\Enums\StatusEmprestimo;
use App\Services\LivroCapaService;
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

        // Excluir a obra apagaria em cascata os empréstimos e sacolas em aberto. Retornar false cancela a exclusão.
        static::deleting(function (Livro $livro): bool {
            return ! $livro->temEmprestimosEmAberto() && ! $livro->temSacolasEmAberto();
        });

        // Capa: a baixada pela busca por ISBN fica "pendente" até o livro ser salvo; aí vira um arquivo próprio.
        static::saving(function (Livro $livro): void {
            if ($livro->isDirty('capa')) {
                app(LivroCapaService::class)->promoverPendente($livro);
            }
        });

        // Trocar a capa ou excluir o livro apaga o arquivo antigo (se nenhum outro livro o usa).
        static::updated(function (Livro $livro): void {
            if ($livro->wasChanged('capa')) {
                app(LivroCapaService::class)->descartar($livro->getOriginal('capa'), $livro->id);
            }
        });

        static::deleted(function (Livro $livro): void {
            app(LivroCapaService::class)->descartar($livro->capa, $livro->id);
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

    public function sacolaItens(): HasMany
    {
        return $this->hasMany(SacolaLeituraItem::class);
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

    public function temSacolasEmAberto(): bool
    {
        return $this->sacolaItens()->where('devolvido', false)->exists();
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

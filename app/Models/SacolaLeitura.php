<?php

namespace App\Models;

use App\Enums\StatusSacolaLeitura;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SacolaLeitura extends Model
{
    use HasFactory;

    protected $table = 'sacolas_leitura';

    protected $attributes = [
        'status' => StatusSacolaLeitura::EmCirculacao,
    ];

    protected $fillable = [
        'codigo',
        'titulo',
        'turma_id',
        'responsavel_id',
        'user_id',
        'data_retirada',
        'data_prevista_devolucao',
        'data_devolucao',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_retirada' => 'date',
            'data_prevista_devolucao' => 'date',
            'data_devolucao' => 'date',
            'status' => StatusSacolaLeitura::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SacolaLeitura $sacola) {
            if (empty($sacola->codigo)) {
                $proximo = (static::query()->max('id') ?? 0) + 1;
                $sacola->codigo = 'SAC-'.str_pad((string) $proximo, 4, '0', STR_PAD_LEFT);
            }
        });

        static::created(function (SacolaLeitura $sacola) {
            $codigoEsperado = 'SAC-'.str_pad((string) $sacola->id, 4, '0', STR_PAD_LEFT);
            if ($sacola->codigo !== $codigoEsperado && str_starts_with((string) $sacola->codigo, 'SAC-')) {
                $sacola->updateQuietly([
                    'codigo' => $codigoEsperado,
                ]);
            }
        });

        // Ao excluir a sacola, qualquer livro ainda pendente é recolocado no acervo
        static::deleting(function (SacolaLeitura $sacola) {
            foreach ($sacola->itens()->where('devolvido', false)->get() as $item) {
                Livro::devolverExemplar((int) $item->livro_id);
            }
        });
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'responsavel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(SacolaLeituraItem::class, 'sacola_id');
    }

    public function totalLivros(): int
    {
        return $this->itens()->count();
    }

    public function totalDevolvidos(): int
    {
        return $this->itens()->where('devolvido', true)->count();
    }

    public function totalPendentes(): int
    {
        return $this->itens()->where('devolvido', false)->count();
    }

    public function isAtrasada(): bool
    {
        return $this->status !== StatusSacolaLeitura::Devolvida
            && $this->data_prevista_devolucao
            && $this->data_prevista_devolucao->isPast();
    }

    /**
     * Adiciona um livro à sacola reservando o exemplar do acervo de forma atômica.
     */
    public function adicionarLivro(Livro $livro): SacolaLeituraItem
    {
        return DB::transaction(function () use ($livro) {
            $reservados = Livro::query()
                ->whereKey($livro->id)
                ->where('quantidade_disponivel', '>', 0)
                ->decrement('quantidade_disponivel');

            if ($reservados === 0) {
                throw new \DomainException("O livro \"{$livro->titulo}\" não possui exemplares disponíveis no momento.");
            }

            return $this->itens()->create([
                'livro_id' => $livro->id,
                'devolvido' => false,
            ]);
        });
    }

    /**
     * Devolve um item específico da sacola de volta ao acervo.
     */
    public function devolverItem(SacolaLeituraItem $item, ?string $observacao = null): void
    {
        if ($item->devolvido) {
            return;
        }

        DB::transaction(function () use ($item, $observacao) {
            $item->update([
                'devolvido' => true,
                'devolvido_em' => now(),
                'observacao_devolucao' => $observacao ?: $item->observacao_devolucao,
            ]);

            Livro::devolverExemplar((int) $item->livro_id);

            $this->atualizarStatusAposDevolucao();
        });
    }

    /**
     * Devolve todos os livros pendentes da sacola e encerra a circulação.
     */
    public function devolverTodosItens(): void
    {
        DB::transaction(function () {
            $pendentes = $this->itens()->where('devolvido', false)->get();

            foreach ($pendentes as $item) {
                $item->update([
                    'devolvido' => true,
                    'devolvido_em' => now(),
                ]);

                Livro::devolverExemplar((int) $item->livro_id);
            }

            $this->update([
                'status' => StatusSacolaLeitura::Devolvida,
                'data_devolucao' => now()->toDateString(),
            ]);
        });
    }

    /**
     * Atualiza o status geral da sacola com base nos itens restantes.
     */
    public function atualizarStatusAposDevolucao(): void
    {
        $total = $this->itens()->count();
        $devolvidos = $this->itens()->where('devolvido', true)->count();

        if ($total > 0 && $devolvidos === $total) {
            $this->update([
                'status' => StatusSacolaLeitura::Devolvida,
                'data_devolucao' => now()->toDateString(),
            ]);
        } elseif ($devolvidos > 0) {
            $this->update([
                'status' => StatusSacolaLeitura::ParcialmenteDevolvida,
            ]);
        }
    }
}

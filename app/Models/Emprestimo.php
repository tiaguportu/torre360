<?php

namespace App\Models;

use App\Enums\StatusEmprestimo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emprestimo extends Model
{
    use HasFactory;

    protected $fillable = [
        'livro_id',
        'matricula_id',
        'data_emprestimo',
        'data_prevista_devolucao',
        'data_devolucao',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data_emprestimo' => 'date',
            'data_prevista_devolucao' => 'date',
            'data_devolucao' => 'date',
            'status' => StatusEmprestimo::class,
        ];
    }

    public function livro(): BelongsTo
    {
        return $this->belongsTo(Livro::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    /**
     * Registra a devolução: marca a data, devolve o status Devolvido e devolve o
     * exemplar ao acervo disponível.
     */
    public function registrarDevolucao(?string $data = null): void
    {
        $this->update([
            'data_devolucao' => $data ?? now()->toDateString(),
            'status' => StatusEmprestimo::Devolvido,
        ]);

        $this->livro()->increment('quantidade_disponivel');
    }

    /**
     * Marca como Atrasado qualquer empréstimo ainda não devolvido cuja data prevista
     * já passou — mesmo princípio de `ContaPagar::atualizarAtrasadas()`.
     */
    public static function atualizarAtrasados(): int
    {
        return static::where('data_prevista_devolucao', '<', now()->toDateString())
            ->where('status', StatusEmprestimo::Emprestado)
            ->update(['status' => StatusEmprestimo::Atrasado]);
    }
}

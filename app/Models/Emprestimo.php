<?php

namespace App\Models;

use App\Enums\StatusEmprestimo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    protected static function booted(): void
    {
        // Excluir um empréstimo ainda em aberto (ex.: exclusão em lote) devolve o exemplar ao acervo; sem isso a
        // cópia ficava indisponível para sempre até alguém corrigir o estoque à mão.
        static::deleted(function (Emprestimo $emprestimo): void {
            if ($emprestimo->status !== StatusEmprestimo::Devolvido) {
                Livro::devolverExemplar((int) $emprestimo->livro_id);
            }
        });
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
     * Registra um empréstimo reservando o exemplar de forma atômica.
     *
     * O decremento condicional (`quantidade_disponivel > 0`) é a trava: dois balcões emprestando o último
     * exemplar ao mesmo tempo não conseguem ambos reservá-lo, e o empréstimo só nasce se a reserva deu certo
     * (tudo na mesma transação — antes o empréstimo era criado primeiro e o estoque estourava depois).
     *
     * @throws \DomainException exemplar indisponível, livro inexistente ou datas inválidas
     */
    public static function emprestar(int $livroId, int $matriculaId, ?string $dataEmprestimo = null, ?string $dataPrevistaDevolucao = null): self
    {
        try {
            $inicio = $dataEmprestimo ? Carbon::parse($dataEmprestimo)->startOfDay() : Carbon::today();
            $previsao = $dataPrevistaDevolucao ? Carbon::parse($dataPrevistaDevolucao)->startOfDay() : $inicio->copy()->addDays(14);
        } catch (\Throwable) {
            throw new \DomainException('Data de empréstimo ou de devolução inválida.');
        }

        if ($previsao->lt($inicio)) {
            throw new \DomainException('A devolução prevista não pode ser anterior à data do empréstimo.');
        }

        return DB::transaction(function () use ($livroId, $matriculaId, $inicio, $previsao): self {
            $reservados = Livro::query()
                ->whereKey($livroId)
                ->where('quantidade_disponivel', '>', 0)
                ->decrement('quantidade_disponivel');

            if ($reservados === 0) {
                throw new \DomainException(
                    Livro::query()->whereKey($livroId)->exists()
                        ? 'Todos os exemplares deste livro já estão emprestados.'
                        : 'Livro não encontrado no acervo.'
                );
            }

            return static::create([
                'livro_id' => $livroId,
                'matricula_id' => $matriculaId,
                'data_emprestimo' => $inicio->toDateString(),
                'data_prevista_devolucao' => $previsao->toDateString(),
                'status' => StatusEmprestimo::Emprestado,
            ]);
        });
    }

    /**
     * Registra a devolução: marca a data, passa o status para Devolvido e devolve o exemplar ao acervo.
     *
     * Idempotente: a baixa é um UPDATE condicional (`status != devolvido`), então um duplo clique ou duas telas
     * devolvendo o mesmo empréstimo só devolvem o exemplar uma vez. O retorno é false quando já estava devolvido.
     */
    public function registrarDevolucao(?string $data = null): bool
    {
        return DB::transaction(function () use ($data): bool {
            $encerrados = static::query()
                ->whereKey($this->getKey())
                ->where('status', '!=', StatusEmprestimo::Devolvido)
                ->update([
                    'data_devolucao' => $data ?? now()->toDateString(),
                    'status' => StatusEmprestimo::Devolvido,
                ]);

            if ($encerrados > 0) {
                Livro::devolverExemplar((int) $this->livro_id);
            }

            $this->refresh();

            return $encerrados > 0;
        });
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

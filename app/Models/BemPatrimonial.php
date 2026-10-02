<?php

namespace App\Models;

use App\Enums\StatusBemPatrimonial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BemPatrimonial extends Model
{
    use HasFactory;

    protected $table = 'bens_patrimoniais';

    protected $fillable = [
        'descricao',
        'numero_patrimonio',
        'categoria',
        'data_aquisicao',
        'valor_aquisicao',
        'unidade_id',
        'sala_id',
        'fornecedor_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data_aquisicao' => 'date',
            'valor_aquisicao' => 'decimal:2',
            'status' => StatusBemPatrimonial::class,
        ];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoPatrimonio::class)->orderByDesc('data');
    }

    /**
     * Transfere o bem para outra unidade/sala, registrando o histórico.
     */
    public function transferir(?int $unidadeId, ?int $salaId, ?string $observacao = null): MovimentacaoPatrimonio
    {
        $movimentacao = $this->movimentacoes()->create([
            'tipo' => 'transferencia',
            'unidade_anterior_id' => $this->unidade_id,
            'unidade_nova_id' => $unidadeId,
            'sala_anterior_id' => $this->sala_id,
            'sala_nova_id' => $salaId,
            'data' => now()->toDateString(),
            'observacao' => $observacao,
            'registrado_por_user_id' => auth()->id(),
        ]);

        $this->update(['unidade_id' => $unidadeId, 'sala_id' => $salaId]);

        return $movimentacao;
    }

    /**
     * Muda o status do bem (ex.: em manutenção, baixado), registrando o histórico.
     */
    public function mudarStatus(StatusBemPatrimonial $novoStatus, ?string $observacao = null): MovimentacaoPatrimonio
    {
        $movimentacao = $this->movimentacoes()->create([
            'tipo' => 'mudanca_status',
            'status_anterior' => $this->status->value,
            'status_novo' => $novoStatus->value,
            'data' => now()->toDateString(),
            'observacao' => $observacao,
            'registrado_por_user_id' => auth()->id(),
        ]);

        $this->update(['status' => $novoStatus]);

        return $movimentacao;
    }
}

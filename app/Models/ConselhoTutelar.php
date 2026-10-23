<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conselho Tutelar do município a que a escola comunica as faltas excessivas (Onda 57). Pode ser cadastrado por unidade
 * (escolas em municípios diferentes) ou sem unidade, valendo para todas.
 */
class ConselhoTutelar extends Model
{
    protected $table = 'conselhos_tutelares';

    protected $fillable = ['unidade_id', 'nome', 'municipio', 'uf', 'endereco', 'email', 'telefone', 'is_ativo'];

    protected function casts(): array
    {
        return ['is_ativo' => 'boolean'];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * @param  Builder<ConselhoTutelar>  $query
     * @return Builder<ConselhoTutelar>
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('is_ativo', true);
    }

    /**
     * O conselho da unidade; sem ele, o primeiro ativo sem unidade.
     */
    public static function paraUnidade(?int $unidadeId): ?self
    {
        $ativos = static::query()->ativos()->orderBy('id');

        return (clone $ativos)->where('unidade_id', $unidadeId)->first()
            ?? (clone $ativos)->whereNull('unidade_id')->first();
    }
}

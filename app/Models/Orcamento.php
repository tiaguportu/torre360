<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusOrcamento;
use App\Services\OrcamentoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orçamento anual (Onda 43). As regras vivem em {@see OrcamentoService}.
 */
class Orcamento extends Model
{
    protected $table = 'orcamentos';

    protected $fillable = ['ano', 'nome', 'versao', 'status', 'origem_orcamento_id', 'aprovado_por_user_id', 'aprovado_em', 'criado_por_user_id', 'observacao'];

    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'versao' => 'integer',
            'status' => StatusOrcamento::class,
            'aprovado_em' => 'datetime',
        ];
    }

    public function linhas(): HasMany
    {
        return $this->hasMany(OrcamentoLinha::class);
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origem_orcamento_id');
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por_user_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    /**
     * @param  Builder<Orcamento>  $query
     * @return Builder<Orcamento>
     */
    public function scopeAprovados(Builder $query): Builder
    {
        return $query->where('status', StatusOrcamento::Aprovado->value);
    }

    public function estaAprovado(): bool
    {
        return $this->status === StatusOrcamento::Aprovado;
    }

    public function ehRascunho(): bool
    {
        return $this->status === StatusOrcamento::Rascunho;
    }

    public function rotulo(): string
    {
        return "{$this->nome} (v{$this->versao})";
    }

    /**
     * O orçamento aprovado do ano, se houver.
     */
    public static function vigenteDoAno(int $ano): ?self
    {
        return static::query()->where('ano', $ano)->aprovados()->orderByDesc('versao')->first();
    }
}

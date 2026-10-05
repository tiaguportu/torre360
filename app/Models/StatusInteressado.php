<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusInteressado extends Model
{
    use HasFactory;

    protected $table = 'status_interessado';

    protected $fillable = ['nome', 'cor', 'ordem', 'is_final', 'is_ganho'];

    protected function casts(): array
    {
        return [
            'is_final' => 'boolean',
            'is_ganho' => 'boolean',
        ];
    }

    public function interessados(): HasMany
    {
        return $this->hasMany(Interessado::class, 'status_interessado_id');
    }

    /**
     * Etapa em que um lead novo entra: a chamada "Novo" ou, se ela foi renomeada, a primeira etapa ativa
     * do funil. Evita o `?? 1` (id fixo) que apontaria para um status qualquer.
     */
    public static function inicial(): ?self
    {
        return static::query()->where('nome', 'Novo')->first()
            ?? static::query()->where('is_final', false)->orderBy('ordem')->orderBy('id')->first();
    }

    /**
     * Etapa de ganho (matrícula): "Matriculado" ou, se renomeada, a primeira marcada com `is_ganho`.
     */
    public static function ganho(): ?self
    {
        return static::query()->where('nome', 'Matriculado')->first()
            ?? static::query()->where('is_ganho', true)->orderBy('ordem')->orderBy('id')->first();
    }

    /**
     * Etapa de perda padrão: "Perdido" ou, se renomeada, a primeira final que não é de ganho.
     */
    public static function perdido(): ?self
    {
        return static::query()->where('nome', 'Perdido')->first()
            ?? static::query()->where('is_final', true)->where('is_ganho', false)->orderBy('ordem')->orderBy('id')->first();
    }

    /**
     * Indica se este status representa um encerramento por perda/descarte (stage gate).
     */
    public function isPerda(): bool
    {
        if ($this->is_final && ! $this->is_ganho) {
            return true;
        }

        $nomeLower = mb_strtolower(trim($this->nome));

        return in_array($nomeLower, [
            'perdido',
            'perda',
            'desistente',
            'desistência',
            'desistencia',
            'cancelado',
            'sem interesse',
            'descarte',
            'não matriculado',
            'nao matriculado',
        ]);
    }
}

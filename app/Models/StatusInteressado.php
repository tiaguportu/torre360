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

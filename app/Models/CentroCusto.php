<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroCusto extends Model
{
    protected $table = 'centro_custos';

    protected $fillable = ['nome', 'is_active', 'turma_id', 'curso_id', 'fora_do_resultado_das_turmas'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fora_do_resultado_das_turmas' => 'boolean',
        ];
    }

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}

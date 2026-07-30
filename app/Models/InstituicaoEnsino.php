<?php

namespace App\Models;

use Database\Factories\InstituicaoEnsinoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstituicaoEnsino extends Model
{
    /** @use HasFactory<InstituicaoEnsinoFactory> */
    use HasFactory;

    protected $guarded = [];

    public function casts(): array
    {
        return [
            'flag_ativo' => 'boolean',
            'flag_secretaria_educacao_mec' => 'boolean',
            'flag_seguranca_publica_forcas_armadas' => 'boolean',
            'flag_secretaria_saude' => 'boolean',
            'flag_outro_orgao_publico' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }

    public function endereco(): BelongsTo
    {
        return $this->belongsTo(Endereco::class);
    }
}

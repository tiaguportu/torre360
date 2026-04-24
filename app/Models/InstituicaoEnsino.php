<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstituicaoEnsino extends Model
{
    /** @use HasFactory<\Database\Factories\InstituicaoEnsinoFactory> */
    use HasFactory;

    protected $guarded = [];

    public function casts(): array
    {
        return [
            'flag_ativo' => 'boolean',
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

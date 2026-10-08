<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoConsentimento extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'texto_padrao',
        'exige_renovacao_periodica',
        'periodicidade_meses',
        'is_ativo',
    ];

    protected function casts(): array
    {
        return [
            'exige_renovacao_periodica' => 'boolean',
            'is_ativo' => 'boolean',
        ];
    }

    public function consentimentos(): HasMany
    {
        return $this->hasMany(ConsentimentoMatricula::class);
    }
}

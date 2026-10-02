<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoBolsa extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'percentual_maximo', 'exige_aprovacao', 'criterio_renovacao'];

    protected function casts(): array
    {
        return [
            'exige_aprovacao' => 'boolean',
        ];
    }

    public function bolsasConcedidas(): HasMany
    {
        return $this->hasMany(BolsaConcedida::class);
    }
}

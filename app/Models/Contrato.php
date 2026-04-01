<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    protected $table = 'contrato';
    protected $guarded = [];

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function titulos(): HasMany
    {
        return $this->hasMany(Titulo::class);
    }

    public function responsaveisFinanceiros(): HasMany
    {
        return $this->hasMany(ResponsavelFinanceiro::class);
    }
}

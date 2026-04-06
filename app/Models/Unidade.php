<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unidade extends Model
{
    protected $table = 'unidade';

    protected $guarded = [];

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    public function endereco(): BelongsTo
    {
        return $this->belongsTo(Endereco::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Curso extends Model
{
    protected $table = 'curso';

    protected $guarded = [];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    public function documentos(): BelongsToMany
    {
        return $this->belongsToMany(TipoDocumento::class, 'tipo_documento_curso');
    }

    public function coordenadores(): HasMany
    {
        return $this->hasMany(Coordenador::class);
    }

    public function tributacao(): HasOne
    {
        return $this->hasOne(TributacaoCurso::class);
    }
}

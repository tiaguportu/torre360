<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pessoa extends Model
{
    protected $table = 'pessoa';
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endereco(): BelongsTo
    {
        return $this->belongsTo(Endereco::class);
    }

    public function naturalidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class, 'naturalidade_id');
    }

    public function nacionalidade(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'nacionalidade_id');
    }

    public function sexo(): BelongsTo
    {
        return $this->belongsTo(Sexo::class);
    }

    public function corRaca(): BelongsTo
    {
        return $this->belongsTo(CorRaca::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function perfis(): BelongsToMany
    {
        return $this->belongsToMany(Perfil::class, 'pessoa_perfil');
    }

    public function coordenacoes(): HasMany
    {
        return $this->hasMany(Coordenador::class);
    }
}

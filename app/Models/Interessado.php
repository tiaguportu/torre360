<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interessado extends Model
{
    protected $table = 'interessado';

    protected $guarded = [];

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(OrigemInteressado::class, 'origem_interessado_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(StatusInteressado::class, 'status_interessado_id');
    }

    public function dependentes(): HasMany
    {
        return $this->hasMany(InteressadoDependente::class);
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(HistoricoContato::class);
    }
}

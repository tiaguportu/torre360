<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function ultimoHistorico(): HasOne
    {
        return $this->hasOne(HistoricoContato::class)->latestOfMany();
    }

    public function precisaDeContato(): bool
    {
        if (! $this->data_proximo_contato) {
            return false;
        }

        $dataProximo = Carbon::parse($this->data_proximo_contato);
        $ultimoContato = $this->ultimoHistorico?->created_at;

        // Se a data do próximo contato já passou (atraso temporal)
        if ($dataProximo->isPast()) {
            return true;
        }

        // Se a data do próximo contato for anterior ao último contato realizado (agendamento desatualizado)
        if ($ultimoContato && $dataProximo->lt($ultimoContato)) {
            return true;
        }

        return false;
    }
}

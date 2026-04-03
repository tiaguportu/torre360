<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Contrato extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['matricula_id', 'valor_total', 'data_aceite'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'contrato';

    protected $guarded = [];

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
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

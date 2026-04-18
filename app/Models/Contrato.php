<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function faturas(): HasMany
    {
        return $this->hasMany(Fatura::class);
    }

    public function responsaveisFinanceiros(): HasMany
    {
        return $this->hasMany(ResponsavelFinanceiro::class);
    }

    public function templateContrato(): BelongsTo
    {
        return $this->belongsTo(TemplateContrato::class);
    }

    protected function casts(): array
    {
        return [
            'assinafy_request_log' => 'array',
            'data_aceite' => 'datetime',
        ];
    }
}

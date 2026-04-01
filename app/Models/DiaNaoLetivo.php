<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaNaoLetivo extends Model
{
    protected $table = 'dia_nao_letivo';
    protected $guarded = [];

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }


}

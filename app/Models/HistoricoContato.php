<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoContato extends Model
{
    protected $table = 'historico_contato';

    protected $guarded = [];

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }

    public function tipoContato(): BelongsTo
    {
        return $this->belongsTo(TipoContatoInteressado::class, 'tipo_contato_interessado_id');
    }
}

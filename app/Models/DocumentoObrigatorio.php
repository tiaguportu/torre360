<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoObrigatorio extends Model
{
    protected $table = 'documento_obrigatorio';
    protected $guarded = [];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}

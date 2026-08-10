<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContatoEmergencia extends Model
{
    use HasFactory;

    protected $table = 'contato_emergencias';

    protected $guarded = [];

    public function fichaMedica(): BelongsTo
    {
        return $this->belongsTo(FichaMedica::class, 'ficha_medica_id');
    }
}
